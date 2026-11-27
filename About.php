<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class MiraiCore_About
{
    const GROUP_URL = 'https://qm.qq.com/q/ENFhT7dCuY';

    public static function getGroupUrl()
    {
        return self::GROUP_URL;
    }

    public static function getThemeVersion()
    {
        $themeVersion = '1.0.0';
        $options = Typecho_Widget::widget('Widget_Options');
        $themeDir = $options->theme;
        $themeFunctionsFile = __TYPECHO_ROOT_DIR__ . '/usr/themes/' . $themeDir . '/functions.php';

        if (file_exists($themeFunctionsFile)) {
            $themeFunctionsContent = @file_get_contents($themeFunctionsFile);
            if ($themeFunctionsContent !== false) {
                if (preg_match('/define\s*\(\s*[\'"]MIRAI_VERSION[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)\s*;/', $themeFunctionsContent, $matches)) {
                    $themeVersion = $matches[1];
                }
            }
        }

        return $themeVersion;
    }
}