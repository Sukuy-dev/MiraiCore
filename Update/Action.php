<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class MiraiCore_Update_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        $this->widget('Widget_User')->pass('administrator');

        $do = $this->request->get('do', '');
        switch ($do) {
            case 'check-update':
                $this->checkUpdate();
                break;
            case 'do-update':
                $this->doUpdate();
                break;
            default:
                break;
        }
    }

    private function checkUpdate()
    {
        require_once __DIR__ . '/Updater.php';
        $force = $this->request->get('force', '0') === '1';
        $updater = new MiraiCore_Updater();
        $result = $updater->checkLatestRelease($force);

        if ($result === null) {
            $this->jsonError('无法连接 GitHub，请检查服务器网络');
        }

        $this->jsonSuccess($result, $result['has_update']
            ? '发现新版本 v' . $result['latest']
            : '已是最新版本 v' . $result['current']);
    }

    private function doUpdate()
    {
        require_once __DIR__ . '/Updater.php';
        $downloadUrl = trim($this->request->get('download_url', ''));
        $newVersion  = trim($this->request->get('new_version', ''));

        if ($downloadUrl === '' || $newVersion === '') {
            $this->jsonError('参数不完整');
        }

        $urlHost = strtolower(parse_url($downloadUrl, PHP_URL_HOST) ?: '');
        $allowed = array('github.com', 'codeload.github.com', 'objects.githubusercontent.com');
        $hostOk = false;
        foreach ($allowed as $a) {
            if ($urlHost === $a || substr($urlHost, -(strlen($a) + 1)) === '.' . $a) {
                $hostOk = true;
                break;
            }
        }
        if (!$hostOk) {
            $this->jsonError('下载地址不合法');
        }

        if (!version_compare($newVersion, MiraiCore_Plugin::VERSION, '>')) {
            $this->jsonError('版本号无效');
        }

        $updater = new MiraiCore_Updater();
        $result = $updater->doUpdate($downloadUrl, $newVersion);

        if ($result['ok']) {
            $this->jsonSuccess(array('new_version' => $newVersion), $result['msg']);
        } else {
            $this->jsonError($result['msg']);
        }
    }

    private function jsonSuccess($data = null, $msg = '')
    {
        $this->response->setContentType('application/json');
        echo json_encode(array('code' => 0, 'message' => $msg, 'data' => $data), JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function jsonError($msg, $code = 1)
    {
        $this->response->setContentType('application/json');
        echo json_encode(array('code' => $code, 'message' => $msg, 'data' => null), JSON_UNESCAPED_UNICODE);
        exit;
    }
}