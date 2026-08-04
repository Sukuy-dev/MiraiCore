<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

$currentPanel = isset($_GET['panel']) ? (string)$_GET['panel'] : 'MiraiCore/Links/manage-links.php';
$isCategories = strpos($currentPanel, 'manage-categories.php') !== false;
$linksListUrl = $options->adminUrl('extending.php?panel=MiraiCore%2FLinks%2Fmanage-links.php', true);
$categoriesUrl = $options->adminUrl('extending.php?panel=MiraiCore%2FLinks%2Fmanage-categories.php', true);
$currentUrl = $isCategories ? $categoriesUrl : $linksListUrl;
?>
<div class="typecho-page-title">
    <h2><?php echo $isCategories ? '链接分类' : '友情链接'; ?></h2>
    <a href="<?php echo $currentUrl . '&action=edit'; ?>">新增</a>
    <a href="<?php echo $isCategories ? $linksListUrl : $categoriesUrl; ?>"><?php echo $isCategories ? '友情链接' : '链接分类'; ?></a>
</div>
