<?php
/**
 * payment.php
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


class PayplusPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    
    public function initContent()
    {
        // Call parent init content method
        parent::initContent();

        // Check if currency is accepted
        if (!$this->checkCurrency())
            Tools::redirect('index.php?controller=order');

        // Check if cart exists and all fields are set
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
            Tools::redirect('index.php?controller=order&step=1');



        // Check if module is enabled
        $authorized = false;
        foreach (Module::getPaymentModules() as $module)
            if ($module['name'] == $this->module->name)
                $authorized = true;
        if (!$authorized)
            die('This payment method is not available.');


        // Check if customer exists
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');

        $this->process_payment($customer);
    }

    private function checkCurrency()
    {
        // Get cart currency and enabled currencies for this module
        $currency_order = new Currency($this->context->cart->id_currency);
        $currencies_module = $this->module->getCurrency($this->context->cart->id_currency);

        // Check if cart currency is one of the enabled currencies
        if (is_array($currencies_module))
            foreach ($currencies_module as $currency_module)
                if ($currency_order->id == $currency_module['id_currency'])
                    return true;

        // Return false otherwise
        return false;
    }

    private function process_payment($customer) {
        $commande = urlencode(json_encode($this->get_payplus_args($customer)));

        $ch = curl_init();
        $api_key = Configuration::get('PayPlus_API_KEY');
        $private_api_key = Configuration::get('PayPlus_PRIVATEAPI_KEY');
        $token = Configuration::get('PayPlus_TOKEN');
        $caller = 5;

        $url = '';
        if (Configuration::get('PayPlus_MODE') == 'live') {
            $url = 'https://app.payplus.africa/payplus-api/v01/checkout-invoice/create';
        } else {
            $url = 'https://apptest.payplus.africa/payplus-api/v01/checkout-invoice/create';
        }

        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_NOBODY => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => array("commande"=>$commande),
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

        if($response_decoded->response_code != "00") {
            die($response_decoded->response_text);
        }

        // Validate order
        $cart = $this->context->cart;
        $currency = $this->context->currency;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $extra_vars = array(
            '{total_to_pay}' => Tools::displayPrice($total)
        );
        $this->module->validateOrder($cart->id, Configuration::get('PS_OS_PayPlus_PAYMENT'), $total,
            $this->module->displayName, NULL, $extra_vars, (int)$currency->id, false, $cart->secure_key);

        Tools::redirectLink($response_decoded->response_text);
        die();
    }


    private function get_payplus_args($customer) {
        $cart = $this->context->cart;
        $order_cart_id = $cart->id;
        $order_total_amount = $cart->getOrderTotal(true, Cart::BOTH);
        $ttx = $order_total_amount - $cart->getOrderTotal(false);
        $order_total_tax_amount = $ttx < 0 ? : $ttx;
        $order_cart_secure_key = $cart->secure_key;
        $order_items = $cart->getProducts(true);
        $order_total_shipping_amount = $cart->getOrderTotal(false, Cart::ONLY_SHIPPING);
        $order_return_url = $this->context->link->getPageLink('order-confirmation', null, null, 'key='.$cart->secure_key.'&id_cart='.$cart->id.'&id_module='.$this->module->id);

        $items = $order_items;
        $payplus_items = array();
        foreach ($items as $item) {
            $payplus_items[] = array(
                "name" => $item['name'],
                "quantity" => $item['cart_quantity'],
                "unit_price" => number_format((float)$item['price'], 2, '.', ''),
                "total_price" => number_format((float)$item['total'], 2, '.', ''),
                "description" => strip_tags($item['description_short'])
            );
        }

        if($order_total_shipping_amount > 0){
            $payplus_items[] = array(
                "name" => "Frais de livraison",
                "quantity" => 1,
                "unit_price" => $order_total_shipping_amount,
                "total_price" => $order_total_shipping_amount
            );
        }

        $sql = 'UPDATE `'._DB_PREFIX_.'orders` WHERE `id_cart` = '.$order_cart_id;
        $orders = Db::getInstance()->executeS($sql);
        $currentOrder = 0;
        if(count($orders)>0){
            $currentOrder = $orders[0]["id_order"];
        }

        //"order_id" => $this->module->currentOrder,

        $payplus_args = array(
            "invoice" => array(
                "items" => $payplus_items,
                "taxes" => array(),
                "total_amount" => $order_total_amount,
                "description" => "Paiement de " . $order_total_amount . " FCFA pour article(s) achetés sur " . Configuration::get('PS_SHOP_NAME')
            ), "store" => array(
                "name" => Configuration::get('PS_SHOP_NAME'),
                "website_url" => Tools::getHttpHost( true ).__PS_BASE_URI__
            ), "actions" => array(
                "cancel_url" => Tools::getHttpHost( true ).__PS_BASE_URI__,
                "callback_url" => $this->context->link->getModuleLink('payplus', 'validationipn'),
                "return_url" => $order_return_url
            ), "custom_data" => array(
                "cart_id" => $order_cart_id,
                "order_id" => $currentOrder,
                "cart_secure_key" => $order_cart_secure_key,
                "Taxes" => $order_total_tax_amount,
            )
        );

        return $payplus_args;
    }

}