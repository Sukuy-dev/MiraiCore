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
$panelName = 'MiraiCore/Sitemap/manage-sitemap.php';
$panelUrl = $security->getAdminUrl('extending.php?panel=' . urlencode($panelName));
$success = isset($_GET['saved']) ? '配置已保存' : '';
$error = isset($_GET['error']) ? urldecode($_GET['error']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $security->protect();
        $config = MiraiCore_Sitemap_Service::getConfig();
        $config['enabled'] = isset($_POST['enabled']) ? '1' : '0';
        $config['include_posts'] = isset($_POST['include_posts']) ? '1' : '0';
        $config['include_pages'] = isset($_POST['include_pages']) ? '1' : '0';
        $config['include_categories'] = isset($_POST['include_categories']) ? '1' : '0';
        $config['include_tags'] = isset($_POST['include_tags']) ? '1' : '0';
        $config['include_authors'] = isset($_POST['include_authors']) ? '1' : '0';
        $config['include_home_pagination'] = isset($_POST['include_home_pagination']) ? '1' : '0';
        $config['include_category_pagination'] = isset($_POST['include_category_pagination']) ? '1' : '0';
        $config['include_tag_pagination'] = isset($_POST['include_tag_pagination']) ? '1' : '0';
        $config['max_urls_per_file'] = isset($_POST['max_urls_per_file']) ? intval($_POST['max_urls_per_file']) : 5000;
        $config['auto_mode'] = isset($_POST['auto_mode']) ? trim((string)$_POST['auto_mode']) : 'interval';
        $config['auto_interval_seconds'] = isset($_POST['auto_interval_seconds']) ? intval($_POST['auto_interval_seconds']) : 1800;
        $config['post_changefreq'] = isset($_POST['post_changefreq']) ? trim((string)$_POST['post_changefreq']) : 'daily';
        $config['page_changefreq'] = isset($_POST['page_changefreq']) ? trim((string)$_POST['page_changefreq']) : 'weekly';
        $config['category_changefreq'] = isset($_POST['category_changefreq']) ? trim((string)$_POST['category_changefreq']) : 'weekly';
        $config['tag_changefreq'] = isset($_POST['tag_changefreq']) ? trim((string)$_POST['tag_changefreq']) : 'weekly';
        $config['author_changefreq'] = isset($_POST['author_changefreq']) ? trim((string)$_POST['author_changefreq']) : 'monthly';
        $config['home_changefreq'] = isset($_POST['home_changefreq']) ? trim((string)$_POST['home_changefreq']) : 'daily';
        $config['post_priority'] = isset($_POST['post_priority']) ? trim((string)$_POST['post_priority']) : '0.8';
        $config['page_priority'] = isset($_POST['page_priority']) ? trim((string)$_POST['page_priority']) : '0.7';
        $config['category_priority'] = isset($_POST['category_priority']) ? trim((string)$_POST['category_priority']) : '0.6';
        $config['tag_priority'] = isset($_POST['tag_priority']) ? trim((string)$_POST['tag_priority']) : '0.6';
        $config['author_priority'] = isset($_POST['author_priority']) ? trim((string)$_POST['author_priority']) : '0.5';
        $config['home_priority'] = isset($_POST['home_priority']) ? trim((string)$_POST['home_priority']) : '1.0';
        $config['exclude_cids'] = isset($_POST['exclude_cids']) ? trim((string)$_POST['exclude_cids']) : '';
        $config['exclude_mids'] = isset($_POST['exclude_mids']) ? trim((string)$_POST['exclude_mids']) : '';
        if (isset($_POST['regen_cron_token']) && $_POST['regen_cron_token'] === '1') {
            $config['cron_token'] = '';
        }
        MiraiCore_Sitemap_Service::saveConfig($config);
        MiraiCore_Sitemap_Service::markDirty();
        header('Location: ' . $panelUrl . '&saved=1');
        exit;
    } catch (Throwable $e) {
        header('Location: ' . $panelUrl . '&error=' . urlencode('保存失败：' . $e->getMessage()));
        exit;
    }
}

$config = MiraiCore_Sitemap_Service::getConfig();
$sitemapUrl = rtrim((string)$options->siteUrl, '/') . '/sitemap.xml';
$actionBase = rtrim((string)$options->index, '/');
if ($actionBase === '' || strpos($actionBase, 'index.php') === false) {
    $actionBase = rtrim((string)$options->siteUrl, '/') . '/index.php';
}
$buildToken = $security->getToken('miraicoresitemap');
$buildUrl = $actionBase . '/action/miraicoresitemap?_token=' . rawurlencode($buildToken);
$cronUrl = $actionBase . '/action/miraicoresitemap?op=cron&token=' . rawurlencode((string)$config['cron_token']);
$lastBuildTime = intval($config['last_build_time']) > 0 ? date('Y-m-d H:i:s', intval($config['last_build_time'])) : '未生成';
$statusMap = ['idle' => '未生成', 'success' => '成功', 'failed' => '失败'];
$lastStatus = isset($statusMap[$config['last_build_status']]) ? $statusMap[$config['last_build_status']] : $config['last_build_status'];
?>
<main class="main">
    <div class="body container">
        <div class="typecho-page-title">
            <h2>网站地图</h2>
        </div>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 typecho-list">
                <?php if ($success !== ''): ?>
                <div class="message success popup"><p><?php echo htmlspecialchars($success); ?></p></div>
                <?php endif; ?>
                <?php if ($error !== ''): ?>
                <div class="message error popup"><p><?php echo htmlspecialchars($error); ?></p></div>
                <?php endif; ?>

                <div class="typecho-list-operate clearfix" style="margin-bottom: 15px;">
                    <a href="<?php echo htmlspecialchars($sitemapUrl); ?>" target="_blank" class="btn btn-s">打开 sitemap.xml</a>
                    <button type="button" class="btn btn-primary btn-s" id="mirai-sitemap-build-btn" data-action="<?php echo htmlspecialchars($buildUrl); ?>" style="margin-left: 8px;">立即生成</button>
                </div>

                <div class="typecho-table-wrap">
                    <table class="typecho-list-table">
                        <tbody>
                            <tr><th width="180">索引地址</th><td><?php echo htmlspecialchars($sitemapUrl); ?></td></tr>
                            <tr><th>上次生成时间</th><td><?php echo htmlspecialchars($lastBuildTime); ?></td></tr>
                            <tr><th>上次状态</th><td><?php echo htmlspecialchars($lastStatus); ?></td></tr>
                            <tr><th>状态消息</th><td><?php echo htmlspecialchars((string)$config['last_build_message']); ?></td></tr>
                            <tr><th>Cron URL</th><td><input type="text" value="<?php echo htmlspecialchars($cronUrl); ?>" readonly style="width:100%;"></td></tr>
                        </tbody>
                    </table>
                </div>

                <form method="post" action="<?php echo htmlspecialchars($panelUrl); ?>" style="margin-top: 20px;">
                    <div class="typecho-table-wrap">
                        <table class="typecho-list-table">
                            <tbody>
                                <tr>
                                    <th width="180">启用网站地图</th>
                                    <td><label><input type="checkbox" name="enabled" value="1" <?php echo $config['enabled'] === '1' ? 'checked' : ''; ?>> 启用</label></td>
                                </tr>
                                <tr>
                                    <th>收录类型</th>
                                    <td>
                                        <label style="margin-right:15px;"><input type="checkbox" name="include_posts" value="1" <?php echo $config['include_posts'] === '1' ? 'checked' : ''; ?>> 文章</label>
                                        <label style="margin-right:15px;"><input type="checkbox" name="include_pages" value="1" <?php echo $config['include_pages'] === '1' ? 'checked' : ''; ?>> 独立页面</label>
                                        <label style="margin-right:15px;"><input type="checkbox" name="include_categories" value="1" <?php echo $config['include_categories'] === '1' ? 'checked' : ''; ?>> 分类</label>
                                        <label style="margin-right:15px;"><input type="checkbox" name="include_tags" value="1" <?php echo $config['include_tags'] === '1' ? 'checked' : ''; ?>> 标签</label>
                                        <label><input type="checkbox" name="include_authors" value="1" <?php echo $config['include_authors'] === '1' ? 'checked' : ''; ?>> 作者</label>
                                    </td>
                                </tr>
                                <tr>
                                    <th>分页收录</th>
                                    <td>
                                        <label style="margin-right:15px;"><input type="checkbox" name="include_home_pagination" value="1" <?php echo $config['include_home_pagination'] === '1' ? 'checked' : ''; ?>> 首页分页</label>
                                        <label style="margin-right:15px;"><input type="checkbox" name="include_category_pagination" value="1" <?php echo $config['include_category_pagination'] === '1' ? 'checked' : ''; ?>> 分类分页</label>
                                        <label><input type="checkbox" name="include_tag_pagination" value="1" <?php echo $config['include_tag_pagination'] === '1' ? 'checked' : ''; ?>> 标签分页</label>
                                    </td>
                                </tr>
                                <tr>
                                    <th>自动更新模式</th>
                                    <td>
                                        <select name="auto_mode">
                                            <option value="manual" <?php echo $config['auto_mode'] === 'manual' ? 'selected' : ''; ?>>手动</option>
                                            <option value="realtime" <?php echo $config['auto_mode'] === 'realtime' ? 'selected' : ''; ?>>实时</option>
                                            <option value="interval" <?php echo $config['auto_mode'] === 'interval' ? 'selected' : ''; ?>>间隔</option>
                                        </select>
                                        <span style="margin-left:10px;">间隔秒数</span>
                                        <input type="number" min="60" name="auto_interval_seconds" value="<?php echo intval($config['auto_interval_seconds']); ?>" style="width:120px;">
                                    </td>
                                </tr>
                                <tr>
                                    <th>单文件URL上限</th>
                                    <td><input type="number" min="100" max="50000" name="max_urls_per_file" value="<?php echo intval($config['max_urls_per_file']); ?>" style="width:140px;"></td>
                                </tr>
                                <tr>
                                    <th>更新频率</th>
                                    <td>
                                        <span>文章</span>
                                        <select name="post_changefreq">
                                            <?php foreach (['always','hourly','daily','weekly','monthly','yearly','never'] as $freq): ?>
                                            <option value="<?php echo $freq; ?>" <?php echo $config['post_changefreq'] === $freq ? 'selected' : ''; ?>><?php echo $freq; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span style="margin-left:10px;">页面</span>
                                        <select name="page_changefreq">
                                            <?php foreach (['always','hourly','daily','weekly','monthly','yearly','never'] as $freq): ?>
                                            <option value="<?php echo $freq; ?>" <?php echo $config['page_changefreq'] === $freq ? 'selected' : ''; ?>><?php echo $freq; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span style="margin-left:10px;">分类</span>
                                        <select name="category_changefreq">
                                            <?php foreach (['always','hourly','daily','weekly','monthly','yearly','never'] as $freq): ?>
                                            <option value="<?php echo $freq; ?>" <?php echo $config['category_changefreq'] === $freq ? 'selected' : ''; ?>><?php echo $freq; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span style="margin-left:10px;">标签</span>
                                        <select name="tag_changefreq">
                                            <?php foreach (['always','hourly','daily','weekly','monthly','yearly','never'] as $freq): ?>
                                            <option value="<?php echo $freq; ?>" <?php echo $config['tag_changefreq'] === $freq ? 'selected' : ''; ?>><?php echo $freq; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span style="margin-left:10px;">作者</span>
                                        <select name="author_changefreq">
                                            <?php foreach (['always','hourly','daily','weekly','monthly','yearly','never'] as $freq): ?>
                                            <option value="<?php echo $freq; ?>" <?php echo $config['author_changefreq'] === $freq ? 'selected' : ''; ?>><?php echo $freq; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span style="margin-left:10px;">首页</span>
                                        <select name="home_changefreq">
                                            <?php foreach (['always','hourly','daily','weekly','monthly','yearly','never'] as $freq): ?>
                                            <option value="<?php echo $freq; ?>" <?php echo $config['home_changefreq'] === $freq ? 'selected' : ''; ?>><?php echo $freq; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th>优先级</th>
                                    <td>
                                        <span>文章</span><input type="text" name="post_priority" value="<?php echo htmlspecialchars((string)$config['post_priority']); ?>" style="width:70px;">
                                        <span style="margin-left:10px;">页面</span><input type="text" name="page_priority" value="<?php echo htmlspecialchars((string)$config['page_priority']); ?>" style="width:70px;">
                                        <span style="margin-left:10px;">分类</span><input type="text" name="category_priority" value="<?php echo htmlspecialchars((string)$config['category_priority']); ?>" style="width:70px;">
                                        <span style="margin-left:10px;">标签</span><input type="text" name="tag_priority" value="<?php echo htmlspecialchars((string)$config['tag_priority']); ?>" style="width:70px;">
                                        <span style="margin-left:10px;">作者</span><input type="text" name="author_priority" value="<?php echo htmlspecialchars((string)$config['author_priority']); ?>" style="width:70px;">
                                        <span style="margin-left:10px;">首页</span><input type="text" name="home_priority" value="<?php echo htmlspecialchars((string)$config['home_priority']); ?>" style="width:70px;">
                                    </td>
                                </tr>
                                <tr>
                                    <th>排除内容ID</th>
                                    <td><input type="text" name="exclude_cids" value="<?php echo htmlspecialchars((string)$config['exclude_cids']); ?>" style="width:100%;" placeholder="例如：1,2,3"></td>
                                </tr>
                                <tr>
                                    <th>排除分类/标签ID</th>
                                    <td><input type="text" name="exclude_mids" value="<?php echo htmlspecialchars((string)$config['exclude_mids']); ?>" style="width:100%;" placeholder="例如：5,8"></td>
                                </tr>
                                <tr>
                                    <th>重置Cron Token</th>
                                    <td><label><input type="checkbox" name="regen_cron_token" value="1"> 生成新的Token</label></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 15px;">
                        <button type="submit" class="btn btn-primary">保存配置</button>
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
    var btn = document.getElementById('mirai-sitemap-build-btn');
    if (!btn) return;
    btn.addEventListener('click', function () {
        if (btn.disabled) return;
        var url = btn.getAttribute('data-action') || '';
        if (!url) return;
        btn.disabled = true;
        var oldText = btn.innerText;
        btn.innerText = '生成中...';
        fetch(url, {method: 'POST', credentials: 'same-origin'})
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.message) {
                    alert(data.message);
                } else {
                    alert('操作完成');
                }
                location.reload();
            })
            .catch(function (err) {
                alert('生成失败：' + (err && err.message ? err.message : '未知错误'));
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerText = oldText;
            });
    });
})();
</script>