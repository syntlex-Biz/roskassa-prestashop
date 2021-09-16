<?php
/**
 * @author  Syntlex Dev https://syntlex.info
 * @copyright 2005-2021  Syntlex Dev
 * @license : GNU General Public License
 * @subpackage Payment plugin for Roskassa
 * @Product : Payment plugin for Roskassa
 * @Date  : 24 March 2021
 * @Contact : cmsmodulsdever@gmail.com
 * This plugin is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License
 *  as published by the Free Software Foundation; either version 2 (GPLv2) of the License, or (at your option) any later version.
 *
 * This plugin is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 *  without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * See the GNU General Public License for more details <http://www.gnu.org/licenses/>.
 *
 **/

class RosKassaValidationModuleFrontController extends ModuleFrontController
{
    /**
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || !$this->module->active)
            Tools::redirect('index.php?controller=order&step=1');

        // Check that this payment option is still available in case the customer
        //changed his address just before the end of the checkout process
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'roskassa') {
                $authorized = true;
                break;
            }
        }
        if (!$authorized)
            die('This payment method is not available.');

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');

        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $cart = $this->context->cart;
        $currency = new Currency((int)($cart->id_currency));
        $amount = number_format($cart->getOrderTotal(true, 3), 2, '.', '');
        $arrSign = array(
            'shop_id' => Configuration::get('ROSKASSA_MNT_ID'),
            'order_id' => (int)$cart->id,
            'amount' => $amount,
            'currency' => $currency->iso_code,
            'test' => Configuration::get('ROSKASSA_MNT_TEST_MODE')
        );
        ksort($arrSign);
        $str = http_build_query($arrSign);
        $mnt_signature = md5($str . Configuration::get('ROSKASSA_MNT_DATAINTEGRITY_CODE'));

        $params = [
            'shop_id' => Configuration::get('ROSKASSA_MNT_ID'),
            'order_id' => (int)$cart->id,
            'test' => Configuration::get('ROSKASSA_MNT_TEST_MODE'),
            'currency' => $currency->iso_code,
            'amount' => $amount,
            'sign' => $mnt_signature,
            'success_url' => Tools::getShopDomain(true, true) . __PS_BASE_URI__ .
                'index.php?controller=order-confirmation&id_cart=' . ($cart->id) . '&id_module=' . ($this->module->id) .
                '&key=' . $customer->secure_key . '&status=success',
            'fail_url' => Tools::getShopDomain(true, true) . __PS_BASE_URI__ .
                'index.php?controller=order-confirmation&id_cart=' . ($cart->id) . '&id_module=' . ($this->module->id) .
                '&key=' . $customer->secure_key . '&status=fail'
        ];
        $urlParams = [];
        foreach ($params as $key => $value)
            $urlParams[] = $key . "=" . urlencode($value);

        $this->module->validateOrder($cart->id, Configuration::get('ROSKASSA_OS_WAITING'), $total,
            $this->module->displayName, NULL, array(), (int)$currency->id, false, $customer->secure_key);
        $url = "https://" . Configuration::get('ROSKASSA_PAYMENT_URL') . "/?";
        Tools::redirect($url . implode("&", $urlParams), '');
    }
}
