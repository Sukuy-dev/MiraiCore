<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class MiraiCore_Sitemap_Service
{
    private static $defaults = [
        'enabled' => '1',
        'include_posts' => '1',
        'include_pages' => '1',
        'include_categories' => '1',
        'include_tags' => '1',
        'include_authors' => '0',
        'include_home_pagination' => '1',
        'include_category_pagination' => '0',
        'include_tag_pagination' => '0',
        'max_urls_per_file' => '5000',
        'auto_mode' => 'interval',
        'auto_interval_seconds' => '1800',
        'post_changefreq' => 'daily',
        'page_changefreq' => 'weekly',
        'category_changefreq' => 'weekly',
        'tag_changefreq' => 'weekly',
        'author_changefreq' => 'monthly',
        'home_changefreq' => 'daily',
        'post_priority' => '0.8',
        'page_priority' => '0.7',
        'category_priority' => '0.6',
        'tag_priority' => '0.6',
        'author_priority' => '0.5',
        'home_priority' => '1.0',
        'exclude_cids' => '',
        'exclude_mids' => '',
        'dirty' => '1',
        'last_build_time' => '0',
        'last_build_message' => '',
        'last_build_status' => 'idle',
        'last_files' => '[]',
        'cron_token' => ''
    ];

    public static function getConfig()
    {
        $raw = self::getOption('mirai_core_sitemap_config', '');
        $config = [];
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }
        $config = array_merge(self::$defaults, $config);
        if (trim((string)$config['cron_token']) === '') {
            $config['cron_token'] = self::generateToken(40);
            self::saveConfig($config);
        }
        return $config;
    }

    public static function saveConfig($config)
    {
        $config = array_merge(self::$defaults, (array)$config);
        $config['enabled'] = self::boolString($config['enabled']);
        $config['include_posts'] = self::boolString($config['include_posts']);
        $config['include_pages'] = self::boolString($config['include_pages']);
        $config['include_categories'] = self::boolString($config['include_categories']);
        $config['include_tags'] = self::boolString($config['include_tags']);
        $config['include_authors'] = self::boolString($config['include_authors']);
        $config['include_home_pagination'] = self::boolString($config['include_home_pagination']);
        $config['include_category_pagination'] = self::boolString($config['include_category_pagination']);
        $config['include_tag_pagination'] = self::boolString($config['include_tag_pagination']);
        $config['max_urls_per_file'] = strval(max(100, min(50000, intval($config['max_urls_per_file']))));
        $config['auto_interval_seconds'] = strval(max(60, intval($config['auto_interval_seconds'])));
        $config['auto_mode'] = in_array($config['auto_mode'], ['manual', 'realtime', 'interval'], true) ? $config['auto_mode'] : 'interval';
        $config['post_changefreq'] = self::normalizeFreq($config['post_changefreq']);
        $config['page_changefreq'] = self::normalizeFreq($config['page_changefreq']);
        $config['category_changefreq'] = self::normalizeFreq($config['category_changefreq']);
        $config['tag_changefreq'] = self::normalizeFreq($config['tag_changefreq']);
        $config['author_changefreq'] = self::normalizeFreq($config['author_changefreq']);
        $config['home_changefreq'] = self::normalizeFreq($config['home_changefreq']);
        $config['post_priority'] = self::normalizePriority($config['post_priority']);
        $config['page_priority'] = self::normalizePriority($config['page_priority']);
        $config['category_priority'] = self::normalizePriority($config['category_priority']);
        $config['tag_priority'] = self::normalizePriority($config['tag_priority']);
        $config['author_priority'] = self::normalizePriority($config['author_priority']);
        $config['home_priority'] = self::normalizePriority($config['home_priority']);
        $config['exclude_cids'] = trim((string)$config['exclude_cids']);
        $config['exclude_mids'] = trim((string)$config['exclude_mids']);
        if (trim((string)$config['cron_token']) === '') {
            $config['cron_token'] = self::generateToken(40);
        }
        self::setOption('mirai_core_sitemap_config', json_encode($config, JSON_UNESCAPED_UNICODE));
        return $config;
    }

    public static function markDirty()
    {
        $config = self::getConfig();
        $config['dirty'] = '1';
        self::saveConfig($config);
    }

    public static function autoBuildIfNeeded($force = false)
    {
        $config = self::getConfig();
        if ($config['enabled'] !== '1' || !self::isThemeSitemapEnabled()) {
            return false;
        }
        if ($force) {
            self::buildAll($config);
            return true;
        }
        $mode = (string)$config['auto_mode'];
        if ($mode === 'manual') {
            return false;
        }
        if ($mode === 'realtime') {
            if ($config['dirty'] === '1') {
                self::buildAll($config);
                return true;
            }
            return false;
        }
        $lastBuild = intval($config['last_build_time']);
        $interval = intval($config['auto_interval_seconds']);
        $now = time();
        if ($config['dirty'] === '1' && ($lastBuild <= 0 || ($now - $lastBuild) >= $interval)) {
            self::buildAll($config);
            return true;
        }
        return false;
    }

    public static function maybeHandleRequest()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
        if ($uri === '') {
            return false;
        }
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return false;
        }
        $path = '/' . ltrim($path, '/');
        
        if ($path === '/sitemap.xml') {
            if (!self::isThemeSitemapEnabled()) {
                return false;
            }
            self::autoBuildIfNeeded();
            $file = self::siteRootPath() . 'sitemap.xml';
        } elseif (preg_match('#^/sitemap/(sitemap-[a-z0-9\-]+\.xml)$#i', $path, $matches)) {
            if (!self::isThemeSitemapEnabled()) {
                return false;
            }
            self::autoBuildIfNeeded();
            $file = self::sitemapDirPath() . $matches[1];
        } else {
            return false;
        }

        if (!file_exists($file)) {
            self::buildAll();
        }
        if (!file_exists($file)) {
            header('HTTP/1.1 404 Not Found');
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Not Found';
            exit;
        }
        header('Content-Type: application/xml; charset=UTF-8');
        readfile($file);
        exit;
    }

    public static function buildAll($config = null)
    {
        $config = $config ?: self::getConfig();
        if (!self::isThemeSitemapEnabled()) {
            self::setBuildResult(false, '主题未启用 SiteMap', []);
            return ['success' => false, 'message' => '主题未启用 SiteMap', 'files' => []];
        }
        $enabled = self::boolValue($config['enabled']);
        if (!$enabled) {
            self::setBuildResult(false, '网站地图未启用', []);
            return ['success' => false, 'message' => '网站地图未启用', 'files' => []];
        }
        $maxUrls = max(100, min(50000, intval($config['max_urls_per_file'])));
        $allFiles = [];
        try {
            $groups = self::collectUrlGroups($config);
            foreach ($groups as $groupName => $urls) {
                if (empty($urls)) {
                    continue;
                }
                $chunks = array_chunk($urls, $maxUrls);
                foreach ($chunks as $index => $chunk) {
                    $fileName = count($chunks) > 1 ? 'sitemap-' . $groupName . '-' . ($index + 1) . '.xml' : 'sitemap-' . $groupName . '.xml';
                    self::writeSitemapFile($fileName, $chunk);
                    $allFiles[] = $fileName;
                }
            }
            if (empty($allFiles)) {
                self::writeSitemapFile('sitemap-empty.xml', []);
                $allFiles[] = 'sitemap-empty.xml';
            }
            self::writeIndexFile('sitemap.xml', $allFiles);
            self::cleanupOldFiles($config, $allFiles);
            $config['dirty'] = '0';
            $config['last_build_time'] = strval(time());
            $config['last_build_status'] = 'success';
            $config['last_build_message'] = '生成成功，共 ' . count($allFiles) . ' 个子文件';
            $config['last_files'] = json_encode($allFiles, JSON_UNESCAPED_UNICODE);
            self::saveConfig($config);
            return ['success' => true, 'message' => $config['last_build_message'], 'files' => $allFiles];
        } catch (Throwable $e) {
            self::setBuildResult(false, $e->getMessage(), $allFiles);
            return ['success' => false, 'message' => '生成失败：' . $e->getMessage(), 'files' => $allFiles];
        }
    }

    private static function setBuildResult($ok, $message, $files)
    {
        $config = self::getConfig();
        $config['last_build_time'] = strval(time());
        $config['last_build_status'] = $ok ? 'success' : 'failed';
        $config['last_build_message'] = (string)$message;
        $config['last_files'] = json_encode(array_values($files), JSON_UNESCAPED_UNICODE);
        self::saveConfig($config);
    }

    private static function isThemeSitemapEnabled()
    {
        try {
            $options = Typecho_Widget::widget('Widget_Options');
            $themeName = isset($options->theme) ? (string)$options->theme : '';
            if ($themeName === '') {
                return false;
            }
            if (strcasecmp($themeName, 'Mirai') !== 0) {
                return false;
            }
            $themeOptions = isset($options->{$themeName}) ? $options->{$themeName} : null;
            if (!$themeOptions) {
                $db = Typecho_Db::get();
                $row = $db->fetchRow($db->select('value')->from('table.options')->where('name = ?', 'theme:' . $themeName));
                if ($row && !empty($row['value'])) {
                    // Mirai 主题使用 JSON 格式存储
                    $themeData = json_decode($row['value'], true);
                    if (is_array($themeData) && isset($themeData['sitemapEnable'])) {
                        return !empty($themeData['sitemapEnable']);
                    }
                    return true;
                }
                return false;
            }
            
            if (!isset($themeOptions->sitemapEnable)) {
                return true;
            }
            return !empty($themeOptions->sitemapEnable);
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function cleanupOldFiles($config, $newFiles)
    {
        $lastFilesRaw = isset($config['last_files']) ? (string)$config['last_files'] : '[]';
        $lastFiles = json_decode($lastFilesRaw, true);
        if (!is_array($lastFiles)) {
            $lastFiles = [];
        }
        $delete = array_values(array_diff($lastFiles, $newFiles));
        $dir = self::sitemapDirPath();
        foreach ($delete as $file) {
            $name = basename((string)$file);
            if (!preg_match('#^sitemap-[a-z0-9\-]+\.xml$#i', $name) && $name !== 'sitemap-empty.xml') {
                continue;
            }
            $path = $dir . $name;
            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    private static function collectUrlGroups($config)
    {
        $groups = [];
        $db = Typecho_Db::get();
        $options = Typecho_Widget::widget('Widget_Options');
        $excludeCids = self::parseCsvInts($config['exclude_cids']);
        $excludeMids = self::parseCsvInts($config['exclude_mids']);
        $now = time();

        $home = [];
        $home[] = self::entry(rtrim((string)$options->siteUrl, '/') . '/', $now, $config['home_changefreq'], $config['home_priority']);
        if (self::boolValue($config['include_home_pagination'])) {
            $pageSize = max(1, intval($options->pageSize));
            $postCount = intval($db->fetchObject(
                $db->select(['COUNT(*)' => 'num'])->from('table.contents')->where('type = ?', 'post')->where('status = ?', 'publish')->where('created <= ?', $now)
            )->num);
            $pages = (int)ceil($postCount / $pageSize);
            for ($i = 2; $i <= $pages; $i++) {
                $home[] = self::entry(Typecho_Common::url('page/' . $i . '/', $options->index), $now, $config['home_changefreq'], $config['home_priority']);
            }
        }
        $groups['home'] = $home;

        if (self::boolValue($config['include_posts'])) {
            $posts = $db->fetchAll(
                $db->select('cid', 'slug', 'created', 'modified', 'title')
                    ->from('table.contents')
                    ->where('type = ?', 'post')
                    ->where('status = ?', 'publish')
                    ->where('created <= ?', $now)
                    ->order('created', Typecho_Db::SORT_DESC)
            );
            $list = [];
            foreach ($posts as $post) {
                $cid = intval($post['cid']);
                if (in_array($cid, $excludeCids, true)) {
                    continue;
                }
                $loc = self::buildPostUrl($post, $options);
                if ($loc === '') {
                    continue;
                }
                $list[] = self::entry($loc, intval($post['modified']) > 0 ? intval($post['modified']) : intval($post['created']), $config['post_changefreq'], $config['post_priority']);
            }
            $groups['posts'] = $list;
        }

        if (self::boolValue($config['include_pages'])) {
            $pages = $db->fetchAll(
                $db->select('cid', 'slug', 'created', 'modified', 'title')
                    ->from('table.contents')
                    ->where('type = ?', 'page')
                    ->where('status = ?', 'publish')
                    ->where('created <= ?', $now)
                    ->order('created', Typecho_Db::SORT_DESC)
            );
            $list = [];
            foreach ($pages as $page) {
                $cid = intval($page['cid']);
                if (in_array($cid, $excludeCids, true)) {
                    continue;
                }
                $loc = self::buildPageUrl($page, $options);
                if ($loc === '') {
                    continue;
                }
                $list[] = self::entry($loc, intval($page['modified']) > 0 ? intval($page['modified']) : intval($page['created']), $config['page_changefreq'], $config['page_priority']);
            }
            $groups['pages'] = $list;
        }

        if (self::boolValue($config['include_categories'])) {
            $rows = $db->fetchAll($db->select('mid', 'slug', 'name', 'count')->from('table.metas')->where('type = ?', 'category')->where('count > ?', 0)->order('mid', Typecho_Db::SORT_ASC));
            $list = [];
            foreach ($rows as $row) {
                $mid = intval($row['mid']);
                if (in_array($mid, $excludeMids, true)) {
                    continue;
                }
                $loc = self::buildCategoryUrl($row, $options);
                if ($loc === '') {
                    continue;
                }
                $list[] = self::entry($loc, $now, $config['category_changefreq'], $config['category_priority']);
                if (self::boolValue($config['include_category_pagination'])) {
                    $pageSize = max(1, intval($options->pageSize));
                    $pages = (int)ceil(intval($row['count']) / $pageSize);
                    for ($i = 2; $i <= $pages; $i++) {
                        $list[] = self::entry(Typecho_Common::url('category/' . rawurlencode((string)$row['slug']) . '/page/' . $i . '/', $options->index), $now, $config['category_changefreq'], $config['category_priority']);
                    }
                }
            }
            $groups['categories'] = $list;
        }

        if (self::boolValue($config['include_tags'])) {
            $rows = $db->fetchAll($db->select('mid', 'slug', 'name', 'count')->from('table.metas')->where('type = ?', 'tag')->where('count > ?', 0)->order('mid', Typecho_Db::SORT_ASC));
            $list = [];
            foreach ($rows as $row) {
                $mid = intval($row['mid']);
                if (in_array($mid, $excludeMids, true)) {
                    continue;
                }
                $loc = self::buildTagUrl($row, $options);
                if ($loc === '') {
                    continue;
                }
                $list[] = self::entry($loc, $now, $config['tag_changefreq'], $config['tag_priority']);
                if (self::boolValue($config['include_tag_pagination'])) {
                    $pageSize = max(1, intval($options->pageSize));
                    $pages = (int)ceil(intval($row['count']) / $pageSize);
                    for ($i = 2; $i <= $pages; $i++) {
                        $list[] = self::entry(Typecho_Common::url('tag/' . rawurlencode((string)$row['slug']) . '/page/' . $i . '/', $options->index), $now, $config['tag_changefreq'], $config['tag_priority']);
                    }
                }
            }
            $groups['tags'] = $list;
        }

        if (self::boolValue($config['include_authors'])) {
            $users = $db->fetchAll($db->query("
                SELECT u.uid, u.name, MAX(c.modified) AS modified_at, COUNT(c.cid) AS post_count
                FROM {$db->getPrefix()}users u
                INNER JOIN {$db->getPrefix()}contents c ON c.authorId = u.uid
                WHERE c.type = 'post' AND c.status = 'publish' AND c.created <= {$now}
                GROUP BY u.uid, u.name
            "));
            $list = [];
            foreach ($users as $userRow) {
                $loc = self::buildAuthorUrl($userRow, $options);
                if ($loc === '') {
                    continue;
                }
                $list[] = self::entry($loc, intval($userRow['modified_at']) > 0 ? intval($userRow['modified_at']) : $now, $config['author_changefreq'], $config['author_priority']);
            }
            $groups['authors'] = $list;
        }

        return $groups;
    }

    private static function writeSitemapFile($name, $entries)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($entries as $entry) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars((string)$entry['loc'], ENT_QUOTES | ENT_XML1, 'UTF-8') . '</loc>' . "\n";
            if (!empty($entry['lastmod'])) {
                $xml .= '    <lastmod>' . $entry['lastmod'] . '</lastmod>' . "\n";
            }
            if (!empty($entry['changefreq'])) {
                $xml .= '    <changefreq>' . $entry['changefreq'] . '</changefreq>' . "\n";
            }
            if (!empty($entry['priority'])) {
                $xml .= '    <priority>' . $entry['priority'] . '</priority>' . "\n";
            }
            $xml .= '  </url>' . "\n";
        }
        $xml .= '</urlset>' . "\n";
        self::atomicWrite(self::sitemapDirPath() . basename($name), $xml);
    }

    private static function writeIndexFile($name, $files)
    {
        $siteUrl = rtrim((string)Typecho_Widget::widget('Widget_Options')->siteUrl, '/');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $lastmod = gmdate('c');
        foreach ($files as $file) {
            $url = $siteUrl . '/sitemap/' . ltrim($file, '/');
            $xml .= '  <sitemap>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($url, ENT_QUOTES | ENT_XML1, 'UTF-8') . '</loc>' . "\n";
            $xml .= '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
            $xml .= '  </sitemap>' . "\n";
        }
        $xml .= '</sitemapindex>' . "\n";
        self::atomicWrite(self::siteRootPath() . basename($name), $xml);
    }

    private static function entry($loc, $time, $freq, $priority)
    {
        return [
            'loc' => (string)$loc,
            'lastmod' => intval($time) > 0 ? gmdate('c', intval($time)) : '',
            'changefreq' => self::normalizeFreq($freq),
            'priority' => self::normalizePriority($priority)
        ];
    }

    private static function buildPostUrl($row, $options)
    {
        try {
            $url = Typecho_Router::url('post', $row, $options->index);
            return self::normalizeUrl($url, $options->siteUrl);
        } catch (Throwable $e) {
            $slug = trim((string)($row['slug'] ?? ''));
            if ($slug === '') {
                return '';
            }
            return self::normalizeUrl(Typecho_Common::url($slug . '.html', $options->index), $options->siteUrl);
        }
    }

    private static function buildPageUrl($row, $options)
    {
        try {
            $url = Typecho_Router::url('page', $row, $options->index);
            return self::normalizeUrl($url, $options->siteUrl);
        } catch (Throwable $e) {
            $slug = trim((string)($row['slug'] ?? ''));
            if ($slug === '') {
                return '';
            }
            return self::normalizeUrl(Typecho_Common::url($slug, $options->index), $options->siteUrl);
        }
    }

    private static function buildCategoryUrl($row, $options)
    {
        try {
            $url = Typecho_Router::url('category', $row, $options->index);
            return self::normalizeUrl($url, $options->siteUrl);
        } catch (Throwable $e) {
            $slug = trim((string)($row['slug'] ?? ''));
            if ($slug === '') {
                return '';
            }
            return self::normalizeUrl(Typecho_Common::url('category/' . rawurlencode($slug) . '/', $options->index), $options->siteUrl);
        }
    }

    private static function buildTagUrl($row, $options)
    {
        try {
            $url = Typecho_Router::url('tag', $row, $options->index);
            return self::normalizeUrl($url, $options->siteUrl);
        } catch (Throwable $e) {
            $slug = trim((string)($row['slug'] ?? ''));
            if ($slug === '') {
                return '';
            }
            return self::normalizeUrl(Typecho_Common::url('tag/' . rawurlencode($slug) . '/', $options->index), $options->siteUrl);
        }
    }

    private static function buildAuthorUrl($row, $options)
    {
        try {
            $url = Typecho_Router::url('author', $row, $options->index);
            return self::normalizeUrl($url, $options->siteUrl);
        } catch (Throwable $e) {
            $uid = intval($row['uid'] ?? 0);
            if ($uid <= 0) {
                return '';
            }
            return self::normalizeUrl(Typecho_Common::url('author/' . $uid . '/', $options->index), $options->siteUrl);
        }
    }

    private static function normalizeUrl($url, $siteUrl)
    {
        $url = trim((string)$url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        return Typecho_Common::url($url, $siteUrl);
    }

    private static function parseCsvInts($text)
    {
        $result = [];
        $parts = preg_split('/[\s,]+/', (string)$text);
        if (!is_array($parts)) {
            return [];
        }
        foreach ($parts as $part) {
            $v = intval($part);
            if ($v > 0) {
                $result[] = $v;
            }
        }
        return array_values(array_unique($result));
    }

    private static function atomicWrite($path, $content)
    {
        $tmp = $path . '.' . uniqid('tmp_', true);
        file_put_contents($tmp, $content);
        @chmod($tmp, 0644);
        if (!@rename($tmp, $path)) {
            @unlink($path);
            rename($tmp, $path);
        }
    }

    private static function sitemapDirPath()
    {
        $dir = self::siteRootPath() . 'sitemap' . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private static function siteRootPath()
    {
        return rtrim(__TYPECHO_ROOT_DIR__, '\\/') . DIRECTORY_SEPARATOR;
    }

    private static function getOption($name, $default = '')
    {
        $db = Typecho_Db::get();
        $row = $db->fetchRow(
            $db->select('value')->from('table.options')->where('name = ?', (string)$name)->where('user = ?', 0)->limit(1)
        );
        if (!$row || !array_key_exists('value', $row)) {
            return $default;
        }
        return (string)$row['value'];
    }

    private static function setOption($name, $value)
    {
        $db = Typecho_Db::get();
        $exists = $db->fetchRow(
            $db->select('name')->from('table.options')->where('name = ?', (string)$name)->where('user = ?', 0)->limit(1)
        );
        if ($exists) {
            $db->query($db->update('table.options')->rows(['value' => (string)$value])->where('name = ?', (string)$name)->where('user = ?', 0));
            return;
        }
        $db->query($db->insert('table.options')->rows(['name' => (string)$name, 'user' => 0, 'value' => (string)$value]));
    }

    private static function boolString($value)
    {
        return self::boolValue($value) ? '1' : '0';
    }

    private static function boolValue($value)
    {
        return in_array((string)$value, ['1', 'true', 'yes', 'on'], true);
    }

    private static function normalizeFreq($value)
    {
        $allowed = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
        $value = strtolower(trim((string)$value));
        return in_array($value, $allowed, true) ? $value : 'weekly';
    }

    private static function normalizePriority($value)
    {
        $num = floatval($value);
        if ($num < 0) {
            $num = 0;
        }
        if ($num > 1) {
            $num = 1;
        }
        return number_format($num, 1, '.', '');
    }

    private static function generateToken($length = 40)
    {
        $length = max(16, intval($length));
        try {
            return bin2hex(random_bytes((int)ceil($length / 2)));
        } catch (Throwable $e) {
            return md5(uniqid('mirai_sitemap_', true)) . sha1((string)microtime(true));
        }
    }
}
