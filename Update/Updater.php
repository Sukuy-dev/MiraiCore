<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class MiraiCore_Updater
{
    const GITHUB_API = 'https://api.github.com/repos/Sukuy-dev/MiraiCore/releases/latest';

    private static $MIRRORS = array(
        'https://gh-proxy.com/',
    );

    private $pluginDir;
    private $tmpDir;

    public function __construct()
    {
        $this->pluginDir = rtrim(dirname(__DIR__), '/\\');
        $this->tmpDir = $this->pluginDir . '/tmp_update';
    }

    public function checkLatestRelease($force = false)
    {
        $cacheFile = $this->tmpDir . '/.update_cache.json';
        $etagFile  = $this->tmpDir . '/.update_etag.txt';

        $cached = $this->readCache($cacheFile);

        if (!$force && $cached && (time() - $cached['ts'] < 3600)) {
            return $this->buildResult($cached['data']);
        }

        $headers = array(
            'Accept: application/vnd.github.v3+json',
            'User-Agent: MiraiCore-Updater/' . MiraiCore_Plugin::VERSION,
        );
        if (!$force && file_exists($etagFile)) {
            $etag = trim(file_get_contents($etagFile));
            if ($etag !== '') {
                $headers[] = 'If-None-Match: ' . $etag;
            }
        }

        list($body, $httpCode, $respHeaders) = $this->httpGetWithHeaders(self::GITHUB_API, $headers, 10);

        if ($httpCode === 304 && $cached) {
            $cached['ts'] = time();
            $this->writeCache($cacheFile, $cached);
            return $this->buildResult($cached['data']);
        }

        if ($httpCode === 200 && $body) {
            $data = @json_decode($body, true);
            if (is_array($data) && !empty($data['tag_name'])) {
                $result = array(
                    'version'      => ltrim($data['tag_name'], 'vV'),
                    'download_url' => $this->extractDownloadUrl($data),
                    'html_url'     => isset($data['html_url']) ? $data['html_url'] : '',
                    'body'         => isset($data['body']) ? $data['body'] : '',
                );
                if (!empty($respHeaders['etag'])) {
                    @file_put_contents($etagFile, $respHeaders['etag']);
                }
                $this->writeCache($cacheFile, array('ts' => time(), 'data' => $result));
                return $this->buildResult($result);
            }
        }

        return $cached ? $this->buildResult($cached['data']) : null;
    }

    private function buildResult($data)
    {
        if (!$data) return null;
        $current = MiraiCore_Plugin::VERSION;
        $latest  = $data['version'];
        return array(
            'has_update'   => version_compare($latest, $current, '>'),
            'current'      => $current,
            'latest'       => $latest,
            'download_url' => $data['download_url'],
            'html_url'     => $data['html_url'],
            'body'         => $data['body'],
        );
    }

    private function extractDownloadUrl($data)
    {
        if (!empty($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (isset($asset['browser_download_url'])
                    && substr($asset['browser_download_url'], -4) === '.zip') {
                    return $asset['browser_download_url'];
                }
            }
        }
        return isset($data['zipball_url']) ? $data['zipball_url'] : '';
    }

    public function doUpdate($downloadUrl, $newVersion)
    {
        $oldVersion = MiraiCore_Plugin::VERSION;

        $zipContent = $this->httpGetWithMirror($downloadUrl, 60);
        if ($zipContent === false || strlen($zipContent) < 100) {
            return array('ok' => false, 'msg' => '下载失败，服务器无法访问 GitHub，请手动更新');
        }

        if (!class_exists('ZipArchive') && !function_exists('zip_open')) {
            return array('ok' => false, 'msg' => '服务器未安装 PHP zip 扩展，无法自动解压');
        }

        if (!is_dir($this->tmpDir)) @mkdir($this->tmpDir, 0755, true);
        $zipFile = $this->tmpDir . '/update.zip';
        if (file_put_contents($zipFile, $zipContent) === false) {
            return array('ok' => false, 'msg' => '无法写入临时文件，请检查目录权限');
        }
        unset($zipContent);

        $extractDir = $this->tmpDir . '/extracted';
        if (is_dir($extractDir)) $this->removeDir($extractDir);
        @mkdir($extractDir, 0755, true);

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($zipFile) !== true) {
                $this->cleanup();
                return array('ok' => false, 'msg' => '无法打开 ZIP 文件');
            }
            $zip->extractTo($extractDir);
            $zip->close();
        } else {
            $zh = zip_open($zipFile);
            if (!is_resource($zh)) {
                $this->cleanup();
                return array('ok' => false, 'msg' => '无法打开 ZIP 文件');
            }
            while ($entry = zip_read($zh)) {
                $name = zip_entry_name($entry);
                $dest = $extractDir . '/' . $name;
                if (substr($name, -1) === '/') {
                    @mkdir($dest, 0755, true);
                } elseif (zip_entry_open($zh, $entry)) {
                    @mkdir(dirname($dest), 0755, true);
                    file_put_contents($dest, zip_entry_read($entry, zip_entry_filesize($entry)));
                    zip_entry_close($entry);
                }
            }
            zip_close($zh);
        }

        $sourceDir = $this->findPluginRoot($extractDir);
        if ($sourceDir === false) {
            $this->cleanup();
            return array('ok' => false, 'msg' => 'ZIP 中找不到 Plugin.php，请手动更新');
        }

        $backupDir = $this->tmpDir . '/backup_' . $oldVersion;
        if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);
        $this->copyDir($this->pluginDir, $backupDir, array('tmp_update'));

        $this->cleanPluginDir();
        $this->copyDir($sourceDir, $this->pluginDir, array('tmp_update'));

        @unlink($zipFile);
        $this->removeDir($extractDir);

        $this->writeCache($this->tmpDir . '/.update_cache.json', array(
            'ts' => time(),
            'data' => array(
                'version' => $newVersion,
                'download_url' => '',
                'html_url' => '',
                'body' => '',
            ),
        ));

        $this->invalidateOpcache($this->pluginDir);

        return array(
            'ok' => true,
            'msg' => '更新成功！已从 v' . $oldVersion . ' 更新至 v' . $newVersion . '，请刷新页面',
        );
    }

    private function httpGetWithHeaders($url, $headers = array(), $timeout = 10)
    {
        if (function_exists('curl_init')) {
            $urls = array_merge(array($url), array_map(function ($m) use ($url) {
                return $m . $url;
            }, self::$MIRRORS));

            foreach ($urls as $tryUrl) {
                $respHeaders = array();
                $ch = curl_init($tryUrl);
                curl_setopt_array($ch, array(
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$respHeaders) {
                        $len = strlen($header);
                        $parts = explode(':', $header, 2);
                        if (count($parts) === 2) {
                            $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                        }
                        return $len;
                    },
                ));
                $body = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($body !== false && ($httpCode === 200 || $httpCode === 304)) {
                    return array($body, $httpCode, $respHeaders);
                }
            }
        }

        // cURL 不可用或全部失败，降级到 file_get_contents
        $opts = array(
            'http' => array(
                'method' => 'GET',
                'timeout' => $timeout,
                'header' => implode("\r\n", $headers),
                'follow_location' => 1,
            ),
            'ssl' => array('verify_peer' => false, 'verify_peer_name' => false),
        );
        $body = @file_get_contents($url, false, stream_context_create($opts));
        $httpCode = 0;
        $respHeaders = array();
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $h) {
                $parts = explode(':', $h, 2);
                if (count($parts) === 2) {
                    $respHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
            }
            if (preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
                $httpCode = (int)$m[1];
            }
        }
        return array($body, $httpCode, $respHeaders);
    }

    private function httpGetWithMirror($url, $timeout = 60)
    {
        $result = $this->httpGetSimple($url, $timeout);
        if ($result !== false) return $result;

        foreach (self::$MIRRORS as $mirror) {
            $result = $this->httpGetSimple($mirror . $url, $timeout);
            if ($result !== false) return $result;
        }
        return false;
    }

    private function httpGetSimple($url, $timeout)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'MiraiCore-Updater/' . MiraiCore_Plugin::VERSION,
            ));
            $result = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ($result !== false && $code >= 200 && $code < 400) ? $result : false;
        }
        $opts = array(
            'http' => array(
                'method' => 'GET',
                'timeout' => $timeout,
                'header' => 'User-Agent: MiraiCore-Updater/' . MiraiCore_Plugin::VERSION,
                'follow_location' => 1,
            ),
            'ssl' => array('verify_peer' => false, 'verify_peer_name' => false),
        );
        return @file_get_contents($url, false, stream_context_create($opts));
    }

    private function findPluginRoot($dir)
    {
        if (file_exists($dir . '/Plugin.php')) return $dir;
        $items = @scandir($dir);
        if ($items) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $sub = $dir . '/' . $item;
                if (is_dir($sub) && file_exists($sub . '/Plugin.php')) return $sub;
            }
        }
        return false;
    }

    private function cleanPluginDir()
    {
        $items = @scandir($this->pluginDir);
        if (!$items) return;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === 'tmp_update') continue;
            $path = $this->pluginDir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }
    }

    private function copyDir($src, $dst, $skipDirs = array())
    {
        $count = 0;
        if (!is_dir($dst)) @mkdir($dst, 0755, true);
        $items = @scandir($src);
        if (!$items) return 0;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (in_array($item, $skipDirs)) continue;
            $srcPath = $src . '/' . $item;
            $dstPath = $dst . '/' . $item;
            if (is_dir($srcPath)) {
                $count += $this->copyDir($srcPath, $dstPath, $skipDirs);
            } else {
                if (copy($srcPath, $dstPath)) $count++;
            }
        }
        return $count;
    }

    private function removeDir($dir)
    {
        if (!is_dir($dir)) return;
        $items = @scandir($dir);
        if ($items) {
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $path = $dir . '/' . $item;
                is_dir($path) ? $this->removeDir($path) : @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function cleanup()
    {
        @unlink($this->tmpDir . '/update.zip');
        $extractDir = $this->tmpDir . '/extracted';
        if (is_dir($extractDir)) $this->removeDir($extractDir);
    }

    private function invalidateOpcache($dir)
    {
        if (!function_exists('opcache_invalidate') || !is_dir($dir)) return;
        $items = @scandir($dir);
        if (!$items) return;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === 'tmp_update') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->invalidateOpcache($path);
            } elseif (substr($path, -4) === '.php') {
                @opcache_invalidate($path, true);
            }
        }
    }

    private function readCache($file)
    {
        if (!file_exists($file)) return null;
        $raw = @file_get_contents($file);
        $obj = $raw ? @json_decode($raw, true) : null;
        return (is_array($obj) && isset($obj['ts'], $obj['data'])) ? $obj : null;
    }

    private function writeCache($file, $data)
    {
        $dir = dirname($file);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}