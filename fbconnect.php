<?php
/*
* 2013 Coluccini
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* It is available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
*
* DISCLAIMER
* This code is provided as is without any warranty.
* No promise of safety or security.
*
*  @author          @coluccini
*  @copyright       2013 Coluccini
*  @license         http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/

if (!defined('_PS_VERSION_'))
  exit;

class FBConnect extends Module
{
	public function __construct()
	{
		$this->name = 'fbconnect';
		$this->tab = 'social_networks';
		$this->author = 'Coluccini';
		$this->version = '1.0';

		$this->need_instance = 0;
		$this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.5.4.1');

		parent::__construct();

		$this->displayName = $this->l('Facebook Connect');
		$this->description = $this->l('Allows customers to login/signup using a Facebook account');

		$this->_mod_errors = array();
	}

	public function install()
	{
		if (parent::install() == false)
				return false;

		return Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'customer_facebook_connect` (
			`id_customer` int(10) unsigned NOT NULL AUTO_INCREMENT,
			`id_shop` int(11) NOT NULL DEFAULT \'1\',
			`facebook_id` varchar(50) NOT NULL,
			`facebook_username` varchar(50) NOT NULL,
			`link` varchar(200) NOT NULL,
			PRIMARY KEY (`id_customer`)
			) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1
		');
	}

	public function uninstall()
	{
		Configuration::deleteByName('FB_CONNECT_APPID');
		Configuration::deleteByName('FB_CONNECT_APPKEY');
		Configuration::deleteByName('FB_CONNECT_SCOPE');
	
		Db::getInstance()->execute('DROP TABLE IF EXISTS '._DB_PREFIX_.'customer_facebook_connect');
		return parent::uninstall();
	}

	public function getContent()
	{
		$output = '<h2>'.$this->displayName.'</h2>';
		if (Tools::isSubmit('submitFBKey'))
		{
			$fb_connect_appid = (Tools::getValue('fb_connect_appid'));
			if (!$fb_connect_appid)
				$errors[] = $this->l('Invalid Facebook AppID');
			else
				Configuration::updateValue('FB_CONNECT_APPID', $fb_connect_appid);
				
			$fb_connect_appkey = (Tools::getValue('fb_connect_appkey'));
			if (!$fb_connect_appkey)
				$errors[] = $this->l('Invalid Facebook App Key');
			else
				Configuration::updateValue('FB_CONNECT_APPKEY', $fb_connect_appkey);

			Configuration::updateValue('FB_CONNECT_SCOPE', $fb_connect_scope);
				
			if (isset($errors) AND sizeof($errors))
				$output .= $this->displayError(implode('<br />', $errors));
			else
				$output .= $this->displayConfirmation($this->l('Settings updated'));
		}
		return $output.$this->displayForm();
	}

	public function displayForm()
	{
		$output = '
		<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
			<fieldset><legend><img src="'.$this->_path.'logo.gif" alt="" title="" />'.$this->l('Settings').'</legend>
				<label>'.$this->l('Facebook AppID').'</label>
				<div class="margin-form">
					<input type="text" size="20" name="fb_connect_appid" value="'.Tools::getValue('fb_connect_appid', Configuration::get('FB_CONNECT_APPID')).'" />

				</div>

				<label>'.$this->l('Facebook App Key').'</label>
				<div class="margin-form">
					<input type="text" size="40" name="fb_connect_appkey" value="'.Tools::getValue('fb_connect_appkey', Configuration::get('FB_CONNECT_APPKEY')).'" />
				</div>

				<label>'.$this->l('Permissions requested (comma separated)').'</label>
				<div class="margin-form">
					<input type="text" size="40" name="fb_connect_scope" value="'.Tools::getValue('fb_connect_scope', Configuration::get('FB_CONNECT_SCOPE')).'" />
					<a href="https://developers.facebook.com/docs/reference/login/#permissions">'.$this->l('See list of permission').'</a>
				</div>
				<center><input type="submit" name="submitFBKey" value="'.$this->l('Save').'" class="button" /></center>
			</fieldset>
		</form>';
		return $output;
	}
}