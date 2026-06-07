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

$panelName = 'MiraiCore/Referral/manage-referral.php';
$panelUrl = $options->adminUrl('extending.php?panel=' . urlencode($panelName), true);
$pageSize = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$referralEnabled = function_exists('Mirai_referralIsEnabled') && Mirai_referralIsEnabled();
$statistics = [];
$orders = [];
$totalOrders = 0;
$totalPages = 1;

if (!$referralEnabled) {
    ?>
    <main class="main">
        <div class="body container">
            <div class="typecho-page-title">
                <h2>推广返佣管理</h2>
            </div>
            <div class="message notice">推广返佣功能未启用，请在主题设置中开启。</div>
        </div>
    </main>
    <?php
    include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/copyright.php';
    include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/footer.php';
    exit;
}

$prefix = $db->getPrefix();
$ordersTable = $prefix . 'mirai_pay_orders';

if (function_exists('Mirai_referralAdminGetStatistics')) {
    $statistics = Mirai_referralAdminGetStatistics();
}

try {
    $countRow = $db->fetchRow($db->query("SELECT COUNT(*) AS cnt FROM {$ordersTable} WHERE rebate_price > 0"));
    $totalOrders = (int)($countRow['cnt'] ?? 0);
    $totalPages = max(1, (int)ceil($totalOrders / $pageSize));
    $page = min($page, max(1, $totalPages));

    $offset = ($page - 1) * $pageSize;
    $sql = "SELECT o.order_no, o.uid, o.author_id, o.order_type, o.amount, o.referrer_uid, o.rebate_price, o.rebate_status, o.created, "
         . "u1.name AS buyer_name, u2.name AS referrer_name "
         . "FROM {$ordersTable} o "
         . "LEFT JOIN {$prefix}users u1 ON o.uid = u1.uid "
         . "LEFT JOIN {$prefix}users u2 ON o.referrer_uid = u2.uid "
         . "WHERE o.rebate_price > 0 ORDER BY o.id DESC LIMIT {$pageSize} OFFSET {$offset}";
    $orders = $db->fetchAll($db->query($sql));
} catch (Exception $e) {
    $error = '返佣数据查询失败：' . $e->getMessage();
}

$rebateStatusLabels = [0 => '未提现', 1 => '已提现', 2 => '提现中'];
$rebateStatusCss = [0 => 'withdrawal-status-pending', 1 => 'withdrawal-status-approved', 2 => 'withdrawal-status-processing'];
$orderTypeLabels = ['read' => '全文付费', 'partial' => '部分付费', 'vip' => '会员购买', 'points_purchase' => '积分兑换'];
?>
<main class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2>推广返佣管理</h2>
        </div>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 typecho-list">
                <div class="typecho-list-operate clearfix">
                    <div class="mirai-stat-cards">
                        <div class="mirai-stat-card total">
                            <span class="label">总返佣金额：</span>
                            <strong class="amount">￥<?php echo number_format($statistics['total']['amount'] ?? 0, 2); ?></strong>
                            <span class="count">(<?php echo $statistics['total']['count'] ?? 0; ?>笔)</span>
                        </div>
                        <div class="mirai-stat-card pending">
                            <span class="label">待提现：</span>
                            <strong class="amount">￥<?php echo number_format($statistics['pending']['amount'] ?? 0, 2); ?></strong>
                            <span class="count">(<?php echo $statistics['pending']['count'] ?? 0; ?>笔)</span>
                        </div>
                        <div class="mirai-stat-card approved">
                            <span class="label">已提现：</span>
                            <strong class="amount">￥<?php echo number_format($statistics['withdrawn']['amount'] ?? 0, 2); ?></strong>
                            <span class="count">(<?php echo $statistics['withdrawn']['count'] ?? 0; ?>笔)</span>
                        </div>
                    </div>
                </div>

                <form method="post" name="manage_referral" class="operate-form">
                    <div class="typecho-table-wrap">
                        <table class="typecho-list-table">
                            <colgroup>
                                <col width="160">
                                <col width="100">
                                <col width="100">
                                <col width="80">
                                <col width="80">
                                <col width="80">
                                <col width="150">
                                <col>
                            </colgroup>
                            <thead>
                            <tr>
                                <th><?php _e('订单号'); ?></th>
                                <th><?php _e('购买者'); ?></th>
                                <th><?php _e('推广人'); ?></th>
                                <th><?php _e('订单金额'); ?></th>
                                <th><?php _e('返佣金额'); ?></th>
                                <th><?php _e('状态'); ?></th>
                                <th><?php _e('时间'); ?></th>
                                <th><?php _e('类型'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($orders)): ?>
                                <tr><td colspan="8"><h6 class="typecho-list-table-title"><?php _e('暂无返佣记录'); ?></h6></td></tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($order['order_no']); ?></td>
                                    <td><?php echo htmlspecialchars($order['buyer_name'] ?? ''); ?><br><small>ID: <?php echo (int)$order['uid']; ?></small></td>
                                    <td><?php echo htmlspecialchars($order['referrer_name'] ?? ''); ?><br><small>ID: <?php echo (int)$order['referrer_uid']; ?></small></td>
                                    <td>￥<?php echo number_format((float)$order['amount'], 2); ?></td>
                                    <td><strong style="color:#e67e22;">￥<?php echo number_format((float)$order['rebate_price'], 2); ?></strong></td>
                                    <td><span class="<?php echo $rebateStatusCss[(int)$order['rebate_status']] ?? ''; ?>"><?php echo htmlspecialchars($rebateStatusLabels[(int)$order['rebate_status']] ?? '未知'); ?></span></td>
                                    <td><?php echo (int)$order['created'] > 0 ? (new \Typecho\Date((int)$order['created']))->format('Y-m-d H:i:s') : '-'; ?></td>
                                    <td><small><?php echo htmlspecialchars($orderTypeLabels[$order['order_type']] ?? $order['order_type']); ?></small></td>
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
                                $pageNav = new \Typecho\Widget\Helper\PageNavigator\Box($totalOrders, $page, $pageSize, \Typecho\Request::getInstance()->makeUriByRequest('page={page}'));
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
