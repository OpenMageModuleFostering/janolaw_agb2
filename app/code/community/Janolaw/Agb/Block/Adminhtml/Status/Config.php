<?php

class Janolaw_Agb_Block_Adminhtml_Status_Config extends Mage_Adminhtml_Block_Abstract
{

    public function getConfigData()
    {
        $blockConfigData = $this->_getBlockConfigData();
        return $blockConfigData;
    }

    protected function _getBlockConfigData()
    {
        $blockIds = array(
            Janolaw_Agb_Model_CmsAssistant::TYPE_REVOCATION => Janolaw_Agb_Model_Downloader::XML_PATH_REVOCATION_ID,
            Janolaw_Agb_Model_CmsAssistant::TYPE_TAC        => Janolaw_Agb_Model_Downloader::XML_PATH_TAC_ID,
            Janolaw_Agb_Model_CmsAssistant::TYPE_PRIVACY    => Janolaw_Agb_Model_Downloader::XML_PATH_PRIVACY_ID,
            Janolaw_Agb_Model_CmsAssistant::TYPE_IMPRINT    => Janolaw_Agb_Model_Downloader::XML_PATH_IMPRINT_ID,
        );
        $data = array();
        foreach ($blockIds as $type => $configPath) {
            $data[$type] = Mage::getStoreConfig($configPath, 0);
        }
        return $data;
    }

    /**
     * @return array
     */
    public function getNotConfiguredValues()
    {
        $missing = array();
        foreach ($this->_getBlockConfigData() as $type => $value) {
            if (empty($value)) {
                if (!isset($missing['block_config'])) {
                    $missing['block_config'] = array();
                }
                $missing['block_config'][] = $this->_getTypeTranslated($type);
            }
        }

        // check shop id / user id / base_url in default scope
        $shopId = Mage::getStoreConfig(\Janolaw_Agb_Model_Downloader::XML_PATH_SHOP_ID, 0);
        $userId = Mage::getStoreConfig(\Janolaw_Agb_Model_Downloader::XML_PATH_USER_ID, 0);
        $baseUrl = Mage::getStoreConfig(\Janolaw_Agb_Model_Downloader::XML_PATH_API_BASE_URL, 0);

        if (empty($shopId)) {
            $missing['shop_id'] = 'Shop Id';
        }
        if (empty($userId)) {
            $missing['user_id'] = 'User Id';
        }
        if (empty($baseUrl)) {
            $missing['base_url'] = 'Base Url';
        }
        return $missing;
    }

    /**
     * @return Mage_Core_Model_Flag
     */
    public function getLastRunFlagData()
    {
        /* @var $flag Mage_Core_Model_Flag */
        $flag = Mage::getModel(
            'core/flag',
            array('flag_code' => Janolaw_Agb_Model_Downloader::FLAG_CODE)
        );
        $flag->loadSelf();
        return $flag;
    }

    /**
     * @param        $utcString
     * @param string $format
     *
     * @return string
     */
    public function convertUTCToLocaleTime($utcString, $format = 'Y-m-d H:i:s')
    {
        $strZone = Mage::getStoreConfig('general/locale/timezone');
        $localeZone = new DateTimeZone($strZone);
        $utcZone = new DateTimeZone('UTC');

        $dt = DateTime::createFromFormat($format, $utcString, $utcZone);
        $dt->setTimezone($localeZone);

        return $dt->format($format);
    }

    protected function _getTypeTranslated($type)
    {
        switch ($type) {
            case \Janolaw_Agb_Model_CmsAssistant::TYPE_REVOCATION:
                return $this->__('Revocation');
            case \Janolaw_Agb_Model_CmsAssistant::TYPE_TAC:
                return $this->__('Terms and conditions');
            case \Janolaw_Agb_Model_CmsAssistant::TYPE_PRIVACY:
                return $this->__('Privacy statement');
            case \Janolaw_Agb_Model_CmsAssistant::TYPE_IMPRINT:
                return $this->__('Imprint');
        }
        return '';
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        $c = Mage::getConfig()->getModuleConfig('Janolaw_Agb');
        return strval($c->version);
    }
} 