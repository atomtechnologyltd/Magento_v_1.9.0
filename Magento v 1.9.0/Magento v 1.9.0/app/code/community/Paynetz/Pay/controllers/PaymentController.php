<?php

class Paynetz_Pay_PaymentController extends Mage_Core_Controller_Front_Action {

    public function indexAction() {
		return;
    }
	
	protected function _getCheckout()
    {
        return Mage::getSingleton('checkout/session');
    }

	// The redirect action is triggered when someone places an order
    public function redirectAction() {
		$session = $this->_getCheckout();
		
        $order = Mage::getModel('sales/order');
		$order->loadByIncrementId($session->getLastRealOrderId());
		
		if (!$order->getId()) {
			Mage::throwException('No order for processing found');
        }
		 $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, Mage_Sales_Model_Order::STATE_PENDING_PAYMENT,
                Mage::helper('paynetz/data')->__('Customer was redirected to payment')
         );
        $order->save();
		$this->loadLayout();
            $this->renderLayout();
        //$order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, Mage_Sales_Model_Order::STATE_PENDING_PAYMENT);
        //$order->save();
	
		$is_active = Mage::getStoreConfig('payment/paynetz/active');
        $merchant_url = Mage::getStoreConfig('payment/paynetz/merchant_url');
        $port = Mage::getStoreConfig('payment/paynetz/port');
        $login_id = Mage::getStoreConfig('payment/paynetz/login_id');
		$password = Mage::getStoreConfig('payment/paynetz/password');
		$product_id = Mage::getStoreConfig('payment/paynetz/product_id');
        $action_gateway = '';
		
		$_url = $merchant_url;
		$returnUrl = Mage::getBaseUrl().'paynetz/payment/response';

		$datenow = date("d/m/Y h:m:s");
		$encodedDate = str_replace(" ", "%20", $datenow);
		$_url = $_url.$param['paynetz_url'];
		$postFields  = "";
		$postFields .= "&login=".$login_id;
		$postFields .= "&pass=".$password;
		$postFields .= "&ttype=NBFundTransfer";
		$postFields .= "&prodid=".$product_id;
		$postFields .= "&amt=".$order->total_due;
		//$postFields .= "&amt=100";
		$postFields .= "&txncurr=INR";
		$postFields .= "&txnscamt=0";
		$postFields .= "&clientcode=".urlencode(base64_encode(007));
		$postFields .= "&txnid=".$order->entity_id;
		$postFields .= "&date=".$encodedDate;
		$postFields .= "&custacc=1234567890";
		$postFields .= "&ru=".$returnUrl;
		$sendUrl = $_url."?".substr($postFields,1)."\n";

		$returnData = $this->curlExec($sendUrl);
		$xmlObjArray     = $this->xmltoarray($returnData);
		
		$url = $xmlObjArray['url'];
		$postFields  = "";
		$postFields .= "&ttype=NBFundTransfer";
		$postFields .= "&tempTxnId=".$xmlObjArray['tempTxnId'];
		$postFields .= "&token=".$xmlObjArray['token'];
		$postFields .= "&txnStage=1";
		$url = $_url."?".$postFields;

		header("Location: ".$url);
		exit;
		//$this->updateRecords( $dbValues['order_number'], $order['details']['BT']->order_total, $d );
		
		//$this->requestMerchant($param,$paymentId);
		
        //Loading current layout
       // $this->loadLayout();
        //Creating a new block
        //$block = $this->getLayout()->createBlock(
		//	'Mage_Core_Block_Template', 'payfort_block_redirect', array('template' => 'paynetz/pay/redirect.phtml')
       // )
       // ->setData('merchant_affiliation_name', $merchant_affiliation_name)
       // ->setData('sha_in_pass_phrase', $sha_in_pass_phrase)
       // ->setData('sha_out_pass_phrase', $sha_out_pass_phrase);

       // $this->getLayout()->getBlock('content')->append($block);

        //Now showing it with rendering of layout
       // $this->renderLayout();
    }

    public function responseAction() {

		$orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());

        /*
         * $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
         * $order->getGrandTotal();
         *
         * */
        /*
         * * Most frequent transaction statuses:
         * *
			0 - Invalid or incomplete
			1 - Cancelled by customer
			2 - Authorisation declined
			5 - Authorised
			9 - Payment requested
          */
		
		$response_code = $this->getRequest()->getParam('f_code');
		
		
		$error = false;
        $status = "";

		if($response_code == "Ok"){
			$response_type = "accept";
		}else{
			$response_type = "decline";
		}
		
        switch($response_type):
			case 'accept':
			/** trying to create invoice * */
			try {
				if (!$order->canInvoice()):
					//Mage::throwException(Mage::helper('core')->__('cannot create an invoice !'));
					$response_message = $this->__('Error: cannot create an invoice !');
					$this->renderResponse($response_message);
					return false;
				else:
					/** create invoice  **/
					//$invoiceId = Mage::getModel('sales/order_invoice_api')->create($order->getIncremenetId(), array());
					$invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
					if (!$invoice->getTotalQty()):
						//Mage::throwException(Mage::helper('core')->__('cannot create an invoice without products !'));
						$response_message = $this->__('Error: cannot create an invoice without products !');
						$this->renderResponse($response_message);
						return false;
					endif;
					$invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
					$invoice->register();
					$transactionSave = Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder());
					$transactionSave->save();

					$order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, 'Payfort has accepted the payment.');
					/** load invoice * */
					//$invoice = Mage::getModel('sales/order_invoice')->loadByIncrementId($invoiceId);
					/** pay invoice * */
					//$invoice->capture()->save();
				endif;
                } catch (Mage_Core_Exception $e) {
					//Mage::throwException(Mage::helper('core')->__('cannot create an invoice !'));
				}

				$order->sendNewOrderEmail();
				$order->setEmailSent(true);
				$order->save();
				$response_status = 9;
				if($response_status == 9) {
					$response_message = $this->__('Your payment is accepted.');
				} elseif($response_status == 5) {
					$response_message = $this->__('Your payment is authorized.');
				} else {
					$response_message = $this->__('Unknown response status.');
				}
				
				// $this->renderResponse($response_message);
				// Mage::getSingleton('checkout/session')->setSuccessMessage($response_status_message);
				Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure' => true));
				return;
			break;
			case 'decline':
				// There is a problem in the response we got
				$this->cancelAction();
				// $response_status_message = Mage::helper('payfort/data')->getResponseCodeDescription($response_status);
				Mage::getSingleton('checkout/session')->setErrorMessage($response_status_message);
				Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/failure', array('_secure' => true));
				// $this->renderResponse($response_message);
				return;
			break;
			case 'exception':
				// There is a problem in the response we got
				$this->cancelAction();
				// $response_status_message = Mage::helper('payfort/data')->getResponseCodeDescription($response_status);
				Mage::getSingleton('checkout/session')->setErrorMessage($response_status_message);
				Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/failure', array('_secure' => true));
				// $this->renderResponse($response_message);
				return;
			break;
			case 'cancel':
				// There is a problem in the response we got
				$this->cancelAction();
				// $response_status_message = Mage::helper('payfort/data')->getResponseCodeDescription($response_status);
				Mage::getSingleton('checkout/session')->setErrorMessage($response_status_message);
				Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/failure', array('_secure' => true));
				// $this->renderResponse($response_message);
				return;
			break;
			default:
				$response_message = $this->__('Response Unknown');
				$this->renderResponse($response_message);
				return;
			break;
		endswitch;

    }

    // The cancel action is triggered when an order is to be cancelled
    public function cancelAction() {
        if (Mage::getSingleton('checkout/session')->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
            if ($order->getId()) {
                // Flag the order as 'cancelled' and save it
                $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Payfort has declined the payment.')->save();
            }
        }
    }

    public function successAction() {
        /**/
    }

    public function renderResponse($response_message) {
		$this->loadLayout();
		//Creating a new block
		$block = $this->getLayout()->createBlock(
			'Mage_Core_Block_Template', 'payfort_block_response', array('template' => 'payfort/pay/response.phtml')
		)
		->setData('response_message', $response_message);

		$this->getLayout()->getBlock('content')->append($block);

		//Now showing it with rendering of layout
		$this->renderLayout();
	}

    public function testAction() {

    }
	
	function xmltoarray($data){
		$parser = xml_parser_create('');
		xml_parser_set_option($parser, XML_OPTION_TARGET_ENCODING, "UTF-8"); 
		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
		xml_parse_into_struct($parser, trim($data), $xml_values);
		xml_parser_free($parser);
		
		$returnArray = array();
		$returnArray['url'] = $xml_values[3]['value'];
		$returnArray['tempTxnId'] = $xml_values[5]['value'];
		$returnArray['token'] = $xml_values[6]['value'];

		return $returnArray;
	}

	function curlExec($base_url){
		$ch = curl_init($base_url);
		curl_setopt_array($ch, array(
		CURLOPT_URL            => $base_url,
		CURLOPT_RETURNTRANSFER => TRUE,
		CURLOPT_TIMEOUT        => 30,
		CURLOPT_USERAGENT      => 'Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)',
		CURLOPT_SSL_VERIFYPEER =>0,
		CURLOPT_SSL_VERIFYHOST => 0
	  ));

	  $results = curl_exec($ch);
	  return $results;
	}

}
