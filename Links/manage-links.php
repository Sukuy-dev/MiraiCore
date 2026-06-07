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

$panelName = 'MiraiCore/Links/manage-links.php';
$panelUrl = $options->adminUrl('extending.php?panel=' . urlencode($panelName), true);

$action = isset($_GET['action']) ? trim($_GET['action']) : 'list';
$lid = isset($_GET['lid']) ? intval($_GET['lid']) : 0;
$success = '';
$error = '';
$total = 0;
$totalPages = 1;
$page = 1;
$pageSize = 20;
$filterCategory = 0;
$filterVisible = '';
$keyword = '';
$links = [];
$categories = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $security->protect();
        $name = trim((string)$_POST['name']);
        $url = trim((string)$_POST['url']);
        $image = trim((string)$_POST['image']);
        $description = trim((string)$_POST['description']);
        $category = isset($_POST['category']) ? intval($_POST['category']) : 0;
        $sort = isset($_POST['sort']) ? intval($_POST['sort']) : 0;
        $visible = (isset($_POST['visible']) && $_POST['visible'] === 'N') ? 'N' : 'Y';

        if ($name === '' || $url === '') {
            throw new Exception('网站名称和地址不能为空');
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception('网站地址格式不正确');
        }
        if ($image !== '' && !filter_var($image, FILTER_VALIDATE_URL)) {
            throw new Exception('网站 LOGO 地址格式不正确');
        }

        if ($category > 0) {
            $existsCategory = $db->fetchRow($db->select('mid')
                ->from($metasTable)
                ->where('type = ?', 'link_category')
                ->where('mid = ?', $category)
                ->limit(1));
            if (!$existsCategory) {
                $category = 0;
            }
        }

        $data = array(
            'name' => $name,
            'url' => $url,
            'image' => $image,
            'description' => $description,
            'category' => max(0, $category),
            'sort' => $sort,
            'visible' => $visible,
            'updated' => time()
        );

        if ($lid > 0) {
            $db->query($db->update($linksTable)->rows($data)->where('lid = ?', $lid));
            $success = '链接已更新';
        } else {
            $data['created'] = time();
            $db->query($db->insert($linksTable)->rows($data));
            $success = '链接已添加';
        }

        $action = 'list';
        $lid = 0;
    } catch (Exception $e) {
        $error = $e->getMessage();
        $action = 'edit';
    }
}

if ($action === 'delete' && $lid > 0) {
    try {
        $security->protect();
        $db->query($db->delete($linksTable)->where('lid = ?', $lid));
        $success = '链接已删除';
        $action = 'list';
    } catch (Exception $e) {
        $error = '删除失败';
        $action = 'list';
    }
}

if ($action === 'toggle' && $lid > 0) {
    try {
        $security->protect();
        $link = $db->fetchRow($db->select()->from($linksTable)->where('lid = ?', $lid)->limit(1));
        if ($link) {
            $newVisible = $link['visible'] === 'Y' ? 'N' : 'Y';
            $db->query($db->update($linksTable)->rows(array('visible' => $newVisible, 'updated' => time()))->where('lid = ?', $lid));
            $success = '状态已切换';
        } else {
            $error = '链接不存在';
        }
        $action = 'list';
    } catch (Exception $e) {
        $error = '状态切换失败';
        $action = 'list';
    }
}

if ($action === 'approve' && $lid > 0) {
    try {
        $security->protect();
        $db->query($db->update($linksTable)->rows(array('visible' => 'Y', 'updated' => time()))->where('lid = ?', $lid));
        $success = '已通过审核';
        $action = 'list';
    } catch (Exception $e) {
        $error = '审核失败';
        $action = 'list';
    }
}

$categories = $db->fetchAll($db->select()->from($metasTable)->where('type = ?', 'link_category')->order('order', Typecho_Db::SORT_ASC));
$categoryMap = array();
foreach ($categories as $cat) {
    $categoryMap[$cat['mid']] = $cat['name'];
}

$currentLink = null;
if (($action === 'edit' || $action === 'copy') && $lid > 0) {
    $currentLink = $db->fetchRow($db->select()->from($linksTable)->where('lid = ?', $lid)->limit(1));
    if ($currentLink && $action === 'copy') {
        $currentLink['lid'] = 0;
        $currentLink['name'] = $currentLink['name'] . ' (复制)';
        $lid = 0;
    }
}

$filterCategory = isset($_GET['category']) ? intval($_GET['category']) : 0;
$filterVisible = isset($_GET['visible']) ? trim((string)$_GET['visible']) : '';
$keyword = isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '';

$query = $db->select()->from($linksTable);
if ($filterCategory > 0) {
    $query->where('category = ?', $filterCategory);
}
if ($filterVisible === 'Y' || $filterVisible === 'N') {
    $query->where('visible = ?', $filterVisible);
}
if ($keyword !== '') {
    $query->where('name LIKE ? OR url LIKE ? OR description LIKE ?', '%' . $keyword . '%', '%' . $keyword . '%', '%' . $keyword . '%');
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$countQuery = clone $query;
$total = $db->fetchObject($countQuery->select(['COUNT(*)' => 'num']))->num;
$totalPages = ceil($total / $pageSize);
$page = min($page, max(1, $totalPages));

$query->order('sort', Typecho_Db::SORT_DESC)->order('lid', Typecho_Db::SORT_DESC)->limit($pageSize)->offset(($page - 1) * $pageSize);
$links = $db->fetchAll($query);

$pendingCount = intval($db->fetchObject(
    $db->select(array('COUNT(*)' => 'num'))->from($linksTable)->where('visible = ?', 'N')
)->num);
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
                    <form method="get" action="<?php echo $options->adminUrl('extending.php', true); ?>">
                        <input type="hidden" name="panel" value="<?php echo htmlspecialchars($panelName); ?>">
                        <div class="operate">
                            <label><i class="sr-only">全选</i><input type="checkbox" class="typecho-table-select-all"/></label>
                            <div class="btn-group btn-drop">
                                <button class="btn dropdown-toggle btn-s" type="button"><i class="sr-only">操作</i>选中项 <i class="i-caret-down"></i></button>
                                <ul class="dropdown-menu">
                                    <li><a lang="<?php _e('你确认要删除选中的链接吗？'); ?>" href="<?php $security->index('/action/mirai-links?do=delete'); ?>"><?php _e('删除'); ?></a></li>
                                    <li><a lang="<?php _e('你确认要显示选中的链接吗？'); ?>" href="<?php $security->index('/action/mirai-links?do=show'); ?>"><?php _e('显示'); ?></a></li>
                                    <li><a lang="<?php _e('你确认要隐藏选中的链接吗？'); ?>" href="<?php $security->index('/action/mirai-links?do=hide'); ?>"><?php _e('隐藏'); ?></a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="search" role="search">
                            <select name="category">
                                <option value="0">所有分类</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['mid']; ?>" <?php echo $filterCategory == $cat['mid'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="visible">
                                <option value="">所有状态</option>
                                <option value="Y" <?php echo $filterVisible === 'Y' ? 'selected' : ''; ?>>显示</option>
                                <option value="N" <?php echo $filterVisible === 'N' ? 'selected' : ''; ?>>待审</option>
                            </select>
                            <input type="text" name="keyword" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="搜索链接">
                            <button type="submit" class="btn btn-s">筛选</button>
                        </div>
                    </form>
                </div>
                <div style="margin: 10px 0;">
                    <a href="<?php echo $panelUrl . '&action=edit'; ?>" class="btn btn-primary btn-s"><i class="i-plus"></i> 添加链接</a>
                    <a href="<?php echo $panelUrl . '&visible=N'; ?>" class="btn btn-s" style="margin-left:8px;">待审链接<?php echo $pendingCount > 0 ? ' (' . $pendingCount . ')' : ''; ?></a>
                </div>

                <form method="post" name="manage_links" class="operate-form">
                    <div class="typecho-table-wrap">
                        <table class="typecho-list-table">
                            <colgroup>
                                <col width="3%" class="kit-hidden-mb">
                                <col width="50">
                                <col width="60">
                                <col width="220">
                                <col width="260">
                                <col width="100">
                                <col width="60">
                                <col>
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="kit-hidden-mb"></th>
                                    <th>ID</th>
                                    <th>排序</th>
                                    <th>网站名称</th>
                                    <th>网站地址</th>
                                    <th>分类</th>
                                    <th>状态</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($links)): ?>
                                <tr><td colspan="8"><h6 class="typecho-list-table-title">没有任何链接</h6></td></tr>
                                <?php else: ?>
                                <?php foreach ($links as $link): ?>
                                <?php
                                    $toggleUrl = $security->getTokenUrl($panelUrl . '&action=toggle&lid=' . $link['lid']);
                                    $deleteUrl = $security->getTokenUrl($panelUrl . '&action=delete&lid=' . $link['lid']);
                                    $approveUrl = $security->getTokenUrl($panelUrl . '&action=approve&lid=' . $link['lid']);
                                ?>
                                <tr>
                                    <td class="kit-hidden-mb"><input type="checkbox" value="<?php echo intval($link['lid']); ?>" name="lid[]"/></td>
                                    <td><?php echo intval($link['lid']); ?></td>
                                    <td><?php echo intval($link['sort']); ?></td>
                                    <td>
                                        <?php if (!empty($link['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($link['image']); ?>" alt="" style="width:32px;height:32px;border-radius:50%;margin-right:8px;vertical-align:middle;">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($link['name']); ?>
                                        <?php if (!empty($link['description'])): ?>
                                        <br><small style="color:#999;"><?php echo htmlspecialchars($link['description']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($link['url']); ?></a></td>
                                    <td><?php echo isset($categoryMap[$link['category']]) ? htmlspecialchars($categoryMap[$link['category']]) : '未分类'; ?></td>
                                    <td><?php echo $link['visible'] === 'Y' ? '显示' : '待审'; ?></td>
                                    <td>
                                        <a href="<?php echo $panelUrl . '&action=edit&lid=' . $link['lid']; ?>" class="btn btn-s">编辑</a>
                                        <?php if ($link['visible'] !== 'Y'): ?>
                                        <a href="<?php echo $approveUrl; ?>" class="btn btn-s">通过</a>
                                        <?php endif; ?>
                                        <a href="<?php echo $toggleUrl; ?>" class="btn btn-s"><?php echo $link['visible'] === 'Y' ? '隐藏' : '显示'; ?></a>
                                        <a href="<?php echo $panelUrl . '&action=copy&lid=' . $link['lid']; ?>" class="btn btn-s">复制</a>
                                        <a href="<?php echo $deleteUrl; ?>" class="btn btn-s btn-warn" onclick="return confirm('确定要删除此链接吗？');">删除</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

                <?php if ($totalPages > 1): ?>
                <div class="typecho-pager">
                <div class="typecho-pager-content">
                    <ul>
                        <?php
                        $pageNav = new \Typecho\Widget\Helper\PageNavigator\Box($total, $page, $pageSize, \Typecho\Request::getInstance()->makeUriByRequest('page={page}'));
                        $pageNav->render('&laquo;', '&raquo;');
                        ?>
                    </ul>
                </div>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <?php $formAction = $security->getTokenUrl($panelUrl . ($lid > 0 ? '&lid=' . $lid : '')); ?>
                <form method="post" action="<?php echo $formAction; ?>">
                    <div class="typecho-post-option">
                        <label class="typecho-label">网站名称</label>
                        <p><input type="text" name="name" value="<?php echo $currentLink ? htmlspecialchars($currentLink['name']) : ''; ?>" class="text-s w-100" required></p>
                    </div>
                    <div class="typecho-post-option">
                        <label class="typecho-label">网站地址</label>
                        <p><input type="url" name="url" value="<?php echo $currentLink ? htmlspecialchars($currentLink['url']) : ''; ?>" class="text-s w-100" required></p>
                    </div>
                    <div class="typecho-post-option">
                        <label class="typecho-label">网站 LOGO</label>
                        <p><input type="url" name="image" value="<?php echo $currentLink ? htmlspecialchars($currentLink['image']) : ''; ?>" class="text-s w-100"></p>
                    </div>
                    <div class="typecho-post-option">
                        <label class="typecho-label">网站简介</label>
                        <p><textarea name="description" rows="3" class="text-s w-100"><?php echo $currentLink ? htmlspecialchars($currentLink['description']) : ''; ?></textarea></p>
                    </div>
                    <div class="typecho-post-option">
                        <label class="typecho-label">分类</label>
                        <p>
                            <select name="category">
                                <option value="0">未分类</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['mid']; ?>" <?php echo ($currentLink && intval($currentLink['category']) === intval($cat['mid'])) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                    </div>
                    <div class="typecho-post-option">
                        <label class="typecho-label">排序权重</label>
                        <p><input type="number" name="sort" value="<?php echo $currentLink ? intval($currentLink['sort']) : 0; ?>" class="text-s" style="width:100px;"></p>
                    </div>
                    <div class="typecho-post-option">
                        <label class="typecho-label">显示状态</label>
                        <p>
                            <label><input type="radio" name="visible" value="Y" <?php echo (!$currentLink || $currentLink['visible'] === 'Y') ? 'checked' : ''; ?>> 显示</label>
                            <label style="margin-left:20px;"><input type="radio" name="visible" value="N" <?php echo ($currentLink && $currentLink['visible'] === 'N') ? 'checked' : ''; ?>> 隐藏</label>
                        </p>
                    </div>
                    <p class="submit">
                        <button type="submit" class="btn btn-primary"><?php echo $lid > 0 ? '保存修改' : '添加链接'; ?></button>
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
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/table-js.php';
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/footer.php';
?>
