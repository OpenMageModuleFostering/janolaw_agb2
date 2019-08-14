<?php

class Janolaw_Agb_Helper_Data extends Mage_Core_Helper_Abstract
{

    /**
     * @var Zend_Http_Client
     */
    protected $_client = null;

    /**
     * TODO replace file_get_contents by correct curl request or something similar...
     * Fetch content from a remote host, using GET request.
     *
     * @param string $url
     *
     * @throws Exception
     * @return string The remote data
     */
    public function getRemoteContent($url)
    {
        $response = $this->_getClient($url)->request(Zend_Http_Client::GET);
        $status = $response->getStatus();
        if ($status < 200 || $status >= 300) {
            throw new Janolaw_Agb_Helper_HttpStatusNotSuccessfulException($response);
        }
        return $response->getBody();
    }

    /**
     * Print a list of error messages, depending on context (admin, command line, frontend).
     * If the array is large, not all messages are printed
     *
     * @param array $errors
     * @param int   $maxCount Print maximum this number or error messages.
     */
    public function printErrors(array $errors, $maxCount = 8)
    {
        if (empty($errors)) {
            return;
        }
        $errorsSliced = array_slice($errors, 0, $maxCount);
        if (count($errors) > count($errorsSliced)) {
            $errorsSliced[] = '...';
        }
        if ($this->_isShellContext()) {
            foreach ($errorsSliced as $e) {
                echo $e;
            }
        } else {
            if (Mage::app()->getStore()->isAdmin()) {
                $session = Mage::getSingleton('adminhtml/session');
            } else {
                $session = Mage::getSingleton('core/session');
            }
            foreach ($errorsSliced as $e) {
                $session->addError($e);
            }
        }
    }

    protected function _isShellContext()
    {
        return !isset($_SERVER['REQUEST_METHOD']);
    }

    /**
     * @param     $name
     * @param int $storeId
     *
     * @return Mage_Checkout_Model_Resource_Agreement_Collection
     */
    public function getAgreements($name, $storeId = 0)
    {
        /* @var $collection Mage_Checkout_Model_Resource_Agreement_Collection */
        $collection = Mage::getModel('checkout/agreement')->getCollection();
        $collection->addStoreFilter($storeId)
            ->addFieldToFilter('name', $name);
        return $collection;
    }

    /**
     * Creates or resets client object and returns it.
     *
     * @param string $url
     *
     * @return Zend_Http_Client
     */
    protected function _getClient($url)
    {
        if (is_null($this->_client)) {
            $this->_client = new Zend_Http_Client($url, array(
                'keepalive' => true,
            ));
        } else {
            $this->_client->resetParameters(true);
            $this->_client->setUri($url);
        }
        return $this->_client;
    }
}
