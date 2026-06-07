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

    public static function build()
    {
        $themeUrl = defined('__TYPECHO_THEME_URL__') ? __TYPECHO_THEME_URL__ : '/usr/themes/Mirai';
        $logo = $themeUrl . '/assets/images/mirai.png';

        $modules = [
            '用户中心' => [
                '支持用户注册登录，可配置邮箱验证码验证',
                '用户可管理个人资料、修改密码',
                '支持配置用户协议与隐私政策'
            ],
            '付费阅读' => [
                '支持整篇付费和部分付费（[pay]标签）',
                '每篇文章可独立定价，支持游客购买',
                '支持创作分成，自动发放收益到作者余额'
            ],
            '支付系统' => [
                '易支付V1/V2接口、支付宝当面付',
                '支持支付宝、微信、QQ支付多通道',
                '完善的异步通知和同步跳转处理'
            ],
            '钱包系统' => [
                '用户可充值余额购买付费内容',
                '完整的收支明细记录',
                '作者可申请提现，管理员后台审核'
            ],
            '首页展示' => [
                '推荐模块支持置顶文章和手动配置',
                '首页底部按分类展示文章',
                '支持3列/4列网格或单列列表布局'
            ],
            'SEO优化' => [
                '站点和文章独立SEO设置',
                'Open Graph和Schema.org结构化数据',
                '支持RSS和Atom订阅功能'
            ],
            '主题外观' => [
                '深色模式（跟随系统/强制浅色/强制深色）',
                '自定义主题色、字体颜色、布局尺寸',
                '侧边栏位置可左可右，支持深浅色Logo'
            ],
            '边栏组件' => [
                '博主信息、最新/热门文章展示',
                '标签云（按热度/时间/随机排序）',
                '最新评论、自定义社交链接'
            ],
            '文章功能' => [
                '自定义封面、浏览量统计、阅读时间',
                '文章摘要手动设置或自动提取',
                '代码高亮按需加载、打赏功能'
            ],
            '友情链接' => [
                '后台管理友情链接，支持分类',
                '用户可前台提交友链申请',
                '可配置提交间隔防止频繁提交'
            ]
        ];
        
        $html = '<div class="mirai-about">'
            . '<div class="mirai-header"><img src="' . $logo . '" alt="Mirai"><div><h2>Mirai 未来主题</h2><p>星芒入海 未来为弦</p></div></div>'
            . '<div class="mirai-desc"><p><strong>Mirai未来主题是一款为Typecho打造的简约优雅、多功能现代化内容管理主题，以未来感设计语言融合现代美学，为你的网站带来不一样的体验。无论是个人博客、自媒体创作者或是企业，都能轻松打造属于自己的网站。</strong></p>'
            . '<p class="mirai-desc-tip">主题的最初设计灵感源自 vhAstro-Theme，在此感谢该作者的创意贡献与开源精神。</p></div>'
            . '<div class="mirai-modules">';
        
        foreach ($modules as $title => $items) {
            $html .= '<div class="mirai-mod"><h3>' . $title . '</h3><ul>';
            foreach ($items as $item) {
                $html .= '<li>' . $item . '</li>';
            }
            $html .= '</ul></div>';
        }
        
        $html .= '</div>'
            . '<div class="mirai-footer">'
            . '<div class="mirai-contact"><p class="mirai-title">问题反馈</p><p>QQ：3288637559</p><p>QQ 交流群：1095102565</p><p>博客：<a href="https://www.sukuy.com/" target="_blank">https://www.sukuy.com/</a></p></div>'
            . '</div></div>';
        
        return $html;
    }
}