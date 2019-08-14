<?php


class Janolaw_Agb_Model_Observer extends Mage_Core_Model_Abstract
{

    const XML_PATH_ATTACHMENT_TAC = 'sales_email/order/include_tac_pdf';

    const XML_PATH_ATTACHMENT_REVOCATION = 'sales_email/order/include_revocation_pdf';

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
    public function addAttachmentToNewOrderEmail(Varien_Event_Observer $observer)
    {
        $templateId = $observer->getData('template_id');
        $storeId = $observer->getData('store_id');

        $newOrderTemplates = array(
            Mage::getStoreConfig(\Mage_Sales_Model_Order::XML_PATH_EMAIL_GUEST_TEMPLATE, $storeId),
            Mage::getStoreConfig(\Mage_Sales_Model_Order::XML_PATH_EMAIL_TEMPLATE, $storeId),
        );
        if (in_array($templateId, $newOrderTemplates)) {
            $templateModel = $observer->getData('template_model');
            $this->_addAttachment($templateModel, $storeId, self::XML_PATH_ATTACHMENT_TAC);
            $this->_addAttachment($templateModel, $storeId, self::XML_PATH_ATTACHMENT_REVOCATION);
        }
    }

    protected function _addAttachment(Mage_Core_Model_Email_Template $template, $storeId, $includeConfigPath)
    {
        switch ($includeConfigPath) {
            case self::XML_PATH_ATTACHMENT_TAC:
                $type = Janolaw_Agb_Model_CmsAssistant::TYPE_TAC;
                $attachmentFilename = $this->_getHelper()->__('Terms-and-conditions') . '.pdf';
                break;
            case self::XML_PATH_ATTACHMENT_REVOCATION:
                $type = Janolaw_Agb_Model_CmsAssistant::TYPE_REVOCATION;
                $attachmentFilename = $this->_getHelper()->__('Revocation-policy') . '.pdf';
                break;
            default:
                return;
        }
        if (Mage::getStoreConfigFlag($includeConfigPath, $storeId)) {
            $pdfPath = $this->_getDownloader()->getPdfPathByType($storeId, $type);
            if ($pdfPath) {
                try {
                    $pdf = Zend_Pdf::load($pdfPath);
                    $template->getMail()->createAttachment(
                        $pdf->render(),
                        Zend_Mime::TYPE_OCTETSTREAM,
                        Zend_Mime::DISPOSITION_ATTACHMENT,
                        Zend_Mime::ENCODING_BASE64,
                        $attachmentFilename
                    );
                } catch (Exception $e) {
                    Mage::logException($e);
                }
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

    protected function _getHelper()
    {
        return Mage::helper('agbdownloader');
    }
} 