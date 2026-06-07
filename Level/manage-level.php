<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$adminDir = '/' . trim((string)__TYPECHO_ADMIN_DIR__, '/');
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/common.php';
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/header.php';
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/menu.php';

if (!$user->pass('administrator')) {
    throw new Typecho_Widget_Exception(_t('禁止访问'), 403);
}

$options = \Typecho\Widget::widget('Widget_Options');
$themeDir = __TYPECHO_ROOT_DIR__ . '/usr/themes/' . $options->theme;
$functionsFile = $themeDir . '/common/functions.php';
if (file_exists($functionsFile)) {
    require_once $functionsFile;
}

$db = \Typecho\Db::get();
$security = \Typecho\Widget::widget('Widget_Security');

$panelName = 'MiraiCore/Level/manage-level.php';
$panelUrl = $options->adminUrl('extending.php?panel=' . urlencode($panelName), true);
$pageSize = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$keyword = isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '';
$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

$levelEnabled = function_exists('Mirai_levelEnabled') && Mirai_levelEnabled();
$levelStats = [];
$users = [];
$totalUsers = 0;
$totalPages = 1;
$maxLevel = 10;

if (!$levelEnabled) {
    ?>
    <main class="main">
        <div class="body container">
            <div class="typecho-page-title">
                <h2>用户等级管理</h2>
            </div>
            <div class="message notice">用户等级功能未启用，请在主题设置中开启。</div>
        </div>
    </main>
    <?php
    include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/copyright.php';
    include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/footer.php';
    exit;
}

if (function_exists('Mirai_levelGetMax')) {
    $maxLevel = Mirai_levelGetMax();
}
if (function_exists('Mirai_levelAdminGetStats')) {
    $levelStats = Mirai_levelAdminGetStats();
}

if ($action === 'adjust' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $security->protect();
    $adjustUid = (int)($_POST['uid'] ?? 0);
    $adjustLevel = isset($_POST['level']) && $_POST['level'] !== '' ? (int)$_POST['level'] : null;
    $adjustExp = isset($_POST['exp']) && $_POST['exp'] !== '' ? (int)$_POST['exp'] : null;

    if ($adjustUid <= 0) {
        \Typecho\Widget::widget('Widget_Notice')->set(_t('用户ID无效'), 'error');
    } else {
        $result = Mirai_levelAdminAdjust($adjustUid, $adjustLevel, $adjustExp);
        if ($result) {
            \Typecho\Widget::widget('Widget_Notice')->set(_t('等级/经验调整成功'), 'success');
        } else {
            \Typecho\Widget::widget('Widget_Notice')->set(_t('等级/经验调整失败'), 'error');
        }
    }
    header('Location: ' . $panelUrl);
    exit;
}

$prefix = $db->getPrefix();

try {
    $where = '1=1';
    if ($keyword !== '') {
        $where .= " AND name LIKE " . $db->quote('%' . $keyword . '%');
    }
    $countRow = $db->fetchRow($db->query("SELECT COUNT(*) AS cnt FROM {$prefix}users WHERE {$where}"));
    $totalUsers = (int)($countRow['cnt'] ?? 0);
    $totalPages = max(1, (int)ceil($totalUsers / $pageSize));
    $page = min($page, max(1, $totalPages));

    $offset = ($page - 1) * $pageSize;
    $sql = "SELECT uid, name, level, exp FROM {$prefix}users WHERE {$where} ORDER BY level DESC, exp DESC LIMIT {$pageSize} OFFSET {$offset}";
    $users = $db->fetchAll($db->query($sql));
} catch (Exception $e) {
    $error = '等级数据查询失败：' . $e->getMessage();
}

$statCardsHtml = '';
$statCount = 0;
for ($lv = 1; $lv <= $maxLevel; $lv++) {
    $count = $levelStats[$lv] ?? 0;
    if ($count > 0 || $lv <= 3) {
        $statCardsHtml .= '<div class="mirai-stat-card ' . ($lv === 1 ? 'total' : ($lv <= 3 ? 'approved' : 'pending')) . '">'
            . '<span class="label">LV' . $lv . '：</span>'
            . '<strong class="amount">' . $count . '</strong>'
            . '<span class="count">人</span>'
            . '</div>';
        $statCount++;
    }
}
?>
<main class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2>用户等级管理</h2>
        </div>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 typecho-list">
                <?php if ($statCardsHtml): ?>
                <div class="typecho-list-operate clearfix">
                    <div class="mirai-stat-cards">
                        <?php echo $statCardsHtml; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="typecho-list-operate clearfix">
                    <form method="post" action="<?php echo $security->getTokenUrl($panelUrl . '&action=adjust'); ?>" class="mirai-inline-form">
                        <span class="mirai-inline-form-label">手动调整：</span>
                        <input type="number" name="uid" min="1" placeholder="用户UID" required style="width:100px;">
                        <input type="number" name="level" min="1" max="<?php echo $maxLevel; ?>" placeholder="等级(1-<?php echo $maxLevel; ?>)" style="width:120px;">
                        <input type="number" name="exp" min="0" placeholder="经验值" style="width:100px;">
                        <button type="submit" class="btn btn-s"><?php _e('确认调整'); ?></button>
                    </form>
                </div>

                <div class="typecho-list-operate clearfix">
                    <form method="get" action="<?php echo $options->adminUrl('extending.php', true); ?>">
                        <input type="hidden" name="panel" value="<?php echo htmlspecialchars($panelName); ?>">
                        <div class="search" role="search">
                            <input type="text" name="keyword" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="<?php _e('搜索用户名'); ?>">
                            <button type="submit" class="btn btn-s"><?php _e('筛选'); ?></button>
                        </div>
                    </form>
                </div>

                <form method="post" name="manage_level" class="operate-form">
                    <div class="typecho-table-wrap">
                        <table class="typecho-list-table">
                            <colgroup>
                                <col width="60">
                                <col width="150">
                                <col width="100">
                                <col width="100">
                                <col width="120">
                                <col>
                            </colgroup>
                            <thead>
                            <tr>
                                <th>UID</th>
                                <th><?php _e('用户名'); ?></th>
                                <th><?php _e('等级'); ?></th>
                                <th><?php _e('经验值'); ?></th>
                                <th><?php _e('下一级所需'); ?></th>
                                <th><?php _e('进度'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="6"><h6 class="typecho-list-table-title"><?php _e('暂无数据'); ?></h6></td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): ?>
                                <?php
                                $currentLevel = (int)$u['level'];
                                $currentExp = (int)$u['exp'];
                                $isMax = $currentLevel >= $maxLevel;
                                $nextThreshold = function_exists('Mirai_levelGetExpThreshold') ? Mirai_levelGetExpThreshold($currentLevel + 1) : 0;
                                $progress = 0;
                                if (!$isMax && $nextThreshold > 0) {
                                    $progress = min(100, floor(($currentExp / $nextThreshold) * 100));
                                } elseif ($isMax) {
                                    $progress = 100;
                                }
                                ?>
                                <tr>
                                    <td><?php echo (int)$u['uid']; ?></td>
                                    <td><?php echo htmlspecialchars($u['name']); ?></td>
                                    <td><span class="withdrawal-status-approved" style="padding:2px 8px;border-radius:3px;font-weight:600;">LV<?php echo $currentLevel; ?></span></td>
                                    <td><?php echo number_format($currentExp); ?></td>
                                    <td><?php echo $isMax ? 'MAX' : number_format($nextThreshold); ?></td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <div style="flex:1;height:6px;background:#eee;border-radius:3px;overflow:hidden;max-width:120px;">
                                                <div style="height:100%;width:<?php echo $progress; ?>%;background:linear-gradient(90deg,#6c5ce7,#a29bfe);border-radius:3px;"></div>
                                            </div>
                                            <small><?php echo $progress; ?>%</small>
                                        </div>
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
                                $pageNav = new \Typecho\Widget\Helper\PageNavigator\Box($totalUsers, $page, $pageSize, \Typecho\Request::getInstance()->makeUriByRequest('page={page}'));
                                $pageNav->render('&laquo;', '&raquo;');
                                ?>
                            </ul>
                        </div>
                    </div>
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
<link rel="stylesheet" href="<?php echo $options->pluginUrl; ?>/MiraiCore/assets/css/mirai.css">
