<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class MiraiCore_Crawler_Service
{
    private static $db = null;
    private static $prefix = null;
    private static $tableName = 'mirai_crawler';
    private static $cacheDir = null;

    private const CRAWLER_NAME_MAP = [
        'Baiduspider' => ['name' => 'Baiduspider (百度)', 'color' => '#3385ff'],
        'Googlebot' => ['name' => 'Googlebot (Google)', 'color' => '#4285f4'],
        'bingbot' => ['name' => 'bingbot (必应)', 'color' => '#00bcf2'],
        '360Spider' => ['name' => '360Spider (360搜索)', 'color' => '#5cb85c'],
        'Bytespider' => ['name' => 'Bytespider (字节跳动)', 'color' => '#ff6b35'],
        'GPTBot' => ['name' => 'GPTBot (OpenAI)', 'color' => '#10a37f'],
        'YisouSpider' => ['name' => 'YisouSpider (神马搜索)', 'color' => '#e74c3c'],
        'Sogou' => ['name' => 'Sogou (搜狗)', 'color' => '#ff9500'],
        'Twitterbot' => ['name' => 'Twitterbot', 'color' => '#1da1f2'],
        'facebookexternalhit' => ['name' => 'Facebook', 'color' => '#1877f2'],
        'Discordbot' => ['name' => 'Discordbot', 'color' => '#5865f2'],
        'DuckDuckBot' => ['name' => 'DuckDuckBot', 'color' => '#de5833'],
        'DeepSeekBot' => ['name' => 'DeepSeekBot', 'color' => '#6f42c1'],
        'ClaudeBot' => ['name' => 'ClaudeBot', 'color' => '#d97706'],
        'Applebot' => ['name' => 'Applebot', 'color' => '#000000'],
        'Amazonbot' => ['name' => 'Amazonbot', 'color' => '#ff9900'],
    ];

    private static function getDb()
    {
        if (self::$db === null) {
            self::$db = Typecho_Db::get();
            self::$prefix = self::$db->getPrefix();
        }
        return self::$db;
    }

    private static function getTable(): string
    {
        return self::$prefix . self::$tableName;
    }

    private static function getCacheDir(): string
    {
        if (self::$cacheDir === null) {
            self::$cacheDir = __DIR__ . '/cache';
            if (!is_dir(self::$cacheDir)) {
                @mkdir(self::$cacheDir, 0755, true);
            }
        }
        return self::$cacheDir;
    }

    private static function getCacheFile(): string
    {
        return self::getCacheDir() . '/crawler_queue.json';
    }

    private static function fallbackToDirectInsert(array $crawlerInfo): bool
    {
        if (empty($crawlerInfo) || empty($crawlerInfo['name'])) {
            return false;
        }

        try {
            $db = self::getDb();
            $table = self::getTable();

            $db->query($db->insert($table)->rows([
                'name' => self::sanitizeString($crawlerInfo['name'], 255),
                'ua' => self::sanitizeString($crawlerInfo['ua'] ?? '', 500),
                'url' => self::sanitizeUrl($crawlerInfo['url'] ?? ''),
                'ip_address' => self::sanitizeString($crawlerInfo['ip_address'] ?? '', 50),
                'crawled_at' => time()
            ]));

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private static function acquireFileLock(string $cacheFile, string $mode = 'c+'): mixed
    {
        $fp = @fopen($cacheFile, $mode);
        if (!$fp) {
            return false;
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return false;
        }

        return $fp;
    }

    public static function recordCrawlerAsync(array $crawlerInfo): bool
    {
        if (empty($crawlerInfo) || empty($crawlerInfo['name'])) {
            return false;
        }

        $cacheFile = self::getCacheFile();

        try {
            $fp = self::acquireFileLock($cacheFile);
            if ($fp === false) {
                return self::fallbackToDirectInsert($crawlerInfo);
            }

            $content = stream_get_contents($fp);
            $queue = $content ? (json_decode($content, true) ?: []) : [];

            $queue[] = [
                'name' => $crawlerInfo['name'],
                'ua' => $crawlerInfo['ua'] ?? '',
                'url' => $crawlerInfo['url'] ?? '',
                'ip_address' => $crawlerInfo['ip_address'] ?? '',
                'queued_at' => time()
            ];

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($queue));
            fflush($fp);

            flock($fp, LOCK_UN);
            fclose($fp);

            return true;
        } catch (Exception $e) {
            return self::fallbackToDirectInsert($crawlerInfo);
        }
    }

    public static function processQueue(): int
    {
        $cacheFile = self::getCacheFile();

        if (!file_exists($cacheFile) || filesize($cacheFile) === 0) {
            return 0;
        }

        try {
            $fp = self::acquireFileLock($cacheFile, 'r');
            if ($fp === false) {
                return 0;
            }

            $content = stream_get_contents($fp);
            flock($fp, LOCK_UN);
            fclose($fp);

            if (!$content) {
                return 0;
            }

            $queue = json_decode($content, true) ?: [];
            if (empty($queue)) {
                return 0;
            }

            $db = self::getDb();
            $table = self::getTable();
            $processed = 0;

            foreach ($queue as $item) {
                $data = [
                    'name' => self::sanitizeString($item['name'] ?? '', 255),
                    'ua' => self::sanitizeString($item['ua'] ?? '', 500),
                    'url' => self::sanitizeUrl($item['url'] ?? ''),
                    'ip_address' => self::sanitizeString($item['ip_address'] ?? '', 50),
                    'crawled_at' => $item['queued_at'] ?? time()
                ];

                try {
                    $db->query($db->insert($table)->rows($data));
                    $processed++;
                } catch (Exception $e) {
                    // 忽略单条插入错误，继续处理下一条
                }
            }

            if ($processed > 0) {
                // 使用 truncate 更高效地清空文件
                $fp = @fopen($cacheFile, 'w');
                if ($fp) {
                    fclose($fp);
                }
            }

            return $processed;
        } catch (Exception $e) {
            return 0;
        }
    }

    public static function getCrawlerStats(int $days = 7): array
    {
        try {
            $db = self::getDb();
            $table = self::getTable();
            $todayStart = strtotime(date('Y-m-d'));

            // days=0 表示查询全部
            if ($days === 0) {
                $total = $db->fetchRow(
                    $db->select('COUNT(*) as total')->from($table)
                )['total'] ?? 0;

                $uniqueCrawlers = $db->fetchRow(
                    $db->select('COUNT(DISTINCT name) as cnt')->from($table)
                )['cnt'] ?? 0;
            } else {
                $threshold = time() - ($days * 86400);

                $total = $db->fetchRow(
                    $db->select('COUNT(*) as total')->from($table)->where('crawled_at >= ?', $threshold)
                )['total'] ?? 0;

                $uniqueCrawlers = $db->fetchRow(
                    $db->select('COUNT(DISTINCT name) as cnt')->from($table)->where('crawled_at >= ?', $threshold)
                )['cnt'] ?? 0;
            }

            $todayCount = $db->fetchRow(
                $db->select('COUNT(*) as today')->from($table)->where('crawled_at >= ?', $todayStart)
            )['today'] ?? 0;

            return [
                'total' => (int)$total,
                'unique_crawlers' => (int)$uniqueCrawlers,
                'today' => (int)$todayCount
            ];
        } catch (Exception $e) {
            return ['total' => 0, 'unique_crawlers' => 0, 'today' => 0];
        }
    }

    public static function getCrawlerList(array $options = []): array
    {
        try {
            $db = self::getDb();
            $table = self::getTable();

            $page = max(1, intval($options['page'] ?? 1));
            $perPage = max(10, min(100, intval($options['per_page'] ?? 50)));
            $days = intval($options['days'] ?? 7);
            if ($days !== 0) {
                $days = max(1, $days);
            }
            $crawler = trim($options['crawler'] ?? '');

            $offset = ($page - 1) * $perPage;

            // days=0 表示查询全部
            if ($days === 0) {
                $select = $db->select('COUNT(*) as count')->from($table);
                $query = $db->select()->from($table)
                    ->order('crawled_at', Typecho_Db::SORT_DESC)
                    ->offset($offset)
                    ->limit($perPage);
            } else {
                $threshold = time() - ($days * 86400);
                $select = $db->select('COUNT(*) as count')->from($table)->where('crawled_at >= ?', $threshold);
                $query = $db->select()->from($table)
                    ->where('crawled_at >= ?', $threshold)
                    ->order('crawled_at', Typecho_Db::SORT_DESC)
                    ->offset($offset)
                    ->limit($perPage);
            }

            if ($crawler !== '') {
                $crawler = self::sanitizeString($crawler, 255);
                $select->where('name LIKE ?', '%' . $crawler . '%');
                $query->where('name LIKE ?', '%' . $crawler . '%');
            }

            $total = $db->fetchRow($select)['count'] ?? 0;
            $rows = $db->fetchAll($query);

            return [
                'data' => $rows,
                'total' => (int)$total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $total > 0 ? ceil($total / $perPage) : 0
            ];
        } catch (Exception $e) {
            return ['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 50, 'total_pages' => 0];
        }
    }

    public static function clearOldRecords(int $days = 30): int
    {
        try {
            $db = self::getDb();
            $table = self::getTable();

            // days=0 表示清理全部数据
            if ($days === 0) {
                $countResult = $db->fetchRow(
                    $db->select('COUNT(*) as count')->from($table)
                );
                $count = intval($countResult['count'] ?? 0);

                if ($count > 0) {
                    $db->query($db->delete($table));
                }
                return $count;
            }

            $threshold = time() - ($days * 86400);

            $countResult = $db->fetchRow(
                $db->select('COUNT(*) as count')->from($table)->where('crawled_at < ?', $threshold)
            );
            $count = intval($countResult['count'] ?? 0);

            if ($count > 0) {
                $db->query($db->delete($table)->where('crawled_at < ?', $threshold));
            }

            return $count;
        } catch (Exception $e) {
            return 0;
        }
    }

    private static function sanitizeString(string $value, int $maxLength = 255): string
    {
        $value = trim($value);
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        if (strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength);
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function sanitizeUrl(string $url): string
    {
        $url = trim($url);
        $url = filter_var($url, FILTER_SANITIZE_URL) ?: '';
        if (strlen($url) > 500) {
            $url = substr($url, 0, 500);
        }
        return $url;
    }

    public static function formatCrawlerName(string $name): array
    {
        foreach (self::CRAWLER_NAME_MAP as $pattern => $info) {
            if (stripos($name, $pattern) !== false) {
                return $info;
            }
        }

        return ['name' => $name, 'color' => '#6c757d'];
    }
}