<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __DIR__ . '/Sitemap/Service.php';
require_once __DIR__ . '/Sitemap/Action.php';
require_once __DIR__ . '/Orders/Action.php';
require_once __DIR__ . '/Withdrawals/Action.php';
require_once __DIR__ . '/Links/Action.php';
require_once __DIR__ . '/Links/SubmitAction.php';
require_once __DIR__ . '/RepairDb/Action.php';
require_once __DIR__ . '/Wechat/Action.php';
require_once __DIR__ . '/About.php';
require_once __DIR__ . '/Balance/Action.php';
require_once __DIR__ . '/Crawler/Service.php';
require_once __DIR__ . '/Crawler/Hook.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Fields/Handler.php';
require_once __DIR__ . '/Update/Updater.php';
require_once __DIR__ . '/Update/Action.php';
require_once __DIR__ . '/Database/Service.php';
require_once __DIR__ . '/InviteCode/Service.php';
require_once __DIR__ . '/InviteCode/Action.php';
require_once __DIR__ . '/OpenList/Service.php';
require_once __DIR__ . '/OpenList/Action.php';

/**
 * Mirai 未来主题核心组件
 *
 * @package MiraiCore
 * @author 苏酷伊Sukuy
 * @version 1.1.5
 * @link https://www.sukuy.com
 */
class MiraiCore_Plugin implements Typecho_Plugin_Interface
{
    const VERSION = '1.1.5';
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Upload')->uploadHandle = array('MiraiCore_Plugin', 'uploadHandle');
        Typecho_Plugin::factory('Widget_Upload')->modifyHandle = array('MiraiCore_Plugin', 'modifyHandle');
        Typecho_Plugin::factory('Widget_Upload')->deleteHandle = array('MiraiCore_Plugin', 'deleteHandle');

        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->fields = array('MiraiCore_Fields_Handler', 'intercept');
        Typecho_Plugin::factory('Widget_Contents_Page_Edit')->fields = array('MiraiCore_Fields_Handler', 'intercept');

        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishSave = array('MiraiCore_Fields_Handler', 'handleSave');
        Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishSave = array('MiraiCore_Fields_Handler', 'handleSave');
        Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array('MiraiCore_Fields_Handler', 'handleSave');
        Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishPublish = array('MiraiCore_Fields_Handler', 'handleSave');

        Typecho_Plugin::factory('index.php')->begin = array('MiraiCore_Plugin', 'interceptIndexBegin');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('MiraiCore_Plugin', 'onArchiveFooter');
        Typecho_Plugin::factory('Widget_Logout')->action = array('MiraiCore_Plugin', 'handleLogout');

        Typecho_Plugin::factory('admin/header.php')->header = array('MiraiCore_Plugin', 'renderHeader');
        Typecho_Plugin::factory('admin/footer.php')->end = array('MiraiCore_Plugin', 'renderFooter');
        Typecho_Plugin::factory('admin/common.php')->begin = array('MiraiCore_Plugin', 'handleAdminBegin');

        $miraiMenuIndex = Helper::addMenu('Mirai');
        Helper::addPanel($miraiMenuIndex, 'MiraiCore/Orders/manage-orders.php', '订单管理', '管理订单', 'administrator');
        Helper::addPanel($miraiMenuIndex, 'MiraiCore/Withdrawals/manage-withdrawals.php', '提现管理', '管理提现申请', 'administrator');
        Helper::addPanel($miraiMenuIndex, 'MiraiCore/Points/manage-points.php', '积分管理', '管理积分记录与调整', 'administrator');
        Helper::addPanel($miraiMenuIndex, 'MiraiCore/Referral/manage-referral.php', '返佣管理', '管理推广返佣', 'administrator');
        Helper::addPanel($miraiMenuIndex, 'MiraiCore/InviteCode/manage-invite-codes.php', '邀请码管理', '管理注册邀请码', 'administrator');
        Helper::addPanel($miraiMenuIndex, 'MiraiCore/Level/manage-level.php', '等级管理', '管理用户等级', 'administrator');
        Helper::addPanel($miraiMenuIndex, 'MiraiCore/Links/manage-links.php', '友情链接', '管理友情链接', 'administrator');
        Helper::addPanel($miraiMenuIndex, 'MiraiCore/Links/manage-categories.php', '链接分类', '管理链接分类', 'administrator');
        Helper::addPanel($miraiMenuIndex, 'MiraiCore/OpenList/manage-openlist.php', 'OpenList资源', '管理OpenList资源配置', 'administrator');
        Helper::addPanel($miraiMenuIndex, 'MiraiCore/Sitemap/manage-sitemap.php', '网站地图', '管理网站地图', 'administrator');
        Helper::addPanel($miraiMenuIndex, 'MiraiCore/Crawler/manage-crawler.php', '爬虫记录', '查看爬虫访问记录', 'administrator');
        Helper::addAction('links-submit', 'MiraiCore_Links_Action');
        Helper::addAction('miraicrawler-process', 'MiraiCore_Crawler_Action');
        Helper::addAction('miraicorerepairdb', 'MiraiCore_RepairDb_Action');
        Helper::addAction('miraicoresitemap', 'MiraiCore_Sitemap_Action');
        Helper::addAction('mirai-orders', 'MiraiCore_Orders_Action');
        Helper::addAction('mirai-withdrawals', 'MiraiCore_Withdrawals_Action');
        Helper::addAction('mirai-links', 'MiraiCore_Links_Batch_Action');
        Helper::addAction('mirai-wechat', 'MiraiCore_Wechat_Action');
        Helper::addAction('mirai-update', 'MiraiCore_Update_Action');
        Helper::addAction('mirai-invite-codes', 'MiraiCore_InviteCode_Action');
        Helper::addAction('mirai-openlist', 'MiraiCore_OpenList_Action');
        
        self::loadThemeFile('migration.php', 'common/mysql');
        if (function_exists('Mirai_checkDatabase')) {
            Mirai_checkDatabase();
        }

        return _t('Mirai 核心组件已激活');
    }

    public static function deactivate()
    {
        $panelTable = Helper::options()->panelTable;
        $parentMenus = empty($panelTable['parent']) ? [] : $panelTable['parent'];
        $menuIndex = array_search('Mirai', $parentMenus);
        
        if ($menuIndex !== false) {
            if (isset($panelTable['child'][$menuIndex + 10])) {
                unset($panelTable['child'][$menuIndex + 10]);
            }
            unset($panelTable['parent'][$menuIndex]);
            Helper::setOption('panelTable', $panelTable);
        }
        
        Helper::removeAction('links-submit');
        Helper::removeAction('miraicrawler-process');
        Helper::removeAction('miraicorerepairdb');
        Helper::removeAction('miraicoresitemap');
        Helper::removeAction('mirai-orders');
        Helper::removeAction('mirai-withdrawals');
        Helper::removeAction('mirai-links');
        Helper::removeAction('mirai-wechat');
        Helper::removeAction('mirai-update');
        Helper::removeAction('mirai-invite-codes');
        Helper::removeAction('mirai-openlist');
    }
    
    public static function config(Typecho_Widget_Helper_Form $form){}

    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    public static function handleLogout()
    {
        $user = Typecho_Widget::widget('Widget_User');
        $user->logout();
        if (session_status() === PHP_SESSION_ACTIVE) {
            $miraiKeys = array_filter(array_keys($_SESSION), function($k) {
                return strpos($k, 'mirai_') === 0;
            });
            foreach ($miraiKeys as $key) {
                unset($_SESSION[$key]);
            }
        }
        @session_destroy();
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
            session_regenerate_id(true);
        }
        header('Location: /');
        exit;
    }

    public static function interceptIndexBegin()
    {
        require_once __DIR__ . '/VIP/Action.php';
        MiraiCore_VIP_Action::interceptIndexBegin();
        MiraiCore_Balance_Action::interceptIndexBegin();

        MiraiCore_Auth::interceptAuth();

        if (defined('__TYPECHO_DEBUG__') && __TYPECHO_DEBUG__) {
            set_exception_handler(function (\Throwable $exception) {
                \Typecho\Response::getInstance()->clean();
                ob_end_clean();

                ob_start(function ($content) {
                    \Typecho\Response::getInstance()->sendHeaders();
                    return $content;
                });

                if (404 == $exception->getCode()) {
                    \Widget\ExceptionHandle::alloc();
                } else {
                    echo '<pre><code>';
                    echo '<h1>' . htmlspecialchars($exception->getMessage()) . '</h1>';
                    echo htmlspecialchars($exception->__toString());
                    echo '</code></pre>';
                }

                exit;
            });
        }
    }

    public static function onArchiveFooter($archive)
    {
        MiraiCore_Auth::onArchiveFooter($archive);
        MiraiCore_OpenList_Action::renderAssets($archive);
    }

    public static function handleAdminBegin()
    {
        MiraiCore_Auth::interceptAdminAccess();
        if (self::isActionRequest()) {
            return;
        }
        MiraiCore_Database_Service::checkOnAdminLogin();
        self::renderAdminScript();
        self::renderWechatAssets();
    }

    public static function renderFooter()
    {
        require_once __DIR__ . '/VIP/Action.php';
        MiraiCore_VIP_Action::renderFooter();
        MiraiCore_Balance_Action::renderFooter();
        MiraiCore_OpenList_Action::renderAssets();
    }

    public static function renderHeader($header)
    {
        $cssUrl = defined('__TYPECHO_PLUGIN_URL__') ? __TYPECHO_PLUGIN_URL__ . '/MiraiCore/assets/css/mirai.css' : '/usr/plugins/MiraiCore/assets/css/mirai.css';
        return $header . '<link rel="stylesheet" type="text/css" href="' . $cssUrl . '" />';
    }

    public static function renderAdminScript()
    {
        if (self::isActionRequest()) {
            return;
        }

        $adminPath = '/' . trim((string)__TYPECHO_ADMIN_DIR__, '/');
        if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_NAME']) && strpos((string)$_SERVER['SCRIPT_NAME'], $adminPath . '/') !== false) {
            $options = Typecho_Widget::widget('Widget_Options');
            $groupUrl = MiraiCore_About::getGroupUrl();
            $security = Typecho_Widget::widget('Widget_Security');
            $repairToken = $security->getToken('miraicorerepairdb');
            $rawRedirect = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : $adminPath . '/';
            $redirect = $rawRedirect;
            if (strpos($redirect, $adminPath . '/') !== 0) {
                $redirect = $adminPath . '/';
            }
            $redirect = preg_replace('/[\x00-\x1f]/', '', $redirect);
            $actionBase = rtrim((string)$options->index, '/');
            if ($actionBase === '' || strpos($actionBase, 'index.php') === false) {
                $actionBase = rtrim((string)$options->siteUrl, '/') . '/index.php';
            }
            $repairUrl = $actionBase . '/action/miraicorerepairdb?_token=' . rawurlencode($repairToken) . '&redirect=' . rawurlencode($redirect) . '&ajax=1';
            $themeVersion = MiraiCore_About::getThemeVersion();
            $pluginVersion = self::VERSION;
            $updateCheckUrl = $actionBase . '/action/mirai-update?do=check-update';
            $updateDoUrl = $actionBase . '/action/mirai-update?do=do-update';
            $updateToken = $security->getToken('mirai-update');

            $builder = '(function(){' .
                'window.MIRAI_CORE_REPAIR_DB_URL=\'' . $repairUrl . '\';' .
                'window.MIRAI_CORE_HEADER_BUILDER=function(){' .
                'return \'<div class="mirai-config-header"><div class="mirai-header-right">\' +' .
                '\'<span class="mirai-badge is-gray"><i class="ri-information-line"></i> 主题 v' . $themeVersion . '</span>\' +' .
                '\'<span class="mirai-badge is-gray"><i class="ri-plug-line"></i> 插件 v' . $pluginVersion . '</span>\' +' .
                '\'<a href="javascript:;" class="mirai-badge is-blue js-mirai-check-update"><i class="ri-refresh-line"></i> 检查插件更新</a>\' +' .
                '\'<a href="javascript:;" class="mirai-badge is-green js-mirai-repair-db"><i class="ri-database-2-line"></i> 修复数据表</a>\' +' .
                '\'<a href="' . $groupUrl . '" target="_blank" class="mirai-badge is-purple"><i class="ri-qq-line"></i> 加入交流群</a>\' +' .
                '\'</div></div><p class="mirai-config-tip"></p>\';' .
                '};' .
                'window.MIRAI_UPDATE_CONFIG={checkUrl:\'' . $updateCheckUrl . '\',doUrl:\'' . $updateDoUrl . '\',token:\'' . $updateToken . '\'};' .
            '})();';

            $jsUrl = defined('__TYPECHO_PLUGIN_URL__') ? __TYPECHO_PLUGIN_URL__ . '/MiraiCore/assets/js/update.js' : '/usr/plugins/MiraiCore/assets/js/update.js';

            echo '<script>' . $builder . '</script>';
            echo '<script src="' . $jsUrl . '"></script>';
        }
    }

    public static function renderWechatAssets()
    {
        if (self::isActionRequest()) {
            return;
        }

        MiraiCore_Wechat_Action::renderAssets();
    }

    private static function isActionRequest()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return false;
        }

        return preg_match('#/(index\.php/)?action/[^/]+$#', '/' . trim($path, '/')) === 1;
    }

    public static function loadThemeFile($file, $subdir = '')
    {
        try {
            if (strpos($file, '..') !== false || strpos($file, "\0") !== false) {
                return false;
            }
            if (!empty($subdir) && (strpos($subdir, '..') !== false || strpos($subdir, "\0") !== false)) {
                return false;
            }
            $options = Typecho_Widget::widget('Widget_Options');
            $themeDir = basename((string)$options->theme);
            $themeBase = __TYPECHO_ROOT_DIR__ . '/usr/themes/' . $themeDir . '/';

            if (!empty($subdir)) {
                $path = $themeBase . $subdir . '/' . $file;
            } else {
                $path = $themeBase . $file;
            }
            
            if (file_exists($path)) {
                require_once $path;
                return true;
            }
        } catch (Exception $e) {
        }
        
        return false;
    }

    public static function uploadHandle($file)
    {
        self::loadThemeFile('functions.php', 'common');
        if (self::loadThemeFile('upload.php', 'modules') && function_exists('Mirai_uploadHandle')) {
            return Mirai_uploadHandle($file);
        }
        return false;
    }

    public static function modifyHandle($content, $file)
    {
        self::loadThemeFile('functions.php', 'common');
        if (self::loadThemeFile('upload.php', 'modules') && function_exists('Mirai_modifyHandle')) {
            return Mirai_modifyHandle($content, $file);
        }
        return false;
    }

    public static function deleteHandle($content)
    {
        self::loadThemeFile('functions.php', 'common');
        if (self::loadThemeFile('upload.php', 'modules') && function_exists('Mirai_deleteHandle')) {
            return Mirai_deleteHandle($content);
        }
        return false;
    }

    public static function triggerSeoPush($contents, $widget)
    {
        self::loadThemeFile('functions.php', 'common');
        if (function_exists('Mirai_seoPushOnSave')) {
            Mirai_seoPushOnSave($contents, $widget);
        }
    }

    public static function triggerOnPublish($contents, $widget)
    {
        self::loadThemeFile('functions.php', 'common');
        if (function_exists('Mirai_onFinishPublish')) {
            Mirai_onFinishPublish($contents, $widget);
        }
    }

    public static function buildAbout()
    {
        return MiraiCore_About::build();
    }
}
