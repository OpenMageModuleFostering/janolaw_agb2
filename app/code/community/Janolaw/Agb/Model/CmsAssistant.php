<?php


class Janolaw_Agb_Model_CmsAssistant extends Mage_Catalog_Model_Abstract
{

    const TYPE_REVOCATION = 'revocation';
    const TYPE_TAC = 'tac';
    const TYPE_PRIVACY = 'privacy';
    const TYPE_IMPRINT = 'imprint';

    /** @var array blocks to search for... */
    protected static $_blocks = array(
        self::TYPE_REVOCATION => array(
            'revocation', // mage setup
            'gs_revocation', // german setup
            'mrg_revocation', // MRG (Symmetrics_Agreement)
            'sym_widerruf', // old MRG
        ),
        self::TYPE_TAC => array(
            'business_terms', // mage setup
            'gs_business_terms', // german setup
            'mrg_business_terms', // MRG (Symmetrics_Agreement)
            'sym_agb', // old MRG
        ),
        self::TYPE_PRIVACY => array(
            'datenschutz_block', // janolaw old setup script
        ),
        self::TYPE_IMPRINT => array(
            'Impressum_block' // janolaq old setup script
        )
    );

    public function getBlockCandidates()
    {
        $existingBlocksByType = array();
        foreach (self::$_blocks as $type => $candidates) {
            $existing = array();
            /* @var $blocks Mage_Cms_Model_Resource_Block_Collection */
            $blocks = Mage::getModel('cms/block')->getCollection();
            $blocks->addFieldToFilter('identifier', array('in' => $candidates));

            foreach ($blocks->getItems() as $block) {
                /* @var $block Mage_Cms_Model_Block */
                $identifier = $block->getIdentifier();
                if (isset($existing[$identifier])) {
                    if ($block->getIsActive()) {
                        $existing[$identifier] = true;
                    } // else do not overwrite existing value
                } else {
                    // add this identifier to existing blocks and set its active status as value
                    $existing[$identifier] = $block->getIsActive();
                }
            }
            $existingBlocksByType[$type] = $existing;
        }
        return $existingBlocksByType;
    }

    public function getCmsDirectiveSnippet($blockIdentifier)
    {
        return '{{block type="cms/block" block_id="' . Mage::helper('core')->escapeHtml($blockIdentifier) . '"}}';
    }

    /**
     * Stores given block identifer for type (only default scope...)
     *
     * @param string $type (revocation|tac|privacy|imprint, see TYPE_x constants of this class)
     * @param string $blockIdentifier
     *
     * @throws InvalidArgumentException
     */
    public function setBlockConfig($type, $blockIdentifier)
    {
        $blockIdentifier = trim($blockIdentifier);
        $this->_validateBlockIdentifier($blockIdentifier);
        $config = Mage::getConfig();
        switch ($type) {
            case self::TYPE_REVOCATION:
                $path = Janolaw_Agb_Model_Downloader::XML_PATH_REVOCATION_ID;
                break;
            case self::TYPE_TAC:
                $path = Janolaw_Agb_Model_Downloader::XML_PATH_TAC_ID;
                break;
            case self::TYPE_PRIVACY:
                $path = Janolaw_Agb_Model_Downloader::XML_PATH_PRIVACY_ID;
                break;
            case self::TYPE_IMPRINT:
                $path = Janolaw_Agb_Model_Downloader::XML_PATH_IMPRINT_ID;
                break;
            default:
                throw new InvalidArgumentException('invalid type ' . $type);
        }
        $config->saveConfig($path, $blockIdentifier); // save on default scope
    }

    protected function _validateBlockIdentifier($blockIdentifier)
    {
        if (!preg_match('/^[A-Z][A-Z0-9_-]*$/i', $blockIdentifier)) {
            // translate message as we will use it in session errors...
            $msg = Mage::helper('agbdownloader')->__(
                'Given block identifer (%s) has the wrong format.', $blockIdentifier
            );
            throw new Exception($msg);
        }
        if (strlen($blockIdentifier) > 255) {
            $msg = Mage::helper('agbdownloader')->__(
                'Given block identifier is too large (only 255 characters allowed at maximum)'
            );
            throw new Exception($msg);
        }
    }
}
