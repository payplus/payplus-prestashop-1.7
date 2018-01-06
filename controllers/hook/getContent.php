<?php
/**
 * getContent.php
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

class PayplusGetContentController
{
    public function __construct($module, $file, $path)
    {
        $this->file = $file;
        $this->module = $module;
        $this->context = Context::getContext(); 
        $this->_path = $path;
    }

    public function processConfiguration()
    {
        if (Tools::isSubmit('payplus_form'))
        {

            Configuration::updateValue('PayPlus_PAYNOW_TEXT', Tools::getValue('PayPlus_PAYNOW_TEXT'));
            Configuration::updateValue('PayPlus_PAYNOW_DESCRIPTION', Tools::getValue('PayPlus_PAYNOW_DESCRIPTION'));
            Configuration::updateValue('PayPlus_API_KEY', Tools::getValue('PayPlus_API_KEY'));
            Configuration::updateValue('PayPlus_PRIVATEAPI_KEY', Tools::getValue('PayPlus_PRIVATEAPI_KEY'));
            Configuration::updateValue('PayPlus_TOKEN', Tools::getValue('PayPlus_TOKEN'));
            Configuration::updateValue('PayPlus_MODE', Tools::getValue('PayPlus_MODE'));
            Configuration::updateValue('PayPlus_SUCCESS_MESSAGE', Tools::getValue('PayPlus_SUCCESS_MESSAGE'));
            Configuration::updateValue('PayPlus_ERROR_MESSAGE', Tools::getValue('PayPlus_ERROR_MESSAGE'));

            $this->context->smarty->assign('confirmation', 'ok');
        }
    }

    public function renderForm()
    {
        $html = '<div class="alert alert-info">' . $this->module->l("Vous trouverez vos clés d'API au niveau de votre application PayPlus.") . '<br/>' . $this->module->l("Si vous n'avez pas encore d'application pour ce site Prestashop, créez-en une en vous rendant dans votre espace client: ") . 'live : <a target="_blank" href="https://client.payplus.africa">https://client.payplus.africa</a>, test : <a target="_blank" href="https://clienttest.payplus.africa">https://clienttest.payplus.africa</a></div>';

        $modes = array(
          array(
            'id_option' => 'test',
            'name' => 'Test'
          ),
          array(
            'id_option' => 'live',
            'name' => 'Live'
          ),
        );

        $inputs = array(
            array('name' => 'PayPlus_API_KEY', 'label' => $this->module->l('Clé Principale'), 'required' => 'true', 'type' => 'text'),
            array('name' => 'PayPlus_PRIVATEAPI_KEY', 'label' => $this->module->l('Clé Secrète'), 'required' => 'true', 'type' => 'text'),
            array('name' => 'PayPlus_TOKEN', 'label' => $this->module->l('Token'), 'required' => 'true', 'type' => 'text'),
            array('name' => 'PayPlus_MODE', 'label' => $this->module->l('Mode'), 'required' => 'true', 'type' => 'select', 'options'  => array('query' => $modes, 'id' => 'id_option', 'name' => 'name'), 'hint' => $this->module->l("Utilisez le mode Test si vous souhaitez effectuer des paiements tests. Vous pourrez par la suite passer en mode Live lorsque vous serez prêt à accepter des paiements réels.")),
            array('name' => 'PayPlus_PAYNOW_TEXT', 'required' => 'true', 'label' => $this->module->l('Texte du bouton de paiement'), 'type' => 'text', 'desc' => $this->module->l('Texte que verra le client lors du paiement de sa commande.'), 'empty_message' => 'dd'),
            array('name' => 'PayPlus_PAYNOW_DESCRIPTION', 'required' => 'true', 'label' => $this->module->l('Description du bouton de paiement'), 'type' => 'textarea', 'desc' => $this->module->l('Description que verra le client lors du paiement de sa commande.')),
            array('name' => 'PayPlus_SUCCESS_MESSAGE', 'required' => 'true', 'label' => $this->module->l('Message affiché en cas de succès de paiement'), 'type' => 'textarea', 'desc' => $this->module->l('Message que verra le client après avoir effectué sa commande.')),
            array('name' => 'PayPlus_ERROR_MESSAGE', 'required' => 'true', 'label' => $this->module->l("Message affiché en cas d'erreur lors du paiement"), 'type' => 'textarea', 'desc' => $this->module->l("Message que verra le client si pour une raison ou une autre la transaction n'a pu être effectuée.")),
        );

        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->module->l('PayPlus | Configuration'),
                    'icon' => 'icon-wrench'
                ),
                'input' => $inputs,
                'submit' => array('title' => $this->module->l('Enregistrer'))
            )
        );

        $helper = new HelperForm();
        $helper->table = 'payplus';
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = (int)Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->submit_action = 'payplus_form';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->module->name.'&tab_module='.$this->module->tab.'&module_name='.$this->module->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => array(
                'PayPlus_API_KEY' => Tools::getValue('PayPlus_API_KEY', Configuration::get('PayPlus_API_KEY')),
                'PayPlus_PRIVATEAPI_KEY' => Tools::getValue('PayPlus_PRIVATEAPI_KEY', Configuration::get('PayPlus_PRIVATEAPI_KEY')),
                'PayPlus_TOKEN' => Tools::getValue('PayPlus_TOKEN', Configuration::get('PayPlus_TOKEN')),
                'PayPlus_PAYNOW_TEXT' => Tools::getValue('PayPlus_PAYNOW_TEXT', Configuration::get('PayPlus_PAYNOW_TEXT')),
                'PayPlus_PAYNOW_DESCRIPTION' => Tools::getValue('PayPlus_PAYNOW_DESCRIPTION', Configuration::get('PayPlus_PAYNOW_DESCRIPTION')),
                'PayPlus_MODE' => Tools::getValue('PayPlus_MODE', Configuration::get('PayPlus_MODE')),
                'PayPlus_SUCCESS_MESSAGE' => Tools::getValue('PayPlus_SUCCESS_MESSAGE', Configuration::get('PayPlus_SUCCESS_MESSAGE')),
                'PayPlus_ERROR_MESSAGE' => Tools::getValue('PayPlus_ERROR_MESSAGE', Configuration::get('PayPlus_ERROR_MESSAGE'))
            ),
            'languages' => $this->context->controller->getLanguages()
        );

        $html .= $helper->generateForm(array($fields_form));

        $html .= '
        <div class="well">
          <h2 class="lead">' . $this->module->l('Information utiles') . '</h2>
          <p>- ' . $this->module->l('Pour les paiements tests, vous devez impérativement fournir votre clé principale, votre clé secrète et votre token obtennus sur la plateforme de test : https://clienttest.payplus.africa.') . '</p>
           <p>- ' . $this->module->l('Pour les paiements en production, vous devez impérativement fournir votre clé principale, votre clé secrète et votre token obtennus sur la plateforme de production : https://client.payplus.africa') . '</p>
          <p>- ' . $this->module->l("Le montant de toute requête de paiement envoyée à PayPlus Africa devra au préalable être converti en XOF(CFA).") . '<br/>' . $this->module->l("Nous vous recommandons donc d'utiliser le système de devise de Prestashop.") . '<p>
          <p>- ' . $this->module->l('Avec Prestashop, il est possible de définir une mise à jour automatique des taux de change.') . '<br/>' . $this->module->l('Pour ce faire, rendez-vous au niveau de "Localisation -> Devises" en vous servant du menu principal.') . '<p>
        </div>';

        return $html;
    }

    public function run()
    {
        $this->processConfiguration();
        $html_confirmation_message = $this->module->display($this->file, 'getContent.tpl');
        $html_form = $this->renderForm();
        return $html_confirmation_message.$html_form;
    }
}