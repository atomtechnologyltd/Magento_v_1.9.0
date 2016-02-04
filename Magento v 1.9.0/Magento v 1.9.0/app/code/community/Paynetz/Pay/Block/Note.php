<?php

class Paynetz_Pay_Block_Note extends Mage_Adminhtml_Block_System_Config_Form_Field {

	function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {
		$element_id = str_replace('paynetz_pay_', '', $element->getId());
		switch($element_id):
			case 'more_info':
				return ;
			break;
			case 'how_to_test':
				return ;
			break;
			case 'feedback_urls':
				return '

				Accepturl: http://[example.com]/[store lanuage code]/paynetz/payment/response <br />
				Declineurl: http://[example.com]/[store lanuage code]/paynetz/payment/response <br />
				Exceptionurl: http://[example.com]/[store lanuage code]/paynetz/payment/response <br/>
				Cancelurl: http://[example.com]/[store lanuage code]/paynetz/payment/response <br />


				';
			break;
		endswitch;
	}
}
