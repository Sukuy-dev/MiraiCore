<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class MiraiCore_InviteCode_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {
        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->pass('administrator', true)) {
            throw new Typecho_Widget_Exception(_t('禁止访问'), 403);
        }

        $security = Typecho_Widget::widget('Widget_Security');
        MiraiCore_InviteCode_Service::ensureSchema();

        $do = trim((string)$this->request->get('do'));
        $redirect = trim((string)$this->request->get('redirect'));
        $panelName = 'MiraiCore/InviteCode/manage-invite-codes.php';
        $defaultRedirect = Typecho_Widget::widget('Widget_Options')->adminUrl('extending.php?panel=' . urlencode($panelName), true);
        if ($do === '') {
            $do = trim((string)$this->request->get('action'));
        }
        if ($redirect === '') {
            $redirect = $defaultRedirect;
        }

        if ($do === 'export') {
            $this->export();
            return;
        }

        $security->protect();

        if (in_array($do, ['disable', 'enable', 'delete'], true)) {
            $this->batch($do);
        }

        if ($redirect !== '') {
            $this->response->redirect($redirect);
        }

        $this->response->goBack();
    }

    private function batch($action)
    {
        $ids = $this->request->getArray('id');
        $count = MiraiCore_InviteCode_Service::batchUpdate($ids, $action);

        $labels = [
            'disable' => '停用',
            'enable' => '启用',
            'delete' => '删除',
        ];
        $label = isset($labels[$action]) ? $labels[$action] : '处理';

        Typecho_Widget::widget('Widget_Notice')->set(
            $count > 0 ? _t('邀请码已%s：%d 个', $label, $count) : _t('没有邀请码被%s', $label),
            $count > 0 ? 'success' : 'notice'
        );
    }

    private static $FULL_HEADERS = ['邀请码', '状态', '积分奖励', '余额奖励', 'VIP等级', 'VIP天数', '备注', '创建时间'];

    private function export()
    {
        $status = trim((string)$this->request->get('status'));
        $keyword = trim((string)$this->request->get('keyword'));
        $format = trim((string)$this->request->get('format'));
        $type = trim((string)$this->request->get('type'));

        $rows = MiraiCore_InviteCode_Service::exportCodes([
            'status' => $status,
            'keyword' => $keyword,
        ]);

        $isTxt = ($type === 'txt');
        $ext = $isTxt ? 'txt' : 'csv';
        $filename = 'invite-codes-' . date('Ymd') . '.' . $ext;
        $isCodeOnly = ($format === 'code_only');

        if ($isTxt) {
            header('Content-Type: text/plain; charset=utf-8');
        } else {
            header('Content-Type: text/csv; charset=utf-8');
        }
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        if ($isTxt) {
            $this->writeTxt($rows, $isCodeOnly);
        } else {
            $this->writeCsv($rows, $isCodeOnly);
        }

        exit;
    }

    private function writeCsv(array $rows, $isCodeOnly)
    {
        $fp = fopen('php://output', 'w');
        fwrite($fp, "\xEF\xBB\xBF");

        if ($isCodeOnly) {
            fputcsv($fp, ['邀请码']);
            foreach ($rows as $row) {
                fputcsv($fp, [$row['code']]);
            }
        } else {
            fputcsv($fp, self::$FULL_HEADERS);
            foreach ($rows as $row) {
                fputcsv($fp, $this->extractFullRow($row));
            }
        }

        fclose($fp);
    }

    private function writeTxt(array $rows, $isCodeOnly)
    {
        if ($isCodeOnly) {
            foreach ($rows as $row) {
                echo $row['code'], "\n";
            }
        } else {
            echo implode("\t", self::$FULL_HEADERS), "\n";
            foreach ($rows as $row) {
                echo implode("\t", $this->extractFullRow($row)), "\n";
            }
        }
    }

    private function extractFullRow(array $row)
    {
        return [
            $row['code'],
            $row['status_text'],
            (int)$row['reward_points'],
            number_format((float)$row['reward_balance'], 2),
            (int)$row['reward_vip_level'],
            (int)$row['reward_vip_days'],
            $row['remark'] ?: '',
            $row['created_text'],
        ];
    }
}
