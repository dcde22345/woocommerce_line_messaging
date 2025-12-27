<?php
/**
 * WooCommerce 訂單通知處理類別
 *
 * @package WooCommerce_LINE_Messaging
 */

// 防止直接訪問
if (!defined('ABSPATH')) {
    exit;
}

class WLM_Order_Notifier {
    
    /**
     * 初始化
     */
    public static function init() {
        // 掛載 WooCommerce 訂單建立完成的 hook
        add_action('woocommerce_new_order', array(__CLASS__, 'send_order_notification'), 10, 1);
        
        // 也可以掛載訂單狀態改變的 hook
        add_action('woocommerce_order_status_changed', array(__CLASS__, 'send_status_change_notification'), 10, 4);
    }
    
    /**
     * 發送訂單建立通知
     *
     * @param int $order_id 訂單 ID
     */
    public static function send_order_notification($order_id) {
        // 檢查是否啟用通知
        if (get_option('wlm_enable_order_notification') !== 'yes') {
            return;
        }
        
        // 獲取訂單物件
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // 獲取訂單的客戶 ID
        $customer_id = $order->get_customer_id();
        if (!$customer_id) {
            error_log('[WLM] 訂單 #' . $order_id . ' 沒有客戶 ID（可能是訪客結帳）');
            return;
        }
        
        // 獲取客戶的 LINE User ID
        $line_user_id = WLM_User_Data_Handler::get_line_user_id($customer_id);
        if (!$line_user_id) {
            error_log('[WLM] 客戶 #' . $customer_id . ' 沒有 LINE User ID');
            return;
        }
        
        // 準備訊息內容
        $message = self::prepare_order_message($order);
        
        // 發送訊息
        $line_messaging = new WLM_LINE_Messaging();
        $result = $line_messaging->send_flex_message(
            $line_user_id,
            '訂單建立成功通知',
            $message
        );
        
        if (is_wp_error($result)) {
            error_log('[WLM] 發送訂單通知失敗: ' . $result->get_error_message());
            
            // 在訂單備註中記錄失敗
            $order->add_order_note(
                'LINE 通知發送失敗: ' . $result->get_error_message()
            );
        } else {
            // 在訂單備註中記錄成功
            $order->add_order_note('LINE 訂單建立通知已發送');
        }
    }
    
    /**
     * 發送訂單狀態改變通知
     *
     * @param int    $order_id   訂單 ID
     * @param string $old_status 舊狀態
     * @param string $new_status 新狀態
     * @param object $order      訂單物件
     */
    public static function send_status_change_notification($order_id, $old_status, $new_status, $order) {
        // 檢查是否啟用狀態改變通知
        $enabled_statuses = get_option('wlm_status_notification_statuses', array('processing', 'completed', 'cancelled'));
        
        if (!in_array($new_status, $enabled_statuses)) {
            return;
        }
        
        // 獲取客戶的 LINE User ID
        $customer_id = $order->get_customer_id();
        if (!$customer_id) {
            return;
        }
        
        $line_user_id = WLM_User_Data_Handler::get_line_user_id($customer_id);
        if (!$line_user_id) {
            return;
        }
        
        // 準備狀態改變訊息
        $message = self::prepare_status_change_message($order, $old_status, $new_status);
        
        // 發送訊息
        $line_messaging = new WLM_LINE_Messaging();
        $result = $line_messaging->send_flex_message(
            $line_user_id,
            '訂單狀態更新通知',
            $message
        );
        
        if (is_wp_error($result)) {
            error_log('[WLM] 發送訂單狀態通知失敗: ' . $result->get_error_message());
        } else {
            $order->add_order_note('LINE 訂單狀態更新通知已發送');
        }
    }
    
    /**
     * 準備訂單訊息（Flex Message 格式）
     *
     * @param WC_Order $order 訂單物件
     * @return array Flex Message 內容
     */
    private static function prepare_order_message($order) {
        // 獲取訂單資訊
        $order_number = $order->get_order_number();
        $order_total = $order->get_formatted_order_total();
        $order_date = $order->get_date_created()->format('Y-m-d H:i:s');
        $order_status = wc_get_order_status_name($order->get_status());
        $order_url = $order->get_view_order_url();
        
        // 獲取商品列表
        $items = $order->get_items();
        $product_list = array();
        
        foreach ($items as $item) {
            $product_name = $item->get_name();
            $quantity = $item->get_quantity();
            $total = $order->get_formatted_line_subtotal($item);
            
            $product_list[] = array(
                'type' => 'box',
                'layout' => 'horizontal',
                'contents' => array(
                    array(
                        'type' => 'text',
                        'text' => $product_name . ' x' . $quantity,
                        'size' => 'sm',
                        'color' => '#555555',
                        'flex' => 0,
                        'wrap' => true
                    ),
                    array(
                        'type' => 'text',
                        'text' => $total,
                        'size' => 'sm',
                        'color' => '#111111',
                        'align' => 'end'
                    )
                )
            );
        }
        
        // 建立 Flex Message
        $flex_message = array(
            'type' => 'bubble',
            'header' => array(
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => array(
                    array(
                        'type' => 'text',
                        'text' => '訂單建立成功',
                        'weight' => 'bold',
                        'color' => '#00B900',
                        'size' => 'xl'
                    )
                )
            ),
            'body' => array(
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => array_merge(
                    array(
                        array(
                            'type' => 'text',
                            'text' => '感謝您的訂購',
                            'size' => 'md',
                            'margin' => 'md',
                            'wrap' => true
                        ),
                        array(
                            'type' => 'separator',
                            'margin' => 'lg'
                        ),
                        array(
                            'type' => 'box',
                            'layout' => 'vertical',
                            'margin' => 'lg',
                            'spacing' => 'sm',
                            'contents' => array(
                                array(
                                    'type' => 'box',
                                    'layout' => 'horizontal',
                                    'contents' => array(
                                        array(
                                            'type' => 'text',
                                            'text' => '訂單編號',
                                            'size' => 'sm',
                                            'color' => '#555555',
                                            'flex' => 0
                                        ),
                                        array(
                                            'type' => 'text',
                                            'text' => '#' . $order_number,
                                            'size' => 'sm',
                                            'color' => '#111111',
                                            'align' => 'end',
                                            'weight' => 'bold'
                                        )
                                    )
                                ),
                                array(
                                    'type' => 'box',
                                    'layout' => 'horizontal',
                                    'contents' => array(
                                        array(
                                            'type' => 'text',
                                            'text' => '訂單狀態',
                                            'size' => 'sm',
                                            'color' => '#555555',
                                            'flex' => 0
                                        ),
                                        array(
                                            'type' => 'text',
                                            'text' => $order_status,
                                            'size' => 'sm',
                                            'color' => '#111111',
                                            'align' => 'end'
                                        )
                                    )
                                ),
                                array(
                                    'type' => 'box',
                                    'layout' => 'horizontal',
                                    'contents' => array(
                                        array(
                                            'type' => 'text',
                                            'text' => '訂單日期',
                                            'size' => 'sm',
                                            'color' => '#555555',
                                            'flex' => 0
                                        ),
                                        array(
                                            'type' => 'text',
                                            'text' => $order_date,
                                            'size' => 'sm',
                                            'color' => '#111111',
                                            'align' => 'end'
                                        )
                                    )
                                )
                            )
                        ),
                        array(
                            'type' => 'separator',
                            'margin' => 'lg'
                        ),
                        array(
                            'type' => 'box',
                            'layout' => 'vertical',
                            'margin' => 'lg',
                            'spacing' => 'sm',
                            'contents' => $product_list
                        ),
                        array(
                            'type' => 'separator',
                            'margin' => 'lg'
                        ),
                        array(
                            'type' => 'box',
                            'layout' => 'horizontal',
                            'margin' => 'lg',
                            'contents' => array(
                                array(
                                    'type' => 'text',
                                    'text' => '總計',
                                    'size' => 'md',
                                    'color' => '#555555',
                                    'weight' => 'bold'
                                ),
                                array(
                                    'type' => 'text',
                                    'text' => $order_total,
                                    'size' => 'md',
                                    'color' => '#111111',
                                    'align' => 'end',
                                    'weight' => 'bold'
                                )
                            )
                        )
                    )
                )
            ),
            'footer' => array(
                'type' => 'box',
                'layout' => 'vertical',
                'spacing' => 'sm',
                'contents' => array(
                    array(
                        'type' => 'button',
                        'style' => 'primary',
                        'height' => 'sm',
                        'action' => array(
                            'type' => 'uri',
                            'label' => '查看訂單詳情',
                            'uri' => $order_url
                        )
                    )
                ),
                'flex' => 0
            )
        );
        
        return $flex_message;
    }
    
    /**
     * 準備訂單狀態改變訊息
     *
     * @param WC_Order $order      訂單物件
     * @param string   $old_status 舊狀態
     * @param string   $new_status 新狀態
     * @return array Flex Message 內容
     */
    private static function prepare_status_change_message($order, $old_status, $new_status) {
        $order_number = $order->get_order_number();
        $status_name = wc_get_order_status_name($new_status);
        $order_url = $order->get_view_order_url();
        
        // 根據狀態設定顏色和標題
        $color = '#00B900';
        $title = '訂單狀態更新';
        
        if ($new_status === 'completed') {
            $color = '#00B900';
            $title = '訂單已完成';
        } elseif ($new_status === 'processing') {
            $color = '#FFA500';
            $title = '訂單處理中';
        } elseif ($new_status === 'cancelled') {
            $color = '#FF0000';
            $title = '訂單已取消';
        }
        
        $flex_message = array(
            'type' => 'bubble',
            'header' => array(
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => array(
                    array(
                        'type' => 'text',
                        'text' => $title,
                        'weight' => 'bold',
                        'color' => $color,
                        'size' => 'xl'
                    )
                )
            ),
            'body' => array(
                'type' => 'box',
                'layout' => 'vertical',
                'contents' => array(
                    array(
                        'type' => 'text',
                        'text' => '您的訂單狀態已更新',
                        'size' => 'md',
                        'margin' => 'md',
                        'wrap' => true
                    ),
                    array(
                        'type' => 'separator',
                        'margin' => 'lg'
                    ),
                    array(
                        'type' => 'box',
                        'layout' => 'vertical',
                        'margin' => 'lg',
                        'spacing' => 'sm',
                        'contents' => array(
                            array(
                                'type' => 'box',
                                'layout' => 'horizontal',
                                'contents' => array(
                                    array(
                                        'type' => 'text',
                                        'text' => '訂單編號',
                                        'size' => 'sm',
                                        'color' => '#555555',
                                        'flex' => 0
                                    ),
                                    array(
                                        'type' => 'text',
                                        'text' => '#' . $order_number,
                                        'size' => 'sm',
                                        'color' => '#111111',
                                        'align' => 'end',
                                        'weight' => 'bold'
                                    )
                                )
                            ),
                            array(
                                'type' => 'box',
                                'layout' => 'horizontal',
                                'contents' => array(
                                    array(
                                        'type' => 'text',
                                        'text' => '新狀態',
                                        'size' => 'sm',
                                        'color' => '#555555',
                                        'flex' => 0
                                    ),
                                    array(
                                        'type' => 'text',
                                        'text' => $status_name,
                                        'size' => 'sm',
                                        'color' => $color,
                                        'align' => 'end',
                                        'weight' => 'bold'
                                    )
                                )
                            )
                        )
                    )
                )
            ),
            'footer' => array(
                'type' => 'box',
                'layout' => 'vertical',
                'spacing' => 'sm',
                'contents' => array(
                    array(
                        'type' => 'button',
                        'style' => 'primary',
                        'height' => 'sm',
                        'action' => array(
                            'type' => 'uri',
                            'label' => '查看訂單詳情',
                            'uri' => $order_url
                        )
                    )
                ),
                'flex' => 0
            )
        );
        
        return $flex_message;
    }
}

