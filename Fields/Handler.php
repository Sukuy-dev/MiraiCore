<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class MiraiCore_Fields_Handler
{
    const MIRAI_FIELDS = ['cover', 'views', 'excerpt', 'keywords', 'description'];

    public static function intercept($fields, $widget)
    {
        $filteredFields = [];
        foreach ($fields as $name => $value) {
            if (!in_array($name, self::MIRAI_FIELDS)) {
                $filteredFields[$name] = $value;
            }
        }

        return $filteredFields;
    }

    public static function handleSave($contents, $widget = NULL)
    {
        if ($widget === NULL) {
            $widget = $contents;
        }

        $cid = $widget->cid ?? 0;

        if ($cid <= 0) {
            return $contents;
        }

        $fields = $widget->request->getArray('fields') ?? [];

        if (!empty($fields)) {
            MiraiCore_Plugin::loadThemeFile('functions.php', 'common');
            if (function_exists('Mirai_vipGetEnabledLevels')) {
                $enabledLevels = Mirai_vipGetEnabledLevels();
                foreach ($fields as $fname => $fval) {
                    if (preg_match('/^pay_vip_(\d+)_price$/', $fname, $m)) {
                        if (!in_array((int)$m[1], $enabledLevels, true)) {
                            unset($fields[$fname]);
                        }
                    }
                }
            }

            self::saveContentsExtensions($cid, $fields);
            self::saveEdkData($cid, $fields);
            self::cleanupTypechoFields($cid);
        }
        MiraiCore_Sitemap_Service::markDirty();
        MiraiCore_Sitemap_Service::autoBuildIfNeeded(false);

        MiraiCore_Plugin::triggerSeoPush($contents, $widget);
        MiraiCore_Plugin::triggerOnPublish($contents, $widget);

        return $contents;
    }

    public static function saveContentsExtensions($cid, $fields)
    {
        if (empty($cid) || empty($fields) || !is_array($fields)) {
            return;
        }

        $db = Typecho_Db::get();
        $updateData = [];

        if (isset($fields['cover']) && is_string($fields['cover'])) {
            $updateData['cover'] = self::sanitize($fields['cover']);
        }

        if (isset($fields['views']) && is_numeric($fields['views'])) {
            $updateData['views'] = max(0, intval($fields['views']));
        }

        if (!empty($updateData)) {
            try {
                $db->query($db->update('table.contents')->rows($updateData)->where('cid = ?', $cid));
            } catch (Exception $e) {
            }
        }
    }

    public static function saveEdkData($cid, $fields)
    {
        if (empty($cid) || empty($fields) || !is_array($fields)) {
            return;
        }

        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $edkTable = $prefix . 'mirai_contents_edk';

        $edkData = [
            'excerpt' => self::sanitize($fields['excerpt'] ?? '', true),
            'keywords' => self::sanitize($fields['keywords'] ?? ''),
            'description' => self::sanitize($fields['description'] ?? '', true)
        ];

        if (empty(array_filter($edkData))) {
            return;
        }

        try {
            $exists = $db->fetchRow($db->select('cid')->from($edkTable)->where('cid = ?', $cid));

            if ($exists) {
                $db->query($db->update($edkTable)->rows($edkData)->where('cid = ?', $cid));
            } else {
                $edkData['cid'] = $cid;
                $db->query($db->insert($edkTable)->rows($edkData));
            }
        } catch (Exception $e) {
        }
    }

    public static function cleanupTypechoFields($cid)
    {
        if (empty($cid)) {
            return;
        }

        try {
            $db = Typecho_Db::get();
            $namesToDelete = self::MIRAI_FIELDS;

            MiraiCore_Plugin::loadThemeFile('functions.php', 'common');
            if (function_exists('Mirai_vipGetEnabledLevels')) {
                $enabledLevels = Mirai_vipGetEnabledLevels();
                for ($lv = 1; $lv <= 3; $lv++) {
                    if (!in_array($lv, $enabledLevels, true)) {
                        $namesToDelete[] = 'pay_vip_' . $lv . '_price';
                    }
                }
            }

            if (!empty($namesToDelete)) {
                $db->query($db->delete('table.fields')
                    ->where('cid = ?', $cid)
                    ->where('name IN ?', $namesToDelete));
            }
        } catch (Exception $e) {
        }
    }

    public static function sanitize($value, $allowHtml = false)
    {
        if (empty($value)) {
            return '';
        }

        $value = trim((string)$value);

        if ($allowHtml) {
            return $value;
        }

        return htmlspecialchars(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
