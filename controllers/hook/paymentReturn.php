<?php
/**
 * paymentReturn.php
 *
 * Copyright (c) 2018 La Pieuvre Technologique
 *
 * LICENSE:
 *
 * This payment module is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation; either version 3 of the License, or (at
 * your option) any later version.
 *
 * This payment module is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public
 * License for more details.
 *
 * @copyright 2018 La Pieuvre Technologique
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 * @link      https://payplus.africa
 */

class PayplusPaymentReturnController
{
    public function __construct($module, $file, $path)
    {
        $this->file = $file;
        $this->module = $module;
        $this->context = Context::getContext();
        $this->_path = $path;
    }

    public function run($params)
    {
        return $this->check_payplus_response( Tools::getValue('token') );
    }

    private function check_payplus_response($invoice_token) {

        if (!empty($invoice_token)) {
            
            try {
                $ch = curl_init();

                $api_key = Configuration::get('PayPlus_API_KEY');
                $private_api_key = Configuration::get('PayPlus_PRIVATEAPI_KEY');
                $token = Configuration::get('PayPlus_TOKEN');
                $caller = 5;

                $url = '';
                if (Configuration::get('PayPlus_MODE') == 'live') {
                    $url = 'https://app.payplus.africa/payplus-api/v01/checkout-invoice/confirm/?invoiceToken=' . $invoice_token;
                } else {
                    $url = 'https://apptest.payplus.africa/payplus-api/v01/checkout-invoice/confirm/?invoiceToken='  . $invoice_token;
                }

                curl_setopt_array($ch, array(
                    CURLOPT_URL => $url,
                    CURLOPT_NOBODY => false,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_HTTPHEADER => array(
                        "PayPlus-API-KEY: $api_key",
                        "PayPlus-PRIVATE-API-KEY: $private_api_key",
                        "PayPlus-API-TOKEN: $token",
                        "PayPlus-API-CALLER: $caller"
                    ),
                ));

                $response = curl_exec($ch);
                $response_decoded = json_decode($response);
                $respond_code = $response_decoded->response_code;
                if ($respond_code == "00") {
                    //payment found
                    $status = $response_decoded->status;
                    $custom_data = $response_decoded->custom_data;

                    // Check if cart is valid
                    $cart_id = $custom_data->cart_id;
                    $order_id = $custom_data->order_id;
                    $cart = new Cart((int)$cart_id);
                    if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 ||
                        $cart->id_address_invoice == 0 || !$this->module->active)
                        die('Invalid cart');

                    // Check if customer exists
                    $customer = new Customer($cart->id_customer);
                    if (!Validate::isLoadedObject($customer))
                        die('Invalid customer');

                    $currency = new Currency((int)$cart->id_currency);
                    $total_paid = $response_decoded->invoice->total_amount;

                    if ($status == "completed") {
                        $order_id = (int)Order::getOrderByCartId($cart_id);
                        $objOrder = new Order($order_id);
                        $objOrder->setCurrentState(Configuration::get('PS_OS_PAYMENT'));

                        $this->context->smarty->assign('return_message', Configuration::get('PayPlus_SUCCESS_MESSAGE'));
                        return $this->module->display($this->file, 'payment_return.tpl');
                    } else {
                        $this->context->smarty->assign('return_message', 'Vous recevrez votre facture électronique par mail une fois le paiement effectué.');
                        return $this->module->display($this->file, 'payment_return.tpl');
                    }
                } else {
                    $order_id = (int)Order::getOrderByCartId($cart_id);
                    $objOrder = new Order($order_id);
                    $objOrder->setCurrentState(Configuration::get('PS_OS_ERROR'));
                    $this->context->smarty->assign('return_message', Configuration::get('PayPlus_ERROR_MESSAGE'));
                    return $this->module->display($this->file, 'payment_return.tpl');
                }
            } catch (Exception $e) {
                $shop = new Shop(Configuration::get('PS_SHOP_DEFAULT'));
                $redirect_url = Tools::getShopProtocol().$shop->domain.$shop->getBaseURI();
                $redirect_url .= $return_url.'index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&key='.$cart->secure_key;
                Tools::redirectLink($redirect_url);
                die();
            }
        }
    }
}