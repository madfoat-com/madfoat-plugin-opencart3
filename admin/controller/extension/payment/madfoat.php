<?php
class ControllerExtensionPaymentMadfoat extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/payment/madfoat');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

		if(!$this->version_ok()) {
			$this->error['warning'] = "This module is not supported on this version of OpenCart. Please upgrade to OpenCart 3.0.0 or later";
		}

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->request->post['payment_madfoat_defaults']='set';
			$this->model_setting_setting->editSetting('payment_madfoat', $this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'].'&type=payment', true));

		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_all_zones'] = $this->language->get('text_all_zones');
		$data['text_yes'] = $this->language->get('text_yes');
		$data['text_no'] = $this->language->get('text_no');
		$data['text_live'] = $this->language->get('text_live');
		$data['text_test'] = $this->language->get('text_test');

		$data['entry_store'] = $this->language->get('entry_store');
		$data['entry_authkey'] = $this->language->get('entry_authkey');
		$data['entry_callback'] = $this->language->get('entry_callback');
		$data['entry_test'] = $this->language->get('entry_test');		
		$data['entry_total'] = $this->language->get('entry_total');
		$data['entry_purdesc'] = $this->language->get('entry_purdesc');
		$data['entry_title'] = $this->language->get('entry_title');
		$data['entry_pend_status'] = $this->language->get('entry_pend_status');
		$data['entry_comp_status'] = $this->language->get('entry_comp_status');
		$data['entry_void_status'] = $this->language->get('entry_void_status');
		$data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['help_store'] = $this->language->get('help_store');
		$data['help_authkey'] = $this->language->get('help_authkey');
		$data['help_callback'] = $this->language->get('help_callback');
		$data['help_test'] = $this->language->get('help_test');		
		$data['help_total'] = $this->language->get('help_total');
		$data['help_purdesc'] = $this->language->get('help_purdesc');
		$data['help_title'] = $this->language->get('help_title');
		$data['help_geo_zone'] = $this->language->get('help_geo_zone');
		$data['help_status'] = $this->language->get('help_status');
		$data['help_sort_order'] = $this->language->get('help_sort_order');

		$data['entry_lang'] = $this->language->get('entry_lang');		
		$data['lang_en'] = $this->language->get('lang_en');		
		$data['lang_ar'] = $this->language->get('lang_ar');	
		$data['entry_pay_mode'] = $this->language->get('entry_pay_mode');		
		$data['pay_std'] = $this->language->get('pay_std');		
		$data['pay_frm'] = $this->language->get('pay_frm');		



		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');

		$data['tab_general'] = $this->language->get('tab_general');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['store'])) {
			$data['error_store'] = $this->error['store'];
		} else {
			$data['error_store'] = '';
		}

		if (isset($this->error['authkey'])) {
			$data['error_authkey'] = $this->error['authkey'];
		} else {
			$data['error_authkey'] = '';
		}
		
		if (isset($this->error['purdesc'])) {
			$data['error_purdesc'] = $this->error['purdesc'];
		} else {
			$data['error_purdesc'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text'	=> $this->language->get('text_home'),
			'href'	=> $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text'	=> $this->language->get('text_payment'),
			'href'	=> $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text'	=> $this->language->get('heading_title'),
			'href'	=> $this->url->link('extension/payment/madfoat', 'user_token=' . $this->session->data['user_token'], true),
			'separator' => ' :: '
		);

		$data['action'] = $this->url->link('extension/payment/madfoat', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], true);

		//$data['callback'] = HTTP_CATALOG . 'index.php?route=payment/madfoat/callback';

		if (isset($this->request->post['payment_madfoat_store'])) {
			$data['payment_madfoat_store'] = $this->request->post['payment_madfoat_store'];
		} else {
			$data['payment_madfoat_store'] = $this->config->get('payment_madfoat_store');
		}

		if (isset($this->request->post['payment_madfoat_authkey'])) {
			$data['payment_madfoat_authkey'] = $this->request->post['payment_madfoat_authkey'];
		} else {
			$data['payment_madfoat_authkey'] = $this->config->get('payment_madfoat_authkey');
		}


		if (isset($this->request->post['payment_madfoat_test'])) {
			$data['payment_madfoat_test'] = $this->request->post['payment_madfoat_test'];
		} else {
			$data['payment_madfoat_test'] = $this->config->get('payment_madfoat_test');
			if (empty($data['payment_madfoat_test'])) {
				$data['payment_madfoat_test']='Yes';
			}
		}

		if (isset($this->request->post['payment_madfoat_total'])) {
			$data['payment_madfoat_total'] = $this->request->post['payment_madfoat_total'];
		} else {
			$data['payment_madfoat_total'] = $this->config->get('payment_madfoat_total');
		}

		if (isset($this->request->post['payment_madfoat_purdesc'])) {
			$data['payment_madfoat_purdesc'] = $this->request->post['payment_madfoat_purdesc'];
		} else {
			$data['payment_madfoat_purdesc'] = $this->config->get('payment_madfoat_purdesc');
		}

		if (isset($this->request->post['payment_madfoat_title'])) {
			$data['payment_madfoat_title'] = $this->request->post['payment_madfoat_title'];
		} else {
			$data['payment_madfoat_title'] = $this->config->get('payment_madfoat_title');
			if (empty($data['payment_madfoat_title'])) {
				$data['payment_madfoat_title']='Credit/Debit Card (Madfoat)';
			}
		}

		if (isset($this->request->post['payment_madfoat_pend_status_id'])) {
			$data['payment_madfoat_pend_status_id'] = $this->request->post['payment_madfoat_pend_status_id'];
		} else {
			$data['payment_madfoat_pend_status_id'] = $this->config->get('payment_madfoat_pend_status_id');
			if (empty($data['payment_madfoat_pend_status_id'])) {
				$data['payment_madfoat_pend_status_id']='1';
			}
		}
		if (isset($this->request->post['payment_madfoat_comp_status_id'])) {
			$data['payment_madfoat_comp_status_id'] = $this->request->post['payment_madfoat_comp_status_id'];
		} else {
			$data['payment_madfoat_comp_status_id'] = $this->config->get('payment_madfoat_comp_status_id');
			if (empty($data['payment_madfoat_comp_status_id'])) {
				$data['payment_madfoat_comp_status_id']='2';
			}
		}
		if (isset($this->request->post['payment_madfoat_void_status_id'])) {
			$data['payment_madfoat_void_status_id'] = $this->request->post['payment_madfoat_void_status_id'];
		} else {
			$data['payment_madfoat_void_status_id'] = $this->config->get('payment_madfoat_void_status_id');
			if (empty($data['payment_madfoat_void_status_id'])) {
				$data['payment_madfoat_void_status_id']='7';
			}
		}

		if (isset($this->request->post['payment_madfoat_lang'])) {
			$data['payment_madfoat_lang'] = $this->request->post['payment_madfoat_lang'];
		} else {
			$data['payment_madfoat_lang'] = $this->config->get('payment_madfoat_lang');
			if (empty($data['payment_madfoat_lang'])) {
				$data['payment_madfoat_lang']='en';
			}
		}

		if (isset($this->request->post['payment_madfoat_pay_mode'])) {
			$data['payment_madfoat_pay_mode'] = $this->request->post['payment_madfoat_pay_mode'];
		} else {
			$data['payment_madfoat_pay_mode'] = $this->config->get('payment_madfoat_pay_mode');
			if (empty($data['payment_madfoat_pay_mode'])) {
				$data['payment_madfoat_pay_mode']='0';
			}
		}

		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->request->post['payment_madfoat_geo_zone_id'])) {
			$data['payment_madfoat_geo_zone_id'] = $this->request->post['payment_madfoat_geo_zone_id'];
		} else {
			$data['payment_madfoat_geo_zone_id'] = $this->config->get('payment_madfoat_geo_zone_id');
		}
		$this->load->model('localisation/geo_zone');
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post['payment_madfoat_status'])) {
			$data['payment_madfoat_status'] = $this->request->post['payment_madfoat_status'];
		} else {
			$data['payment_madfoat_status'] = $this->config->get('payment_madfoat_status');
		}

		if (isset($this->request->post['payment_madfoat_sort_order'])) {
			$data['payment_madfoat_sort_order'] = $this->request->post['payment_madfoat_sort_order'];
		} else {
			$data['payment_madfoat_sort_order'] = $this->config->get('payment_madfoat_sort_order');
		}
		$defaults=$this->config->get('payment_madfoat_defaults');
		if (empty($defaults)) {
			$data['payment_madfoat_title'] = 'Credit/Debit Card (Madfoat)';	// Module Title
			$data['payment_madfoat_test'] = 'Yes';				// Test mode
			$data['payment_madfoat_pend_status_id'] = '1';			// Pending
			$data['payment_madfoat_comp_status_id'] = '2';			// Processing
			$data['payment_madfoat_void_status_id'] = '7';			// Cancelled
			$data['payment_madfoat_status'] = '1';				// Enabled			
			$data['payment_madfoat_lang'] = 'en';				// Enabled			
			$data['payment_madfoat_pay_mode'] = '0';				// Enabled			
		}
	
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/madfoat', $data));
	}

	private function validate() {
		if (!$this->user->hasPermission('modify', 'extension/payment/madfoat')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		$store = $this->request->post['payment_madfoat_store'];
		$store = intval(preg_replace('/[^0-9]+/', '', $store), 10);
		$this->request->post['payment_madfoat_store'] = (string)$store;

		if ($store<=0) {
			$this->error['store'] = $this->language->get('error_store');
		}

		if (!$this->request->post['payment_madfoat_authkey']) {
			$this->error['authkey'] = $this->language->get('error_authkey');
		}
		
		if (!$this->request->post['payment_madfoat_purdesc'] || strlen($this->request->post['payment_madfoat_purdesc']) > 63) {
			$this->error['purdesc'] = $this->language->get('error_purdesc');
		}

		if (!$this->error) {
			return true;
		} else {
			return false;
		}
	}

	private function version_ok() {
		if (version_compare(VERSION, '3.0.0.0', '<')) {
			return false;
		}
		return true;
	}

}
?>
