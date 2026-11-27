<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class MiraiCore_Ban_Service
{
    public static function ensureSchema()
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $db = Typecho_Db::get();
        $adapter = $db->getAdapter();
        $driver = $adapter->getDriver();
        $table = $db->getPrefix() . 'users';
        $quotedTable = $adapter->quoteColumn($table);

        if ($driver === 'mysql') {
            $columns = $db->fetchAll($db->query('SHOW COLUMNS FROM ' . $quotedTable));
            $columnNames = array_column($columns, 'Field');
        } elseif ($driver === 'sqlite') {
            $columns = $db->fetchAll($db->query('PRAGMA table_info(' . $quotedTable . ')'));
            $columnNames = array_column($columns, 'name');
        } elseif ($driver === 'pgsql') {
            $columns = $db->fetchAll($db->select('column_name')
                ->from('information_schema.columns')
                ->where('table_name = ?', $table)
                ->where('table_schema = current_schema()'));
            $columnNames = array_column($columns, 'column_name');
        } else {
            throw new RuntimeException('Unsupported database driver for MiraiCore user bans: ' . $driver);
        }

        $definitions = $driver === 'mysql'
            ? [
                'banned' => 'TINYINT(1) NOT NULL DEFAULT 0',
                'banned_reason' => "VARCHAR(255) NOT NULL DEFAULT ''",
                'banned_time' => 'INT(10) UNSIGNED NOT NULL DEFAULT 0',
            ]
            : [
                'banned' => 'INTEGER NOT NULL DEFAULT 0',
                'banned_reason' => "VARCHAR(255) NOT NULL DEFAULT ''",
                'banned_time' => 'INTEGER NOT NULL DEFAULT 0',
            ];

        foreach ($definitions as $name => $definition) {
            if (!in_array($name, $columnNames, true)) {
                $db->query(
                    'ALTER TABLE ' . $quotedTable . ' ADD COLUMN '
                    . $adapter->quoteColumn($name) . ' ' . $definition,
                    Typecho_Db::WRITE,
                    ''
                );
            }
        }

        $ensured = true;
    }

    public static function guard()
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->hasLogin() || (int)$user->banned !== 1) {
            return;
        }

        $message = self::buildMessage($user->banned_reason);
        $user->logout();
        http_response_code(403);

        if (isset($_GET['mirai_api']) || isset($_POST['mirai_api'])) {
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            echo json_encode(['code' => -1, 'msg' => $message, 'success' => false, 'need_login' => true]);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            echo self::renderBlockedPage($message);
        }
        exit;
    }

    public static function onLoginSucceed($user, $name, $password, $temporarily, $expire)
    {
        if ((int)$user->banned === 1) {
            self::rejectLogin($user, $user->banned_reason);
        }
    }

    public static function onSimpleLoginSucceed($user, $row)
    {
        if (isset($row['banned']) && (int)$row['banned'] === 1) {
            self::rejectLogin($user, $row['banned_reason'] ?? '');
        }
    }

    public static function ban(array $uids, $reason, $operatorUid)
    {
        self::ensureSchema();
        $targets = self::filterTargets($uids, $operatorUid, 0);
        if (empty($targets)) {
            return 0;
        }

        $db = Typecho_Db::get();
        return $db->query($db->update('table.users')
            ->rows([
                'banned' => 1,
                'banned_reason' => $reason,
                'banned_time' => time(),
                'authCode' => '',
            ])
            ->where('uid IN ?', $targets)
            ->where('banned = ?', 0));
    }

    public static function unban(array $uids, $operatorUid)
    {
        self::ensureSchema();
        $targets = self::filterTargets($uids, $operatorUid, 1);
        if (empty($targets)) {
            return 0;
        }

        $db = Typecho_Db::get();
        return $db->query($db->update('table.users')
            ->rows(['banned' => 0, 'banned_reason' => '', 'banned_time' => 0])
            ->where('uid IN ?', $targets)
            ->where('banned = ?', 1));
    }

    public static function renderManageUsersActions()
    {
        $paths = [
            (string)($_SERVER['SCRIPT_NAME'] ?? ''),
            (string)($_SERVER['PHP_SELF'] ?? ''),
            (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? ''),
        ];
        if (PHP_SAPI === 'cli' || !in_array('manage-users.php', array_map('basename', $paths), true)) {
            return;
        }

        $user = Typecho_Widget::widget('Widget_User');
        if (!$user->pass('administrator', true)) {
            return;
        }

        $security = Typecho_Widget::widget('Widget_Security');
        $config = [
            'banUrl' => $security->getIndex('/action/mirai-ban?do=ban'),
            'unbanUrl' => $security->getIndex('/action/mirai-ban?do=unban'),
        ];
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if ($json === false) {
            throw new RuntimeException('Unable to encode MiraiCore user-ban actions: ' . json_last_error_msg());
        }

        $script = <<<EOT
(function (cfg) {
    function init() {
        document.querySelectorAll('.typecho-list-operate .dropdown-menu').forEach(function (menu) {
            if (menu.querySelector('.mirai-ban-batch')) {
                return;
            }
            [
                ['ban', '封禁', '你确认要封禁选中的用户吗？', cfg.banUrl],
                ['unban', '解封', '你确认要解封选中的用户吗？', cfg.unbanUrl]
            ].forEach(function (item) {
                var li = document.createElement('li');
                li.className = 'mirai-ban-batch mirai-ban-batch-' + item[0];
                var link = document.createElement('a');
                link.setAttribute('lang', item[2]);
                link.href = item[3];
                link.textContent = item[1];
                if (item[0] === 'ban') {
                    link.addEventListener('click', function (event) {
                        var reason = window.prompt('封禁原因（可选，将在该用户登录时提示）：', '');
                        if (reason === null) {
                            event.preventDefault();
                            event.stopImmediatePropagation();
                            return;
                        }

                        var form = document.forms['manage_users'];
                        if (!form) {
                            event.preventDefault();
                            event.stopImmediatePropagation();
                            return;
                        }

                        var reasonInput = form.querySelector('input[name="reason"]');
                        if (!reasonInput) {
                            reasonInput = document.createElement('input');
                            reasonInput.type = 'hidden';
                            reasonInput.name = 'reason';
                            form.appendChild(reasonInput);
                        }
                        reasonInput.value = reason;
                    }, true);
                }
                li.appendChild(link);
                menu.appendChild(li);
            });
        });
    }

    init();
})({$json});
EOT;
        echo '<script>' . $script . '</script>';
    }

    private static function filterTargets(array $uids, $operatorUid, $bannedStatus)
    {
        $uids = array_values(array_unique(array_filter(array_map('intval', $uids), function ($uid) {
            return $uid > 0;
        })));
        if (empty($uids)) {
            return [];
        }

        $db = Typecho_Db::get();
        $founder = $db->fetchObject($db->select(['MIN(uid)' => 'num'])->from('table.users'));
        $founderUid = $founder ? (int)$founder->num : 0;
        $targets = [];

        foreach ($db->fetchAll($db->select('uid', 'group', 'banned')
            ->from('table.users')->where('uid IN ?', $uids)) as $row) {
            $uid = (int)$row['uid'];
            if ($uid === $founderUid
                || $uid === (int)$operatorUid
                || $row['group'] === 'administrator'
                || (int)$row['banned'] !== (int)$bannedStatus) {
                continue;
            }
            $targets[] = $uid;
        }

        return $targets;
    }

    private static function rejectLogin($user, $reason)
    {
        $message = self::buildMessage($reason);
        $user->logout();
        throw new Typecho_Widget_Exception($message, 403);
    }

    private static function buildMessage($reason)
    {
        $reason = trim((string)$reason);
        return $reason !== '' ? '账号已被封禁，封禁原因：' . $reason : '账号已被封禁';
    }

    private static function renderBlockedPage($message)
    {
        $homeUrl = rtrim((string)Typecho_Widget::widget('Widget_Options')->siteUrl, '/') . '/';

        return '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>账号已被封禁</title>'
            . '<style>body{margin:0;font-family:system-ui,-apple-system,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;'
            . 'background:#f5f6f7;color:#333}.mirai-ban-page{max-width:420px;margin:12vh auto;padding:32px;background:#fff;'
            . 'border-radius:12px;text-align:center;box-shadow:0 4px 16px rgba(0,0,0,.08)}'
            . '.mirai-ban-page h1{font-size:18px;margin:0 0 12px}.mirai-ban-page p{color:#666;font-size:14px;line-height:1.7;margin:0 0 8px}'
            . '.mirai-ban-page a{display:inline-block;margin-top:12px;padding:8px 20px;border-radius:6px;background:#467bcb;'
            . 'color:#fff;text-decoration:none;font-size:14px}</style></head><body>'
            . '<div class="mirai-ban-page"><h1>账号已被封禁</h1>'
            . '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>如有疑问，请联系站点管理员。</p>'
            . '<a href="' . htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') . '">返回首页</a></div></body></html>';
    }
}
