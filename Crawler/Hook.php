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
        // 解码后再检查：防止 /credentials/aws%2ejson 这类 URL 编码绕过静态资源排除
        $decodedUri = rawurldecode($requestUri);
        foreach (self::EXCLUDE_PATHS as $path) {
            if (strpos($decodedUri, $path) !== false) {
                return;
            }
        }

        // 额外检查：URL是否包含文件扩展名（可能是直接文件请求）
        // 放行 .html/.htm——Typecho 文章固定链接（/{category}/{slug}.html）以此结尾，属于正常页面
        $pathInfo = parse_url($decodedUri, PHP_URL_PATH) ?? '';
        if (preg_match('/\.(?!html?$)[a-zA-Z0-9]{2,6}$/i', $pathInfo)) {
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

        $candidates = [];
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $ip) {
                $candidates[] = trim($ip);
            }
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $candidates[] = trim($_SERVER['HTTP_X_REAL_IP']);
        }
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $candidates[] = trim($_SERVER['REMOTE_ADDR']);
        }

        $ipAddress = '';
        foreach ($candidates as $ip) {
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $ipAddress = $ip;
                break;
            }
        }

        if ($ipAddress === '') {
            foreach ($candidates as $ip) {
                if ($ip !== '') {
                    $ipAddress = $ip;
                    break;
                }
            }
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