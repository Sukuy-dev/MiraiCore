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

$panelName = 'MiraiCore/Points/manage-points.php';
$panelUrl = $options->adminUrl('extending.php?panel=' . urlencode($panelName), true);
$pageSize = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$keyword = isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '';
$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';

$pointsEnabled = function_exists('Mirai_pointsEnabled') && Mirai_pointsEnabled();
$pointsName = function_exists('Mirai_pointsName') ? Mirai_pointsName() : '积分';
$totalUsers = 0;
$totalPoints = 0;
$logs = [];
$totalLogs = 0;
$totalPages = 1;

if (!$pointsEnabled) {
    ?>
    <main class="main">
        <div class="body container">
            <div class="typecho-page-title">
                <h2><?php echo $pointsName; ?>管理</h2>
            </div>
            <div class="message notice"><?php echo $pointsName; ?>功能未启用，请在主题设置中开启。</div>
        </div>
    </main>
    <?php
    include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/copyright.php';
    include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/footer.php';
    exit;
}

if ($action === 'adjust' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $security->protect();
    $adjustUid = (int)($_POST['uid'] ?? 0);
    $adjustAmount = (int)($_POST['amount'] ?? 0);
    $adjustRemark = trim((string)($_POST['remark'] ?? '管理员手动调整'));

    if ($adjustUid <= 0) {
        \Typecho\Widget::widget('Widget_Notice')->set(_t('用户ID无效'), 'error');
    } elseif ($adjustAmount === 0) {
        \Typecho\Widget::widget('Widget_Notice')->set(_t('调整数量不能为0'), 'error');
    } else {
        $result = Mirai_pointsAdjust($adjustUid, $adjustAmount, 'admin_adjust', null, null, $adjustRemark);
        if (!empty($result['success'])) {
            \Typecho\Widget::widget('Widget_Notice')->set(_t('%s调整成功', NULL, $pointsName), 'success');
        } else {
            \Typecho\Widget::widget('Widget_Notice')->set(_t('%s调整失败：' . (isset($result['msg']) ? $result['msg'] : '未知错误'), NULL, $pointsName), 'error');
        }
    }
    header('Location: ' . $panelUrl);
    exit;
}

$prefix = $db->getPrefix();
$logsTable = $prefix . 'mirai_points_logs';

try {
    $statsRow = $db->fetchRow($db->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(points), 0) AS total FROM {$prefix}users WHERE points > 0"));
    $totalUsers = (int)($statsRow['cnt'] ?? 0);
    $totalPoints = (int)($statsRow['total'] ?? 0);

    $countWhere = '';
    if ($keyword !== '') {
        $countWhere = " WHERE l.uid IN (SELECT uid FROM {$prefix}users WHERE name LIKE " . $db->quote('%' . $keyword . '%') . ")";
    }
    $countRow = $db->fetchRow($db->query("SELECT COUNT(*) AS cnt FROM {$logsTable} l" . $countWhere));
    $totalLogs = (int)($countRow['cnt'] ?? 0);
    $totalPages = max(1, (int)ceil($totalLogs / $pageSize));
    $page = min($page, max(1, $totalPages));

    $offset = ($page - 1) * $pageSize;
    $sql = "SELECT l.*, u.name AS user_name FROM {$logsTable} l LEFT JOIN {$prefix}users u ON l.uid = u.uid";
    if ($keyword !== '') {
        $sql .= " WHERE u.name LIKE " . $db->quote('%' . $keyword . '%');
    }
    $sql .= " ORDER BY l.id DESC LIMIT {$pageSize} OFFSET {$offset}";
    $logs = $db->fetchAll($db->query($sql));
} catch (Exception $e) {
    $error = $pointsName . '数据表未初始化，请先启用' . $pointsName . '功能并修复数据表。';
}

$actionLabels = [
    'sign_up' => '注册奖励', 'sign_in' => '每日登录', 'post_new' => '发布文章',
    'post_like' => '文章获赞', 'post_collect' => '文章被收藏', 'comment_new' => '发表评论',
    'purchase' => '购买积分', 'points_pay' => '积分兑换内容',
    'exchange_vip' => '兑换VIP', 'admin_adjust' => '管理员调整',
    'hide_content_spend' => '消耗积分查看隐藏内容', 'refund' => '退款',
];
?>
<main class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2><?php echo $pointsName; ?>管理</h2>
        </div>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 typecho-list">
                <div class="typecho-list-operate clearfix">
                    <div class="mirai-stat-cards">
                        <div class="mirai-stat-card total">
                            <span class="label">系统<?php echo $pointsName; ?>总量：</span>
                            <strong class="amount"><?php echo number_format($totalPoints); ?></strong>
                        </div>
                        <div class="mirai-stat-card approved">
                            <span class="label">持有用户数：</span>
                            <strong class="amount"><?php echo $totalUsers; ?></strong>
                        </div>
                        <div class="mirai-stat-card pending">
                            <span class="label">变动记录：</span>
                            <strong class="amount"><?php echo $totalLogs; ?></strong>
                        </div>
                    </div>
                </div>

                <div class="typecho-list-operate clearfix">
                    <form method="post" action="<?php echo $security->getTokenUrl($panelUrl . '&action=adjust'); ?>" class="mirai-inline-form">
                        <span class="mirai-inline-form-label">手动调整：</span>
                        <input type="number" name="uid" min="1" placeholder="用户UID" required style="width:100px;">
                        <input type="number" name="amount" placeholder="数量(正增负减)" required style="width:130px;">
                        <input type="text" name="remark" value="管理员手动调整" style="width:160px;">
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

                <form method="post" name="manage_points" class="operate-form">
                    <div class="typecho-table-wrap">
                        <table class="typecho-list-table">
                            <colgroup>
                                <col width="50">
                                <col width="120">
                                <col width="100">
                                <col width="80">
                                <col width="80">
                                <col width="200">
                                <col width="150">
                                <col>
                            </colgroup>
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th><?php _e('用户'); ?></th>
                                <th><?php _e('类型'); ?></th>
                                <th><?php _e('数量'); ?></th>
                                <th><?php _e('余额'); ?></th>
                                <th><?php _e('备注'); ?></th>
                                <th><?php _e('时间'); ?></th>
                                <th><?php _e('关联ID'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="8"><h6 class="typecho-list-table-title"><?php _e('暂无记录'); ?></h6></td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo (int)$log['id']; ?></td>
                                    <td><?php echo htmlspecialchars($log['user_name'] ?? ''); ?><br><small>ID: <?php echo (int)$log['uid']; ?></small></td>
                                    <td><?php echo htmlspecialchars($actionLabels[$log['action']] ?? $log['action']); ?></td>
                                    <td><strong style="color:<?php echo $log['amount'] > 0 ? '#27ae60' : '#e74c3c'; ?>;"><?php echo $log['amount'] > 0 ? '+' : ''; ?><?php echo (int)$log['amount']; ?></strong></td>
                                    <td><?php echo isset($log['balance_after']) ? (int)$log['balance_after'] : '-'; ?></td>
                                    <td><?php echo htmlspecialchars(mb_substr($log['remark'] ?? '', 0, 30)); ?></td>
                                    <td><?php echo (int)$log['created'] > 0 ? (new \Typecho\Date((int)$log['created']))->format('Y-m-d H:i:s') : '-'; ?></td>
                                    <td><small><?php echo htmlspecialchars($log['ref_id'] ?? ''); ?></small></td>
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
                                $pageNav = new \Typecho\Widget\Helper\PageNavigator\Box($totalLogs, $page, $pageSize, \Typecho\Request::getInstance()->makeUriByRequest('page={page}'));
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
