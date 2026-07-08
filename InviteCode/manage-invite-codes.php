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
$security = \Typecho\Widget::widget('Widget_Security');
MiraiCore_InviteCode_Service::ensureSchema();

$panelName = 'MiraiCore/InviteCode/manage-invite-codes.php';
$panelUrl = $options->adminUrl('extending.php?panel=' . urlencode($panelName), true);
$actionUrl = \Typecho\Common::url('/action/mirai-invite-codes', $options->index);
$exportBaseUrl = $actionUrl . '?do=export';
$tab = isset($_GET['tab']) ? trim((string)$_GET['tab']) : 'list';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$keyword = isset($_GET['keyword']) ? trim((string)$_GET['keyword']) : '';
$status = isset($_GET['status']) ? trim((string)$_GET['status']) : '';
$notice = \Typecho\Widget::widget('Widget_Notice');

function mirai_invite_admin_redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function mirai_invite_admin_form_payload()
{
    return [
        'reward_points' => (int)($_POST['reward_points'] ?? 0),
        'reward_balance' => (float)($_POST['reward_balance'] ?? 0),
        'reward_vip_level' => (int)($_POST['reward_vip_level'] ?? 0),
        'reward_vip_days' => (int)($_POST['reward_vip_days'] ?? 0),
        'remark' => trim((string)($_POST['remark'] ?? '')),
    ];
}

function mirai_invite_admin_page_url($panelUrl, $tab, array $params = [])
{
    $url = $panelUrl . '&tab=' . rawurlencode($tab);
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            continue;
        }
        $url .= '&' . rawurlencode((string)$key) . '=' . rawurlencode((string)$value);
    }
    return $url;
}

function mirai_invite_status_options()
{
    return [
        '' => '全部状态',
        'active' => '可用',
        'used' => '已使用',
        'disabled' => '已停用',
    ];
}

function mirai_invite_date($timestamp)
{
    $timestamp = (int)$timestamp;
    return $timestamp > 0 ? (new \Typecho\Date($timestamp))->format('Y-m-d H:i:s') : '-';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $security->protect();
    $postAction = isset($_POST['do']) ? trim((string)$_POST['do']) : '';

    if ($postAction === 'generate') {
        $count = max(1, min(500, (int)($_POST['count'] ?? 1)));
        $payload = mirai_invite_admin_form_payload();
        $payload['length'] = (int)($_POST['length'] ?? 8);
        $payload['prefix'] = trim((string)($_POST['prefix'] ?? ''));
        $result = MiraiCore_InviteCode_Service::generateBatch($count, $payload);
        $createdCount = count($result['created']);
        if ($createdCount > 0) {
            $notice->set(_t('已生成 %d 个邀请码', $createdCount), 'success');
        } else {
            $notice->set(_t('邀请码生成失败：%s', implode('; ', $result['errors'])), 'error');
        }
        mirai_invite_admin_redirect(mirai_invite_admin_page_url($panelUrl, 'list'));
    }
}

$stats = MiraiCore_InviteCode_Service::stats();
$listData = MiraiCore_InviteCode_Service::listCodes([
    'page' => $page,
    'pageSize' => 20,
    'keyword' => $keyword,
    'status' => $status
]);

$nav = [
    'list' => '邀请码列表',
    'add' => '批量生成',
];

$listPageUrl = mirai_invite_admin_page_url($panelUrl, 'list', [
    'status' => $status,
    'keyword' => $keyword,
    'page' => $page > 1 ? $page : null,
]);
?>
<main class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2>邀请码管理</h2>
        </div>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 typecho-list">
                <div class="mirai-stat-cards">
                    <div class="mirai-stat-card total">
                        <span class="label">邀请码总数</span>
                        <strong class="amount"><?php echo (int)$stats['total']; ?></strong>
                    </div>
                    <div class="mirai-stat-card approved">
                        <span class="label">当前可用</span>
                        <strong class="amount"><?php echo (int)$stats['active']; ?></strong>
                    </div>
                    <div class="mirai-stat-card pending">
                        <span class="label">已使用</span>
                        <strong class="amount"><?php echo (int)$stats['used']; ?></strong>
                    </div>
                    <div class="mirai-stat-card rejected">
                        <span class="label">已停用</span>
                        <strong class="amount"><?php echo (int)$stats['disabled']; ?></strong>
                    </div>
                </div>

                <div class="typecho-list-operate clearfix">
                    <div class="mirai-panel-tabs">
                        <?php foreach ($nav as $key => $label): ?>
                            <a class="<?php echo $tab === $key ? 'current' : ''; ?>" href="<?php echo htmlspecialchars(mirai_invite_admin_page_url($panelUrl, $key)); ?>">
                                <span class="mirai-tab-title"><?php echo htmlspecialchars($label); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($tab === 'add'): ?>
                    <form method="post" action="<?php echo htmlspecialchars($security->getTokenUrl(mirai_invite_admin_page_url($panelUrl, 'add'))); ?>" class="mirai-admin-card mirai-admin-card-spacious">
                        <input type="hidden" name="do" value="generate">

                        <div class="mirai-admin-section">
                            <div class="mirai-admin-section-head">
                                <h3>基础参数</h3>
                                <p>邀请码为一次性消耗品，生成后可用于注册校验。</p>
                            </div>
                            <div class="mirai-form-grid">
                                <label>生成数量<input type="number" name="count" min="1" max="500" value="10" required></label>
                                <label>邀请码长度<input type="number" name="length" min="4" max="64" value="8" required></label>
                                <label>前缀<input type="text" name="prefix" placeholder="可选，如 Mirai"></label>
                                <label>备注<input type="text" name="remark" placeholder="便于筛选管理"></label>
                            </div>
                        </div>

                        <div class="mirai-admin-section">
                            <div class="mirai-admin-section-head">
                                <h3>注册用户奖励</h3>
                                <p>这些奖励会在新用户注册成功并消费邀请码后立即发放。</p>
                            </div>
                            <div class="mirai-form-grid mirai-form-grid-4">
                                <label>积分奖励<input type="number" name="reward_points" value="0"></label>
                                <label>余额奖励<input type="number" step="0.01" min="0" name="reward_balance" value="0"></label>
                                <label>VIP 等级<input type="number" min="0" max="9" name="reward_vip_level" value="0"></label>
                                <label>VIP 天数<input type="number" min="0" name="reward_vip_days" value="0"></label>
                            </div>
                        </div>

                        <p class="description">VIP 天数填 0 表示永久；生成后可复制或导出给第三方虚拟商品系统发货。</p>
                        <p class="submit"><button type="submit" class="btn primary">生成邀请码</button></p>
                    </form>

                <?php else: ?>
                    <div class="mirai-toolbar-card">
                        <div class="mirai-toolbar-head">
                            <div>
                                <h3>邀请码列表</h3>
                            </div>
                            <form method="get" action="<?php echo htmlspecialchars($options->adminUrl('extending.php', true)); ?>" class="mirai-inline-form">
                                <input type="hidden" name="panel" value="<?php echo htmlspecialchars($panelName); ?>">
                                <input type="hidden" name="tab" value="list">
                                <select name="status">
                                    <?php foreach (mirai_invite_status_options() as $value => $label): ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $status === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" name="keyword" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="搜索邀请码/备注">
                                <button type="submit" class="btn btn-s"><?php _e('筛选'); ?></button>
                                <div class="btn-group btn-drop">
                                    <button class="btn btn-s dropdown-toggle" type="button"><?php _e('导出'); ?> <i class="i-caret-down"></i></button>
                                    <ul class="dropdown-menu">
                                        <?php
                                        $exportParams = '';
                                        if ($status !== '') $exportParams .= '&status=' . rawurlencode($status);
                                        if ($keyword !== '') $exportParams .= '&keyword=' . rawurlencode($keyword);
                                        ?>
                                        <li><a href="<?php echo htmlspecialchars($exportBaseUrl . '&format=code_only&type=txt' . $exportParams); ?>">仅邀请码 (TXT)</a></li>
                                        <li><a href="<?php echo htmlspecialchars($exportBaseUrl . '&format=code_only&type=csv' . $exportParams); ?>">仅邀请码 (CSV)</a></li>
                                        <li class="divider"></li>
                                        <li><a href="<?php echo htmlspecialchars($exportBaseUrl . '&format=full&type=txt' . $exportParams); ?>">全部字段 (TXT)</a></li>
                                        <li><a href="<?php echo htmlspecialchars($exportBaseUrl . '&format=full&type=csv' . $exportParams); ?>">全部字段 (CSV)</a></li>
                                    </ul>
                                </div>
                            </form>
                        </div>
                    </div>

                    <form method="post" action="<?php echo htmlspecialchars($security->getTokenUrl($actionUrl)); ?>" name="manage_invite_codes" class="operate-form">
                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($listPageUrl); ?>">
                        <div class="typecho-list-operate clearfix">
                            <div class="operate">
                                <label><i class="sr-only"><?php _e('全选'); ?></i><input type="checkbox" class="typecho-table-select-all"></label>
                                <div class="btn-group btn-drop">
                                    <button class="btn dropdown-toggle btn-s" type="button"><?php _e('选中项'); ?> <i class="i-caret-down"></i></button>
                                    <ul class="dropdown-menu">
                                        <li><a lang="你确认要停用这些邀请码吗?" href="<?php echo htmlspecialchars($security->getTokenUrl($actionUrl . '?do=disable&redirect=' . rawurlencode($listPageUrl))); ?>"><?php _e('停用'); ?></a></li>
                                        <li><a lang="你确认要启用这些邀请码吗?" href="<?php echo htmlspecialchars($security->getTokenUrl($actionUrl . '?do=enable&redirect=' . rawurlencode($listPageUrl))); ?>"><?php _e('启用'); ?></a></li>
                                        <li><a lang="你确认要删除这些邀请码吗?" href="<?php echo htmlspecialchars($security->getTokenUrl($actionUrl . '?do=delete&redirect=' . rawurlencode($listPageUrl))); ?>"><?php _e('删除'); ?></a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="typecho-table-wrap">
                            <table class="typecho-list-table mirai-invite-table">
                                <colgroup><col width="35"><col width="160"><col width="90"><col width="220"><col><col width="165"></colgroup>
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>邀请码</th>
                                        <th>状态</th>
                                        <th>奖励内容</th>
                                        <th>备注</th>
                                        <th>创建时间</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($listData['list'])): ?>
                                    <tr><td colspan="6"><h6 class="typecho-list-table-title">暂无邀请码</h6></td></tr>
                                <?php else: foreach ($listData['list'] as $item): $statusInfo = MiraiCore_InviteCode_Service::statusLabel($item); ?>
                                    <tr class="mirai-table-row">
                                        <td><input type="checkbox" value="<?php echo (int)$item['id']; ?>" name="id[]"></td>
                                        <td><code class="mirai-copy-code" data-code="<?php echo htmlspecialchars($item['code']); ?>" title="点击复制邀请码"><?php echo htmlspecialchars($item['code']); ?></code></td>
                                        <td><span class="<?php echo htmlspecialchars($statusInfo['class']); ?>"><?php echo htmlspecialchars($statusInfo['text']); ?></span></td>
                                        <td><small><?php echo MiraiCore_InviteCode_Service::rewardText($item); ?></small></td>
                                        <td><small><?php echo htmlspecialchars($item['remark'] ?: '-'); ?></small></td>
                                        <td><small><?php echo mirai_invite_date($item['created']); ?></small></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                    <?php if ($listData['totalPages'] > 1): ?>
                        <div class="typecho-pager"><div class="typecho-pager-content"><ul><?php
                            $pageNav = new \Typecho\Widget\Helper\PageNavigator\Box($listData['total'], $listData['page'], $listData['pageSize'], \Typecho\Request::getInstance()->makeUriByRequest('page={page}'));
                            $pageNav->render('&laquo;', '&raquo;');
                        ?></ul></div></div>
                    <?php endif; ?>
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
<script src="<?php echo $options->pluginUrl; ?>/MiraiCore/assets/js/invite-code.js"></script>
