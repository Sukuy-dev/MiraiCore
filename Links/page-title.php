<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$currentPanel = isset($_GET['panel']) ? $_GET['panel'] : 'MiraiCore/Links/manage-links.php';

$linksListUrl = $options->adminUrl('extending.php?panel=MiraiCore%2FLinks%2Fmanage-links.php', true);
$categoriesUrl = $options->adminUrl('extending.php?panel=MiraiCore%2FLinks%2Fmanage-categories.php', true);
?>
<div class="typecho-page-title">
    <h2>友情链接管理</h2>
    <div class="typecho-option-tabs">
        <ul class="typecho-option-tabs clearfix">
            <li<?php echo strpos($currentPanel, 'manage-links.php') !== false ? ' class="current"' : ''; ?>>
                <a href="<?php echo $linksListUrl; ?>">链接列表</a>
            </li>
            <li<?php echo strpos($currentPanel, 'manage-categories.php') !== false ? ' class="current"' : ''; ?>>
                <a href="<?php echo $categoriesUrl; ?>">链接分类</a>
            </li>
        </ul>
    </div>
</div>
