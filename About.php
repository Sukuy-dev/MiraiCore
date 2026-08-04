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
                '用户可管理个人资料、修改密码、绑定社交账号',
                '支持配置用户协议与隐私政策',
                '内置用户等级系统，6种经验值来源自动升级'
            ],
            '付费阅读' => [
                '支持整篇付费和部分付费（[pay]标签）',
                '每篇文章可独立定价、设置购买方式与会员折扣',
                '支持游客购买、积分兑换、推广让利折扣'
            ],
            '支付系统' => [
                '易支付V1/V2接口、虎皮椒、支付宝官方/当面付',
                '微信官方支付（JSAPI/Native/H5）、余额支付',
                '完善的异步通知、并发锁防重复支付、订单超时自动关闭'
            ],
            'VIP会员' => [
                '三级会员体系，5种时长套餐独立定价',
                '4种折扣模式 + 按比例/固定金额两种折扣方式',
                '灵活升级补差价、续费折扣、到期邮件提醒'
            ],
            '创作分成' => [
                '普通作者+VIP1/2/3四级分成比例',
                '文章级覆盖，优先级：文章自定义>VIP/普通>全局',
                '作者收益统计与提现申请，余额/佣金独立配置'
            ],
            '积分系统' => [
                '自定义积分名称，多场景获取与消费',
                '积分兑换余额、购买文章、兑换VIP会员',
                '精细化管控：汇率、限额、每日获取上限'
            ],
            '推荐返利' => [
                '专属推广链接，UID或自定义推广码',
                'VIP分级返佣，多类型订单参与返佣',
                '推广让利折扣，注册绑定/仅会话两种识别模式'
            ],
            '第三方登录' => [
                '16种社交平台一键登录',
                '直接OAuth + 彩虹聚合登录两种模式',
                '账号绑定/解绑、头像同步、推荐人追踪'
            ],
            '邀请码系统' => [
                '关闭/选填/必填三种模式',
                '批量生成，支持积分/余额/VIP奖励',
                'TXT/CSV导出，一次性消耗，奖励自动发放'
            ],
            'SEO优化' => [
                '5种Schema.org结构化数据 + Open Graph + Twitter Card',
                '百度主动推送、IndexNow协议、自动Sitemap',
                '外链nofollow、Canonical规范化、RSS/Atom订阅'
            ],
            '性能优化' => [
                '图片懒加载 + 自动压缩转换WebP/AVIF',
                'DNS预解析、Preconnect预连接、Instant Page预加载',
                'Ajax无刷新分页（文章+评论）、代码高亮按需加载'
            ],
            '首页展示' => [
                '大屏幻灯片（图片+视频轮播、搜索叠加、图标卡片）',
                '精选推荐（三栏卡片布局，置顶+手动配置）',
                '首页分类推荐，3列/4列网格或单列列表布局'
            ],
            '主题外观' => [
                '深色模式（跟随系统/强制浅色/强制深色）',
                '自定义主题色、字体颜色、布局尺寸、全局圆角',
                '边栏位置可左可右，支持深浅色Logo、全局动画开关'
            ],
            '边栏组件' => [
                '管理员信息、最新/热门文章（封面图/纯文本）',
                '标签云（按热度/时间/随机排序）',
                '最新评论、人生倒计时，位置与顺序可调'
            ],
            '文章功能' => [
                '自定义封面、浏览量统计、TOC目录、灯箱',
                '6种内容可见短代码、版权声明、相关文章推荐',
                'Editor.md/Vditor双编辑器、6大扩展工具'
            ],
            '友情链接' => [
                '后台管理友情链接，支持分类与排序',
                '用户可前台提交友链申请，可配置提示文案与提交间隔',
                '推荐站点/文章展示、首页友链、nofollow控制'
            ],
            '评论与通知' => [
                'IP归属地显示（多接口+轮询备份）',
                'SMTP邮件通知（评论/回复/投稿/会员到期）',
                '极验行为验4.0 + 图片验证码，登录/注册/评论独立配置'
            ],
            '运营工具' => [
                '弹窗公告（HTML内容、智能弹出周期、零性能影响）',
                '广告系统（图片/HTML广告、蜘蛛隐藏）',
                '搜索设置、备份恢复、自定义头部/底部代码'
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