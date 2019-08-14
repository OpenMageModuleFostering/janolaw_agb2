<?php

/**
 * Does not show the field if janolaw pdfs are not available
 */
class Janolaw_Agb_Block_Adminhtml_System_Config_Form_Field
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    /**
     * {@inheritDoc}
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        if ($this->_canShowField()) {
            return parent::render($element);
        }
        return '';
    }

    /**
     * Returns true if the field should be rendered, false otherwise
     *
     * @return bool
     */
    protected function _canShowField()
    {
        /* @var $downloader Janolaw_Agb_Model_Downloader */
        $downloader = Mage::getSingleton('agbdownloader/downloader');
        return $downloader->checkPdfAvailable();
    }
}