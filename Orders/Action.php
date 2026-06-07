<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class MiraiCore_Orders_Action extends Typecho_Widget implements Widget_Interface_Do
{
    private function getThemeFunctions()
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $themeDir = __TYPECHO_ROOT_DIR__ . '/usr/themes/' . $options->theme;
        $coreFile = $themeDir . '/common/functions/core.php';
        if (file_exists($coreFile)) {
            require_once $coreFile;
        }
        $functionsFile = $themeDir . '/common/functions/pay.php';
        if (file_exists($functionsFile)) {
            require_once $functionsFile;
        }
    }

    public function action()
    {
        $this->getThemeFunctions();
        $security = Typecho_Widget::widget('Widget_Security');
        $security->protect();
        
        $do = trim((string)$this->request->get('do'));
        
        if ($do === 'delete') {
            $this->deleteOrders();
        } elseif ($do === 'close') {
            $this->closeOrders();
        }
        
        $this->response->goBack();
    }

    private function deleteOrders()
    {
        $orders = $this->request->getArray('order_no');
        $deleteRows = 0;
        $ordersTable = Mirai_payTable('orders');
        $db = Typecho_Db::get();

        foreach ($orders as $orderNo) {
            if (!preg_match('/^MR[A-Fa-f0-9]{20}$/', $orderNo)) {
                continue;
            }
            $order = $db->fetchRow($db->select('status')->from($ordersTable)->where('order_no = ?', $orderNo)->limit(1));
            if ($order && in_array($order['status'], ['pending', 'closed'], true)) {
                $db->query($db->delete($ordersTable)->where('order_no = ?', $orderNo));
                $deleteRows++;
            }
        }

        Typecho_Widget::widget('Widget_Notice')->set(
            $deleteRows > 0 ? _t('订单已经被删除') : _t('没有订单被删除，只能删除未支付或已关闭的订单'),
            $deleteRows > 0 ? 'success' : 'notice'
        );
    }

    private function closeOrders()
    {
        $orders = $this->request->getArray('order_no');
        $closeRows = 0;
        $ordersTable = Mirai_payTable('orders');
        $db = Typecho_Db::get();

        foreach ($orders as $orderNo) {
            if (!preg_match('/^MR[A-Fa-f0-9]{20}$/', $orderNo)) {
                continue;
            }
            $result = $db->query($db->update($ordersTable)->rows(['status' => 'closed'])->where('order_no = ?', $orderNo)->where('status = ?', 'pending'));
            if ($result > 0) {
                $closeRows++;
            }
        }

        Typecho_Widget::widget('Widget_Notice')->set(
            $closeRows > 0 ? _t('订单已经被关闭') : _t('没有订单被关闭，只能关闭待支付的订单'),
            $closeRows > 0 ? 'success' : 'notice'
        );
    }
}