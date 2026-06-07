<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class MiraiCore_Sitemap_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        $op = trim((string)$this->request->get('op'));
        if ($op === 'cron') {
            $this->cronBuild();
            return;
        }
        $this->adminBuild();
    }

    private function adminBuild()
    {
        $security = Typecho_Widget::widget('Widget_Security');
        $token = trim((string)$this->request->get('_token'));
        $expected = $security->getToken('miraicoresitemap');
        $valid = function_exists('hash_equals') ? hash_equals($expected, $token) : ($expected === $token);
        if ($token === '' || !$valid) {
            $this->response->throwJson(['success' => false, 'message' => '请求验证失败']);
            return;
        }
        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->pass('administrator', true)) {
            $this->response->throwJson(['success' => false, 'message' => '权限不足']);
            return;
        }
        $result = MiraiCore_Sitemap_Service::buildAll();
        $this->response->throwJson($result);
    }

    private function cronBuild()
    {
        $config = MiraiCore_Sitemap_Service::getConfig();
        $token = trim((string)$this->request->get('token'));
        $expected = trim((string)$config['cron_token']);
        $valid = function_exists('hash_equals') ? hash_equals($expected, $token) : ($expected === $token);
        if ($token === '' || !$valid) {
            header('HTTP/1.1 403 Forbidden');
            $this->response->throwJson(['success' => false, 'message' => 'token无效']);
            return;
        }
        $built = MiraiCore_Sitemap_Service::autoBuildIfNeeded(true);
        if (!$built) {
            $this->response->throwJson(['success' => true, 'message' => '无需生成']);
            return;
        }
        $this->response->throwJson(['success' => true, 'message' => '生成完成']);
    }
}
