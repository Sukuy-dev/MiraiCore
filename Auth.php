<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class MiraiCore_Auth
{
    public static function onArchiveFooter($archive)
    {
        MiraiCore_Crawler_Hook::handle($archive);
    }

    public static function interceptAuth()
    {
        if (MiraiCore_Sitemap_Service::maybeHandleRequest()) {
            return;
        }
        $script = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
        $adminPath = '/' . trim((string)__TYPECHO_ADMIN_DIR__, '/');
        $isLoginPage = stripos($script, $adminPath . '/login.php') !== false;
        $isRegisterPage = stripos($script, $adminPath . '/register.php') !== false;
        if (!$isLoginPage && !$isRegisterPage) {
            return;
        }
        try {
            $options = Typecho_Widget::widget('Widget_Options');
            $user = Typecho_Widget::widget('Widget_User');
            if ($user->pass('administrator', true)) {
                return;
            }
            $themeOptions = isset($options->theme) ? $options->{$options->theme} : null;
            $userCenterEnabled = !isset($themeOptions->enableUserCenter) || $themeOptions->enableUserCenter === '1';
            $frontendLoginEnabled = !isset($themeOptions->enableFrontendLogin) || $themeOptions->enableFrontendLogin === '1';
            if ($isLoginPage && $userCenterEnabled && $frontendLoginEnabled) {
                return;
            }
            if ($isRegisterPage && $options->allowRegister) {
                return;
            }
            header('Location: /');
            exit;
        } catch (Exception $e) {
        }
    }

    public static function interceptAdminAccess()
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        try {
            $options = Typecho_Widget::widget('Widget_Options');
            $user = Typecho_Widget::widget('Widget_User');
            if ($user->pass('administrator', true)) {
                return;
            }
            $themeOptions = isset($options->theme) ? $options->{$options->theme} : null;
            $userCenterEnabled = !isset($themeOptions->enableUserCenter) || $themeOptions->enableUserCenter === '1';
            if ($userCenterEnabled) {
                return;
            }
            $script = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
            $adminPath = '/' . trim((string)__TYPECHO_ADMIN_DIR__, '/');
            if (stripos($script, $adminPath . '/') === false) {
                return;
            }
            $entry = basename($script);
            if ($entry === 'login.php' || $entry === 'register.php') {
                header('Location: /');
                exit;
            }
            if (!$user->hasLogin()) {
                header('Location: /');
                exit;
            }
            header('Location: /');
            exit;
        } catch (Exception $e) {
        }
    }
}
