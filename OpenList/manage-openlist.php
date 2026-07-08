<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$adminDir = '/' . trim((string)__TYPECHO_ADMIN_DIR__, '/');
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/common.php';
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/header.php';
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/menu.php';

if (!$user->pass('administrator')) {
    throw new Typecho_Widget_Exception(_t('禁止访问'), 403);
}

$security = Typecho_Widget::widget('Widget_Security');
$options = Typecho_Widget::widget('Widget_Options');
$panelName = 'MiraiCore/OpenList/manage-openlist.php';
$panelUrl = $security->getAdminUrl('extending.php?panel=' . urlencode($panelName));
$success = isset($_GET['saved']) ? '配置已保存' : '';
$error = isset($_GET['error']) ? urldecode((string)$_GET['error']) : '';

$config = MiraiCore_OpenList_Service::getConfig();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $security->protect();
        $newPassword = isset($_POST['password']) ? trim((string)$_POST['password']) : '';
        if ($newPassword === '') {
            $newPassword = (string)$config['password'];
        }
        MiraiCore_OpenList_Service::saveConfig([
            'enabled' => isset($_POST['enabled']) ? '1' : '0',
            'host' => isset($_POST['host']) ? $_POST['host'] : '',
            'username' => isset($_POST['username']) ? $_POST['username'] : '',
            'password' => $newPassword,
            'base_path' => isset($_POST['base_path']) ? $_POST['base_path'] : '',
            'per_page' => isset($_POST['per_page']) ? $_POST['per_page'] : '0',
            'timeout' => isset($_POST['timeout']) ? $_POST['timeout'] : '30',
        ]);
        header('Location: ' . $panelUrl . '&saved=1');
        exit;
    } catch (Throwable $e) {
        header('Location: ' . $panelUrl . '&error=' . urlencode('保存失败：' . $e->getMessage()));
        exit;
    }
}
$actionBase = rtrim((string)$options->index, '/');
if ($actionBase === '' || strpos($actionBase, 'index.php') === false) {
    $actionBase = rtrim((string)$options->siteUrl, '/') . '/index.php';
}
$testUrl = $security->getTokenUrl($actionBase . '/action/mirai-openlist?do=test');
?>
<main class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2>OpenList 资源</h2>
        </div>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 typecho-list">
                <?php if ($success !== ''): ?>
                <div class="message success popup"><p><?php echo htmlspecialchars($success); ?></p></div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                <div class="message error popup"><p><?php echo htmlspecialchars($error); ?></p></div>
                <?php endif; ?>

                <form method="post" action="<?php echo htmlspecialchars($panelUrl); ?>">
                    <div class="typecho-table-wrap">
                        <table class="typecho-list-table">
                            <tbody>
                                <tr>
                                    <th width="180">启用资源选择器</th>
                                    <td><label><input type="checkbox" name="enabled" value="1" <?php echo $config['enabled'] === '1' ? 'checked' : ''; ?>> 启用</label></td>
                                </tr>
                                <tr>
                                    <th>OpenList 地址</th>
                                    <td>
                                        <input type="text" name="host" value="<?php echo htmlspecialchars((string)$config['host']); ?>" style="width:100%;" placeholder="https://openlist.example.com">
                                        <p class="description">填写 OpenList 站点地址，结尾不需要斜杠。</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>管理员账号</th>
                                    <td><input type="text" name="username" value="<?php echo htmlspecialchars((string)$config['username']); ?>" style="width:260px;"></td>
                                </tr>
                                <tr>
                                    <th>管理员密码</th>
                                    <td><input type="password" name="password" style="width:260px;" placeholder="<?php echo $config['password'] !== '' ? '已配置，留空保持不变' : ''; ?>"></td>
                                </tr>
                                <tr>
                                    <th>默认挂载路径</th>
                                    <td>
                                        <input type="text" name="base_path" value="<?php echo htmlspecialchars((string)$config['base_path']); ?>" style="width:100%;" placeholder="例如：我的图片">
                                        <p class="description">必须填写 OpenList 存储中的挂载路径。比如存储挂载在 /我的图片，这里填：我的图片。</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>每页数量</th>
                                    <td>
                                        <input type="number" min="0" max="500" name="per_page" value="<?php echo intval($config['per_page']); ?>" style="width:120px;">
                                        <p class="description">0 表示由 OpenList 返回全部。</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th>请求超时</th>
                                    <td><input type="number" min="5" max="120" name="timeout" value="<?php echo intval($config['timeout']); ?>" style="width:120px;"> 秒</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 15px;">
                        <button type="submit" class="btn btn-primary">保存配置</button>
                        <button type="button" class="btn btn-s" id="mirai-openlist-test" data-url="<?php echo htmlspecialchars($testUrl); ?>" style="margin-left:8px;">测试连接</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
<?php
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/copyright.php';
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/common-js.php';
include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/footer.php';
?>
<script>
(function () {
    var btn = document.getElementById('mirai-openlist-test');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var text = btn.innerText;
        btn.disabled = true;
        btn.innerText = '测试中...';
        var form = btn.closest('form');
        var body = new FormData(form);
        fetch(btn.getAttribute('data-url'), {method: 'POST', body: body, credentials: 'same-origin'})
            .then(function (res) {
                return res.text().then(function (text) {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        var preview = text.replace(/\s+/g, ' ').slice(0, 180);
                        throw new Error('接口返回的不是 JSON：' + preview);
                    }
                });
            })
            .then(function (data) { alert(data && data.message ? data.message : '测试完成'); })
            .catch(function (err) { alert('测试失败：' + (err && err.message ? err.message : '未知错误')); })
            .finally(function () {
                btn.disabled = false;
                btn.innerText = text;
            });
    });
})();
</script>
