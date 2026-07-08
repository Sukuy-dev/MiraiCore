<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class MiraiCore_Database_Service
{
    public static function repair()
    {
        $before = self::getSchemaSnapshot();
        MiraiCore_Plugin::loadThemeFile('migration.php', 'common/mysql');
        if (function_exists('Mirai_checkDatabase')) {
            Mirai_checkDatabase();
        }
        $after = self::getSchemaSnapshot();
        return self::summarizeSchemaChanges($before, $after);
    }

    public static function checkOnAdminLogin()
    {
        try {
            $user = Typecho_Widget::widget('Widget_User');

            if (!$user->pass('administrator', true)) {
                return;
            }

            $options = Typecho_Widget::widget('Widget_Options');
            $lastCheckTime = (int)($options->mirai_last_db_check_time ?? 0);
            $currentTime = time();
            $checkInterval = 24 * 60 * 60;

            if (!$lastCheckTime || ($currentTime - $lastCheckTime > $checkInterval)) {
                $lockKey = 'mirai_db_check_lock';
                $lockTime = (int)($options->{$lockKey} ?? 0);

                if ($lockTime && ($currentTime - $lockTime <= 300)) {
                    return;
                }

                \Utils\Helper::setOption($lockKey, $currentTime);

                try {
                    MiraiCore_Plugin::loadThemeFile('migration.php', 'common/mysql');

                    if (function_exists('Mirai_checkDatabase')) {
                        Mirai_checkDatabase();
                    }

                    \Utils\Helper::setOption('mirai_last_db_check_time', $currentTime);
                } catch (Exception $e) {
                    error_log('Mirai database check failed: ' . $e->getMessage());
                } finally {
                    \Utils\Helper::setOption($lockKey, 0);
                }
            }
        } catch (Exception $e) {
            error_log('Mirai checkDatabaseOnAdminLogin error: ' . $e->getMessage());
        }
    }

    public static function getSchemaSnapshot()
    {
        $snapshot = [];
        try {
            $db = Typecho_Db::get();
            $prefix = $db->getPrefix();
            $tables = $db->fetchAll($db->query("SHOW TABLES LIKE '" . $prefix . "%'"));
            foreach ($tables as $row) {
                $tableName = '';
                foreach ($row as $value) {
                    $tableName = (string)$value;
                    break;
                }
                if ($tableName === '') {
                    continue;
                }
                try {
                    $columns = $db->fetchAll($db->query('SHOW COLUMNS FROM `' . $tableName . '`'));
                } catch (Exception $e) {
                    continue;
                }
                $columnInfo = [];
                foreach ($columns as $column) {
                    if (!isset($column['Field'])) {
                        continue;
                    }
                    $columnInfo[(string)$column['Field']] = (string)$column['Type'];
                }
                ksort($columnInfo);
                $snapshot[$tableName] = $columnInfo;
            }
        } catch (Exception $e) {
        }
        ksort($snapshot);
        return $snapshot;
    }

    public static function summarizeSchemaChanges($before, $after)
    {
        $changes = [];
        foreach ($after as $tableName => $columns) {
            if (!isset($before[$tableName])) {
                $changes[] = '新建表：' . $tableName;
                continue;
            }
            foreach ($columns as $colName => $colType) {
                if (!isset($before[$tableName][$colName])) {
                    $changes[] = '新增字段：' . $tableName . '.' . $colName;
                } elseif ($before[$tableName][$colName] !== $colType) {
                    $changes[] = '修正字段类型：' . $tableName . '.' . $colName . '（' . $before[$tableName][$colName] . ' → ' . $colType . '）';
                }
            }
        }
        return $changes;
    }
}