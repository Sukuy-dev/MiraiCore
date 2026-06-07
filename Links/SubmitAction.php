<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class MiraiCore_Links_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        $this->submit();
    }

    public function submit()
    {
        try {
            $token = trim((string)$this->request->get('_token'));
            $security = Typecho_Widget::widget('Widget_Security');
            $expectedToken = $security->getToken('links-submit');
            $tokenValid = function_exists('hash_equals') ? hash_equals($expectedToken, $token) : ($expectedToken === $token);
            if ($token === '' || !$tokenValid) {
                $this->response->throwJson(array('success' => false, 'message' => '请求验证失败，请刷新页面后重试'));
            }
            $linkName = trim((string)$this->request->get('linkName'));
            $linkUrl = trim((string)$this->request->get('linkUrl'));
            $linkImage = trim((string)$this->request->get('linkImage'));
            $linkCategory = intval($this->request->get('linkCategory', 0));
            $linkDescription = trim((string)$this->request->get('linkDescription'));
            if ($linkName === '' || $linkUrl === '') {
                $this->response->throwJson(array('success' => false, 'message' => '请填写必填项'));
            }
            if (!filter_var($linkUrl, FILTER_VALIDATE_URL)) {
                $this->response->throwJson(array('success' => false, 'message' => '网站地址格式不正确'));
            }
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $exists = $db->fetchRow($db->select()
                ->from($prefix . 'mirai_links')
                ->where('url = ?', $linkUrl));
            if ($exists) {
                $this->response->throwJson(array('success' => false, 'message' => '该链接已存在'));
            }
            $options = Typecho_Widget::widget('Widget_Options');
            $defaultVisible = 'N';
            $submitLimit = isset($options->linksSubmitLimit) ? intval($options->linksSubmitLimit) : 300;
            $clientIp = '';
            if (method_exists($this->request, 'getIp')) {
                $clientIp = (string)$this->request->getIp();
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $clientIp = (string)$_SERVER['REMOTE_ADDR'];
            }
            if ($clientIp === '') {
                $clientIp = 'guest';
            }
            if ($submitLimit > 0) {
                $cacheKey = 'mirai_links_submit_' . md5($clientIp);
                $lastSubmit = intval(Typecho_Cookie::get($cacheKey));
                if ($lastSubmit > 0 && (time() - $lastSubmit) < $submitLimit) {
                    $this->response->throwJson(array('success' => false, 'message' => '提交过于频繁，请稍后再试'));
                }
            }
            if ($linkCategory > 0) {
                $existsCategory = $db->fetchRow($db->select('mid')
                    ->from($prefix . 'metas')
                    ->where('type = ?', 'link_category')
                    ->where('mid = ?', $linkCategory)
                    ->limit(1));
                if (!$existsCategory) {
                    $linkCategory = 0;
                }
            }
            $insertData = array(
                'name' => $linkName,
                'url' => $linkUrl,
                'image' => $linkImage,
                'description' => $linkDescription,
                'category' => $linkCategory,
                'visible' => $defaultVisible,
                'created' => time(),
                'updated' => time()
            );
            $db->query($db->insert($prefix . 'mirai_links')->rows($insertData));
            if ($submitLimit > 0) {
                Typecho_Cookie::set($cacheKey, strval(time()), $submitLimit);
            }
            $message = '提交成功，等待审核';
            $this->response->throwJson(array(
                'success' => true,
                'message' => $message
            ));
        } catch (Throwable $e) {
            $message = '提交失败，请重试';
            if (defined('__TYPECHO_DEBUG__') && __TYPECHO_DEBUG__) {
                $message = '提交失败：' . $e->getMessage();
            }
            $this->response->throwJson(array('success' => false, 'message' => $message));
        }
    }
}
