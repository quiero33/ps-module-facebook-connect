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

class FBConnectLoginModuleFrontController extends ModuleFrontController
{
	public $display_column_left = false;
	public $ssl = true;
 
	public function initContent()
	{
		parent::initContent();
 
		$fb_connect_appid = (Configuration::get('FB_CONNECT_APPID'));
		$fb_connect_appkey = (Configuration::get('FB_CONNECT_APPKEY'));
		$fb_connect_scope = (Configuration::get('FB_CONNECT_SCOPE'));
		
		$back = isset($_GET['back']) ? $_GET['back'] : '';

		$args['redirect_uri'] = $this->context->link->getPageLink('module-fbconnect-login', true, NULL, 'back='.$back);
		$args['scope'] = $fb_connect_scope;

		require_once(_PS_ROOT_DIR_.'/modules/fbconnect/fb_sdk/facebook.php');

		$facebook = new Facebook(array(
			'appId'  => $fb_connect_appid,
			'secret' => $fb_connect_appkey,
		));

		$user = $facebook->getUser();

		if ($user)
		{
			try {
				$user_profile = $facebook->api('/me');
			} catch (FacebookApiException $e) {
				error_log($e);
				$user = null;
			}
		}

		if($user)
		{
			$response_email = trim($user_profile['email']);

			$sql = 'SELECT `id_customer`
					FROM `'._DB_PREFIX_.'customer_facebook_connect`
					WHERE `facebook_id` = \''.(int)$user_profile['id'].'\'';
				$id_customer = Db::getInstance()->getValue($sql);
			
			if($id_customer){
				$customer = new Customer($id_customer);
				$this->login($customer);
			}
			else if (Customer::customerExists($response_email)) {
				$customer = new Customer();
				$authenticate = $customer->getByEmail($response_email);

				if(Db::getInstance()->insert('customer_facebook_connect',array( 'id_customer' => (int)$customer->id, 'facebook_id' => (int)$user_profile['id'], 'facebook_username' => $user_profile['username'], 'link' => $user_profile['link'])))
					$this->errors[] = Tools::displayError('There was an error while creating your account. Please, try again.');

				$this->login($customer);
			}
			else {
				$customer = new Customer();
				$_POST['lastname'] = $user_profile['last_name'];
				$_POST['firstname'] = $user_profile['first_name'];
				$_POST['passwd'] = substr(md5($user_profile['last_name'].$user_profile['first_name']), 0, 8);
				$_POST['email'] = $user_profile['email'];
				$_POST['newsletter'] = true;
				$this->errors = $customer->validateControler();

				if (!sizeof($this->errors))
				{
					$customer->active = 1;
					if (!$customer->add())
						$this->errors[] = Tools::displayError('There was an error while creating your account. Please, try again.');
					else
					{
						if(Db::getInstance()->insert('customer_facebook_connect',array( 'id_customer' => (int)$customer->id, 'facebook_id' => (int)$user_profile['id'], 'facebook_username' => $user_profile['username'], 'link' => $user_profile['link'])))
							$this->errors[] = Tools::displayError('There was an error while trying to link your new account with your Facebook account. Please, try again.');

						$email_var = array('{firstname}' => $customer->firstname, '{lastname}' => $customer->lastname, '{email}' => $customer->email, '{passwd}' => $customer->passwd);

						if (!Mail::Send(intval($this->context->cookie->id_lang), 'account', 'Welcome!', $email_var, $customer->email, $customer->firstname.' '.$customer->lastname))
							$this->errors[] = Tools::displayError('There was an error while sending the new account email.');

						$this->context->smarty->assign('confirmation', 1);
						$this->context->cookie->id_customer = intval($customer->id);
						$this->context->cookie->customer_lastname = $customer->lastname;
						$this->context->cookie->customer_firstname = $customer->firstname;
						$this->context->cookie->passwd = $customer->passwd;
						$this->context->cookie->logged = 1;
						$this->context->cookie->email = $customer->email;
						$this->context->cookie->suscribed = $customer->optin;

						Module::hookExec('createAccount', array(
							'_POST' => $_POST,
							'newCustomer' => $customer
						));
					}
				}
				else {
					print_r($this->errors);
				}
			}
			
			if ($back!='') {
				Tools::redirect('index.php?controller='.$back);
			}
			else{
				Tools::redirect('index.php?controller=index');
			}
		}
		else
		{
			if(isset($_GET['error']) && $_GET['error']=='access_denied') {
				Tools::redirect('index.php?controller=authentication&e=access_denied');
			}
			else {
				Tools::redirect($facebook->getLoginUrl($args));
			}
		}
	}

	public function login($customer){
		$customer->active = 1;
		$customer->deleted = 0;
		$this->context->cookie->id_customer = intval($customer->id);
		$this->context->cookie->customer_lastname = $customer->lastname;
		$this->context->cookie->customer_firstname = $customer->firstname;
		$this->context->cookie->logged = 1;
		$this->context->cookie->passwd = $customer->passwd;
		$this->context->cookie->email = $customer->email;
		$this->context->cookie->suscribed = $customer->optin;
		if (Configuration::get('PS_CART_FOLLOWING') AND (empty($this->context->cookie->id_cart) OR Cart::getNbProducts($this->context->cookie->id_cart) == 0))
			$this->context->cookie->id_cart = intval(Cart::lastNoneOrderedCart(intval($customer->id)));

		Module::hookExec('authentication');
	}
}