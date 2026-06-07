<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class MiraiCore_Crawler_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        $action = $this->request->get('do', 'clear');

        switch ($action) {
            case 'clear':
                $this->clearOld();
                break;
            default:
                $this->response->throwJson(['success' => false, 'message' => 'Unknown action']);
        }
    }

    private function clearOld()
    {
        try {
            $security = Typecho_Widget::widget('Widget_Security');
            $token = trim((string)$this->request->get('_token'));
            $expectedToken = $security->getToken('miraicrawler-clear');

            $tokenValid = function_exists('hash_equals') ? hash_equals($expectedToken, $token) : ($expectedToken === $token);
            if ($token === '' || !$tokenValid) {
                $this->response->throwJson(['success' => false, 'message' => 'Token 验证失败']);
            }

            $days = intval($this->request->get('days', 30));
            // days=0 表示清理全部，其他值至少为1
            if ($days !== 0) {
                $days = max(1, $days);
            }
            $deleted = MiraiCore_Crawler_Service::clearOldRecords($days);
            $this->response->throwJson(['success' => true, 'deleted' => $deleted]);
        } catch (Exception $e) {
            $this->response->throwJson(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}