<?php
class ControllerExtensionPaymentMadfoat extends Controller {

	public function index() {
		$this->write_log($this->session->data['order_id'], "Opencart initiated Madfoat Payment for order");

		$data['button_confirm'] = $this->language->get('button_confirm');

		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		$testmode=$this->_testmode($this->config->get('payment_madfoat_test'));
		$amount= $this->currency->format(
			$order_info['total'],
			$order_info['currency_code'],
			$order_info['currency_value'],
			false);
		$cart_desc=trim($this->config->get('payment_madfoat_purdesc'));
		if (empty($cart_desc)) {
			$cart_desc='Order ID {order} ';
		}
		$order_id = trim($order_info['order_id']);
		$cart_id = $order_id.'~'.(string)time();
		$cart_desc=str_replace('{order}', $order_id, $cart_desc);
		$framed = false;

		$post_data = Array(
			'ivp_method'		=> 'create',
			'ivp_authkey'		=> $this->config->get('payment_madfoat_authkey'),
			'ivp_store'		=> $this->config->get('payment_madfoat_store'),
			'ivp_lang'		=> $this->config->get('payment_madfoat_lang'),
			'ivp_cart'		=> $cart_id,
			'ivp_amount'		=> $amount,
			'ivp_currency'		=> trim($order_info['currency_code']),
			'ivp_test'		=> $testmode,
			'ivp_desc'		=> trim($cart_desc),
			'ivp_source'		=> trim('OpenCart '.VERSION),
			'return_auth'		=> 	$this->url->link('extension/payment/madfoat/callback', '', true),
			'return_can'		=>  $this->url->link('checkout/checkout', '', true),
			'return_decl'		=>  $this->url->link('extension/payment/madfoat/callback', '', true),
			'ivp_update_url'	=>  $this->url->link('extension/payment/madfoat/ivpcallback', array('cart_id' => $order_id), true),
		);

		//Billing details

		$post_data['bill_fname'] = trim($order_info['payment_firstname']);
		$post_data['bill_sname'] = trim($order_info['payment_lastname']);
		$post_data['bill_addr1'] = trim($order_info['payment_address_1']);
		$post_data['bill_addr2'] = trim($order_info['payment_address_2']);
		$post_data['bill_addr3'] = '';
		$post_data['bill_city'] = trim($order_info['payment_city']);
		$post_data['bill_region'] = trim($order_info['payment_zone']);
		$post_data['bill_zip'] = trim($order_info['payment_postcode']);
		$post_data['bill_ctry'] = trim($order_info['payment_iso_code_2']);
		$post_data['bill_email'] = trim($order_info['email']);
		$post_data['bill_phone1'] = trim($order_info['telephone']);

		if ($this->_isHttps() && $this->config->get('payment_madfoat_pay_mode') == '2') {
			$post_data['ivp_framed']='2';
			$framed = true;
			if ($this->customer->isLogged()) {
				$post_data['bill_custref']=$this->customer->getId();
			}
		}else{
			$post_data['ivp_framed']='0';
			$framed = false;
		}

		$returnData = $this->_requestGateway($post_data);

		$this->write_log($order_id, "Payment initiated for order " . $order_id . " with cart id " . $cart_id);

		$jobj='';
		$redirurl='';
		if(isset($returnData['order'])) {

			$pendingOrderStatus = $this->config->get('payment_madfoat_pend_status_id');

			$jobj = $returnData['order'];
			$this->session->data['madfoat_orderref']=$jobj['ref'];
			$this->db->query("UPDATE " . DB_PREFIX . "order SET order_status_id = " . $pendingOrderStatus . ", payment_custom_field = '" . $jobj['ref'] . "' WHERE order_id = " . $order_id);
			$redirurl=$jobj['url'];

			$this->write_log($order_id, "Order creation response ref id: " . $jobj['ref']);
		} elseif(isset($returnData['error'])) {
			$jobj = $returnData['error'];
			echo '<font color="#cc0000">'.$jobj['message'].' :: '.$jobj['note'].'</font>';

			$this->write_log($order_id, "Order creation failed with error " . $jobj['message'] . ' :: ' . $jobj['note']);
			exit();
		}

		$data['action'] = $redirurl;

		if ($framed) {
			return $this->load->view('extension/payment/madfoat_frame',$data);
		} else {
			return $this->load->view('extension/payment/madfoat',$data);
		}
	}

	public function payment_authorised($order_id,$txref) {
		$this->load->model('checkout/order');
		$message='Payment Completed: '.$txref;
		$order_status = $cart_desc=trim($this->config->get('payment_madfoat_comp_status_id'));
		if (empty($order_status)) {
			$order_status='2'; // Order status 2 = Processing
		}
		$this->model_checkout_order->addOrderHistory(
			$order_id,		// Order ID
			$order_status,		// New order status
			$message,		// Message text to add to order history
			true);			// Notify customer
	}

	public function payment_refund($order_id,$txref,$currency,$amount) {
		$this->load->model('checkout/order');
		$message='Refund of '.$currency.' '.$amount.': '.$txref;
		$order_info = $this->model_checkout_order->getOrder($order_id);
		$order_status='11';

		$this->model_checkout_order->addOrderHistory(
			$order_id,		// Order ID
			$order_status,		// New order status
			$message,		// Message text to add to order history
			true);			// Notify customer
	}

	public function payment_cancelled($order_id,$txref) {
		$this->load->model('checkout/order');
		$message='Payment Cancelled: '.$txref;
		$order_status = $cart_desc=trim($this->config->get('payment_madfoat_void_status_id'));
		if (empty($order_status)) {
			$order_status='7'; // Order status 2 = Cancelled
		}

		$pendingStatusId = $this->config->get('payment_madfoat_pend_status_id');
		$failedStatusId = $this->config->get('payment_madfoat_void_status_id');
		$currentStatusId = $this->get_order_status_id($order_id);

		if($currentStatusId == $pendingStatusId || $currentStatusId == $failedStatusId){
			$this->model_checkout_order->addOrderHistory(
				$order_id,		// Order ID
				$order_status,		// New order status
				$message,		// Message text to add to order history
				true);			// Notify customer
		}
	}

	public function payment_pending($order_id,$txref) {
		$this->load->model('checkout/order');
		$message='Payment Pending: '.$txref;
		$order_status = $cart_desc=trim($this->config->get('payment_madfoat_pend_status_id'));
		if (empty($order_status)) {
			$order_status='1'; // Order status 1 = Pending
		}

		$pendingStatusId = $this->config->get('payment_madfoat_pend_status_id');
		$failedStatusId = $this->config->get('payment_madfoat_void_status_id');
		$currentStatusId = $this->get_order_status_id($order_id);

		if($currentStatusId == $pendingStatusId || $currentStatusId == $failedStatusId){
			$this->model_checkout_order->addOrderHistory(
				$order_id,		// Order ID
				$order_status,		// New order status
				$message,		// Message text to add to order history
				true);			// Notify customer
		}
	}

	private function get_order_status_id($order_id){
		$query = $this->db->query("SELECT order_status_id FROM " . DB_PREFIX . "order WHERE order_id = " . $order_id);
		$orders = $query->rows;
		if(count($orders) > 0){
			return $orders[0]['order_status_id'];
		}else{
			return 0;
		}
	}

	public function check_payment($order_ref) {
		$post_data = Array(
			'ivp_method'	=> 'check',
			'ivp_authkey'	=> $this->config->get('payment_madfoat_authkey'),
			'ivp_store'	=> $this->config->get('payment_madfoat_store'),
			'order_ref'	=> $order_ref,
		);
		$returnData = $this->_requestGateway($post_data);
		$objOrder='';
		$objError='';
		if (isset($returnData['order'])) { $objOrder = $returnData['order']; }
		if (isset($returnData['error'])) { $objError = $returnData['error']; }
		if (is_array($objError)) { // Failed
			return false;
		}
		if (!isset(
			$objOrder['cartid'],
			$objOrder['status']['code'])) {
			// Missing fields
			return false;
		}
		$ordStatus=$objOrder['status']['code'];
		$cart_id=$objOrder['cartid'];
		$parts=explode('~', $cart_id, 2);
		$order_id=$parts[0];
		if (($ordStatus==-1) || ($ordStatus==-2) || ($ordStatus==-3)) {
			// Order status EXPIRED (-1) or CANCELLED (-2)
			$this->payment_cancelled($order_id,'');
			return false;
		}
		if (($ordStatus==2) || ($ordStatus==4)) {
			// Order status AUTH (2) or PAYMENT_REQUESTED (4)
			$this->write_log($order_id, "Check Payment: marking pending order, Order Status: " . $ordStatus);
			$this->payment_pending($order_id,'');
			return true;
		}
		if ($ordStatus==3) {
			$new_tx=$objOrder['transaction']['ref'];
			$txStatus=$objOrder['transaction']['status'];
			// Order status PAID (3)
			if (($txStatus=='P') || ($txStatus=='H')) {
				// Transaction status of pending or held
				$this->write_log($order_id, "Check Payment: marking pending order, Order Status: " . $ordStatus . " Tx Status: " . $txStatus);
				$this->payment_pending($order_id,$new_tx);
				return true;
			}
			if ($txStatus=='A') {
				// Transaction status = authorised
				$this->payment_authorised($order_id,$new_tx);
				return true;
			}
		}
		return false;
	}

	public function callback_return() {
		if ($this->check_payment($this->session->data['madfoat_orderref'])) {
			// Authorised
			$this->response->redirect($this->url->link('checkout/success'));
		} else {
			// Declined
			$this->response->redirect($this->url->link('checkout/failure'));
		}
	}

	public function callback() {
		if(isset($this->session->data['madfoat_orderref'])) {
			$this->write_log("general", "Callback received, session order ref: " . $this->session->data['madfoat_orderref']);
			$this->callback_return();
			return;
		}

		$this->write_log("general", "Callback received, session order ref not found, redirecting to checkout.");
		$this->response->redirect($this->url->link('checkout/checkout'));
	}

	public function requery(){
		//$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order WHERE order_status_id = 0 AND payment_code = 'madfoat' AND payment_custom_field != '[]' ORDER BY order_id DESC LIMIT 20");
		echo "Processing Requery CRON <br/>";
		$pendingOrderStatus = $this->config->get('payment_madfoat_pend_status_id');

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "order WHERE (order_status_id = 0 OR order_status_id = " . $pendingOrderStatus . ") AND payment_code = 'madfoat' AND payment_custom_field != '[]' and date_added < (NOW() - INTERVAL 10 MINUTE) and date_added > (NOW() - INTERVAL 24 HOUR) ORDER BY order_id DESC LIMIT 50");
		$orders = $query->rows;

		$chs = array();
		$mh = curl_multi_init();

		foreach ($orders as $key => $current_order) {
			$payment_custom_field = $current_order['payment_custom_field'];

			$post_data = Array(
				'ivp_method'	=> 'check',
				'ivp_authkey'	=> $this->config->get('payment_madfoat_authkey'),
				'ivp_store'	=> $this->config->get('payment_madfoat_store'),
				'order_ref'	=> $payment_custom_field,
			);

			$url='https://uat-secure.madfoat.dev/gateway/order.json';
			$fields='';
			foreach ($post_data as $k => $v) {
				$fields.=$k .'='.$v . '&';
			}
			$fields = rtrim($fields, '&');
			$chs[$key] = curl_init();
			curl_setopt($chs[$key], CURLOPT_URL, $url);
			curl_setopt($chs[$key],CURLOPT_RETURNTRANSFER, true);
			curl_setopt($chs[$key], CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($chs[$key], CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($chs[$key],CURLOPT_POSTFIELDS,$fields);
			curl_setopt($chs[$key],CURLOPT_TIMEOUT, 30);
			curl_multi_add_handle($mh, $chs[$key]);
		}

		$running = null;
		do {
		  curl_multi_exec($mh, $running);
		} while ($running);

		//getting the responses
		foreach(array_keys($chs) as $key){
		    $error = curl_error($chs[$key]);
		    $last_effective_URL = curl_getinfo($chs[$key], CURLINFO_EFFECTIVE_URL);
		    $time = curl_getinfo($chs[$key], CURLINFO_TOTAL_TIME);
		    $response = curl_multi_getcontent($chs[$key]);  // get results
		    if (!empty($error)) {
		    	echo "The request $key return a error: $error" . "\n";
		    }
		    else {
		    	$returnData = json_decode($response,true);
		    	echo "<pre>"; print_r($returnData);
		    	$objOrder='';
				$objError='';
		    	if (isset($returnData['order'])) { $objOrder = $returnData['order']; }
				if (isset($returnData['error'])) { $objError = $returnData['error']; }
				if (is_array($objError)) { // Failed
					// No Action
				}else{
					if (!isset(
						$objOrder['cartid'],
						$objOrder['status']['code'])) {
						// No Action
					}else{
						$ordStatus=$objOrder['status']['code'];
						$cart_id=$objOrder['cartid'];
						$order_ref=$objOrder['ref'];
						$parts=explode('~', $cart_id, 2);
						$order_id=$parts[0];
						if (($ordStatus==-1) || ($ordStatus==-2) || ($ordStatus==-3)) {
							echo "Order Cancelled<br/>";
							$this->payment_cancelled($order_id,"");
						}
						if (($ordStatus==2) || ($ordStatus==4)) {
							echo "Order Pending<br/>";
							$this->write_log($order_id, "Requery Payment: marking pending order, Order Status: " . $ordStatus);
							$this->payment_pending($order_id,"");
						}
						if ($ordStatus==3) {
							$new_tx=$objOrder['transaction']['ref'];
							$txStatus=$objOrder['transaction']['status'];

							if (($txStatus=='P') || ($txStatus=='H')) {
								echo "Order Pending or Hold<br/>";
								$this->write_log($order_id, "Requery Payment: marking pending order, Order Status: " . $ordStatus . " Tx Status: " . $txStatus);
								$this->payment_pending($order_id,$new_tx);
							}
							if ($txStatus=='A') {
								echo "Payment Authorised<br/>";
								$this->payment_authorised($order_id,$new_tx);
							}
						}
					}
				}
		    }

		    curl_multi_remove_handle($mh, $chs[$key]);
		}
		// close current handler
		curl_multi_close($mh);
		echo "Processed " . count($orders) . " Orders."; exit;
	}

	public function ivpcallback() {
		$check = $this->_signature(
			$this->request->post,
			$this->config->get('payment_madfoat_authkey'),
			'tran_store,tran_cartid,tran_order,tran_ref,tran_type,tran_authstatus,tran_authcode,tran_authmessage,tran_currency,tran_amount');
		if (strcasecmp($check,$this->request->post['tran_check'])!=0) {
			$this->response->setOutput("OK");
			return;
		}
		
		$new_tx=$this->request->post['tran_ref'];
		$url_order_id=$this->request->get['cart_id'];
		$txType=intval($this->request->post['tran_type']);
		$txStatus=$this->request->post['tran_authstatus'];
		$cart_id=$this->request->post['tran_cartid'];
		$parts=explode('~', $cart_id, 2);
		$order_id=$parts[0];
		$this->write_log($order_id, "IVP Callback received");
		if ($txStatus=='A') {
			switch ($txType) {
				case 2:	// Void
				case 6:	// Cancel
				case 8:	// Capture Reversal
					$this->write_log($order_id, "IVP Callback: cancelling order, Transaction Status: " . $txStatus . " TX Type: " . $txType);
					$this->payment_cancelled($order_id,$new_tx);
					break;
				case 1:	// Sale
				case 7:	// Capture
					$this->write_log($order_id, "IVP Callback: success order, Transaction Status: " . $txStatus . " TX Type: " . $txType);
					$this->payment_authorised($order_id,$new_tx);
					break;
				case 3:	// Refund
					$this->write_log($order_id, "IVP Callback: refunded order, Transaction Status: " . $txStatus . " TX Type: " . $txType);
					$this->payment_refund($order_id,$new_tx,$this->request->post['tran_currency'],$this->request->post['tran_amount']);
					break;
			}
		}
		$this->response->setOutput("OK");
	}


	private function _signature($post_data,$secretKey,$fieldList) {
		$signatureParams = explode(',', $fieldList);
		$signatureString = $secretKey;
		foreach ($signatureParams as $param) {
			if (array_key_exists($param, $post_data)) {
				$signatureString .= ':' . trim($post_data[$param]);
			} else {
				$signatureString .= ':';
			}
		}
		return sha1($signatureString);
	}

	private function _logger($data) {
		$log_fp = fopen('/var/www/html/log.txt','a');
		$ts = gmdate("d/m/y H:i:s");
		$backtrace = debug_backtrace();
		$p1=$backtrace[1]['class'].'::'.$backtrace[1]['function'];
		$p2=$backtrace[2]['class'].'::'.$backtrace[2]['function'];
		fprintf($log_fp,"%s %s %s %s \n",$ts,$p2,$p1,print_r($data,true));
		fflush($log_fp);
		fclose($log_fp);
	}

	private function _isHttps() {
		$url = $this->url->link('checkout/checkout', '', true);

		if (!isset($url)) { return false; }
		
		if (preg_match('#^https:#i', $url) === 1) {
			return true;
		}
		
		return false;
	}

	private function _requestGateway($post_data)
	{
		$url='https://uat-secure.madfoat.dev/gateway/order.json';
		$fields='';
		foreach ($post_data as $k => $v) {
			$fields.=$k .'='.$v . '&';
		}
		$fields = rtrim($fields, '&');

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		//curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($fields)));
		curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		/*curl_setopt($ch,CURLOPT_POST, count($fields));*/
		curl_setopt($ch,CURLOPT_POSTFIELDS,$fields);
		//curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,10);
		curl_setopt($ch,CURLOPT_TIMEOUT, 30);
		$returnData = json_decode(curl_exec($ch),true);
		//$returnCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		return $returnData;
	}
	
	private function _testmode($madfoat_testmode) {
		if (strcasecmp($madfoat_testmode,'live')==0) { return 0; }
		if (strcasecmp($madfoat_testmode,'no')==0) { return 0; }
		return 1;
	}

	private function write_log($orderId, $string){
		return true;
		/*try{
			$myfile = fopen("payment_logs/order_" . $orderId . ".log", "a");
			$txt = date("Y-m-d H:i:s") . " : " . $string . "\n";
			fwrite($myfile, $txt);
			fclose($myfile);
		}catch (exception $e) {
		}*/
	}
}
?>
