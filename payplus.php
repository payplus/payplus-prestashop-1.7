<?php
/*
* 2007-2017 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Payplus extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

    public $details;
    public $owner;
    public $address;
    public $extra_mail_vars;

    public function __construct()
    {
        $this->name = 'payplus';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'La Pieuvre Technologique';
        $this->controllers = array('validation');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('PayPlus');
        $this->description = $this->l('Acceptez des paiements via Mtn Mobile Money et Moov money de manière simple, rapide et sécurisée.');

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
        }
    }

    public function install()
    {
        if (!parent::install() 
            or !$this->registerHook('paymentOptions') 
            or !$this->registerHook('paymentReturn')
            or !Configuration::updateValue('PayPlus_PAYNOW_TEXT', 'Paiement avec PayPlus')
            or !Configuration::updateValue('PayPlus_PAYNOW_DESCRIPTION', 'PayPlus est la passerelle de paiement la plus populaire pour les achats en ligne au Bénin.')
            or !Configuration::updateValue('PayPlus_API_KEY', '')
            or !Configuration::updateValue('PayPlus_PRIVATEAPI_KEY', '')
            or !Configuration::updateValue('PayPlus_TOKEN', '')
            or !Configuration::updateValue('PayPlus_MODE', 'test')
            or !Configuration::updateValue('PayPlus_SUCCESS_MESSAGE', 'Félicitations, votre commande a été effectuée avec succès.')
            or !Configuration::updateValue('PayPlus_ERROR_MESSAGE', 'La transaction a échoué.')){
            return false;
        }

        if (!$this->installOrderState()) {
            return false;
        }
        
        return true;
    }

    public function installOrderState()
    {
        if (Configuration::get('PS_OS_PayPlus_PAYMENT') < 1) {
            $order_state = new OrderState();
            $order_state->send_email = true;
            $order_state->module_name = $this->name;
            $order_state->invoice = true;
            $order_state->color = '#4a884f';
            $order_state->logable = true;
            $order_state->shipped = false;
            $order_state->unremovable = false;
            $order_state->delivery = false;
            $order_state->hidden = false;
            $order_state->paid = true;
            $order_state->deleted = false;
            $order_state->name = array((int)Configuration::get('PS_LANG_DEFAULT') => pSQL($this->l('PayPlus - paiement validé')));
           
            if ($order_state->add()) {
                // We save the order State ID in Configuration database
                Configuration::updateValue('PS_OS_PayPlus_PAYMENT', $order_state->id);
                // We copy the module logo in order state logo directory
                copy(dirname(__FILE__).'/logo.png', dirname(__FILE__).'/../../img/os/'.$order_state->id.'.png');
                copy(dirname(__FILE__).'/logo.png', dirname(__FILE__).'/../../img/tmp/order_state_mini_'.$order_state->id.'.png');
            } else {
                return false;
            }
        }
        return true;
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $this->context->smarty->assign(array(
            'payplus_paynow_description' => Configuration::get('PayPlus_PAYNOW_DESCRIPTION')
        ));

        $payment_options = [
            $this->getOfflinePaymentOption(),
        ];

        return $payment_options;
    }

    public function hookPaymentReturn($params)
    {
        $controller = $this->getHookController('paymentReturn');
        return $controller->run($params);
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function getOfflinePaymentOption()
    {
        $offlineOption = new PaymentOption();
        $offlineOption->setCallToActionText(Configuration::get('PayPlus_PAYNOW_TEXT'))
                      ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
                      ->setAdditionalInformation($this->context->smarty->fetch('module:payplus/views/templates/front/payment_infos.tpl'))
                      ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/payment.png'));

        return $offlineOption;
    }

    public function getContent()
    {
        $controller = $this->getHookController('getContent');
        return $controller->run();
    }


    public function getHookController($hook_name)
    {
        // Include the controller file
        require_once(dirname(__FILE__).'/controllers/hook/'. $hook_name.'.php');
        
        // Build dynamically the controller name
        $controller_name = $this->name.$hook_name.'Controller';
        
        // Instantiate controller
        $controller = new $controller_name($this, __FILE__, $this->_path);
        
        // Return the controller
        return $controller;
    }
}
