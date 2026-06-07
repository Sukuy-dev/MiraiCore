<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class MiraiCore_Withdrawals_Action extends Typecho_Widget implements Widget_Interface_Do
{
    private function getThemeFunctions()
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $themeDir = __TYPECHO_ROOT_DIR__ . '/usr/themes/' . $options->theme;
        $coreFile = $themeDir . '/common/functions/core.php';
        if (file_exists($coreFile)) {
            require_once $coreFile;
        }
        $functionsFile = $themeDir . '/common/functions/pay.php';
        if (file_exists($functionsFile)) {
            require_once $functionsFile;
        }
    }

    public function action()
    {
        $this->getThemeFunctions();
        $security = Typecho_Widget::widget('Widget_Security');
        $security->protect();
        
        $do = trim((string)$this->request->get('do'));
        
        if ($do === 'approve') {
            $this->approveWithdrawals();
        } elseif ($do === 'reject') {
            $this->rejectWithdrawals();
        } elseif ($do === 'delete') {
            $this->deleteWithdrawals();
        }
        
        $this->response->goBack();
    }

    private function approveWithdrawals()
    {
        $ids = $this->request->getArray('id');
        $ids = array_map('intval', $ids);
        $approveRows = 0;

        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }
            
            $withdrawalsTable = Mirai_payTable('withdrawals');
            $db = Typecho_Db::get();
            $withdrawal = $db->fetchRow($db->select('withdraw_type')->from($withdrawalsTable)->where('id = ?', $id)->limit(1));
            $withdrawType = isset($withdrawal['withdraw_type']) ? $withdrawal['withdraw_type'] : 'balance';
            
            if ($withdrawType === 'rebate' && function_exists('Mirai_referralProcessWithdraw')) {
                $result = Mirai_referralProcessWithdraw($id, true, '批量通过');
                $result = ['success' => $result !== false];
            } else {
                $result = Mirai_payAdminProcessBalanceWithdrawal($id, true, '批量通过');
            }
            
            if (!empty($result['success'])) {
                $approveRows++;
            }
        }

        Typecho_Widget::widget('Widget_Notice')->set(
            $approveRows > 0 ? _t('提现申请已经通过') : _t('没有提现申请被通过'),
            $approveRows > 0 ? 'success' : 'notice'
        );
    }

    private function rejectWithdrawals()
    {
        $ids = $this->request->getArray('id');
        $ids = array_map('intval', $ids);
        $rejectRows = 0;

        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }
            
            $withdrawalsTable = Mirai_payTable('withdrawals');
            $db = Typecho_Db::get();
            $withdrawal = $db->fetchRow($db->select('withdraw_type')->from($withdrawalsTable)->where('id = ?', $id)->limit(1));
            $withdrawType = isset($withdrawal['withdraw_type']) ? $withdrawal['withdraw_type'] : 'balance';
            
            if ($withdrawType === 'rebate' && function_exists('Mirai_referralProcessWithdraw')) {
                $result = Mirai_referralProcessWithdraw($id, false, '批量拒绝');
                $result = ['success' => $result !== false];
            } else {
                $result = Mirai_payAdminProcessBalanceWithdrawal($id, false, '批量拒绝');
            }
            
            if (!empty($result['success'])) {
                $rejectRows++;
            }
        }

        Typecho_Widget::widget('Widget_Notice')->set(
            $rejectRows > 0 ? _t('提现申请已经拒绝') : _t('没有提现申请被拒绝'),
            $rejectRows > 0 ? 'success' : 'notice'
        );
    }

    private function deleteWithdrawals()
    {
        $ids = $this->request->getArray('id');
        $ids = array_map('intval', $ids);
        $deleteRows = 0;
        $withdrawalsTable = Mirai_payTable('withdrawals');
        $db = Typecho_Db::get();

        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }
            
            $withdrawal = $db->fetchRow($db->select()->from($withdrawalsTable)->where('id = ?', $id)->limit(1));
            
            if ($withdrawal && (int)$withdrawal['status'] !== 0) {
                $db->query($db->delete($withdrawalsTable)->where('id = ?', $id));
                $deleteRows++;
            }
        }

        Typecho_Widget::widget('Widget_Notice')->set(
            $deleteRows > 0 ? _t('提现记录已经被删除') : _t('没有提现记录被删除，只能删除已处理的记录'),
            $deleteRows > 0 ? 'success' : 'notice'
        );
    }
}
