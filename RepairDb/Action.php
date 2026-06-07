<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class MiraiCore_RepairDb_Action extends Typecho_Widget implements Widget_Interface_Do
{
    private $bufferLevel = 0;

    private function returnResult($ok, $message, $redirect, $details = [])
    {
        $isAjax = trim((string)$this->request->get('ajax')) === '1';
        if ($isAjax) {
            while (ob_get_level() > $this->bufferLevel) {
                ob_end_clean();
            }
            $this->response->throwJson([
                'success' => $ok,
                'message' => $message,
                'details' => $details
            ]);
            return;
        }
        $glue = strpos($redirect, '?') === false ? '?' : '&';
        $flag = $ok ? 'ok' : 'fail';
        $target = $redirect . $glue . 'mirai_db_repair=' . $flag . '&mirai_db_repair_msg=' . rawurlencode($message);
        $this->response->redirect($target);
    }

    public function action()
    {
        $this->bufferLevel = ob_get_level();
        ob_start();
        $options = Typecho_Widget::widget('Widget_Options');
        $pluginsUrl = $options->adminUrl('plugins.php');
        $security = Typecho_Widget::widget('Widget_Security');
        $token = trim((string)$this->request->get('_token'));
        $expectedNew = $security->getToken('miraicorerepairdb');
        $expectedOld = $security->getToken('mirai-core-repair-db');
        if (function_exists('hash_equals')) {
            $valid = hash_equals($expectedNew, $token) || hash_equals($expectedOld, $token);
        } else {
            $valid = ($expectedNew === $token) || ($expectedOld === $token);
        }
        $redirect = trim((string)$this->request->get('redirect'));
        $adminPath = '/' . trim((string)__TYPECHO_ADMIN_DIR__, '/');
        $redirect = preg_replace('/[\x00-\x1f]/', '', $redirect);
        if ($redirect === '' || strpos($redirect, $adminPath . '/') !== 0) {
            $redirect = $adminPath . '/options-theme.php';
        }
        if ($token === '' || !$valid) {
            $this->returnResult(false, '请求验证失败', $redirect, []);
            return;
        }
        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->pass('administrator', true)) {
            $this->returnResult(false, '权限不足', $pluginsUrl, []);
            return;
        }
        try {
            $changes = MiraiCore_Database_Service::repair();
            if (empty($changes)) {
                $message = '数据库结构已是最新，无需修复';
            } else {
                $message = '数据库结构补齐完成：' . implode('；', $changes);
            }
            $this->returnResult(true, $message, $redirect, $changes);
        } catch (Throwable $e) {
            $this->returnResult(false, '数据库修复失败：' . $e->getMessage(), $redirect, []);
        }
    }
}
