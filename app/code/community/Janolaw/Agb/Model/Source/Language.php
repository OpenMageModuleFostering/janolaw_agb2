<?php

class Janolaw_Agb_Model_Source_Language
{

    /**
     * Config options for available janolaw languages. This depends
     * on the janolaw service version.
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = array();
        $options[] = array(
            'value' => 'de',
            'label' => Mage::helper('agbdownloader')->__('German')
        );

        /* @var $downloader Janolaw_Agb_Model_Downloader */
        $downloader = Mage::getSingleton('agbdownloader/downloader');
        if ($downloader->checkPdfAvailable()) {
            // we're in version 3 (or maybe higher) of the janolaw service, where
            // additional languages are available
            $options[] = array(
                'value' => 'gb',
                'label' => Mage::helper('agbdownloader')->__('English')
            );
            $options[] = array(
                'value' => 'fr',
                'label' => Mage::helper('agbdownloader')->__('French')
            );
        }
        return $options;
    }
}