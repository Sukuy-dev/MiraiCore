<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class MiraiCore_VIP_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function action()
    {

    }
    
    public static function interceptIndexBegin()
    {

        $request = \Typecho\Request::getInstance();
        $pathInfo = $request->getPathInfo();
        
        if (strpos($pathInfo, '/action/users-edit') !== false && $request->isPost() && $request->get('do') === 'update') {
            try {
                $user = Typecho_Widget::widget('Widget_User');
                if ($user->hasLogin() && $user->pass('administrator', true)) {
                    $uid = intval($request->get('uid', 0));
                    $vipLevel = intval($request->get('vip_level', 0));
                    $vipExpDate = $request->get('vip_exp_date', '');

                    if ($vipExpDate !== '' && $vipExpDate !== 'Permanent') {
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}(\s+\d{2}:\d{2}:\d{2})?$/', $vipExpDate)) {
                            return;
                        }
                        $timestamp = strtotime($vipExpDate);
                        if ($timestamp === false) {
                            return;
                        }
                    }

                    if ($uid > 0) {
                        $db = Typecho_Db::get();
                        $db->query($db->update('table.users')->rows([
                            'vip_level' => $vipLevel,
                            'vip_exp_date' => $vipExpDate
                        ])->where('uid = ?', $uid));
                        if (function_exists('Mirai_vipClearCache')) {
                            Mirai_vipClearCache($uid);
                        }
                    }
                }
            } catch (Exception $e) {
            }
        }
    }

    public static function renderFooter()
    {
        $script = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
        $adminPath = '/' . trim((string)__TYPECHO_ADMIN_DIR__, '/');
        if (stripos($script, $adminPath . '/user.php') !== false) {
            $request = \Typecho\Request::getInstance();
            $uid = intval($request->get('uid', 0));
            if ($uid > 0) {
                try {
                    $themeOptions = \Typecho\Widget::widget('Widget_Options');
                    $themeDir = __TYPECHO_ROOT_DIR__ . '/usr/themes/' . $themeOptions->theme;
                    $functionsFile = $themeDir . '/common/functions.php';
                    if (file_exists($functionsFile)) {
                        require_once $functionsFile;
                    }

                    $db = Typecho_Db::get();
                    $user = $db->fetchRow($db->select('vip_level', 'vip_exp_date')->from('table.users')->where('uid = ?', $uid));
                    $vipLevel = intval($user['vip_level'] ?? 0);
                    $vipExpDate = htmlspecialchars((string)($user['vip_exp_date'] ?? ''), ENT_QUOTES);

                    $vipName1 = function_exists('Mirai_vipGetName') ? Mirai_vipGetName(1) : '一级会员';
                    $vipName2 = function_exists('Mirai_vipGetName') ? Mirai_vipGetName(2) : '二级会员';
                    $vipName3 = function_exists('Mirai_vipGetName') ? Mirai_vipGetName(3) : '三级会员';

                    $enabledLevels = function_exists('Mirai_vipGetEnabledLevels') ? Mirai_vipGetEnabledLevels() : [1, 2, 3];
                    $optionsHtml = '<option value="0" \' + (' . $vipLevel . ' == 0 ? "selected" : "") + \'>普通用户</option>';
                    $levelNames = [1 => $vipName1, 2 => $vipName2, 3 => $vipName3];
                    foreach ($enabledLevels as $lvl) {
                        $optionsHtml .= '<option value="' . $lvl . '" \' + (' . $vipLevel . ' == ' . $lvl . ' ? "selected" : "") + \'>' . $levelNames[$lvl] . '</option>';
                    }

                    $expDateValue = $vipExpDate;
                    $isPermanent = ($vipExpDate === 'Permanent');
                    $isCustom = !$isPermanent && $vipExpDate !== '';

                    echo '<script>
                    $(document).ready(function() {
                        function calcExpDate(type) {
                            if (type === "" || type === "Permanent") return type;
                            var now = new Date();
                            var map = {
                                "1month": 1,
                                "3month": 3,
                                "6month": 6,
                                "1year": 12,
                                "2year": 24
                            };
                            if (map[type]) {
                                now.setMonth(now.getMonth() + map[type]);
                                var y = now.getFullYear();
                                var m = String(now.getMonth() + 1).padStart(2, "0");
                                var d = String(now.getDate()).padStart(2, "0");
                                var h = String(now.getHours()).padStart(2, "0");
                                var i = String(now.getMinutes()).padStart(2, "0");
                                var s = String(now.getSeconds()).padStart(2, "0");
                                return y + "-" + m + "-" + d + " " + h + ":" + i + ":" + s;
                            }
                            return type;
                        }

                        var vipHtml = \'<ul class="typecho-option"><li><label class="typecho-label">会员等级</label><select name="vip_level">' . $optionsHtml . '</select><p class="description">设置用户的会员等级。</p></li></ul>\' +
                                      \'<ul class="typecho-option"><li><label class="typecho-label">会员有效期</label><select name="vip_exp_select" id="vip_exp_select"><option value="">不设置</option><option value="Permanent" ' . ($isPermanent ? 'selected' : '') . '>永久有效</option><option value="1month">1个月</option><option value="3month">3个月</option><option value="6month">6个月</option><option value="1year">1年</option><option value="2year">2年</option><option value="custom" ' . ($isCustom ? 'selected' : '') . '>自定义日期</option></select><p class="description">选择会员有效期。</p></li></ul>\' +
                                      \'<ul class="typecho-option" id="vip_exp_custom_wrap" style="display:none;"><li><label class="typecho-label">自定义到期时间</label><input type="text" class="text w-100" name="vip_exp_date_custom" value="' . $expDateValue . '" placeholder="例如：2099-12-31 23:59:59"><p class="description">设置具体的到期时间。</p></li></ul>\' +
                                      \'<input type="hidden" name="vip_exp_date" id="vip_exp_date_hidden" value="' . $expDateValue . '">\';
                        $("form[action*=users-edit]").find(".typecho-option").last().before(vipHtml);

                        $("#vip_exp_select").on("change", function() {
                            var val = $(this).val();
                            if (val === "custom") {
                                $("#vip_exp_custom_wrap").show();
                                $("#vip_exp_date_hidden").val($("#vip_exp_custom_wrap input[name=vip_exp_date_custom]").val());
                            } else {
                                $("#vip_exp_custom_wrap").hide();
                                $("#vip_exp_date_hidden").val(calcExpDate(val));
                            }
                        });

                        $("#vip_exp_custom_wrap input[name=vip_exp_date_custom]").on("input", function() {
                            $("#vip_exp_date_hidden").val($(this).val());
                        });

                        if ($("#vip_exp_select").val() === "custom") {
                            $("#vip_exp_custom_wrap").show();
                        }
                    });
                    </script>';
                } catch (Exception $e) {
                }
            }
        } elseif (stripos($script, $adminPath . '/manage-users.php') !== false) {
            try {
                echo '<script>
                $(document).ready(function() {
                    var $table = $(".typecho-list-table");
                    
                    // 防止重复执行
                    if ($table.find(".mirai-vip-col").length > 0) return;
                    
                    var $colgroup = $table.find("colgroup");
                    var $thead = $table.find("thead tr");
                    var uids = [];
                    
                    // 一次性遍历tbody收集uids并添加占位td
                    $table.find("tbody tr").each(function() {
                        var idStr = $(this).attr("id");
                        if (idStr && idStr.indexOf("user-") === 0) {
                            uids.push(idStr.replace("user-", ""));
                            $(this).append("<td class=\'mirai-vip-td mirai-vip-loading\'>加载中...</td>");
                        }
                    });
                    
                    // 无用户数据时直接返回
                    if (uids.length === 0) return;
                    
                    // 调整列宽并添加会员等级列
                    var $cols = $colgroup.find("col");
                    var hasBalanceCol = $(".typecho-list-table").find(".mirai-balance-col").length > 0;

                    if ($cols.length >= 6) {
                        if (hasBalanceCol) {
                            $cols.eq(2).attr("width", "18%");
                        } else {
                            $cols.eq(2).attr("width", "25%");
                        }
                    }
                    $colgroup.append("<col width=\'" + (hasBalanceCol ? "11%" : "12%") + "\' class=\'mirai-vip-col\'/>");
                    $thead.append("<th class=\'mirai-vip-th\'>会员等级</th>");
                    
                    // 添加样式
                    if (!$("#mirai-vip-style").length) {
                        $("head").append("<style id=\'mirai-vip-style\'>\
                            .mirai-vip-td { white-space: nowrap; }\
                            .mirai-vip-name { color: #ff6600; font-weight: bold; }\
                            .mirai-vip-exp { color: #999; font-size: 12px; }\
                            table.typecho-list-table { table-layout: fixed; width: 100%; }\
                            table.typecho-list-table th, table.typecho-list-table td { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }\
                        </style>");
                    }
                    
                    // 发送AJAX请求
                    var apiUrl = "' . \Typecho\Common::url('index.php', \Typecho\Widget::widget('Widget_Options')->rootUrl) . '";
                    apiUrl += (apiUrl.indexOf("?") === -1 ? "?" : "&") + "mirai_api=get_users_vip";
                    var apiToken = "' . \Typecho\Widget::widget('Widget_Security')->getToken('api') . '";
                    
                    $.ajax({
                        url: apiUrl,
                        type: "POST",
                        data: { _ajax: 1, uids: uids.join(","), token: apiToken },
                        dataType: "json",
                        success: function(res) {
                            if (res && res.success && res.data) {
                                $table.find("tbody tr").each(function() {
                                    var idStr = $(this).attr("id");
                                    if (idStr && idStr.indexOf("user-") === 0) {
                                        var uid = idStr.replace("user-", "");
                                        var v = res.data[uid];
                                        var $td = $(this).find(".mirai-vip-loading");
                                        
                                        if (v && v.level > 0) {
                                            var expText = v.exp === "Permanent" ? "永久" : (v.exp ? v.exp : "未知");
                                            var levelNames = ["", "一级会员", "二级会员", "三级会员"];
                                            var vName = v.name || levelNames[v.level] || ("会员 " + v.level);
                                            $td.html("<span class=\'mirai-vip-name\'>" + vName + "</span><br><span class=\'mirai-vip-exp\'>" + expText + "</span>");
                                        } else {
                                            $td.text("普通用户");
                                        }
                                        $td.removeClass("mirai-vip-loading");
                                    }
                                });
                            } else {
                                $table.find(".mirai-vip-loading").text("-").removeClass("mirai-vip-loading");
                            }
                        },
                        error: function() {
                            $table.find(".mirai-vip-loading").text("-").removeClass("mirai-vip-loading");
                        }
                    });
                });
                </script>';
            } catch (Exception $e) {
            }
        }

        if (stripos($script, $adminPath . '/write-post.php') !== false || stripos($script, $adminPath . '/write-page.php') !== false) {
            try {
                $themeOptions = \Typecho\Widget::widget('Widget_Options');
                $themeDir = __TYPECHO_ROOT_DIR__ . '/usr/themes/' . $themeOptions->theme;
                $functionsFile = $themeDir . '/common/functions.php';
                if (file_exists($functionsFile)) {
                    require_once $functionsFile;
                }

                $enabledLevels = function_exists('Mirai_vipGetEnabledLevels') ? Mirai_vipGetEnabledLevels() : [1, 2, 3];
                $enabledJson = json_encode($enabledLevels);

                echo '<script>
                $(document).ready(function() {
                    var enabledLevels = ' . $enabledJson . ';
                    $("#custom-field .fields .field").each(function() {
                        var $nameInput = $(this).find("input[name^=fieldNames]");
                        if (!$nameInput.length) return;
                        var fname = $nameInput.val();
                        var match = fname.match(/^pay_vip_(\d+)_price$/);
                        if (!match) return;
                        var lvl = parseInt(match[1], 10);
                        if (enabledLevels.indexOf(lvl) === -1) {
                            $(this).hide();
                        }
                    });
                });
                </script>';
            } catch (Exception $e) {
            }
        }
    }
}
