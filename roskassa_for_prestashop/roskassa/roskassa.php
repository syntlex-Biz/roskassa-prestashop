<?php

/**
 * @subpackage Payment plugin for Roskassa
 * @copyright 2005-2021  Syntlex Dev
 * @author  Syntlex Dev https://syntlex.info
 * @Product : Paymant plugin for Roskassa
 * @Date    : 24 March 2021
 * @Contact : cmsmodulsdever@gmail.com
 * @Licence : GNU General Public License
 * This plugin is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License
 *  as published by the Free Software Foundation; either version 2 (GPLv2) of the License, or (at your option) any later version.
 *
 * This plugin is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 *  without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * See the GNU General Public License for more details <http://www.gnu.org/licenses/>.
 *
 **/



use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_'))
	exit;

class RosKassa extends PaymentModule
{
    protected $_html = '';
    protected $_postErrors = array();

	public function __construct()
	{
		$this->name = 'roskassa';
		$this->tab = 'payments_gateways';
		$this->version = '1.0';
		$this->author = 'RosKassa';
        $this->controllers = array('validation');
        $this->bootstrap = true;
        $this->currencies = true;
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);

        parent::__construct();

		$this->displayName = 'RosKassa';
		$this->description = 'Receive payment with RosKassa';

        if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = 'No currency has been set for RosKassa.';
        }
	}

	/**
	 * create new order statuses
	 *
	 * @param $status
	 * @param $title
	 * @param $color
	 * @param $template
	 * @param $invoice
	 * @param $send_email
	 * @param $paid
	 * @param $logable
	 */
	public function createRosKassaPaymentStatus($status, $title, $color, $template, $invoice, $send_email, $paid, $logable)
	{
		$ow_status = Configuration::get($status);
		if ($ow_status === false)
		{
			$order_state = new OrderState();
			//$order_state->id_order_state = (int)$key;
		}
		else
			$order_state = new OrderState((int)$ow_status);

		$order_state->module_name = $this->name;

		$langs = Language::getLanguages();

		foreach ($langs as $lang)
			$order_state->name[$lang['id_lang']] = utf8_encode(html_entity_decode($title));

		$order_state->invoice = $invoice;
		$order_state->send_email = $send_email;

		if ($template != '')
			$order_state->template = $template;

		if ($paid != '')
			$order_state->paid = $paid;

		$order_state->logable = $logable;
		$order_state->color = $color;
		$order_state->save();

		Configuration::updateValue($status, (int)$order_state->id);

		copy(dirname(__FILE__).'/roskassa.svg', dirname(__FILE__).'/../../img/os/'.(int)$order_state->id.'.svg');
	}

	public function install()
	{
		if (!parent::install() || !$this->registerHook('paymentOptions'))
			return false;

		Configuration::updateValue('ROSKASSA_PAYMENT_URL', 'pay.roskassa.net');
		Configuration::updateValue('ROSKASSA_MNT_ID', '');
		Configuration::updateValue('ROSKASSA_MNT_DATAINTEGRITY_CODE', '');
		Configuration::updateValue('ROSKASSA_MNT_TEST_MODE', '0');

		$this->createRosKassaPaymentStatus('ROSKASSA_OS_WAITING', 'Awaiting RosKassa payment', '#3333FF', 'payment_waiting', false, false, '', false);
		$this->createRosKassaPaymentStatus('ROSKASSA_OS_SUCCEED', 'Accepted RosKassa payment', '#32cd32', 'payment', true, true, true, true);

		return true;
	}

    public function hookPaymentOptions($params)
    {
        global $cookie;

        if (!$this->active)
            return null;
        if (!$this->checkCurrency($params['cart']))
            return null;

        $embeddedOption = new PaymentOption();
        $embeddedOption
            ->setCallToActionText('Платежная система RosKassa')
            ->setAdditionalInformation($this->context->smarty->fetch('module:roskassa/views/templates/front/payment_infos.tpl'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/roskassa.svg'));

        return array($embeddedOption);
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

    public function uninstall()
    {
        Configuration::deleteByName('ROSKASSA_PAYMENT_URL');
        Configuration::deleteByName('ROSKASSA_MNT_ID');
        Configuration::deleteByName('ROSKASSA_MNT_DATAINTEGRITY_CODE');
        Configuration::updateValue('ROSKASSA_MNT_TEST_MODE');

        return parent::uninstall();
    }

    public function getContent()
    {
        $this->_html = '<h2>'.$this->displayName.'</h2>';

        if (Tools::isSubmit('submitModule'))
        {
            Configuration::updateValue('ROSKASSA_PAYMENT_URL', Tools::getvalue('roskassa_payment_url'));
            Configuration::updateValue('ROSKASSA_MNT_ID', Tools::getvalue('roskassa_mnt_id'));
            Configuration::updateValue('ROSKASSA_MNT_DATAINTEGRITY_CODE', Tools::getvalue('roskassa_mnt_dataintegrity_code'));

            $this->_html .= $this->displayConfirmation($this->l('Configuration updated'));
        }

        $this->_html .= '
		<fieldset>
			<a href="http://roskassa.net/" target="_blank"  style="float:left;margin-right:15px;"><img src="../modules/roskassa/roskassa.svg" alt="RosKassa" /></a>
			<b>'.$this->l('Подробную информацию можете получить на сайте.').'</b><br/><br/>
			<br />
		</fieldset><br />
		<form action="'.Tools::htmlentitiesutf8($_SERVER['REQUEST_URI']).'" method="post">
			<fieldset>
				<legend><img src="../img/admin/contact.gif" alt="" />'.$this->l('Settings').'</legend>

				<label for="roskassa_payment_url">'.$this->l('Payment URL').'</label>
				<div class="margin-form">
				<select id="roskassa_payment_url" name="roskassa_payment_url">';
        $payment_urls = array('pay.roskassa.net');
        foreach ($payment_urls as $item)
        {
            $selected = (Configuration::get('ROSKASSA_PAYMENT_URL') == $item) ? 'selected="selected"' : '';
            $this->_html .= '<option value="'.$item.'" '.$selected.'>'.$item.'</option>';
        }
        $this->_html .='
				</select>
				</div>

				<label for="roskassa_mnt_id">'.$this->l('Account number').'</label>
				<div class="margin-form"><input type="text" size="20" id="roskassa_mnt_id" name="roskassa_mnt_id" value="'.Configuration::get('ROSKASSA_MNT_ID').'" /></div>

				<label for="roskassa_mnt_dataintegrity_code">'.$this->l('Data integrity code').'</label>
				<div class="margin-form"><input type="text" size="20" id="roskassa_mnt_dataintegrity_code" name="roskassa_mnt_dataintegrity_code" value="'.Configuration::get('ROSKASSA_MNT_DATAINTEGRITY_CODE').'" /></div>

				<label for="roskassa_mnt_test_mode">'.$this->l('Mode').'</label>
				<div class="margin-form" id="roskassa_mnt_test">
					<input type="radio" name="roskassa_mnt_test_mode" value="0" style="vertical-align: middle;" '.(!Tools::getValue('roskassa_mnt_test_mode', Configuration::get('ROSKASSA_MNT_TEST_MODE')) ? 'checked="checked"' : '').' />
					<span style="color: #080;">'.$this->l('Production').'</span>
					<input type="radio" name="roskassa_mnt_test_mode" value="1" style="vertical-align: middle;" '.(Tools::getValue('roskassa_mnt_test_mode', Configuration::get('ROSKASSA_MNT_TEST_MODE')) ? 'checked="checked"' : '').' />
					<span style="color: #900;">'.$this->l('Test').'</span>
				</div>

				<br /><center><input type="submit" name="submitModule" value="'.$this->l('Update settings').'" class="button" /></center>
			</fieldset>
		</form>';

        return $this->_html;
    }

}

?>
