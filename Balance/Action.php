<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class MiraiCore_Balance_Action extends Typecho_Widget implements Widget_Interface_Do
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
                    $balance = $request->get('balance', '');
                    $balanceRemark = $request->get('balance_remark', '');

                    if ($uid > 0 && $balance !== '') {
                        $options = \Typecho\Widget::widget('Widget_Options');
                        $themeDir = __TYPECHO_ROOT_DIR__ . '/usr/themes/' . $options->theme;
                        $coreFile = $themeDir . '/common/functions/core.php';
                        $functionsFile = $themeDir . '/common/functions/pay.php';
                        if (file_exists($coreFile)) {
                            require_once $coreFile;
                        }
                        if (file_exists($functionsFile)) {
                            require_once $functionsFile;
                        }

                        if (function_exists('Mirai_payAdjustBalance')) {
                            $wallet = function_exists('Mirai_payGetWallet') ? Mirai_payGetWallet($uid) : ['balance' => 0];
                            $currentBalance = (float)($wallet['balance'] ?? 0);
                            $newBalance = (float)$balance;
                            $diff = round($newBalance - $currentBalance, 2);
                            if ($diff != 0) {
                                $remark = !empty($balanceRemark) ? $balanceRemark : '管理员直接设置';
                                Mirai_payAdjustBalance($uid, $diff, $diff > 0 ? 'admin_add' : 'admin_sub', $remark);
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Ignore
            }
        }
    }

    public static function renderFooter()
    {
        $script = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
        $adminPath = '/' . trim((string)__TYPECHO_ADMIN_DIR__, '/');

        $options = \Typecho\Widget::widget('Widget_Options');
        $security = \Typecho\Widget::widget('Widget_Security');

        $themeDir = __TYPECHO_ROOT_DIR__ . '/usr/themes/' . $options->theme;
        $coreFile = $themeDir . '/common/functions/core.php';
        $functionsFile = $themeDir . '/common/functions/pay.php';

        $payEnabled = false;
        if (file_exists($coreFile)) {
            require_once $coreFile;
        }
        if (file_exists($functionsFile)) {
            require_once $functionsFile;
        }
        if (function_exists('Mirai_payEnabled')) {
            $payEnabled = Mirai_payEnabled();
        }

        if (stripos($script, $adminPath . '/user.php') !== false) {
            $request = \Typecho\Request::getInstance();
            $uid = intval($request->get('uid', 0));

            if ($uid <= 0) {
                return;
            }

            $balanceName = function_exists('Mirai_payBalanceName') ? Mirai_payBalanceName() : '余额';

            $balance = '0.00';
            if (function_exists('Mirai_payGetWallet')) {
                $wallet = Mirai_payGetWallet($uid);
                $balance = isset($wallet['balance']) ? number_format((float)$wallet['balance'], 2, '.', '') : '0.00';
            }

            echo '<script>
            $(document).ready(function() {
                var balanceHtml = \'<ul class="typecho-option"><li><label class="typecho-label">' . $balanceName . '</label><input type="text" class="text w-100" name="balance" value="' . $balance . '" placeholder="输入金额"><p class="description">直接设置用户余额。</p></li></ul>\' +
                                  \'<ul class="typecho-option"><li><label class="typecho-label">备注</label><input type="text" class="text w-100" name="balance_remark" value="" placeholder="管理员调整备注（选填）"><p class="description">备注将显示在用户余额明细中。</p></li></ul>\';
                $("form[action*=users-edit]").find(".typecho-option").last().before(balanceHtml);
            });
            </script>';
        } elseif (stripos($script, $adminPath . '/manage-users.php') !== false) {
            try {
                $currency = function_exists('Mirai_payCurrencyName') ? Mirai_payCurrencyName() : '';
                $balanceName = function_exists('Mirai_payBalanceName') ? Mirai_payBalanceName() : '余额';
                $apiUrl = \Typecho\Common::url('index.php', $options->rootUrl);
                $apiToken = $security->getToken('api');

                echo '<script>
                $(document).ready(function() {
                    var $table = $(".typecho-list-table");
                    
                    if ($table.find(".mirai-balance-col").length > 0) {
                        return;
                    }

                    var uids = [];
                    $table.find("tbody tr").each(function() {
                        var idStr = $(this).attr("id");
                        if (idStr && idStr.indexOf("user-") === 0) {
                            uids.push(idStr.replace("user-", ""));
                            $(this).append("<td class=\"mirai-balance-td mirai-balance-loading\">...</td>");
                        }
                    });

                    if (uids.length === 0) {
                        return;
                    }

                    var $colgroup = $table.find("colgroup");
                    var $cols = $colgroup.find("col");
                    var hasVipCol = $table.find(".mirai-vip-col").length > 0;

                    if ($cols.length >= 6) {
                        if (hasVipCol) {
                            $cols.eq(2).attr("width", "18%");
                        } else {
                            $cols.eq(2).attr("width", "25%");
                        }
                    }

                    $colgroup.append("<col width=\"" + (hasVipCol ? "12%" : "13%") + "\" class=\"mirai-balance-col\"/>");
                    $table.find("thead tr").append("<th class=\"mirai-balance-th\">' . htmlspecialchars($balanceName, ENT_QUOTES, 'UTF-8') . '</th>");

                    var apiUrl = ' . json_encode($apiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';
                    var url = apiUrl + (apiUrl.indexOf("?") === -1 ? "?" : "&") + "mirai_api=getBalances";

                    $.ajax({
                        url: url,
                        type: "POST",
                        data: { _ajax: "1", uids: uids.join(","), token: ' . json_encode($apiToken, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ' },
                        dataType: "json",
                        success: function(res) {
                            if (res && res.success) {
                                $table.find("tbody tr").each(function() {
                                    var idStr = $(this).attr("id");
                                    if (idStr && idStr.indexOf("user-") === 0) {
                                        var uid = idStr.replace("user-", "");
                                        var v = res.data[uid];
                                        var balance = v ? parseFloat(v.balance).toFixed(2) : "0.00";
                                        $(this).find(".mirai-balance-td").removeClass("mirai-balance-loading").html("<span class=\"mirai-balance-value\">" + balance + "</span> ' . htmlspecialchars($currency, ENT_QUOTES, 'UTF-8') . '");
                                    }
                                });
                            } else {
                                $table.find(".mirai-balance-loading").text("-").removeClass("mirai-balance-loading");
                            }
                        },
                        error: function() {
                            $table.find(".mirai-balance-loading").text("-").removeClass("mirai-balance-loading");
                        }
                    });
                });
                </script>';
            } catch (Exception $e) {
                // Ignore
            }
        }
    }
}
