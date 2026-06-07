<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$adminDir = '/' . trim((string)__TYPECHO_ADMIN_DIR__, '/');
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/common.php';
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/header.php';
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/menu.php';

$db = Typecho_Db::get();
$prefix = $db->getPrefix();
$linksTable = $prefix . 'mirai_links';
$metasTable = $prefix . 'metas';
$security = Typecho_Widget::widget('Widget_Security');

if (!$user->pass('administrator')) {
    throw new Typecho_Widget_Exception(_t('禁止访问'), 403);
}

$panelName = 'MiraiCore/Links/manage-categories.php';
$panelUrl = $options->adminUrl('extending.php?panel=' . urlencode($panelName), true);

$action = isset($_GET['action']) ? trim($_GET['action']) : 'list';
$mid = isset($_GET['mid']) ? intval($_GET['mid']) : 0;
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $security->protect();
        $name = trim((string)$_POST['name']);
        $description = trim((string)$_POST['description']);
        $order = isset($_POST['order']) ? intval($_POST['order']) : 0;

        if ($name === '') {
            throw new Exception('分类名称不能为空');
        }

        $exists = $db->fetchRow($db->select('mid')
            ->from($metasTable)
            ->where('type = ?', 'link_category')
            ->where('name = ?', $name)
            ->where('mid != ?', $mid)
            ->limit(1));

        if ($exists) {
            throw new Exception('分类名称已存在');
        }

        $data = array(
            'name' => $name,
            'description' => $description,
            'order' => $order,
            'type' => 'link_category'
        );

        if ($mid > 0) {
            $db->query($db->update($metasTable)->rows($data)->where('mid = ?', $mid)->where('type = ?', 'link_category'));
            $success = '分类已更新';
        } else {
            $db->query($db->insert($metasTable)->rows($data));
            $success = '分类已添加';
        }

        $action = 'list';
        $mid = 0;
    } catch (Exception $e) {
        $error = $e->getMessage();
        $action = 'edit';
    }
}

if ($action === 'delete' && $mid > 0) {
    try {
        $security->protect();
        $linkCount = intval($db->fetchObject(
            $db->select(array('COUNT(*)' => 'num'))->from($linksTable)->where('category = ?', $mid)
        )->num);
        if ($linkCount > 0) {
            $error = '该分类下还有 ' . $linkCount . ' 个链接，请先删除或移动这些链接';
        } else {
            $db->query($db->delete($metasTable)->where('mid = ?', $mid)->where('type = ?', 'link_category'));
            $success = '分类已删除';
        }
    } catch (Exception $e) {
        $error = '删除失败';
    }
    $action = 'list';
}

$currentCategory = null;
if ($action === 'edit' && $mid > 0) {
    $currentCategory = $db->fetchRow(
        $db->select()->from($metasTable)->where('mid = ?', $mid)->where('type = ?', 'link_category')->limit(1)
    );
}

$categories = $db->fetchAll(
    $db->select()->from($metasTable)->where('type = ?', 'link_category')->order('order', Typecho_Db::SORT_ASC)
);

foreach ($categories as &$cat) {
    $cat['count'] = intval($db->fetchObject(
        $db->select(array('COUNT(*)' => 'num'))->from($linksTable)->where('category = ?', $cat['mid'])
    )->num);
}
unset($cat);
?>
<main class="main">
    <div class="body container">
        <?php include __DIR__ . '/page-title.php'; ?>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 typecho-list">
                <?php if ($success !== ''): ?>
                <div class="message success popup"><p><?php echo htmlspecialchars($success); ?></p></div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                <div class="message error popup"><p><?php echo htmlspecialchars($error); ?></p></div>
                <?php endif; ?>

                <?php if ($action === 'list'): ?>
                <div class="typecho-list-operate clearfix">
                    <a href="<?php echo $panelUrl . '&action=edit'; ?>" class="btn btn-primary btn-s" style="float: right;"><i class="i-plus"></i> 添加分类</a>
                </div>
                <div class="typecho-table-wrap">
                    <table class="typecho-list-table">
                        <colgroup>
                            <col width="80">
                            <col width="200">
                            <col width="280">
                            <col width="100">
                            <col width="">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>排序</th>
                                <th>分类名称</th>
                                <th>描述</th>
                                <th>链接数</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                            <tr><td colspan="5"><h6 class="typecho-list-table-title">没有任何分类</h6></td></tr>
                            <?php else: ?>
                            <?php foreach ($categories as $cat): ?>
                            <?php $deleteUrl = $security->getTokenUrl($panelUrl . '&action=delete&mid=' . $cat['mid']); ?>
                            <tr>
                                <td><?php echo intval($cat['order']); ?></td>
                                <td><?php echo htmlspecialchars($cat['name']); ?></td>
                                <td><?php echo htmlspecialchars($cat['description']); ?></td>
                                <td><?php echo intval($cat['count']); ?></td>
                                <td>
                                    <a href="<?php echo $panelUrl . '&action=edit&mid=' . $cat['mid']; ?>" class="btn btn-s">编辑</a>
                                    <a href="<?php echo $deleteUrl; ?>" class="btn btn-s btn-warn" onclick="return confirm('确定要删除此分类吗？');">删除</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <?php $formAction = $security->getTokenUrl($panelUrl . ($mid > 0 ? '&mid=' . $mid : '')); ?>
                <form method="post" action="<?php echo $formAction; ?>">
                    <div class="typecho-post-option">
                        <label class="typecho-label">分类名称</label>
                        <p><input type="text" name="name" value="<?php echo $currentCategory ? htmlspecialchars($currentCategory['name']) : ''; ?>" class="text-s w-100" required></p>
                    </div>
                    <div class="typecho-post-option">
                        <label class="typecho-label">分类描述</label>
                        <p><textarea name="description" rows="3" class="text-s w-100"><?php echo $currentCategory ? htmlspecialchars($currentCategory['description']) : ''; ?></textarea></p>
                    </div>
                    <div class="typecho-post-option">
                        <label class="typecho-label">排序</label>
                        <p><input type="number" name="order" value="<?php echo $currentCategory ? intval($currentCategory['order']) : 0; ?>" class="text-s" style="width:100px;"></p>
                    </div>
                    <p class="submit">
                        <button type="submit" class="btn btn-primary"><?php echo $mid > 0 ? '保存修改' : '添加分类'; ?></button>
                        <a href="<?php echo $panelUrl; ?>" class="btn">返回列表</a>
                    </p>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<?php
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/copyright.php';
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/common-js.php';
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/footer.php';
