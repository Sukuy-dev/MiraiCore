<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class MiraiCore_TagSlugStatus
{
    public static function render()
    {
        if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) !== 'manage-tags.php') {
            return;
        }

        try {
            $db = Typecho_Db::get();
            $rows = $db->fetchAll($db->select('mid', 'name', 'slug')->from('table.metas')->where('type = ?', 'tag'));
        } catch (Exception $e) {
            return;
        }

        $data = [];
        foreach ($rows as $row) {
            $name = trim((string)$row['name']);
            $slug = trim((string)$row['slug']);
            $data[(int)$row['mid']] = $slug === '' || $slug === $name ? '#fbe3e4' : '#e6efc2';
        }

        $json = json_encode($data);
        echo '<script>(function(data){
function init(){
document.querySelectorAll(".tag-list.typecho-list-notable li").forEach(function(li){
var box=li.querySelector("input[name=\"mid[]\"]"),color=box&&data[box.value];
if(color)li.style.backgroundColor=color;
});
}
document.readyState==="loading"?document.addEventListener("DOMContentLoaded",init):init();
})(' . ($json ?: '{}') . ');</script>';
    }
}
