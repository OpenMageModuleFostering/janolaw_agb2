<?php

class Janolaw_Agb_Block_Adminhtml_System_Config_Fieldset_Pdf
    extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{

    /**
     * @var Janolaw_Agb_Model_Downloader
     */
    protected $_downloader;

    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        if (!$this->_getDownloader()->checkPdfAvailable()) {
            $this->_clearChildren($element);
        }
        return parent::render($element);
    }

    /**
     * Removes all children of the given fieldset
     *
     * @param Varien_Data_Form_Element_Abstract $fieldset
     */
    protected function _clearChildren(Varien_Data_Form_Element_Abstract $fieldset)
    {
        if (!$fieldset instanceof Varien_Data_Form_Element_Fieldset) {
            return;
        }
        // note: we cannot rely on element ids when iterating and removing
        // elements in one single loop (because elements are renumbered
        // during remove method)
        while (($cnt = $fieldset->getElements()->count()) > 0) {
            $iterator = $fieldset->getElements()->getIterator();
            $iterator->rewind();
            $elem = $iterator->current();
            $fieldset->getElements()->remove($elem->getId());
            if ($cnt === $fieldset->getElements()->count()) {
                break; // just to make sure we never end up in an endless loop
            }
        }
    }

    /**
     * Return header comment part of html for fieldset
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getHeaderCommentHtml($element)
    {
        if ($this->_getDownloader()->checkPdfAvailable()) {
            return parent::_getHeaderCommentHtml($element);
        }
        $notAvailableNotice = $this->__(
            'Please check, if you have booked the multi-language option of the janolaw service.'
        );
        $additionalNote = <<<EOT
Please note:
If you don't want to regenerate the texts, you should insert the documents to the order confirmation email using the snippets. This should add the content of the documents to the email text.
You find an overview of the available snippets in the Janolaw AGB Hosting Setup, step 3
Important:
The terms and conditions, cancellation policy and the withdrawal form must be sent by e-mail or at the latest with the dispatch of goods.
EOT;

        $additionalNote = $this->__($additionalNote);
        $notAvailableNotice .= '<br /><br />'
            . str_replace("\n", '<br /><br />', $additionalNote);

        return '<div class="comment">' . $notAvailableNotice . '</div>';
    }

    protected function _getDownloader()
    {
        if (!isset($this->_downloader)) {
            $this->_downloader = Mage::getSingleton('agbdownloader/downloader');
        }
        return $this->_downloader;
    }
} 