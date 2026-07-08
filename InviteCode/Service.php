<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class MiraiCore_InviteCode_Service
{
    const THEME_MODE_KEY = 'inviteCodeMode';
    const STATUS_ACTIVE = 0;
    const STATUS_USED = 1;
    const STATUS_DISABLED = 2;

    public static function codesTable()
    {
        $db = \Typecho\Db::get();
        return $db->getPrefix() . 'mirai_invite_codes';
    }

    public static function ensureSchema()
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        if (class_exists('MiraiCore_Plugin')) {
            MiraiCore_Plugin::loadThemeFile('migration.php', 'common/mysql');
        }

        if (function_exists('Mirai_ensureDatabaseSchema')) {
            Mirai_ensureDatabaseSchema();
            $ensured = true;
        }
    }

    public static function getConfig()
    {
        $options = \Typecho\Widget::widget('Widget_Options');
        $config = ['mode' => 'off'];

        $themeMode = trim((string)($options->{self::THEME_MODE_KEY} ?? ''));
        if ($themeMode !== '') {
            $config['mode'] = $themeMode;
        }

        if (!in_array($config['mode'], ['off', 'optional', 'required'], true)) {
            $config['mode'] = 'off';
        }
        return $config;
    }

    public static function normalizeCode($code)
    {
        $code = strtoupper(trim((string)$code));
        $code = preg_replace('/\s+/', '', $code);
        return $code;
    }

    public static function isValidCodeFormat($code)
    {
        return (bool)preg_match('/^[A-Z0-9_-]{4,64}$/', (string)$code);
    }

    public static function getByCode($code)
    {
        $code = self::normalizeCode($code);
        if ($code === '') {
            return null;
        }
        $db = \Typecho\Db::get();
        return $db->fetchRow($db->select()->from(self::codesTable())->where('code = ?', $code)->limit(1));
    }

    public static function validateForRegister($code)
    {
        $config = self::getConfig();
        $code = self::normalizeCode($code);

        if ($config['mode'] === 'off') {
            return ['success' => true, 'code' => null];
        }
        if ($code === '') {
            if ($config['mode'] === 'required') {
                return ['success' => false, 'msg' => '请输入邀请码'];
            }
            return ['success' => true, 'code' => null];
        }
        if (!self::isValidCodeFormat($code)) {
            return ['success' => false, 'msg' => '邀请码格式不正确'];
        }

        $row = self::getByCode($code);
        if (!$row) {
            return ['success' => false, 'msg' => '邀请码无效'];
        }
        $usable = self::checkUsable($row);
        if (!$usable['success']) {
            return $usable;
        }
        return ['success' => true, 'code' => $row];
    }

    public static function checkUsable(array $row)
    {
        $status = (int)($row['status'] ?? self::STATUS_DISABLED);
        if ($status === self::STATUS_ACTIVE) {
            return ['success' => true];
        }
        if ($status === self::STATUS_DISABLED) {
            return ['success' => false, 'msg' => '邀请码已停用'];
        }
        if ($status === self::STATUS_USED) {
            return ['success' => false, 'msg' => '邀请码已使用'];
        }
        return ['success' => false, 'msg' => '邀请码状态异常'];
    }

    public static function generateCode($length = 8, $prefix = '')
    {
        $length = max(4, min(64, (int)$length));
        $prefix = preg_replace('/[^A-Z0-9_-]/', '', self::normalizeCode($prefix));
        $prefix = substr($prefix, 0, max(0, $length - 4));
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $randomLength = max(4, $length - strlen($prefix));

        for ($try = 0; $try < 30; $try++) {
            $random = '';
            for ($i = 0; $i < $randomLength; $i++) {
                $random .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = substr($prefix . $random, 0, 64);
            if (!self::getByCode($code)) {
                return $code;
            }
        }

        return substr($prefix . strtoupper(bin2hex(random_bytes(16))), 0, 64);
    }

    public static function createCode(array $data)
    {
        self::ensureSchema();
        $code = isset($data['code']) ? self::normalizeCode($data['code']) : '';
        if ($code === '') {
            $code = self::generateCode($data['length'] ?? 8, $data['prefix'] ?? '');
        }
        if (!self::isValidCodeFormat($code)) {
            return ['success' => false, 'msg' => '邀请码格式不正确：' . $code];
        }
        if (self::getByCode($code)) {
            return ['success' => false, 'msg' => '邀请码已存在：' . $code];
        }

        $now = time();
        $row = [
            'code' => $code,
            'status' => self::STATUS_ACTIVE,
            'reward_points' => (int)($data['reward_points'] ?? 0),
            'reward_balance' => max(0, round((float)($data['reward_balance'] ?? 0), 2)),
            'reward_vip_level' => max(0, (int)($data['reward_vip_level'] ?? 0)),
            'reward_vip_days' => max(0, (int)($data['reward_vip_days'] ?? 0)),
            'remark' => mb_substr(trim((string)($data['remark'] ?? '')), 0, 255, 'UTF-8'),
            'created' => $now,
            'updated' => $now,
        ];

        $db = \Typecho\Db::get();
        try {
            $db->query($db->insert(self::codesTable())->rows($row));
            return ['success' => true, 'code' => $code];
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => '邀请码创建失败：' . $e->getMessage()];
        }
    }

    public static function generateBatch($count, array $data)
    {
        $count = max(1, min(500, (int)$count));
        $created = [];
        $errors = [];
        for ($i = 0; $i < $count; $i++) {
            $result = self::createCode($data);
            if (!empty($result['success'])) {
                $created[] = $result['code'];
            } else {
                $errors[] = $result['msg'] ?? '生成失败';
            }
        }
        return ['created' => $created, 'errors' => $errors];
    }

    public static function consume($code, $uid)
    {
        self::ensureSchema();
        $uid = (int)$uid;
        $code = self::normalizeCode($code);
        if ($uid <= 0 || $code === '') {
            return ['success' => true, 'code' => null];
        }

        $row = self::getByCode($code);
        if (!$row) {
            return ['success' => false, 'msg' => '邀请码无效'];
        }
        $usable = self::checkUsable($row);
        if (!$usable['success']) {
            return $usable;
        }

        $db = \Typecho\Db::get();
        $codesTable = self::codesTable();
        $id = (int)$row['id'];

        try {
            $affected = $db->query(
                $db->update($codesTable)
                    ->rows(['status' => self::STATUS_USED, 'updated' => time()])
                    ->where('id = ?', $id)
                    ->where('status = ?', self::STATUS_ACTIVE)
            );
            if (!$affected) {
                return ['success' => false, 'msg' => '邀请码已被使用或不可用'];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => '邀请码使用失败：' . $e->getMessage()];
        }

        return ['success' => true, 'code' => $row, 'code_id' => $id];
    }

    private static function rollbackConsume($codeId)
    {
        $codeId = (int)$codeId;
        if ($codeId <= 0) {
            return;
        }

        $db = \Typecho\Db::get();
        $codesTable = self::codesTable();
        try {
            $db->query($db->update($codesTable)->rows([
                'status' => self::STATUS_ACTIVE,
                'updated' => time(),
            ])->where('id = ?', $codeId)->where('status = ?', self::STATUS_USED));
        } catch (\Exception $e) {
            error_log('MiraiCore InviteCode rollbackConsume failed: codeId=' . $codeId . ' error=' . $e->getMessage());
        }
    }

    public static function completeRegistration(array $codeRow, $uid)
    {
        $uid = (int)$uid;
        $code = self::normalizeCode((string)($codeRow['code'] ?? ''));
        if ($uid <= 0 || $code === '') {
            return ['success' => true, 'code' => null, 'code_id' => 0, 'rewards' => []];
        }

        $consume = self::consume($code, $uid);
        if (empty($consume['success']) || empty($consume['code'])) {
            return ['success' => false, 'msg' => $consume['msg'] ?? '邀请码使用失败'];
        }

        $reward = ['success' => true, 'details' => [], 'operations' => []];

        try {
            $reward = self::grantRewardsTransactional($consume['code'], $uid);
            if (empty($reward['success'])) {
                throw new \Exception($reward['msg'] ?? '邀请码奖励发放失败');
            }

            return [
                'success' => true,
                'code' => $consume['code'],
                'code_id' => (int)$consume['code_id'],
                'rewards' => $reward['details'],
            ];
        } catch (\Exception $e) {
            self::rollbackRewardOperations($reward['operations'] ?? []);
            self::rollbackConsume((int)($consume['code_id'] ?? 0));
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    private static function grantRewardsTransactional(array $codeRow, $uid)
    {
        $uid = (int)$uid;
        if ($uid <= 0) {
            return ['success' => true, 'details' => [], 'operations' => []];
        }

        $details = [];
        $operations = [];
        $code = (string)($codeRow['code'] ?? '');
        $codeId = (int)($codeRow['id'] ?? 0);
        $orderRef = 'INV' . $codeId . 'U' . $uid;

        try {
            $points = (int)($codeRow['reward_points'] ?? 0);
            if ($points !== 0 && function_exists('Mirai_pointsAdjust') && (!function_exists('Mirai_pointsEnabled') || Mirai_pointsEnabled())) {
                $result = Mirai_pointsAdjust($uid, $points, 'invite_code', 'invite_code', $code, '邀请码注册奖励');
                if (empty($result['success'])) {
                    return ['success' => false, 'msg' => $result['msg'] ?? '注册用户积分奖励发放失败', 'details' => $details, 'operations' => $operations];
                }
                $details['invitee_points'] = $points;
                $operations[] = ['type' => 'points', 'uid' => $uid, 'amount' => $points, 'code' => $code, 'remark' => '邀请码注册奖励回滚'];
            }

            $balance = max(0, round((float)($codeRow['reward_balance'] ?? 0), 2));
            if ($balance > 0 && function_exists('Mirai_payAdjustBalance')) {
                if (!Mirai_payAdjustBalance($uid, $balance, 'invite_code', '邀请码注册奖励', $orderRef)) {
                    return ['success' => false, 'msg' => '注册用户余额奖励发放失败', 'details' => $details, 'operations' => $operations];
                }
                $details['invitee_balance'] = $balance;
                $operations[] = ['type' => 'balance', 'uid' => $uid, 'amount' => $balance, 'order_no' => $orderRef . '_rollback', 'remark' => '邀请码注册余额奖励回滚'];
            }

            $vipLevel = (int)($codeRow['reward_vip_level'] ?? 0);
            $vipDays = max(0, (int)($codeRow['reward_vip_days'] ?? 0));
            if ($vipLevel > 0 && function_exists('Mirai_vipProcessOrderPaid')) {
                $snapshot = self::captureVipSnapshot($uid);
                $vipRollback = ['type' => 'vip', 'uid' => $uid, 'snapshot' => $snapshot];
                $operations[] = $vipRollback;
                if (!Mirai_vipProcessOrderPaid($uid, $vipLevel, $vipDays, 'new')) {
                    return ['success' => false, 'msg' => '注册用户会员奖励发放失败', 'details' => $details, 'operations' => $operations];
                }
                $details['invitee_vip'] = ['level' => $vipLevel, 'days' => $vipDays];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'msg' => $e->getMessage(), 'details' => $details, 'operations' => $operations];
        }

        return ['success' => true, 'details' => $details, 'operations' => $operations];
    }

    private static function rollbackRewardOperations(array $operations)
    {
        if (empty($operations)) {
            return;
        }

        for ($i = count($operations) - 1; $i >= 0; $i--) {
            $operation = $operations[$i];
            $uid = (int)($operation['uid'] ?? 0);
            if ($uid <= 0) {
                continue;
            }

            try {
                if (($operation['type'] ?? '') === 'points' && function_exists('Mirai_pointsAdjust')) {
                    Mirai_pointsAdjust($uid, 0 - (int)($operation['amount'] ?? 0), 'invite_reward_rollback', 'invite_code', (string)($operation['code'] ?? ''), (string)($operation['remark'] ?? '邀请码积分奖励回滚'));
                    continue;
                }

                if (($operation['type'] ?? '') === 'balance' && function_exists('Mirai_payAdjustBalance')) {
                    Mirai_payAdjustBalance($uid, 0 - round((float)($operation['amount'] ?? 0), 2), 'invite_reward_rollback', (string)($operation['remark'] ?? '邀请码余额奖励回滚'), (string)($operation['order_no'] ?? ''));
                    continue;
                }

                if (($operation['type'] ?? '') === 'vip') {
                    self::restoreVipSnapshot($uid, (array)($operation['snapshot'] ?? []));
                }
            } catch (\Exception $e) {
                error_log('MiraiCore InviteCode rollback step failed: type=' . ($operation['type'] ?? '') . ' uid=' . $uid . ' error=' . $e->getMessage());
            }
        }
    }

    private static function captureVipSnapshot($uid)
    {
        $uid = (int)$uid;
        if ($uid <= 0) {
            return ['vip_level' => 0, 'vip_exp_date' => ''];
        }

        $db = \Typecho\Db::get();
        $row = $db->fetchRow($db->select('vip_level', 'vip_exp_date')->from('table.users')->where('uid = ?', $uid)->limit(1));
        return [
            'vip_level' => (int)($row['vip_level'] ?? 0),
            'vip_exp_date' => (string)($row['vip_exp_date'] ?? ''),
        ];
    }

    private static function restoreVipSnapshot($uid, array $snapshot)
    {
        $uid = (int)$uid;
        if ($uid <= 0) {
            return;
        }

        $db = \Typecho\Db::get();
        $db->query($db->update('table.users')->rows([
            'vip_level' => (int)($snapshot['vip_level'] ?? 0),
            'vip_exp_date' => (string)($snapshot['vip_exp_date'] ?? ''),
        ])->where('uid = ?', $uid));

        if (function_exists('Mirai_vipClearCache')) {
            Mirai_vipClearCache($uid);
        }
    }

    public static function batchUpdate(array $ids, $action)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return 0;
        }
        $db = \Typecho\Db::get();
        $codesTable = self::codesTable();
        $idList = implode(',', $ids);

        if ($action === 'delete') {
            return $db->query($db->delete($codesTable)->where("id IN ({$idList})"));
        }
        if ($action === 'disable') {
            return $db->query(
                $db->update($codesTable)
                    ->rows(['status' => self::STATUS_DISABLED, 'updated' => time()])
                    ->where("id IN ({$idList})")
                    ->where('status = ?', self::STATUS_ACTIVE)
            );
        }
        if ($action === 'enable') {
            return $db->query(
                $db->update($codesTable)
                    ->rows(['status' => self::STATUS_ACTIVE, 'updated' => time()])
                    ->where("id IN ({$idList})")
                    ->where('status = ?', self::STATUS_DISABLED)
            );
        }
        return 0;
    }

    public static function stats()
    {
        self::ensureSchema();
        $db = \Typecho\Db::get();
        $codesTable = self::codesTable();
        $row = $db->fetchRow($db->query("SELECT COUNT(*) AS total,"
            . " SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS active,"
            . " SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS used,"
            . " SUM(CASE WHEN status = 2 THEN 1 ELSE 0 END) AS disabled FROM `{$codesTable}`"));
        return [
            'total' => (int)($row['total'] ?? 0),
            'active' => (int)($row['active'] ?? 0),
            'used' => (int)($row['used'] ?? 0),
            'disabled' => (int)($row['disabled'] ?? 0),
        ];
    }

    public static function listCodes(array $args = [])
    {
        self::ensureSchema();
        $db = \Typecho\Db::get();
        $codesTable = self::codesTable();
        $page = max(1, (int)($args['page'] ?? 1));
        $pageSize = max(1, min(100, (int)($args['pageSize'] ?? 20)));
        $keyword = trim((string)($args['keyword'] ?? ''));
        $status = trim((string)($args['status'] ?? ''));

        $countQuery = $db->select(['COUNT(*)' => 'cnt'])->from($codesTable);
        $listQuery = $db->select()->from($codesTable);

        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $countQuery->where('(code LIKE ? OR remark LIKE ?)', $like, $like);
            $listQuery->where('(code LIKE ? OR remark LIKE ?)', $like, $like);
        }
        $statusMap = [
            'active' => self::STATUS_ACTIVE,
            'used' => self::STATUS_USED,
            'disabled' => self::STATUS_DISABLED,
        ];
        if (isset($statusMap[$status])) {
            $countQuery->where('status = ?', $statusMap[$status]);
            $listQuery->where('status = ?', $statusMap[$status]);
        }

        $count = $db->fetchRow($countQuery);
        $total = (int)($count['cnt'] ?? 0);
        $totalPages = max(1, (int)ceil($total / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;

        $list = $db->fetchAll(
            $listQuery->order('id', \Typecho\Db::SORT_DESC)->limit($pageSize)->offset($offset)
        );

        return ['list' => $list, 'total' => $total, 'page' => $page, 'pageSize' => $pageSize, 'totalPages' => $totalPages];
    }

    public static function statusLabel(array $row)
    {
        if ((int)$row['status'] === self::STATUS_DISABLED) {
            return ['text' => '已停用', 'class' => 'withdrawal-status-rejected'];
        }
        if ((int)$row['status'] === self::STATUS_USED) {
            return ['text' => '已使用', 'class' => 'withdrawal-status-cancelled'];
        }
        return ['text' => '可用', 'class' => 'withdrawal-status-approved'];
    }

    public static function rewardText(array $row)
    {
        $parts = [];
        if ((int)$row['reward_points'] !== 0) {
            $parts[] = '注册用户积分 ' . ((int)$row['reward_points'] > 0 ? '+' : '') . (int)$row['reward_points'];
        }
        if ((float)$row['reward_balance'] > 0) {
            $parts[] = '注册用户余额 +' . number_format((float)$row['reward_balance'], 2);
        }
        if ((int)$row['reward_vip_level'] > 0) {
            $days = (int)$row['reward_vip_days'] > 0 ? ((int)$row['reward_vip_days'] . '天') : '永久';
            $parts[] = '注册用户VIP ' . (int)$row['reward_vip_level'] . '级/' . $days;
        }
        return empty($parts) ? '-' : implode('<br>', array_map('htmlspecialchars', $parts));
    }

    public static function exportCodes(array $args = [])
    {
        self::ensureSchema();
        $db = \Typecho\Db::get();
        $codesTable = self::codesTable();

        $keyword = trim((string)($args['keyword'] ?? ''));
        $status = trim((string)($args['status'] ?? ''));

        $query = $db->select()->from($codesTable)->order('id', \Typecho\Db::SORT_DESC);

        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->where('(code LIKE ? OR remark LIKE ?)', $like, $like);
        }

        $statusMap = [
            'active' => self::STATUS_ACTIVE,
            'used' => self::STATUS_USED,
            'disabled' => self::STATUS_DISABLED,
        ];
        if (isset($statusMap[$status])) {
            $query->where('status = ?', $statusMap[$status]);
        }

        $rows = $db->fetchAll($query);
        $statusTexts = [self::STATUS_ACTIVE => '可用', self::STATUS_USED => '已使用', self::STATUS_DISABLED => '已停用'];

        foreach ($rows as &$row) {
            $row['status_text'] = $statusTexts[(int)$row['status']] ?? '未知';
            $row['created_text'] = $row['created'] > 0 ? date('Y-m-d H:i:s', (int)$row['created']) : '-';
        }

        return $rows;
    }
}