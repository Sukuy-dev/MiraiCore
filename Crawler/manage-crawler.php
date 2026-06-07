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

require_once __DIR__ . '/Service.php';

MiraiCore_Crawler_Service::processQueue();

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$crawlerFilter = isset($_GET['crawler']) ? trim($_GET['crawler']) : '';
$days = isset($_GET['days']) ? intval($_GET['days']) : 7;

$stats = MiraiCore_Crawler_Service::getCrawlerStats($days);

$listResult = MiraiCore_Crawler_Service::getCrawlerList([
    'page' => $page,
    'per_page' => $perPage,
    'crawler' => $crawlerFilter,
    'days' => $days
]);

$list = $listResult['data'];
$total = $listResult['total'];
$totalPages = $listResult['total_pages'];
$page = min($page, max(1, $totalPages));
?>
<div class="main">
    <div class="body container">
        <div class="row typecho-page-title">
            <div class="col-mb-12">
                <h2>爬虫记录</h2>
            </div>
        </div>

        <div class="row">
            <div class="col-mb-12" role="main">
                <div class="typecho-list-operate clearfix">
                    <div class="operate" style="float: right;">
                        <button type="button" class="btn btn-s" onclick="location.reload();">刷新</button>
                        <select id="clearDaysSelect" class="btn btn-s" style="margin-right: 5px;">
                            <option value="1">清理1天前</option>
                            <option value="7">清理7天前</option>
                            <option value="14">清理14天前</option>
                            <option value="30" selected>清理30天前</option>
                            <option value="90">清理90天前</option>
                            <option value="0">清理全部</option>
                        </select>
                        <button type="button" class="btn btn-s btn-warn" id="clearOldBtn">清理旧数据</button>
                    </div>
                </div>

                <div style="margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 12px;">
                    <div style="flex: 1; min-width: 200px; max-width: calc(33.33% - 8px);">
                        <div style="background: #4e73df; color: white; padding: 20px 15px; border-radius: 6px; text-align: center;">
                            <div style="font-size: 12px; opacity: 0.85; margin-bottom: 8px;">今日访问</div>
                            <div style="font-size: 32px; font-weight: bold; line-height: 1;"><?php echo number_format($stats['today']); ?></div>
                        </div>
                    </div>
                    <div style="flex: 1; min-width: 200px; max-width: calc(33.33% - 8px);">
                        <div style="background: #1cc88a; color: white; padding: 20px 15px; border-radius: 6px; text-align: center;">
                            <div style="font-size: 12px; opacity: 0.85; margin-bottom: 8px;"><?php echo $days === 0 ? '全部' : $days . '天'; ?>总计</div>
                            <div style="font-size: 32px; font-weight: bold; line-height: 1;"><?php echo number_format($stats['total']); ?></div>
                        </div>
                    </div>
                    <div style="flex: 1; min-width: 200px; max-width: calc(33.33% - 8px);">
                        <div style="background: #36b9cc; color: white; padding: 20px 15px; border-radius: 6px; text-align: center;">
                            <div style="font-size: 12px; opacity: 0.85; margin-bottom: 8px;">爬虫种类</div>
                            <div style="font-size: 32px; font-weight: bold; line-height: 1;"><?php echo number_format($stats['unique_crawlers']); ?></div>
                        </div>
                    </div>
                </div>

                <div class="typecho-table-wrap">
                    <div style="margin-bottom: 15px;">
                        <h4 style="display: inline-block; margin: 0;">最近访问记录</h4>
                        <form method="get" style="float: right;">
                            <input type="hidden" name="panel" value="MiraiCore/Crawler/manage-crawler.php">
                            <select name="days" class="btn btn-s" onchange="this.form.submit()" style="margin-right: 5px;">
                                <option value="7" <?php echo $days == 7 ? 'selected' : ''; ?>>最近7天</option>
                                <option value="14" <?php echo $days == 14 ? 'selected' : ''; ?>>最近14天</option>
                                <option value="30" <?php echo $days == 30 ? 'selected' : ''; ?>>最近30天</option>
                                <option value="90" <?php echo $days == 90 ? 'selected' : ''; ?>>最近3个月</option>
                                <option value="180" <?php echo $days == 180 ? 'selected' : ''; ?>>最近半年</option>
                                <option value="365" <?php echo $days == 365 ? 'selected' : ''; ?>>最近一年</option>
                                <option value="0" <?php echo $days == 0 ? 'selected' : ''; ?>>全部记录</option>
                            </select>
                            <input type="text" name="crawler" class="text-s" placeholder="搜索爬虫名称" value="<?php echo htmlspecialchars($crawlerFilter); ?>" style="padding: 4px 8px; margin-right: 5px;">
                            <button type="submit" class="btn btn-s btn-primary">筛选</button>
                        </form>
                    </div>

                    <table class="typecho-list-table">
                        <thead>
                            <tr>
                                <th>爬虫名称</th>
                                <th>访问URL</th>
                                <th width="130">IP地址</th>
                                <th width="160">访问时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($list)): ?>
                                <?php foreach ($list as $item): ?>
                                    <?php $formatted = MiraiCore_Crawler_Service::formatCrawlerName($item['name']); ?>
                                    <tr>
                                        <td>
                                            <span style="color: <?php echo $formatted['color']; ?>; font-weight: bold;">
                                                <?php echo htmlspecialchars($formatted['name']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?php echo htmlspecialchars($item['url']); ?>" target="_blank" title="<?php echo htmlspecialchars($item['url']); ?>">
                                                <?php
                                                    $urlPath = parse_url($item['url'], PHP_URL_PATH);
                                                    echo htmlspecialchars(mb_strlen($urlPath) > 50 ? mb_substr($urlPath, 0, 50) . '...' : $urlPath);
                                                ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['ip_address'] ?? '-'); ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', $item['crawled_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #999; padding: 30px;">
                                        暂无爬虫访问记录
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if ($totalPages > 1): ?>
                    <div class="typecho-pager">
                        <div class="typecho-pager-content">
                            <ul>
                                <?php
                                $pageNav = new \Typecho\Widget\Helper\PageNavigator\Box($total, $page, $perPage, \Typecho\Request::getInstance()->makeUriByRequest('page={page}'));
                                $pageNav->render('&laquo;', '&raquo;');
                                ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/copyright.php'; ?>
<?php include_once __TYPECHO_ROOT_DIR__ . $adminDir . '/footer.php'; ?>

<script>
(function() {
    'use strict';
    
    function initCrawlerPage() {
        var clearBtn = document.getElementById('clearOldBtn');
        if (!clearBtn) return;
        
        clearBtn.addEventListener('click', function() {
            var daysSelect = document.getElementById('clearDaysSelect');
            var days = parseInt(daysSelect.value);
            var daysText = daysSelect.options[daysSelect.selectedIndex].text;
            
            if (!confirm('确定要' + daysText + '的爬虫记录吗？此操作不可恢复。')) {
                return;
            }
            
            clearBtn.disabled = true;
            clearBtn.textContent = '清理中...';
            
            var token = '<?php echo $security->getToken("miraicrawler-clear"); ?>';
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo Typecho_Common::url('/action/miraicrawler-process', Typecho_Widget::widget('Widget_Options')->index); ?>', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var res = JSON.parse(xhr.responseText);
                            if (res.success) {
                                alert('已清理 ' + res.deleted + ' 条记录');
                                location.reload();
                            } else {
                                alert(res.message || '清理失败');
                                clearBtn.disabled = false;
                                clearBtn.textContent = '清理旧数据';
                            }
                        } catch (e) {
                            alert('响应解析失败');
                            clearBtn.disabled = false;
                            clearBtn.textContent = '清理旧数据';
                        }
                    } else {
                        alert('请求失败，请重试');
                        clearBtn.disabled = false;
                        clearBtn.textContent = '清理旧数据';
                    }
                }
            };
            xhr.send('do=clear&_token=' + encodeURIComponent(token) + '&days=' + days);
        });
    }
    
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ready(initCrawlerPage);
    } else if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCrawlerPage);
    } else {
        initCrawlerPage();
    }
})();
</script>
