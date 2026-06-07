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
$coreFile = $themeDir . '/common/functions/core.php';
if (file_exists($coreFile)) {
    require_once $coreFile;
}
$functionsFile = $themeDir . '/common/functions/pay.php';
if (file_exists($functionsFile)) {
    require_once $functionsFile;
}

$db = \Typecho\Db::get();
$security = \Typecho\Widget::widget('Widget_Security');

$ordersTable = Mirai_payTable('orders');
$panelName = 'MiraiCore/Orders/manage-orders.php';
$panelUrl = $options->adminUrl('extending.php?panel=' . urlencode($panelName), true);
$total = 0;
$totalPages = 1;
$page = 1;
$pageSize = 20;
$status = '';
$keyword = '';
$orders = [];
$stats = [];
$userMap = [];

if (!Mirai_payDbCheck()) {
    $error = '支付功能相关数据表未正确初始化，请尝试禁用并重新启用主题。';
} else {
    $action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
    $orderNo = isset($_GET['order_no']) ? trim((string)$_GET['order_no']) : '';

    if ($action !== '' && $orderNo !== '') {
        try {
            $security->protect();

            if (!preg_match('/^MR[A-Fa-f0-9]{20}$/', $orderNo)) {
                throw new \Exception('订单号格式错误');
            }

            $order = $db->fetchRow($db->select('order_no', 'status')->from($ordersTable)->where('order_no = ?', $orderNo)->limit(1));
            if (!$order) {
                throw new \Exception('订单不存在');
            }
            
            if ($action === 'delete') {
                if (!in_array($order['status'], ['pending', 'closed'], true)) {
                    throw new \Exception('只能删除未支付或已关闭的订单');
                }
                $db->query($db->delete($ordersTable)->where('order_no = ?', $orderNo));
                \Typecho\Widget::widget('Widget_Notice')->set(_t('订单已删除'), 'success');
            } elseif ($action === 'close') {
                if ($order['status'] !== 'pending') {
                    throw new \Exception('只能关闭待支付的订单');
                }
                $db->query($db->update($ordersTable)->rows(['status' => 'closed'])->where('order_no = ?', $orderNo)->where('status = ?', 'pending'));
                \Typecho\Widget::widget('Widget_Notice')->set(_t('订单已关闭'), 'success');
            }
            
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        } catch (\Exception $e) {
            \Typecho\Widget::widget('Widget_Notice')->set($e->getMessage(), 'error');
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }
    }

    $status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
    $keyword = isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '';
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

    $result = Mirai_payAdminGetOrders($page, $pageSize, [
        'status' => $status,
        'keyword' => $keyword
    ]);
    $orders = $result['list'];
    $total = (int)$result['total'];
    $totalPages = max(1, (int)ceil($total / $pageSize));
    $page = min($page, $totalPages);
    if ($page < 1) {
        $page = 1;
    }
    $stats = Mirai_payAdminGetStatistics();
    $uids = [];
    foreach ($orders as $order) {
        $uid = isset($order['uid']) ? (int)$order['uid'] : 0;
        if ($uid > 0) {
            $uids[] = $uid;
        }
    }
    $userMap = Mirai_payAdminGetUsersMap($uids);
}
?>
<main class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2>订单管理</h2>
        </div>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 typecho-list">
                <?php if (!empty($stats)): ?>
                <div class="typecho-list-operate clearfix">
                    <div class="mirai-order-stats">
                        <div class="mirai-order-stat-card total">
                            <span class="label">总订单：</span>
                            <strong class="value"><?php echo (int)$stats['total_orders']; ?></strong>
                        </div>
                        <div class="mirai-order-stat-card paid">
                            <span class="label">已支付：</span>
                            <strong class="value"><?php echo (int)$stats['paid_orders']; ?></strong>
                        </div>
                        <div class="mirai-order-stat-card amount">
                            <span class="label">总金额：</span>
                            <strong class="value">￥<?php echo number_format((float)$stats['total_amount'], 2); ?></strong>
                        </div>
                        <div class="mirai-order-stat-card today">
                            <span class="label">今日金额：</span>
                            <strong class="value">￥<?php echo number_format((float)$stats['today_amount'], 2); ?></strong>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="typecho-list-operate clearfix">
                    <form method="get" action="<?php echo $options->adminUrl('extending.php', true); ?>">
                        <input type="hidden" name="panel" value="<?php echo htmlspecialchars($panelName); ?>">
                        <div class="operate">
                            <label><i class="sr-only">全选</i><input type="checkbox" class="typecho-table-select-all"/></label>
                            <div class="btn-group btn-drop">
                                <button class="btn dropdown-toggle btn-s" type="button"><i class="sr-only">操作</i>选中项 <i class="i-caret-down"></i></button>
                                <ul class="dropdown-menu">
                                    <li><a lang="<?php _e('你确认要删除选中的订单吗？只能删除未支付或已关闭的订单。'); ?>" href="<?php $security->index('/action/mirai-orders?do=delete'); ?>"><?php _e('删除'); ?></a></li>
                                    <li><a lang="<?php _e('你确认要关闭选中的订单吗？只能关闭待支付的订单。'); ?>" href="<?php $security->index('/action/mirai-orders?do=close'); ?>"><?php _e('关闭'); ?></a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="search" role="search">
                            <select name="status">
                                <option value=""><?php _e('全部状态'); ?></option>
                                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>><?php _e('待支付'); ?></option>
                                <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>><?php _e('已支付'); ?></option>
                                <option value="closed" <?php echo $status === 'closed' ? 'selected' : ''; ?>><?php _e('已关闭'); ?></option>
                            </select>
                            <input type="text" name="keyword" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="<?php _e('订单号或流水号'); ?>">
                            <button type="submit" class="btn btn-s"><?php _e('筛选'); ?></button>
                        </div>
                    </form>
                </div>
                <form method="post" name="manage_orders" class="operate-form">
                    <div class="typecho-table-wrap">
                        <table class="typecho-list-table">
                            <colgroup>
                                <col width="3%" class="kit-hidden-mb">
                                <col width="160">
                                <col width="200">
                                <col width="80">
                                <col width="80">
                                <col width="80">
                                <col width="90">
                                <col width="150">
                                <col>
                            </colgroup>
                            <thead>
                            <tr>
                                <th class="kit-hidden-mb"></th>
                                <th><?php _e('订单号'); ?></th>
                                <th><?php _e('商品'); ?></th>
                                <th><?php _e('用户'); ?></th>
                                <th><?php _e('金额'); ?></th>
                                <th><?php _e('方式'); ?></th>
                                <th><?php _e('状态'); ?></th>
                                <th><?php _e('创建时间'); ?></th>
                                <th><?php _e('操作'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($orders)): ?>
                                <tr><td colspan="9"><h6 class="typecho-list-table-title"><?php _e('暂无订单'); ?></h6></td></tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <?php
                                    $meta = Mirai_payOrderMeta($order);
                                    $ip = isset($meta['ip']) ? (string)$meta['ip'] : '-';
                                    $displayStatus = Mirai_payGetOrderDisplayStatus($order);
                                    $statusText = Mirai_payOrderStatusLabel($displayStatus);
                                    $deleteUrl = $security->getTokenUrl($panelUrl . '&action=delete&order_no=' . rawurlencode((string)$order['order_no']) . '&status=' . rawurlencode($status) . '&keyword=' . rawurlencode($keyword));
                                    $closeUrl = $security->getTokenUrl($panelUrl . '&action=close&order_no=' . rawurlencode((string)$order['order_no']) . '&status=' . rawurlencode($status) . '&keyword=' . rawurlencode($keyword));
                                    $orderUser = $userMap[(int)$order['uid']] ?? null;
                                    $userName = $orderUser ? ($orderUser['screenName'] ?: $orderUser['name']) : '游客';
                                    $userId = (int)$order['uid'];
                                    $authorId = isset($order['author_id']) ? (int)$order['author_id'] : 0;
                                    $incomePrice = isset($order['income_price']) ? (float)$order['income_price'] : 0;
                                    $incomeStatus = isset($order['income_status']) ? (int)$order['income_status'] : 0;
                                    $gateway = Mirai_payChannelGateway((string)$order['payment_method']);
                                    $gatewayLabel = Mirai_payGatewayLabel($gateway !== '' ? $gateway : (string)$order['payment_method']);
                                    $detailData = [
                                        '商品名称' => Mirai_payOrderTitle($order),
                                        '订单类型' => Mirai_payOrderTypeLabel((string)$order['order_type']),
                                        '创建时间' => (new \Typecho\Date((int)$order['created']))->format('Y-m-d H:i:s'),
                                        '订单号' => (string)$order['order_no'],
                                        '支付流水号' => isset($order['trade_no']) && $order['trade_no'] !== '' ? (string)$order['trade_no'] : '-',
                                        '支付金额' => number_format((float)$order['amount'], 2) . ' ' . Mirai_payCurrencyName(),
                                        '完成支付时间' => (int)$order['paid_at'] > 0 ? (new \Typecho\Date((int)$order['paid_at']))->format('Y-m-d H:i:s') : '-',
                                        '订单状态' => $statusText,
                                        '支付方式' => Mirai_payMethodLabel((string)$order['payment_method']),
                                        '支付网关' => $gatewayLabel,
                                        '用户' => $userName . ' (ID: ' . $userId . ')',
                                        '内容ID' => (int)$order['cid'] > 0 ? (int)$order['cid'] : '-',
                                        '作者ID' => $authorId > 0 ? $authorId : '-',
                                        '分成金额' => $incomePrice > 0 ? ('￥' . number_format($incomePrice, 2)) : '-',
                                        '分成状态' => $incomePrice > 0 ? Mirai_payIncomeStatusLabel($incomeStatus) : '-',
                                        'IP地址' => $ip
                                    ];
                                    $detailJson = htmlspecialchars(json_encode($detailData, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td class="kit-hidden-mb"><input type="checkbox" value="<?php echo htmlspecialchars((string)$order['order_no']); ?>" name="order_no[]"/></td>
                                        <td><?php echo htmlspecialchars($order['order_no']); ?><br><small><?php echo htmlspecialchars((string)$order['trade_no']); ?></small></td>
                                        <td><?php echo htmlspecialchars(Mirai_payOrderTitle($order)); ?><br><small><?php echo htmlspecialchars(Mirai_payOrderTypeLabel((string)$order['order_type'])); ?></small></td>
                                        <td><?php echo htmlspecialchars($userName); ?><br><small>ID: <?php echo $userId; ?></small></td>
                                        <td><?php echo number_format((float)$order['amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars(Mirai_payMethodLabel((string)$order['payment_method'])); ?></td>
                                        <td><?php echo htmlspecialchars($statusText); ?></td>
                                        <td><?php echo (new \Typecho\Date((int)$order['created']))->format('Y-m-d H:i:s'); ?><br><small>IP: <?php echo htmlspecialchars($ip); ?></small></td>
                                        <td>
                                            <a href="javascript:;" class="btn btn-s js-order-detail-btn" data-order-detail="<?php echo $detailJson; ?>"><?php _e('详情'); ?></a>
                                            <?php if ((string)$order['status'] === 'pending' && $displayStatus === 'pending'): ?>
                                                <a href="<?php echo $closeUrl; ?>" class="btn btn-s"><?php _e('关闭'); ?></a>
                                            <?php endif; ?>
                                            <a href="<?php echo $deleteUrl; ?>" class="btn btn-s btn-warn" onclick="return confirm('<?php _e('确定删除该订单吗？'); ?>');"><?php _e('删除'); ?></a>
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
            </div>
        </div>
    </div>
</main>
<div id="mirai-order-detail-modal" class="mirai-order-modal">
    <div class="mirai-order-modal-mask"></div>
    <div class="mirai-order-modal-panel">
        <button type="button" class="mirai-order-modal-close" aria-label="close">×</button>
        <div class="mirai-order-modal-title"><?php _e('订单详情'); ?></div>
        <div class="mirai-pay-order-detail"></div>
    </div>
</div>

<link rel="stylesheet" href="<?php echo $options->pluginUrl; ?>/MiraiCore/assets/css/mirai.css">

<?php
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/copyright.php';
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/common-js.php';
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/table-js.php';
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/footer.php';
?>

<script>
$(document).ready(function() {
    var modal = document.getElementById('mirai-order-detail-modal');
    if (!modal) return;
    var mask = modal.querySelector('.mirai-order-modal-mask');
    var closeBtn = modal.querySelector('.mirai-order-modal-close');
    var detailBox = modal.querySelector('.mirai-pay-order-detail');
    var close = function() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };
    if (mask) mask.addEventListener('click', close);
    if (closeBtn) closeBtn.addEventListener('click', close);
    document.querySelectorAll('.js-order-detail-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var detailJson = btn.getAttribute('data-order-detail');
            if (!detailJson) return;
            try {
                var detail = JSON.parse(detailJson);
                var html = '';
                for (var key in detail) {
                    if (detail.hasOwnProperty(key)) {
                        html += '<div class="mirai-pay-order-detail-row"><div class="mirai-pay-order-detail-label">' + key + '</div><div class="mirai-pay-order-detail-value">' + detail[key] + '</div></div>';
                    }
                }
                detailBox.innerHTML = html;
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            } catch (err) {
                console.error('Parse error:', err);
            }
        });
    });
});
</script>
