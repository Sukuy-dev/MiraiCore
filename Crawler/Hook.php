<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class MiraiCore_Crawler_Hook
{
    private const EXCLUDE_PATHS = [
        '/action/', '/admin/', '/oauth/', '/api/', '/user/',
        '/images/', '/uploads/', '/usr/', '/var/',
        '.json', '.xml', '.rss', '.atom', '.txt', '.ico',
        '.css', '.js', '.jpg', '.jpeg', '.png', '.gif', '.svg', '.avif', '.webp', '.bmp',
        '.woff', '.woff2', '.ttf', '.eot', '.map',
        '.zip', '.tar', '.gz', '.rar', '.7z',
        '.pdf', '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx',
        '.mp3', '.mp4', '.avi', '.mov', '.wmv', '.flv', '.webm',
        '.exe', '.dll', '.so', '.bin'
    ];

    public static function handle($archive)
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return;
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        if (strlen($userAgent) < 10 || strlen($userAgent) > 500) {
            return;
        }

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        foreach (self::EXCLUDE_PATHS as $path) {
            if (strpos($requestUri, $path) !== false) {
                return;
            }
        }

        // 额外检查：URL是否包含文件扩展名（可能是直接文件请求）
        $pathInfo = parse_url($requestUri, PHP_URL_PATH) ?? '';
        if (preg_match('/\.[a-zA-Z0-9]{2,6}$/', $pathInfo)) {
            return;
        }

        require_once __DIR__ . '/Detect.php';

        $crawlerDetect = new MiraiCore_Crawler_Detect();

        if (!$crawlerDetect->isCrawler()) {
            return;
        }

        $crawlerName = $crawlerDetect->getMatches();
        if (empty($crawlerName)) {
            $crawlerName = 'Unknown_' . substr(md5($userAgent), 0, 8);
        }

        $isValidPage = false;
        if ($archive instanceof Typecho_Widget) {
            try {
                $isValidPage = (
                    $archive->is('single') ||
                    $archive->is('page') ||
                    $archive->is('archive') ||
                    $archive->is('index') ||
                    $archive->is('search')
                );
            } catch (Exception $e) {
            }
        }

        if (!$isValidPage) {
            return;
        }

        $currentUrl = '';
        if ($archive instanceof Typecho_Widget) {
            try {
                $options = Typecho_Widget::widget('Widget_Options');
                $currentUrl = $archive->getArchiveUrl();
                if (empty($currentUrl)) {
                    $currentUrl = $options->siteUrl;
                }
            } catch (Exception $e) {
            }
        }

        if (empty($currentUrl)) {
            $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://';
            $currentUrl .= $_SERVER['HTTP_HOST'] ?? 'localhost';
            $currentUrl .= $requestUri;
        }

        $ipAddress = '';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $ipAddress = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        }

        if (strpos($ipAddress, ',') !== false) {
            $ips = explode(',', $ipAddress);
            $ipAddress = trim($ips[0]);
        }

        $crawlerInfo = [
            'name' => $crawlerName,
            'ua' => $userAgent,
            'url' => $currentUrl,
            'ip_address' => $ipAddress
        ];

        MiraiCore_Crawler_Service::recordCrawlerAsync($crawlerInfo);

        if (rand(1, 100) <= 5) {
            try {
                MiraiCore_Crawler_Service::processQueue();
            } catch (Exception $e) {
                // 静默处理
            }
        }
    }
}