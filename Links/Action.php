<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class MiraiCore_Links_Batch_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        $security = Typecho_Widget::widget('Widget_Security');
        $security->protect();
        
        $do = trim((string)$this->request->get('do'));
        
        if ($do === 'delete') {
            $this->deleteLinks();
        } elseif ($do === 'show') {
            $this->showLinks();
        } elseif ($do === 'hide') {
            $this->hideLinks();
        }
        
        $this->response->goBack();
    }

    private function deleteLinks()
    {
        $lids = $this->request->getArray('lid');
        $lids = array_map('intval', $lids);
        $deleteRows = 0;
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $linksTable = $prefix . 'mirai_links';

        foreach ($lids as $lid) {
            if ($lid <= 0) {
                continue;
            }
            $db->query($db->delete($linksTable)->where('lid = ?', $lid));
            $deleteRows++;
        }

        Typecho_Widget::widget('Widget_Notice')->set(
            $deleteRows > 0 ? _t('链接已经被删除') : _t('没有链接被删除'),
            $deleteRows > 0 ? 'success' : 'notice'
        );
    }

    private function showLinks()
    {
        $lids = $this->request->getArray('lid');
        $lids = array_map('intval', $lids);
        $showRows = 0;
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $linksTable = $prefix . 'mirai_links';

        foreach ($lids as $lid) {
            if ($lid <= 0) {
                continue;
            }
            $db->query($db->update($linksTable)->rows(['visible' => 'Y', 'updated' => time()])->where('lid = ?', $lid));
            $showRows++;
        }

        Typecho_Widget::widget('Widget_Notice')->set(
            $showRows > 0 ? _t('链接已经显示') : _t('没有链接被显示'),
            $showRows > 0 ? 'success' : 'notice'
        );
    }

    private function hideLinks()
    {
        $lids = $this->request->getArray('lid');
        $lids = array_map('intval', $lids);
        $hideRows = 0;
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $linksTable = $prefix . 'mirai_links';

        foreach ($lids as $lid) {
            if ($lid <= 0) {
                continue;
            }
            $db->query($db->update($linksTable)->rows(['visible' => 'N', 'updated' => time()])->where('lid = ?', $lid));
            $hideRows++;
        }

        Typecho_Widget::widget('Widget_Notice')->set(
            $hideRows > 0 ? _t('链接已经隐藏') : _t('没有链接被隐藏'),
            $hideRows > 0 ? 'success' : 'notice'
        );
    }
}