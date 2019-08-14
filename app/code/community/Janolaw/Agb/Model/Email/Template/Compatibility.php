<?php
if (Mage::helper('core')->isModuleEnabled('Aschroder_SMTPPro')
    && Mage::helper('smtppro')->isEnabled()
    && version_compare(
        (string)Mage::getConfig()->getNode()->modules->Aschroder_SMTPPro->version,
        '2.0.6', '>'
    )
) {
    class Janolaw_Agb_Model_Email_Template_Compatibility
        extends Aschroder_SMTPPro_Model_Email_Template
    {

    }
} else {
    class Janolaw_Agb_Model_Email_Template_Compatibility
        extends Mage_Core_Model_Email_Template
    {

    }
}