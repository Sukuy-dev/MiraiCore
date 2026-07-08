<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class MiraiCore_OpenList_Service
{
    private static $defaults = [
        'enabled' => '0',
        'host' => '',
        'username' => '',
        'password' => '',
        'base_path' => '',
        'per_page' => '0',
        'timeout' => '30',
    ];

    private $config;
    private $token = null;

    public function __construct($config = null)
    {
        $this->config = $config ?: self::getConfig();
    }

    public static function getConfig()
    {
        $raw = self::getOption('mirai_core_openlist_config', '');
        $config = [];
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $config = $decoded;
            }
        }
        return self::normalizeConfig($config);
    }

    public static function saveConfig($config)
    {
        $config = self::normalizeConfig($config);
        self::setOption('mirai_core_openlist_config', json_encode($config, JSON_UNESCAPED_UNICODE));
        self::invalidateTokenCache();
        return $config;
    }

    public static function normalizeConfig($config)
    {
        $config = array_merge(self::$defaults, (array)$config);
        $config['enabled'] = !empty($config['enabled']) ? '1' : '0';
        $config['host'] = self::normalizeHost($config['host']);
        $config['username'] = trim((string)$config['username']);
        $config['password'] = (string)$config['password'];
        $config['base_path'] = self::normalizePath($config['base_path']);
        $config['per_page'] = (string)max(0, min(500, intval($config['per_page'])));
        $config['timeout'] = (string)max(5, min(120, intval($config['timeout'])));
        return $config;
    }

    public static function isEnabled()
    {
        $config = self::getConfig();
        return $config['enabled'] === '1' && trim((string)$config['host']) !== '';
    }

    public static function normalizeHost($host)
    {
        $host = trim((string)$host);
        if ($host === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $host)) {
            $host = 'https://' . $host;
        }
        return rtrim($host, '/');
    }

    public static function normalizePath($path)
    {
        $path = str_replace('\\', '/', (string)$path);
        $path = preg_replace('/[\x00-\x1f]/', '', $path);
        $parts = [];
        foreach (explode('/', $path) as $part) {
            $part = trim($part);
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }
        return implode('/', $parts);
    }

    public static function joinPath()
    {
        $parts = func_get_args();
        $clean = [];
        foreach ($parts as $part) {
            $part = self::normalizePath($part);
            if ($part !== '') {
                $clean[] = $part;
            }
        }
        return implode('/', $clean);
    }

    public function test()
    {
        $this->assertConfigured();
        $result = $this->postJson('/api/auth/login', [
            'username' => (string)$this->config['username'],
            'password' => (string)$this->config['password'],
        ], false);
        return !empty($result['data']['token']);
    }

    public function listFiles($path = '', $password = '', $page = 1, $perPage = null)
    {
        $this->assertConfigured();
        $relativePath = self::normalizePath($path);
        $remotePath = self::remotePath($this->config['base_path'], $relativePath);
        $perPage = $perPage === null ? intval($this->config['per_page']) : intval($perPage);

        $result = $this->postJson('/api/fs/list', [
            'path' => $remotePath,
            'password' => (string)$password,
            'page' => max(1, intval($page)),
            'per_page' => max(0, $perPage),
            'refresh' => false,
        ], true);

        return $this->normalizeListResult($result, $relativePath);
    }

    public function remove($dir, array $names)
    {
        $this->assertConfigured();
        $dir = self::normalizePath($dir);
        $remoteDir = self::remotePath($this->config['base_path'], $dir);
        $cleanNames = [];
        foreach ($names as $name) {
            $name = basename(str_replace('\\', '/', (string)$name));
            if ($name !== '') {
                $cleanNames[] = $name;
            }
        }
        if (empty($cleanNames)) {
            throw new RuntimeException('请选择要删除的文件');
        }
        return $this->postJson('/api/fs/remove', [
            'dir' => $remoteDir,
            'names' => $cleanNames,
        ], true);
    }

    public function upload(array $file, $path = '')
    {
        $this->assertConfigured();
        if (trim((string)$this->config['base_path']) === '') {
            throw new RuntimeException('请先在 OpenList 配置中填写默认挂载路径，例如：我的图片');
        }

        if (!isset($file['tmp_name'], $file['name'], $file['size']) || !is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('上传文件无效');
        }

        $name = basename(str_replace('\\', '/', (string)$file['name']));
        if ($name === '') {
            throw new RuntimeException('文件名无效');
        }

        $relativePath = self::normalizePath($path);
        $remotePath = self::remotePath($this->config['base_path'], $relativePath, $name);
        $handle = fopen($file['tmp_name'], 'rb');
        if (!$handle) {
            throw new RuntimeException('无法读取上传文件');
        }

        $url = $this->endpoint('/api/fs/put');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_UPLOAD, true);
        curl_setopt($ch, CURLOPT_INFILE, $handle);
        curl_setopt($ch, CURLOPT_INFILESIZE, (int)$file['size']);
        curl_setopt($ch, CURLOPT_TIMEOUT, intval($this->config['timeout']));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $this->getToken(),
            'Content-Length: ' . (int)$file['size'],
            'File-Path: ' . rawurlencode($remotePath),
            'Content-Type: application/octet-stream',
        ]);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        fclose($handle);

        if ($errno) {
            throw new RuntimeException('OpenList 请求失败：' . $error);
        }

        $result = json_decode((string)$body, true);
        if (!is_array($result)) {
            throw new RuntimeException('OpenList 返回了无效响应（HTTP ' . $httpCode . '）');
        }
        if ((int)($result['code'] ?? 0) !== 200) {
            throw new RuntimeException((string)($result['message'] ?? '上传失败'));
        }

        return [
            'name' => $name,
            'url' => $this->downloadUrl($relativePath, $name, ''),
            'result' => $result,
        ];
    }

    private function getToken()
    {
        if ($this->token !== null) {
            return $this->token;
        }
        $cache = self::getTokenCache();
        $key = md5((string)$this->config['host'] . '|' . (string)$this->config['username']);
        if ($cache && isset($cache['key']) && $cache['key'] === $key && !empty($cache['token']) && !empty($cache['expire']) && $cache['expire'] > time()) {
            $this->token = $cache['token'];
            return $this->token;
        }
        $result = $this->postJson('/api/auth/login', [
            'username' => (string)$this->config['username'],
            'password' => (string)$this->config['password'],
        ], false);
        $token = isset($result['data']['token']) ? trim((string)$result['data']['token']) : '';
        if ($token === '') {
            throw new RuntimeException('OpenList 登录失败，未返回 token');
        }
        $this->token = $token;
        self::setTokenCache(['key' => $key, 'token' => $token, 'expire' => time() + 7200]);
        return $this->token;
    }

    public static function getTokenCache()
    {
        $raw = self::getOption('mirai_core_openlist_token', '');
        if ($raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    public static function setTokenCache(array $cache)
    {
        self::setOption('mirai_core_openlist_token', json_encode($cache, JSON_UNESCAPED_UNICODE));
    }

    public static function invalidateTokenCache()
    {
        self::setOption('mirai_core_openlist_token', '');
    }

    private function postJson($path, array $payload, $auth)
    {
        $headers = ['Content-Type: application/json'];
        if ($auth) {
            $headers[] = 'Authorization: ' . $this->getToken();
        }
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $ch = curl_init($this->endpoint($path));
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => intval($this->config['timeout']),
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($errno) {
            throw new RuntimeException('OpenList 请求失败：' . $error);
        }

        $result = json_decode((string)$response, true);
        if (!is_array($result)) {
            throw new RuntimeException('OpenList 返回了无效响应（HTTP ' . $httpCode . '）');
        }
        if ((int)($result['code'] ?? 0) !== 200) {
            throw new RuntimeException((string)($result['message'] ?? 'OpenList 请求失败'));
        }
        return $result;
    }

    private function normalizeListResult(array $result, $relativePath)
    {
        $items = isset($result['data']['content']) && is_array($result['data']['content']) ? $result['data']['content'] : [];
        $files = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = isset($item['name']) ? (string)$item['name'] : '';
            if ($name === '') {
                continue;
            }
            $isDir = !empty($item['is_dir']) || intval($item['type'] ?? -1) === 1;
            $type = intval($item['type'] ?? 0);
            $url = $isDir ? '' : $this->downloadUrl($relativePath, $name, isset($item['sign']) ? (string)$item['sign'] : '');
            $thumb = isset($item['thumb']) ? trim((string)$item['thumb']) : '';
            $files[] = [
                'name' => $name,
                'is_dir' => $isDir,
                'type' => $type,
                'size' => isset($item['size']) ? intval($item['size']) : 0,
                'size_text' => self::formatBytes(isset($item['size']) ? intval($item['size']) : 0),
                'modified' => isset($item['modified']) ? (string)$item['modified'] : '',
                'path' => $isDir ? self::joinPath($relativePath, $name) : '',
                'url' => $url,
                'thumb' => $thumb !== '' ? $thumb : ($this->isPreviewable($type) ? $url : ''),
                'kind' => $this->kindFromType($type),
            ];
        }

        usort($files, function ($a, $b) {
            if ($a['is_dir'] !== $b['is_dir']) {
                return $a['is_dir'] ? -1 : 1;
            }
            return strnatcasecmp($a['name'], $b['name']);
        });

        return [
            'path' => self::normalizePath($relativePath),
            'count' => count($files),
            'total' => isset($result['data']['total']) ? intval($result['data']['total']) : count($files),
            'files' => $files,
        ];
    }

    private function kindFromType($type)
    {
        switch (intval($type)) {
            case 1:
                return 'dir';
            case 2:
                return 'video';
            case 3:
                return 'audio';
            case 5:
                return 'image';
            default:
                return 'file';
        }
    }

    private function isPreviewable($type)
    {
        return in_array(intval($type), [2, 5], true);
    }

    private function downloadUrl($relativePath, $name, $sign)
    {
        $path = self::joinPath($this->config['base_path'], $relativePath, $name);
        $segments = array_map('rawurlencode', explode('/', $path));
        $url = rtrim((string)$this->config['host'], '/') . '/d/' . implode('/', $segments);
        if ($sign !== '') {
            $url .= '?sign=' . rawurlencode($sign);
        }
        return $url;
    }

    private function endpoint($path)
    {
        return rtrim((string)$this->config['host'], '/') . '/' . ltrim((string)$path, '/');
    }

    private static function remotePath()
    {
        $path = call_user_func_array([__CLASS__, 'joinPath'], func_get_args());
        return '/' . trim($path, '/');
    }

    private function assertConfigured()
    {
        if ((string)$this->config['host'] === '') {
            throw new RuntimeException('请先配置 OpenList 地址');
        }
        if ((string)$this->config['username'] === '' || (string)$this->config['password'] === '') {
            throw new RuntimeException('请先配置 OpenList 账号和密码');
        }
    }

    public static function formatBytes($size)
    {
        $size = max(0, (int)$size);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }
        return round($size, 2) . ' ' . $units[$index];
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
}
