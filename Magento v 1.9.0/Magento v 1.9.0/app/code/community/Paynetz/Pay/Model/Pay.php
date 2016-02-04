<?php

class Paynetz_Pay_Model_Pay extends Mage_Payment_Model_Method_Abstract {

    protected $_code = 'paynetz';
    protected $_isInitializeNeeded = true;
    protected $_canUseInternal = true;
    protected $_canUseForMultishipping = false;

    public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('paynetz/payment/redirect', array('_secure' => true));
    }

}
