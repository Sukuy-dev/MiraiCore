<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class MiraiCore_Wechat_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public static function renderAssets()
    {
        $script = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
        $adminPath = '/' . trim((string)__TYPECHO_ADMIN_DIR__, '/');
        
        $isManagePosts = strpos($script, $adminPath . '/manage-posts.php') !== false || strpos($requestUri, $adminPath . '/manage-posts.php') !== false;
        
        if (!$isManagePosts) {
            return;
        }
        
        $pluginUrl = defined('__TYPECHO_PLUGIN_URL__') ? __TYPECHO_PLUGIN_URL__ . '/MiraiCore' : '/usr/plugins/MiraiCore';
        
        echo '<!-- MiraiWechat Start -->';
        echo '<link rel="stylesheet" href="' . $pluginUrl . '/assets/css/wechat.css">';
        $modalFile = __DIR__ . '/modal.html';
        if (file_exists($modalFile)) {
            include $modalFile;
        }
        
        echo '<script src="' . $pluginUrl . '/assets/js/clipboard.min.js"></script>';
        
        $options = Typecho_Widget::widget('Widget_Options');
        $actionBase = rtrim((string)$options->index, '/');
        if ($actionBase === '' || strpos($actionBase, 'index.php') === false) {
            $actionBase = rtrim((string)$options->siteUrl, '/') . '/index.php';
        }
        $wechatActionUrl = $actionBase . '/action/mirai-wechat';
        
        echo '<script>window.miraiWechatActionUrl = "' . $wechatActionUrl . '";</script>';
        echo '<script src="' . $pluginUrl . '/assets/js/wechat.js"></script>';
        echo '<!-- MiraiWechat End -->';
    }

    public function action()
    {
        $user = Typecho_Widget::widget('Widget_User');
        $user->pass('editor');
        
        if ($this->request->is('do=getArticle')) {
            $this->getArticle();
        } else {
            $this->response->throwJson(['success' => false, 'message' => '未知操作']);
        }
    }

    public function getArticle()
    {
        $cid = intval($this->request->get('cid'));
        if ($cid <= 0) {
            $this->response->throwJson(['success' => false, 'message' => '参数错误']);
        }

        $db = Typecho_Db::get();
        $post = $db->fetchRow($db->select()->from('table.contents')
            ->where('cid = ?', $cid)
            ->where('type = ?', 'post')
            ->limit(1));

        if (!$post) {
            $this->response->throwJson(['success' => false, 'message' => '文章不存在']);
        }

        $content = (string)$post['text'];
        if (class_exists('\Utils\Markdown')) {
            $html = \Utils\Markdown::convert($content);
        } elseif (method_exists('Typecho_Common', 'markdown')) {
            $html = Typecho_Common::markdown($content);
        } else {
            $html = $content;
        }
        
        $options = Typecho_Widget::widget('Widget_Options');
        $siteUrl = rtrim($options->siteUrl, '/');
        $html = preg_replace('/src="(\/[^"]+)"/i', 'src="' . $siteUrl . '$1"', $html);

        $this->response->throwJson([
            'success' => true,
            'data' => [
                'content' => $html
            ]
        ]);
    }
}
