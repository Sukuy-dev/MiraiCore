<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class MiraiCore_UserSearch extends \Widget\Base\Users
{
    private \Typecho\Db\Query $countSql;
    private int $total;
    private int $currentPage;
    public static function register()
    {
        if (!class_exists('Widget\Users\Admin', false)) {
            class_alias(__CLASS__, 'Widget\Users\Admin');
        }
    }

    public function execute()
    {
        $this->parameter->setDefault('pageSize=20');
        $select = $this->select();
        $this->currentPage = $this->request->filter('int')->get('page', 1);

        if (null != ($keywords = $this->request->get('keywords'))) {
            $filtered = '%' . \Typecho\Common::filterSearchQuery($keywords) . '%';
            $select->where('name LIKE ? OR screenName LIKE ? OR mail LIKE ?', $filtered, $filtered, $filtered);
        }

        $this->countSql = clone $select;

        $select->order('table.users.uid')
            ->page($this->currentPage, $this->parameter->pageSize);

        $this->db->fetchAll($select, [$this, 'push']);
    }

    public function pageNav()
    {
        $query = $this->request->makeUriByRequest('page={page}');

        $nav = new \Typecho\Widget\Helper\PageNavigator\Box(
            !isset($this->total) ? $this->total = $this->size($this->countSql) : $this->total,
            $this->currentPage,
            $this->parameter->pageSize,
            $query
        );
        $nav->render('&laquo;', '&raquo;');
    }

    protected function ___postsNum(): int
    {
        return $this->db->fetchObject($this->db->select(['COUNT(cid)' => 'num'])
            ->from('table.contents')
            ->where('table.contents.type = ?', 'post')
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.authorId = ?', $this->uid))->num;
    }
}