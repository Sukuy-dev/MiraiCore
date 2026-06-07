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

$withdrawalsTable = Mirai_payTable('withdrawals');
$panelName = 'MiraiCore/Withdrawals/manage-withdrawals.php';
$panelUrl = $options->adminUrl('extending.php?panel=' . urlencode($panelName), true);
$pageSize = 20;
$list = [];
$userMap = [];
$stats = ['pending_amount' => 0, 'pending_count' => 0, 'approved_amount' => 0, 'approved_count' => 0, 'total_amount' => 0, 'total_count' => 0];

// 状态样式映射（避免在循环中重复定义）
$statusCssClasses = [
    0 => 'withdrawal-status-pending',
    1 => 'withdrawal-status-approved',
    2 => 'withdrawal-status-rejected',
    3 => 'withdrawal-status-cancelled'
];

// 账户类型映射（避免在循环中重复定义）
$accountTypeLabels = [
    'alipay' => '支付宝-账号',
    'alipay_qr' => '支付宝-二维码',
    'wechat' => '微信-账号',
    'wechat_qr' => '微信-二维码',
    'bank' => '银行卡'
];

$withdrawTypeLabels = [
    'balance' => '余额提现',
    'rebate' => '佣金提现'
];

if (!Mirai_payTableExists($withdrawalsTable)) {
    // 表不存在，显示错误信息
    ?>
    <main class="main">
        <div class="body container">
            <div class="typecho-page-title">
                <h2>提现管理</h2>
            </div>
            <div class="message error">提现表未初始化，请刷新页面重试。</div>
        </div>
    </main>
    <?php
    include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/copyright.php';
    include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/footer.php';
    exit;
}

$action = isset($_GET['action']) ? trim((string)$_GET['action']) : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action !== '' && $id > 0) {
    try {
        $security->protect();

            $withdrawal = $db->fetchRow($db->select()->from($withdrawalsTable)->where('id = ?', $id));
            if (!$withdrawal) {
                throw new \Exception('提现记录不存在');
            }

            if ((int)$withdrawal['status'] !== 0) {
                throw new \Exception('该提现已处理');
            }

            if ($action === 'approve') {
                $withdrawType = isset($withdrawal['withdraw_type']) ? $withdrawal['withdraw_type'] : 'balance';
                if ($withdrawType === 'rebate' && function_exists('Mirai_referralProcessWithdraw')) {
                    $result = Mirai_referralProcessWithdraw($id, true, isset($_GET['remark']) ? trim((string)$_GET['remark']) : '已处理');
                    $result = $result !== false ? ['success' => true] : ['success' => false, 'msg' => '处理失败'];
                } else {
                    $result = Mirai_payAdminProcessBalanceWithdrawal($id, true, isset($_GET['remark']) ? trim((string)$_GET['remark']) : '已处理');
                }
                
                if (!$result['success']) {
                    throw new \Exception($result['msg']);
                }
                \Typecho\Widget::widget('Widget_Notice')->set(_t('提现已通过'), 'success');
            } elseif ($action === 'reject') {
                $withdrawType = isset($withdrawal['withdraw_type']) ? $withdrawal['withdraw_type'] : 'balance';
                if ($withdrawType === 'rebate' && function_exists('Mirai_referralProcessWithdraw')) {
                    $result = Mirai_referralProcessWithdraw($id, false, isset($_GET['remark']) ? trim((string)$_GET['remark']) : '');
                    $result = $result !== false ? ['success' => true] : ['success' => false, 'msg' => '处理失败'];
                } else {
                    $result = Mirai_payAdminProcessBalanceWithdrawal($id, false, isset($_GET['remark']) ? trim((string)$_GET['remark']) : '');
                }
                
                if (!$result['success']) {
                    throw new \Exception($result['msg']);
                }
                \Typecho\Widget::widget('Widget_Notice')->set(_t('提现已拒绝'), 'success');
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
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$statusFilter = $status === '' ? null : (int)$status;
$result = Mirai_payAdminGetWithdrawals($page, $pageSize, $statusFilter);
$list = $result['list'];
$total = $result['total'];
$totalPages = (int)ceil($total / $pageSize);
$page = min($page, max(1, $totalPages));
$uids = [];
foreach ($list as $item) {
    $uid = isset($item['uid']) ? (int)$item['uid'] : 0;
    if ($uid > 0) {
        $uids[] = $uid;
    }
}
$userMap = Mirai_payAdminGetUsersMap($uids);

$stats = Mirai_payAdminGetWithdrawalStatistics();
?>
<main class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2>提现管理</h2>
        </div>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 typecho-list">
                <div class="typecho-list-operate clearfix">
                    <div class="mirai-stat-cards">
                        <div class="mirai-stat-card pending">
                            <span class="label">待处理提现：</span>
                            <strong class="amount">￥<?php echo number_format($stats['pending_amount'], 2); ?></strong>
                            <span class="count">(<?php echo $stats['pending_count']; ?>笔)</span>
                        </div>
                        <div class="mirai-stat-card approved">
                            <span class="label">已通过提现：</span>
                            <strong class="amount">￥<?php echo number_format($stats['approved_amount'], 2); ?></strong>
                            <span class="count">(<?php echo $stats['approved_count']; ?>笔)</span>
                        </div>
                        <div class="mirai-stat-card total">
                            <span class="label">总提现金额：</span>
                            <strong class="amount">￥<?php echo number_format($stats['total_amount'], 2); ?></strong>
                            <span class="count">(<?php echo $stats['total_count']; ?>笔)</span>
                        </div>
                    </div>
                </div>

                <div class="typecho-list-operate clearfix">
                    <form method="get" action="<?php echo $options->adminUrl('extending.php', true); ?>">
                        <input type="hidden" name="panel" value="<?php echo htmlspecialchars($panelName); ?>">
                        <div class="operate">
                            <label><i class="sr-only">全选</i><input type="checkbox" class="typecho-table-select-all"/></label>
                            <div class="btn-group btn-drop">
                                <button class="btn dropdown-toggle btn-s" type="button"><i class="sr-only">操作</i>选中项 <i class="i-caret-down"></i></button>
                                <ul class="dropdown-menu">
                                    <li><a lang="<?php _e('你确认要通过选中的提现申请吗？'); ?>" href="<?php $security->index('/action/mirai-withdrawals?do=approve'); ?>"><?php _e('通过'); ?></a></li>
                                    <li><a lang="<?php _e('你确认要拒绝选中的提现申请吗？'); ?>" href="<?php $security->index('/action/mirai-withdrawals?do=reject'); ?>"><?php _e('拒绝'); ?></a></li>
                                    <li><a lang="<?php _e('你确认要删除选中的提现记录吗？只能删除已处理的记录'); ?>" href="<?php $security->index('/action/mirai-withdrawals?do=delete'); ?>"><?php _e('删除'); ?></a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="search" role="search">
                            <select name="status">
                                <option value=""><?php _e('全部状态'); ?></option>
                                <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>><?php _e('待处理'); ?></option>
                                <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>><?php _e('已通过'); ?></option>
                                <option value="2" <?php echo $status === '2' ? 'selected' : ''; ?>><?php _e('已拒绝'); ?></option>
                                <option value="3" <?php echo $status === '3' ? 'selected' : ''; ?>><?php _e('已取消'); ?></option>
                            </select>
                            <button type="submit" class="btn btn-s"><?php _e('筛选'); ?></button>
                        </div>
                    </form>
                </div>

                <form method="post" name="manage_withdrawals" class="operate-form">
                    <div class="typecho-table-wrap">
                        <table class="typecho-list-table">
                            <colgroup>
                                <col width="3%" class="kit-hidden-mb">
                                <col width="50">
                                <col width="90">
                                <col width="90">
                                <col width="70">
                                <col width="100">
                                <col width="130">
                                <col width="130">
                                <col width="130">
                                <col>
                            </colgroup>
                            <thead>
                            <tr>
                                <th class="kit-hidden-mb"></th>
                                <th>ID</th>
                                <th><?php _e('用户'); ?></th>
                                <th><?php _e('金额'); ?></th>
                                <th><?php _e('类型'); ?></th>
                                <th><?php _e('状态'); ?></th>
                                <th><?php _e('账户类型'); ?></th>
                                <th><?php _e('账户信息'); ?></th>
                                <th><?php _e('申请时间'); ?></th>
                                <th><?php _e('处理时间'); ?></th>
                                <th><?php _e('操作'); ?></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($list)): ?>
                                <tr><td colspan="10"><h6 class="typecho-list-table-title"><?php _e('暂无提现记录'); ?></h6></td></tr>
                            <?php else: ?>
                                <?php foreach ($list as $item): ?>
                                    <?php
                                    $statusLabel = Mirai_payWithdrawalStatusLabel((int)$item['status']);
                                    $statusCssClass = $statusCssClasses[(int)$item['status']] ?? '';
                                    $user = $userMap[(int)$item['uid']] ?? null;
                                    $userName = $user ? ($user['screenName'] ?: $user['name']) : '未知用户';
                                    $accountTypeText = $accountTypeLabels[$item['account_type']] ?? $item['account_type'];
                                    $withdrawTypeText = $withdrawTypeLabels[$item['withdraw_type']] ?? $item['withdraw_type'];
                                    $isQrCode = in_array($item['account_type'], ['alipay_qr', 'wechat_qr'], true);
                                    $qrCodeUrl = !empty($item['qr_code']) ? $item['qr_code'] : '';
                                    $actualAmount = '';
                                    if (isset($item['remark']) && preg_match('/实际到账([\d.]+)元/', $item['remark'], $am)) {
                                        $actualAmount = (float)$am[1];
                                    }
                                    $approveUrl = $security->getTokenUrl($panelUrl . '&action=approve&id=' . (int)$item['id'] . '&status=' . rawurlencode($status));
                                    $rejectUrl = $security->getTokenUrl($panelUrl . '&action=reject&id=' . (int)$item['id'] . '&status=' . rawurlencode($status));
                                    
                                    // 准备详情数据
                                    $detailData = [
                                        '提现ID' => '#' . (int)$item['id'],
                                        '提现类型' => $withdrawTypeText,
                                        '用户信息' => $userName . ' (ID: ' . (int)$item['uid'] . ')',
                                        '申请金额' => '￥' . number_format((float)$item['amount'], 2),
                                        '状态' => $statusLabel,
                                        '账户类型' => $accountTypeText,
                                        '账户名称' => $item['account_name'],
                                    ];
                                    if ($actualAmount > 0 && $actualAmount != (float)$item['amount']) {
                                        $detailData['实际到账'] = '￥' . number_format($actualAmount, 2);
                                    }
                                    if (!$isQrCode && $item['account_no']) {
                                        $detailData['账户号码'] = $item['account_no'];
                                    }
                                    $detailData['申请时间'] = $item['created'] > 0 ? (new \Typecho\Date((int)$item['created']))->format('Y-m-d H:i:s') : '-';
                                    if ($item['processed_at'] > 0) {
                                        $detailData['处理时间'] = (new \Typecho\Date((int)$item['processed_at']))->format('Y-m-d H:i:s');
                                    }
                                    if ($item['remark']) {
                                        $detailData['用户备注'] = $item['remark'];
                                    }
                                    if ($item['admin_remark']) {
                                        $detailData['管理员备注'] = $item['admin_remark'];
                                    }
                                    if ($isQrCode && $qrCodeUrl) {
                                        $detailData['收款二维码'] = '<img src="' . htmlspecialchars($qrCodeUrl) . '" alt="收款码" onclick="window.open(this.src, \'_blank\')" title="点击查看大图"><div class="mirai-qr-hint">点击图片在新窗口查看大图</div>';
                                    }
                                    $detailJson = htmlspecialchars(json_encode($detailData, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td class="kit-hidden-mb"><input type="checkbox" value="<?php echo (int)$item['id']; ?>" name="id[]"/></td>
                                        <td><?php echo (int)$item['id']; ?></td>
                                        <td><?php echo htmlspecialchars($userName); ?><br><small>ID: <?php echo (int)$item['uid']; ?></small></td>
                                        <td>
                                            <strong>￥<?php echo number_format((float)$item['amount'], 2); ?></strong>
                                            <?php if ($actualAmount > 0 && $actualAmount != (float)$item['amount']): ?>
                                                <br><small class="text-muted">到账 ￥<?php echo number_format($actualAmount, 2); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="<?php echo $item['withdraw_type'] === 'rebate' ? 'withdrawal-status-pending' : ''; ?>"><?php echo htmlspecialchars($withdrawTypeText); ?></span></td>
                                        <td><span class="<?php echo $statusCssClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span></td>
                                        <td><?php echo htmlspecialchars($accountTypeText); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($item['account_name']); ?><br>
                                            <?php if ($isQrCode && $qrCodeUrl): ?>
                                                <small class="qr-payment-badge"><?php _e('二维码收款'); ?></small>
                                            <?php else: ?>
                                                <small><?php echo htmlspecialchars($item['account_no']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $item['created'] > 0 ? (new \Typecho\Date((int)$item['created']))->format('Y-m-d H:i:s') : '-'; ?></td>
                                        <td><?php echo $item['processed_at'] > 0 ? (new \Typecho\Date((int)$item['processed_at']))->format('Y-m-d H:i:s') : '-'; ?></td>
                                        <td>
                                            <a href="javascript:;" class="btn btn-s js-withdrawal-detail-btn" data-withdrawal-detail="<?php echo $detailJson; ?>"><?php _e('详情'); ?></a>
                                            <?php if ((int)$item['status'] === 0): ?>
                                                <a href="<?php echo $approveUrl; ?>" class="btn btn-s" onclick="return confirm('<?php _e('确定通过该提现申请吗？'); ?>');"><?php _e('通过'); ?></a>
                                                <a href="<?php echo $rejectUrl; ?>" class="btn btn-s btn-warn" onclick="return confirm('<?php _e('确定拒绝该提现申请吗？'); ?>');"><?php _e('拒绝'); ?></a>
                                            <?php else: ?>
                                                <?php if ($item['admin_remark']): ?>
                                                    <span title="<?php echo htmlspecialchars($item['admin_remark']); ?>"><?php _e('已处理'); ?></span>
                                                <?php else: ?>
                                                    <span>-</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
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
<?php
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/copyright.php';
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/common-js.php';
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/table-js.php';
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/footer.php';
?>

<div id="mirai-withdrawal-detail-modal" class="mirai-withdrawal-modal">
    <div class="mirai-withdrawal-modal-mask"></div>
    <div class="mirai-withdrawal-modal-panel">
        <button type="button" class="mirai-withdrawal-modal-close" aria-label="close">×</button>
        <div class="mirai-withdrawal-modal-title"><?php _e('提现详情'); ?></div>
        <div class="mirai-pay-withdrawal-detail"></div>
    </div>
</div>

<link rel="stylesheet" href="<?php echo $options->pluginUrl; ?>/MiraiCore/assets/css/mirai.css">

<script>
$(document).ready(function() {
    var modal = document.getElementById('mirai-withdrawal-detail-modal');
    if (!modal) return;
    var mask = modal.querySelector('.mirai-withdrawal-modal-mask');
    var closeBtn = modal.querySelector('.mirai-withdrawal-modal-close');
    var detailBox = modal.querySelector('.mirai-pay-withdrawal-detail');
    var close = function() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    };
    if (mask) mask.addEventListener('click', close);
    if (closeBtn) closeBtn.addEventListener('click', close);
    document.querySelectorAll('.js-withdrawal-detail-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var detailJson = btn.getAttribute('data-withdrawal-detail');
            if (!detailJson) return;
            try {
                var detail = JSON.parse(detailJson);
                var html = '';
                for (var key in detail) {
                    if (detail.hasOwnProperty(key)) {
                        html += '<div class="mirai-withdrawal-detail-row"><div class="mirai-withdrawal-detail-label">' + key + '</div><div class="mirai-withdrawal-detail-value">' + detail[key] + '</div></div>';
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
