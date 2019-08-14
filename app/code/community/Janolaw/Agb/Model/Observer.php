<?php


class Janolaw_Agb_Model_Observer extends Mage_Core_Model_Abstract
{
    /**
     * Array with allowed email templates where pdf documents would be attached
     * @var array
     */
    protected $_allowedEmailTemplateWithAttachment = array(
        \Mage_Sales_Model_Order::XML_PATH_EMAIL_GUEST_TEMPLATE,
        \Mage_Sales_Model_Order::XML_PATH_EMAIL_TEMPLATE
    );

    /**
     * Array with allowed queue types where pdf documents would be attached
     * \Mage_Sales_Model_Order::EMAIL_EVENT_TYPE
     * @var array
     */
    protected $_allowedEmailQueueWithAttachment = array(
        'new_order'
    );

    /**
     * Janolaw_Agb_Model_CmsAssistant::ITEM => system config path to enabled selectbox
     * @var array
     */
    protected $_attachedPdfTypes = array(
        \Janolaw_Agb_Model_CmsAssistant::TYPE_TAC => 'agbdownload/janoloaw_agb_pdf/agb',
        \Janolaw_Agb_Model_CmsAssistant::TYPE_REVOCATION => 'agbdownload/janoloaw_agb_pdf/wiederruf',
        \Janolaw_Agb_Model_CmsAssistant::TYPE_WITHDRAWAL => 'agbdownload/janoloaw_agb_pdf/withdrawal'
    );

    protected $_downloader;

    /**
     * (From FireGento_MageSetup...)
     * Filters all agreements
     *
     * Filters all agreements against the Magento template filter. This enables the Magento
     * administrator define a cms static block as the content of the checkout agreements..
     *
     * Event: <core_block_abstract_to_html_before>
     *
     * @param  Varien_Event_Observer $observer Observer
     */
    public function filterAgreements(Varien_Event_Observer $observer)
    {
        $helper = Mage::helper('core');
        if ($helper->isModuleEnabled('FireGento_GermanSetup') || $helper->isModuleEnabled('FireGento_MageSetup')) {
            return;
        }
        $block = $observer->getEvent()->getBlock();
        if ($block->getType() == 'checkout/agreements') {
            if ($agreements = $block->getAgreements()) {
                $collection = new Varien_Data_Collection();
                foreach ($agreements as $agreement) {
                    $agreement->setData('content', $this->_filterString($agreement->getData('content')));
                    $agreement->setData('checkbox_text', $this->_filterString($agreement->getData('checkbox_text')));
                    $collection->addItem($agreement);
                }
                $observer->getEvent()->getBlock()->setAgreements($collection);
            }
        }
    }

    /**
     * (From FireGento_MageSetup...)
     * Calls the Magento template filter to transform {{block type="cms/block" block_id="xyz"}}
     * into the specific html code
     *
     * @param  string $string Agreement to filter
     * @return string Processed String
     */
    protected function _filterString($string)
    {
        $processor = Mage::getModel('cms/template_filter');
        $string = $processor->filter($string);

        return $string;
    }

    /**
     * event: janolaw_send_transactional_before
     * @see \Janolaw_Agb_Model_Email_Template::sendTransactional
     *
     * @param Varien_Event_Observer $observer
     */
    public function addAttachmentTransactionEmail(Varien_Event_Observer $observer)
    {
        $templateId = $observer->getData('template_id');
        $storeId = $observer->getData('store_id');

        if (in_array($templateId, $this->getAllowedEmailTemplateWithAttachment($storeId))) {
            $templateModel = $observer->getData('template_model');
            foreach($this->getAttachedPdfTypes($storeId) as $type => $configPath){
                $pdfTitle = $this->_getDownloader()->getPdfTitleByType($type, $storeId).'.pdf';
                $pdfPath = $this->_getDownloader()->getPdfPathByType($storeId, $type);

                $this->_getHelper()->addAttachment($templateModel->getMail(), $pdfTitle, $pdfPath);
            }
        }
    }

    /**
     * event: janolaw_send_queue_before
     * @see \Janolaw_Agb_Model_Email_Queue::send
     *
     * @param Varien_Event_Observer $observer
     */
    public function addAttachmentQueueEmail(Varien_Event_Observer $observer)
    {
        /** @var Janolaw_Agb_Model_Email_Queue $message */
        $message = $observer->getData('message');
        /** @var Zend_Mail $mailer */
        $mailer = $observer->getData('mailer');

        if($message
            && $message->getData('event_type')
            && $this->_validEmailQueueType($message->getData('event_type'))
        ){
            $storeId = $this->_storeIdByQueueObject(
                $message->getData('entity_type'),
                $message->getData('entity_id')
            );

            foreach($this->getAttachedPdfTypes($storeId) as $type => $configPath){
                $pdfTitle = $this->_getDownloader()->getPdfTitleByType($type, $storeId).'.pdf';
                $pdfPath = $this->_getDownloader()->getPdfPathByType($storeId, $type);

                $this->_getHelper()->addAttachment($mailer, $pdfTitle, $pdfPath);
            }
        }
    }

    /**
     * @return Janolaw_Agb_Model_Downloader
     */
    protected function _getDownloader()
    {
        if (!$this->_downloader) {
            $this->_downloader = Mage::getModel('agbdownloader/downloader');
        }
        return $this->_downloader;
    }

    /**
     * @return Janolaw_Agb_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('agbdownloader');
    }

    /**
     * get AllowedEmailTemplateWithAttachment
     * @param $storeId
     * @return array
     */
    public function getAllowedEmailTemplateWithAttachment($storeId){
        $items = array();
        foreach($this->_allowedEmailTemplateWithAttachment as $path){
            $templateId = Mage::getStoreConfig($path, $storeId);
            if($templateId) $items[] = $templateId;
        }
        return $items;
    }

    /**
     * get AllowedEmailQueueWithAttachment
     * @return array
     */
    public function getAllowedEmailQueueWithAttachment(){
        return $this->_allowedEmailQueueWithAttachment;
    }

    /**
     * get AttachedPdfTypes
     * @param $storeId
     * @return array
     */
    public function getAttachedPdfTypes($storeId){
        $items = array();
        foreach ($this->_attachedPdfTypes as $type => $configPathEnablePdfAttachment) {
            if(Mage::getStoreConfigFlag($configPathEnablePdfAttachment, $storeId)) $items[$type] = $configPathEnablePdfAttachment;
        }
        return $items;
    }

    /**
     * Check queue email type
     * @param $event_type
     * @return bool
     */
    private function _validEmailQueueType($event_type){
        if(in_array($event_type, $this->getAllowedEmailQueueWithAttachment())) return true;
        else return false;
    }

    /**
     * @param $entity_type
     * @param $entity_id
     * @return int
     */
    private function _storeIdByQueueObject($entity_type, $entity_id){
        $storeId = 0;

        try {
            switch($entity_type){
                case 'order':
                    $entity_id = intval($entity_id);
                    $storeId = Mage::getModel('sales/order')->load($entity_id)->getStoreId();
                    break;
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }

        return $storeId;
    }
} 