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

    /**
     * Add attachment to mailer
     * @param Zend_Mail $mailer
     * @param $pdfTitle
     * @param $pdfPath
     * @return Zend_Mail
     */
    public function addAttachment(Zend_Mail $mailer, $pdfTitle, $pdfPath){
        if ($pdfPath) {
            try {
                $pdf = Zend_Pdf::load($pdfPath);
                $mimePart = new Janolaw_Agb_Model_Email_Attachment_Pdf(
                    $pdf->render(),
                    $pdfTitle
                );
                $mailer->addAttachment($mimePart);
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }

        return $mailer;
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

        //Set Zend_Http_Client_Adapter_Curl Adapter. With default Zend_Http_Client_Adapter_Socket adapter pdf does not work
        $adapter = new Zend_Http_Client_Adapter_Curl();
        $this->_client->setAdapter($adapter);

        return $this->_client;
    }

    /**
     * Adds the block type 'cms/block' to the whitelist, so
     * processed templates may include janolaw texts which
     * are stored as cms blocks
     *
     * @throws Janolaw_Agb_Helper_NoBlockWhitelistException
     */
    public function allowCmsBlockIncludes()
    {
        $allowedBlock = 'cms/block';

        $block = Mage::getModel('admin/block');
        if (false === $block) {
            throw new Janolaw_Agb_Helper_NoBlockWhitelistException('Block whitelist does not exist');
        }

        // load collection of all previously defined blocks to prevent publicates (block_name is unique)
        $existingBlocksByName = array();
        foreach ($block->getCollection() as $b) {
            /* @var $b Mage_Admin_Model_Block */
            $existingBlocksByName[$b->getData('block_name')] = $b;
        }

        if (array_key_exists($allowedBlock, $existingBlocksByName)) {
            $block = $existingBlocksByName[$allowedBlock];
        } else {
            $block = Mage::getModel('admin/block');
            $block->setData('block_name', $allowedBlock);
        }
        $block->setData('is_allowed', 1);
        $block->save();
    }
}
