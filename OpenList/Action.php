<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class MiraiCore_OpenList_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->pass('contributor', true)) {
            $this->json(false, '权限不足');
        }

        $security = Typecho_Widget::widget('Widget_Security');
        try {
            $security->protect();
        } catch (Exception $e) {
            $this->json(false, '请求验证失败');
        }

        $do = trim((string)$this->request->get('do'));
        if ($do === '') {
            $do = trim((string)$this->request->get('action'));
        }

        try {
            switch ($do) {
                case 'list':
                    $this->listFiles();
                    return;
                case 'upload':
                    $this->upload();
                    return;
                case 'delete':
                    if (!$user->pass('administrator', true)) {
                        $this->json(false, '删除需要管理员权限');
                    }
                    $this->delete();
                    return;
                case 'test':
                    if (!$user->pass('administrator', true)) {
                        $this->json(false, '权限不足');
                    }
                    $service = new MiraiCore_OpenList_Service();
                    $service->test();
                    $this->json(true, '连接成功');
                    return;
                default:
                    $this->json(false, '未知操作');
            }
        } catch (Throwable $e) {
            $this->json(false, $e->getMessage());
        }
    }

    private function json($success, $message = '', array $data = [])
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        $payload = ['success' => (bool)$success, 'message' => (string)$message];
        if (!empty($data)) {
            $payload['data'] = $data;
        }
        $this->response->throwJson($payload);
    }

    private function listFiles()
    {
        $service = new MiraiCore_OpenList_Service();
        $data = $service->listFiles(
            (string)$this->request->get('path', ''),
            (string)$this->request->get('password', ''),
            intval($this->request->get('page', 1)),
            $this->request->get('per_page', null)
        );
        $this->json(true, '', $data);
    }

    private function upload()
    {
        $path = (string)$this->request->get('path', '');
        $files = $this->normalizeFiles(isset($_FILES['file']) ? $_FILES['file'] : (isset($_FILES['files']) ? $_FILES['files'] : null));
        if (empty($files)) {
            $this->json(false, '请选择上传文件');
        }

        $service = new MiraiCore_OpenList_Service();
        $uploaded = [];
        $errors = [];
        foreach ($files as $file) {
            if ((int)$file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = $file['name'];
                continue;
            }
            try {
                $uploaded[] = $service->upload($file, $path);
            } catch (Throwable $e) {
                $errors[] = $file['name'] . '：' . $e->getMessage();
            }
        }

        $this->json(empty($errors), empty($errors) ? '上传完成' : '部分文件上传失败', [
            'uploaded' => $uploaded,
            'errors' => $errors,
        ]);
    }

    private function delete()
    {
        $names = $this->request->getArray('names');
        $dir = (string)$this->request->get('dir', '');
        $service = new MiraiCore_OpenList_Service();
        $service->remove($dir, $names);
        $this->json(true, '删除成功');
    }

    private function normalizeFiles($input)
    {
        if (!is_array($input) || !isset($input['name'])) {
            return [];
        }
        if (!is_array($input['name'])) {
            return [$input];
        }

        $files = [];
        foreach ($input['name'] as $index => $name) {
            $files[] = [
                'name' => $name,
                'type' => isset($input['type'][$index]) ? $input['type'][$index] : '',
                'tmp_name' => isset($input['tmp_name'][$index]) ? $input['tmp_name'][$index] : '',
                'error' => isset($input['error'][$index]) ? $input['error'][$index] : UPLOAD_ERR_NO_FILE,
                'size' => isset($input['size'][$index]) ? $input['size'][$index] : 0,
            ];
        }
        return $files;
    }

    public static function renderAssets($archive = null)
    {
        if (!MiraiCore_OpenList_Service::isEnabled()) {
            return;
        }

        $script = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
        $adminPath = '/' . trim((string)__TYPECHO_ADMIN_DIR__, '/');
        $isAdminWrite = stripos($script, $adminPath . '/write-post.php') !== false
            || stripos($script, $adminPath . '/write-page.php') !== false
            || stripos($requestUri, $adminPath . '/write-post.php') !== false
            || stripos($requestUri, $adminPath . '/write-page.php') !== false;

        if (!$isAdminWrite) {
            return;
        }

        $pluginUrl = defined('__TYPECHO_PLUGIN_URL__') ? __TYPECHO_PLUGIN_URL__ . '/MiraiCore' : '/usr/plugins/MiraiCore';
        $options = Typecho_Widget::widget('Widget_Options');
        $actionBase = rtrim((string)$options->index, '/');
        if ($actionBase === '' || strpos($actionBase, 'index.php') === false) {
            $actionBase = rtrim((string)$options->siteUrl, '/') . '/index.php';
        }
        $security = Typecho_Widget::widget('Widget_Security');
        $actionUrl = $security->getTokenUrl($actionBase . '/action/mirai-openlist');
        $user = Typecho_Widget::widget('Widget_User');
        $payload = [
            'actionUrl' => $actionUrl,
            'canDelete' => $user->pass('administrator', true),
        ];

        echo '<!-- Mirai OpenList Start -->';
        echo '<link rel="stylesheet" href="' . htmlspecialchars($pluginUrl, ENT_QUOTES, 'UTF-8') . '/assets/css/openlist.css">';
        echo '<script>window.MIRAI_OPENLIST=' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';
        echo '<script src="' . htmlspecialchars($pluginUrl, ENT_QUOTES, 'UTF-8') . '/assets/js/openlist.js"></script>';
        echo '<!-- Mirai OpenList End -->';
    }
}
