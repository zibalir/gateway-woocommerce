<?php

if (!defined('ABSPATH'))
    exit;

function Load_Zibal_Gateway()
{

    if (class_exists('WC_Payment_Gateway') && !class_exists('WC_Zibal') && !function_exists('Woocommerce_Add_Zibal_Gateway')) {


        add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_Zibal_Gateway');

        function Woocommerce_Add_Zibal_Gateway($methods)
        {
            $methods[] = 'WC_Zibal';
            return $methods;
        }

        add_filter('woocommerce_currencies', 'add_IR_currency');

        function add_IR_currency($currencies)
        {
            $currencies['IRR'] = __('ریال', 'woocommerce');
            $currencies['IRT'] = __('تومان', 'woocommerce');
            $currencies['IRHR'] = __('هزار ریال', 'woocommerce');
            $currencies['IRHT'] = __('هزار تومان', 'woocommerce');

            return $currencies;
        }

        add_filter('woocommerce_currency_symbol', 'add_IR_currency_symbol', 10, 2);

        function add_IR_currency_symbol($currency_symbol, $currency)
        {
            switch ($currency) {
                case 'IRR':
                    $currency_symbol = 'ریال';
                    break;
                case 'IRT':
                    $currency_symbol = 'تومان';
                    break;
                case 'IRHR':
                    $currency_symbol = 'هزار ریال';
                    break;
                case 'IRHT':
                    $currency_symbol = 'هزار تومان';
                    break;
            }
            return $currency_symbol;
        }

        class WC_Zibal extends WC_Payment_Gateway
        {

            public function __construct()
            {

                $this->id = 'WC_Zibal';
                $this->method_title = __('پرداخت زیبال', 'woocommerce');
                $this->method_description = __('تنظیمات درگاه پرداخت زیبال برای افزونه فروشگاه ساز ووکامرس', 'woocommerce');
                $this->icon = apply_filters('WC_Zibal_logo', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/images/logo.png');
                $this->has_fields = false;

                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->settings['title'];
                $this->description = $this->settings['description'];

                $this->merchantcode = $this->settings['merchantcode'];

                $this->success_massage = $this->settings['success_massage'];
                $this->failed_massage = $this->settings['failed_massage'];

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                else
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));

                add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_Zibal_Gateway'));
                add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'Return_from_Zibal_Gateway'));


            }


            public function admin_options()
            {


                parent::admin_options();
            }

            public function init_form_fields()
            {
                $this->form_fields = apply_filters('WC_Zibal_Config', array(
                        'base_confing' => array(
                            'title' => __('تنظیمات پایه ای', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'enabled' => array(
                            'title' => __('فعالسازی/غیرفعالسازی', 'woocommerce'),
                            'type' => 'checkbox',
                            'label' => __('فعالسازی درگاه زیبال', 'woocommerce'),
                            'description' => __('برای فعالسازی درگاه پرداخت زیبال باید چک باکس را تیک بزنید', 'woocommerce'),
                            'default' => 'yes',
                            'desc_tip' => true,
                        ),
                        'title' => array(
                            'title' => __('عنوان درگاه', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce'),
                            'default' => __('پرداخت امن زیبال', 'woocommerce'),
                            'desc_tip' => true,
                        ),
                        'description' => array(
                            'title' => __('توضیحات درگاه', 'woocommerce'),
                            'type' => 'text',
                            'desc_tip' => true,
                            'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce'),
                            'default' => __('پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه زیبال', 'woocommerce')
                        ),
                        'account_confing' => array(
                            'title' => __('تنظیمات حساب زیبال', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'merchantcode' => array(
                            'title' => __('مرچنت کد', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('مرچنت کد درگاه زیبال', 'woocommerce'),
                            'default' => 'zibal',
                            'desc_tip' => true	
                        ),
                        'zibaldirect' => array(
                            'title' => __('فعالسازی زیبال دایرکت', 'woocommerce'),
                            'type' => 'checkbox',
                            'label' => __('برای فعالسازی درگاه مستقیم (زیبال دایرکت) باید چک باکس را تیک بزنید', 'woocommerce'),
                            'description' => __('درگاه مستقیم زیبال', 'woocommerce'),
                            'default' => 'no',
                            'desc_tip' => true,
                        ),
                        'payment_confing' => array(
                            'title' => __('تنظیمات عملیات پرداخت', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'success_massage' => array(
                            'title' => __('پیام پرداخت موفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (توکن) زیبال استفاده نمایید .', 'woocommerce'),
                            'default' => __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'woocommerce'),
                        ),
                        'failed_massage' => array(
                            'title' => __('پیام پرداخت ناموفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت زیبال ارسال میگردد .', 'woocommerce'),
                            'default' => __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'woocommerce'),
                        ),
                    )
                );
            }

            public function process_payment($order_id)
            {
                $order = new WC_Order($order_id);
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }

            /**
             * @param $action (PaymentRequest, )
             * @param $params string
             *
             * @return mixed
             */
            public function SendRequestToZibal($action, $params)
            {
                try {
                    $ch = curl_init('https://gateway.zibal.ir/' . $action);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Zibal Rest Api v1');
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($params)
                    ));
                    $result = curl_exec($ch);
                    return json_decode($result, true);
                } catch (Exception $ex) {
                    return false;
                }
            }

            public function Send_to_Zibal_Gateway($order_id)
            {


                global $woocommerce;
                $woocommerce->session->order_id_Zibal = $order_id;
                $order = new WC_Order($order_id);
                $currency = $order->get_order_currency();
                $currency = apply_filters('WC_Zibal_Currency', $currency, $order_id);


                $form = '<form action="" method="POST" class="Zibal-checkout-form" id="Zibal-checkout-form">
						<input type="submit" name="Zibal_submit" class="button alt" id="Zibal-payment-button" value="' . __('پرداخت', 'woocommerce') . '"/>
						<a class="button cancel" href="' . $woocommerce->cart->get_checkout_url() . '">' . __('بازگشت', 'woocommerce') . '</a>
					 </form><br/>';
                $form = apply_filters('WC_Zibal_Form', $form, $order_id, $woocommerce);

                do_action('WC_Zibal_Gateway_Before_Form', $order_id, $woocommerce);
                echo $form;
                do_action('WC_Zibal_Gateway_After_Form', $order_id, $woocommerce);


                $Amount = intval($order->order_total);
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                if (strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
                )
                    $Amount = $Amount * 10;
                else if (strtolower($currency) == strtolower('IRHT'))
                    $Amount = $Amount * 10000;
                else if (strtolower($currency) == strtolower('IRHR'))
                    $Amount = $Amount * 1000;
                else if (strtolower($currency) == strtolower('IRR'))
                    $Amount = $Amount;


                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_Zibal_gateway', $Amount, $currency);

                $MerchantCode = $this->merchantcode;
                $CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_Zibal'));

                $products = array();
                $order_items = $order->get_items();
                foreach ((array)$order_items as $product) {
                    $products[] = $product['name'] . ' (' . $product['qty'] . ') ';
                }
                $products = implode(' - ', $products);

                $Description = 'خریدار : ' . $order->billing_first_name . ' ' . $order->billing_last_name . ' | محصولات : ' . $products;
                $Mobile = get_post_meta($order_id, '_billing_phone', true) ? get_post_meta($order_id, '_billing_phone', true) : '-';
                $Email = $order->billing_email;
                $Paymenter = $order->billing_first_name . ' ' . $order->billing_last_name;
                $ResNumber = intval($order->get_order_number());

                //Hooks for iranian developer
                $Description = apply_filters('WC_Zibal_Description', $Description, $order_id);
                $Mobile = apply_filters('WC_Zibal_Mobile', $Mobile, $order_id);
                $Email = apply_filters('WC_Zibal_Email', $Email, $order_id);
                $Paymenter = apply_filters('WC_Zibal_Paymenter', $Paymenter, $order_id);
                $ResNumber = apply_filters('WC_Zibal_ResNumber', $ResNumber, $order_id);
                do_action('WC_Zibal_Gateway_Payment', $order_id, $Description, $Mobile);
                $Email = !filter_var($Email, FILTER_VALIDATE_EMAIL) === false ? $Email : '';
                $Mobile = preg_match('/^09[0-9]{9}/i', $Mobile) ? $Mobile : '';

                $zibaldirect = ($this->settings['zibaldirect'] == 'no') ? 'https://gateway.zibal.ir/start/%s' : 'https://gateway.zibal.ir/start/%s/direct';

                $data = array('merchant' => $this->merchantcode, 'amount' => $Amount,'orderId'=>$order->get_order_number() , 'callbackUrl' => $CallbackUrl, 'description' => $Description,'mobile'=>$Mobile);

                $result = $this->SendRequestToZibal('request', json_encode($data));
                if ($result === false) {
                    echo "cURL Error #:" . $err;
                } else {
                    if ($result["result"] == 100) {
                        wp_redirect(sprintf($zibaldirect, $result['trackId']));
                        exit;
                    } else {
                        $Message = ' تراکنش ناموفق بود- کد خطا : ' . $result["result"];
                        $Fault = '';
                    }
                }

                if (!empty($Message) && $Message) {

                    $Note = sprintf(__('خطا در هنگام ارسال به بانک : %s', 'woocommerce'), $Message);
                    $Note = apply_filters('WC_Zibal_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault);
                    $order->add_order_note($Note);


                    $Notice = sprintf(__('در هنگام اتصال به بانک خطای زیر رخ داده است : <br/>%s', 'woocommerce'), $Message);
                    $Notice = apply_filters('WC_Zibal_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault);
                    if ($Notice)
                        wc_add_notice($Notice, 'error');

                    do_action('WC_Zibal_Send_to_Gateway_Failed', $order_id, $Fault);
                }
            }


            public function Return_from_Zibal_Gateway()
            {


                $InvoiceNumber = isset($_POST['orderId']) ? $_POST['orderId'] : '';

                global $woocommerce;


                if (isset($_GET['wc_order']))
                    $order_id = $_GET['wc_order'];
                else if ($InvoiceNumber) {
                    $order_id = $InvoiceNumber;
                } else {
                    $order_id = $woocommerce->session->order_id_Zibal;
                    unset($woocommerce->session->order_id_Zibal);
                }

                if ($order_id) {

                    $order = new WC_Order($order_id);
                    $currency = $order->get_order_currency();
                    $currency = apply_filters('WC_Zibal_Currency', $currency, $order_id);

                    if ($order->status != 'completed') {

                        $MerchantCode = $this->merchantcode;

                        if ($_POST['success'] == '1') {

                            $MerchantID = $this->merchantcode;
                            $Amount = intval($order->order_total);
                            $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                            if (strtolower($currency) == strtolower('IRT') || strtolower($currency) == strtolower('TOMAN') || strtolower($currency) == strtolower('Iran TOMAN') || strtolower($currency) == strtolower('Iranian TOMAN') || strtolower($currency) == strtolower('Iran-TOMAN') || strtolower($currency) == strtolower('Iranian-TOMAN') || strtolower($currency) == strtolower('Iran_TOMAN') || strtolower($currency) == strtolower('Iranian_TOMAN') || strtolower($currency) == strtolower('تومان') || strtolower($currency) == strtolower('تومان ایران')
                            )
                                $Amount = $Amount * 10;
                            else if (strtolower($currency) == strtolower('IRHT'))
                                $Amount = $Amount * 10000;
                            else if (strtolower($currency) == strtolower('IRHR'))
                                $Amount = $Amount * 1000;
                            else if (strtolower($currency) == strtolower('IRR'))
                                $Amount = $Amount;

                            $trackId = $_POST['trackId'];

                            $data = array('merchant' => $MerchantID, 'trackId' => $trackId);
                            $result = $this->SendRequestToZibal('verify', json_encode($data));

                            if ($result['result'] == 100) {
                                $Status = 'completed';
                                $Transaction_ID = $trackId;
                                $Fault = '';
                                $Message = '';
                            } elseif ($result['result'] == 102) {

                                $Message = 'این تراکنش قبلا تایید شده است';
                                $Notice = wpautop(wptexturize($Message));
                                wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                                exit;
                            } else {
                                $Status = 'failed';
                                $Fault = $result['result'];
                                $Message = 'تراکنش ناموفق بود';
                            }
                        } else {
                            $Status = 'failed';
                            $Fault = '';
                            $Message = 'تراکنش انجام نشد .';
                        }

                        if ($Status == 'completed' && isset($Transaction_ID) && $Transaction_ID != 0) {

                            update_post_meta($order_id, '_transaction_id', $Transaction_ID);


                            $order->payment_complete($Transaction_ID);
                            $woocommerce->cart->empty_cart();

                            $Note = sprintf(__('پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce'), $Transaction_ID);
                            $Note = apply_filters('WC_Zibal_Return_from_Gateway_Success_Note', $Note, $order_id, $Transaction_ID);
                            if ($Note)
                                $order->add_order_note($Note, 1);


                            $Notice = wpautop(wptexturize($this->success_massage));

                            $Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

                            $Notice = apply_filters('WC_Zibal_Return_from_Gateway_Success_Notice', $Notice, $order_id, $Transaction_ID);
                            if ($Notice)
                                wc_add_notice($Notice, 'success');

                            do_action('WC_Zibal_Return_from_Gateway_Success', $order_id, $Transaction_ID);

                            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                            exit;
                        } else {


                            $tr_id = ($Transaction_ID && $Transaction_ID != 0) ? ('<br/>توکن : ' . $Transaction_ID) : '';

                            $Note = sprintf(__('خطا در هنگام بازگشت از بانک : %s %s', 'woocommerce'), $Message, $tr_id);

                            $Note = apply_filters('WC_Zibal_Return_from_Gateway_Failed_Note', $Note, $order_id, $Transaction_ID, $Fault);
                            if ($Note)
                                $order->add_order_note($Note, 1);

                            $Notice = wpautop(wptexturize($this->failed_massage));

                            $Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

                            $Notice = str_replace("{fault}", $Message, $Notice);
                            $Notice = apply_filters('WC_Zibal_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $Transaction_ID, $Fault);
                            if ($Notice)
                                wc_add_notice($Notice, 'error');

                            do_action('WC_Zibal_Return_from_Gateway_Failed', $order_id, $Transaction_ID, $Fault);

                            wp_redirect($woocommerce->cart->get_checkout_url());
                            exit;
                        }
                    } else {


                        $Transaction_ID = get_post_meta($order_id, '_transaction_id', true);

                        $Notice = wpautop(wptexturize($this->success_massage));

                        $Notice = str_replace("{transaction_id}", $Transaction_ID, $Notice);

                        $Notice = apply_filters('WC_Zibal_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $Transaction_ID);
                        if ($Notice)
                            wc_add_notice($Notice, 'success');


                        do_action('WC_Zibal_Return_from_Gateway_ReSuccess', $order_id, $Transaction_ID);

                        wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                        exit;
                    }
                } else {


                    $Fault = __('شماره سفارش وجود ندارد .', 'woocommerce');
                    $Notice = wpautop(wptexturize($this->failed_massage));
                    $Notice = str_replace("{fault}", $Fault, $Notice);
                    $Notice = apply_filters('WC_Zibal_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);
                    if ($Notice)
                        wc_add_notice($Notice, 'error');

                    do_action('WC_Zibal_Return_from_Gateway_No_Order_ID', $order_id, $Transaction_ID, $Fault);

                    wp_redirect($woocommerce->cart->get_checkout_url());
                    exit;
                }
            }

        }

    }
}

add_action('plugins_loaded', 'Load_Zibal_Gateway', 0);
