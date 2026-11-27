<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class MiraiCore_Ban_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->pass('administrator', true)) {
            throw new Typecho_Widget_Exception(_t('禁止访问'), 403);
        }

        if (!$this->request->isPost()) {
            throw new Typecho_Widget_Exception(_t('请求方式无效'), 405);
        }

        Typecho_Widget::widget('Widget_Security')->protect();

        $do = (string)$this->request->get('do');
        if (!in_array($do, ['ban', 'unban'], true)) {
            throw new Typecho_Widget_Exception(_t('无效的用户操作'), 400);
        }

        $uids = $this->request->filter('int')->getArray('uid');
        $reason = $this->request->get('reason', '');
        $reason = is_string($reason) ? mb_substr(trim($reason), 0, 200, 'UTF-8') : '';

        if (empty($uids)) {
            Typecho_Widget::widget('Widget_Notice')->set(_t('没有用户被操作'), 'notice');
        } elseif ($do === 'ban') {
            $count = MiraiCore_Ban_Service::ban($uids, $reason, (int)$user->uid);
            Typecho_Widget::widget('Widget_Notice')->set(
                $count > 0
                    ? _t('已封禁 %d 个用户', $count)
                    : _t('没有用户被封禁（创始人、管理员、当前登录用户或已封禁用户不可操作）'),
                $count > 0 ? 'success' : 'notice'
            );
        } else {
            $count = MiraiCore_Ban_Service::unban($uids, (int)$user->uid);
            Typecho_Widget::widget('Widget_Notice')->set(
                $count > 0
                    ? _t('已解封 %d 个用户', $count)
                    : _t('没有用户被解封（创始人、管理员、当前登录用户或未封禁用户不可操作）'),
                $count > 0 ? 'success' : 'notice'
            );
        }

        $this->response->redirect(
            Typecho_Widget::widget('Widget_Options')->adminUrl('manage-users.php', true)
        );
    }
}
