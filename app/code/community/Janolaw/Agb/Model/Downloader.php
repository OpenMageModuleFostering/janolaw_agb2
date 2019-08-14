<?php


class Janolaw_Agb_Model_Downloader
{

    const XML_PATH_ENABLED = 'agbdownload/janoloaw_agb_user/active';

    // api settings
    const XML_PATH_SHOP_ID = 'agbdownload/janoloaw_agb_user/shopid';
    const XML_PATH_USER_ID = 'agbdownload/janoloaw_agb_user/userid';
    const XML_PATH_API_BASE_URL = 'agbdownload/janoloaw_agb_user/api_base_url';
    const XML_PATH_LANGUAGE = 'agbdownload/janoloaw_agb_user/language';

    // cms block/page definitions
    const XML_PATH_TAC_ID = 'agbdownload/janoloaw_agb_cms/agbid';

    const XML_PATH_IMPRINT_ID = 'agbdownload/janoloaw_agb_cms/impressumid';

    const XML_PATH_REVOCATION_ID = 'agbdownload/janoloaw_agb_cms/wiederrufid';

    const XML_PATH_PRIVACY_ID = 'agbdownload/janoloaw_agb_cms/datenschutzid';

    const XML_PATH_WITHDRAWAL_ID = 'agbdownload/janoloaw_agb_cms/withdrawalid';

    const FLAG_CODE = 'janolaw_download_state';

    protected $_pdfBasePath;

    /**
     * @var Janolaw_Agb_Helper_Data
     */
    protected $_helper = null;

    /**
     * @var Janolaw_Agb_Model_Resource_Downloader
     */
    protected $_resource = null;

    /**
     * To prevent additional loading of the same texts for multiple stores...
     * @var array
     */
    protected $_urlCache = array();

    /**
     * Cache pdf check result
     *
     * @var bool|null
     */
    protected $_isPdfAvailable = null;

    /**
     * @var array
     */
    protected $_contentItems;

    public function __construct()
    {
        $this->_pdfBasePath = Mage::getBaseDir('media') . DIRECTORY_SEPARATOR . 'janolaw_pdf';
        $this->_contentItems = array(
            Janolaw_Agb_Model_CmsAssistant::TYPE_TAC => array(
                'filename' => 'terms_include.html',
                'config_id' => self::XML_PATH_TAC_ID,
            ),
            Janolaw_Agb_Model_CmsAssistant::TYPE_IMPRINT => array(
                'filename' => 'legaldetails_include.html',
                'config_id' => self::XML_PATH_IMPRINT_ID,
            ),
            Janolaw_Agb_Model_CmsAssistant::TYPE_REVOCATION => array(
                'filename' => 'revocation_include.html',
                'config_id' => self::XML_PATH_REVOCATION_ID,
            ),
            Janolaw_Agb_Model_CmsAssistant::TYPE_PRIVACY => array(
                'filename' => 'datasecurity_include.html',
                'config_id' => self::XML_PATH_PRIVACY_ID,
            ),
        );
        if ($this->checkPdfAvailable()) {
            $this->_contentItems[Janolaw_Agb_Model_CmsAssistant::TYPE_TAC]['pdf_filename']
                = 'terms.pdf';
            $this->_contentItems[Janolaw_Agb_Model_CmsAssistant::TYPE_TAC]['pdf_title']
                = array(
                'de' => 'AGB',
                'gb' => 'General Terms and Conditions',
                'fr' => 'Conditions Générales de Vente'
            );

            $this->_contentItems[Janolaw_Agb_Model_CmsAssistant::TYPE_IMPRINT]['pdf_filename']
                = 'legaldetails.pdf';
            $this->_contentItems[Janolaw_Agb_Model_CmsAssistant::TYPE_IMPRINT]['pdf_title']
                = array(
                'de' => 'Impressum',
                'gb' => 'Imprint',
                'fr' => 'Mentions légales'
            );

            $this->_contentItems[Janolaw_Agb_Model_CmsAssistant::TYPE_REVOCATION]['pdf_filename']
                = 'revocation.pdf';
            $this->_contentItems[Janolaw_Agb_Model_CmsAssistant::TYPE_REVOCATION]['pdf_title']
                = array(
                'de' => 'Widerrufsbelehrung',
                'gb' => 'Instructions on withdrawal',
                'fr' => 'Informations standardisées sur la rétractation'
            );

            $this->_contentItems[Janolaw_Agb_Model_CmsAssistant::TYPE_PRIVACY]['pdf_filename']
                = 'datasecurity.pdf';
            $this->_contentItems[Janolaw_Agb_Model_CmsAssistant::TYPE_PRIVACY]['pdf_title']
                = array(
                'de' => 'Datenschutzerklärung',
                'gb' => 'Data privacy policy',
                'fr' => 'Déclaration quant à la protection des données'
            );

            // withdrawal is only available since janolaw version 3 (where pdfs are available)
            $this->_contentItems[Janolaw_Agb_Model_CmsAssistant::TYPE_WITHDRAWAL] = array(
                'filename' => 'model-withdrawal-form_include.html',
                'config_id' => self::XML_PATH_WITHDRAWAL_ID,
                'pdf_filename' => 'model-withdrawal-form.pdf',
                'pdf_title' => array(
                    'de' => 'Muster-Widerrufsformular',
                    'gb' => 'Model withdrawal form',
                    'fr' => 'Modèle de formulaire de rétractation'
                ),
            );
        }
    }

    /**
     * "Downloads" the content from remote server, and updates cms blocks/pages accordingly.
     */
    public function download()
    {
        //Download default css
        $this->_downloadCss();

        $stores = Mage::app()->getStores();

        if (Mage::app()->isSingleStoreMode()) {
            // only update one store's blocks. The blocks may be assigned
            // to either the default store (id 0) or the single store.
            // Note that if you edit a block of default store in single store mode,
            // magento will change its store id to the single store's id
            $singleStore = reset($stores);
            $storeId = $singleStore->getId();
            if (Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $storeId)) {
                $errors = $this->_updateAllContent($storeId, true);
            } else {
                $errors = array();
            }
        } else {
            // first fetch content for the default store
            // note: this just creates blocks for all texts in default scope to
            // prevent errors due to missing blocks. We therefor do not check if
            // synchronization is enabled in global (default) scope in the configuration.
            // (using the block is another -- independent -- topic)
            $errors = $this->_updateAllContent(0);
            /* @var $store Mage_Core_Model_Store */;
            foreach ($stores as $store) {
                $storeId = $store->getId();
                if (!Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $storeId)) {
                    continue;
                }
                $e = $this->_updateAllContent($storeId);
                $errors = array_merge($errors, $e);
            }
        }

        $this->_getHelper()->printErrors($errors);
        if ($errors) {
            Mage::log($errors, Zend_Log::ERR);
        }
        Mage::log('Done downloading (synchronizing) of contents from janolaw.');
        $this->_storeLastSyncState($errors);
    }

    public function getPdfBasePath()
    {
        return $this->_pdfBasePath;
    }

    /**
     * Returns the path to a pdf document identified by store and pdf basename.
     * Note that this function does not check if the file exists!
     *
     * @param int $storeId
     * @param string $pdfName
     *
     * @return string Absolute path to the pdf file
     */
    public function getPdfPath($storeId, $pdfName)
    {
        $storeCode = $store = Mage::app()->getStore($storeId)->getCode();
        $dir = $this->_pdfBasePath . DIRECTORY_SEPARATOR . $storeCode;
        if(!file_exists($dir)){
            mkdir($dir, 0777, true);
        }
        return $dir . DIRECTORY_SEPARATOR . $pdfName;
    }

    /**
     * @param $storeId
     * @param $type
     *
     * @return null|string Returns null if pdf was not found
     */
    public function getPdfPathByType($storeId, $type)
    {
        if (!isset($this->_contentItems[$type]['pdf_filename'])) {
            return null;
        }
        $pdfName = $this->_contentItems[$type]['pdf_filename'];
        if (!$pdfName) {
            return null;
        }
        $pdfPath = $this->getPdfPath($storeId, $pdfName);
        if ($pdfPath && is_readable($pdfPath)) {
            return $pdfPath;
        }
        return null;
    }

    /**
     * @param $type
     * @param $storeId
     * @return null|string Returns null if pdf was not found
     */
    public function getPdfTitleByType($type, $storeId)
    {
        $pdfTitle = null;
        if (!isset($this->_contentItems[$type]['pdf_title'])) {
            return null;
        }
        $pdfTitle = $this->_contentItems[$type]['pdf_title'][$this->_getLanguageUrlPart($storeId)];
        if (!$pdfTitle) {
            return null;
        }
        return $pdfTitle;
    }

    /**
     * @return bool
     */
    public function checkPdfAvailable()
    {
        if (!isset($this->_isPdfAvailable)) {
            // check imprint pdf for default store
            $store = Mage::app()->getDefaultStoreView()->getId();
            $filename = 'legaldetails.pdf'; // try legaldetails to test the service version
            try {
                $this->_isPdfAvailable = (null !== $this->_downloadPdf($store, $filename));
            } catch (Janolaw_Agb_Helper_HttpStatusNotSuccessfulException $e) {
                $this->_isPdfAvailable = false;
            } catch (Exception $e) {
                // any other exception, do also return false to not break anything
                $this->_isPdfAvailable = false;
            }
        }
        return $this->_isPdfAvailable;
    }

    protected function _storeLastSyncState(array $errors)
    {
        /* @var $flagModel Mage_Core_Model_Flag */
        $flagModel = Mage::getModel(
            'core/flag',
            array('flag_code' => Janolaw_Agb_Model_Downloader::FLAG_CODE)
        );
        $flagModel->loadSelf();

        $success = empty($errors);
        $flagModel->setState(intval($success));
        $flagModel->setFlagData($errors);
        $flagModel->save();
    }

    /**
     * @param int|string $storeId
     * @param bool       $isSingleStore If true, the default store is also
     *                                  searched for blocks to update
     *
     * @return array
     */
    protected function _updateAllContent($storeId, $isSingleStore = false)
    {
        $errors = array();
        foreach ($this->_contentItems as $itemDefinition) {
            // config id are independant from store... (only global / default scope)
            $cmsIdent = trim(Mage::getStoreConfig($itemDefinition['config_id'], 0));

            if (!$cmsIdent) {
                // if no value for the block is configured, we do not create or update any blocks
                continue;
            }

            try {
                $cmsId = $this->_getResourceModel()->getCmsId($cmsIdent, $storeId);
                if ($cmsId === false && $isSingleStore) {
                    // try default store
                    $cmsId = $this->_getResourceModel()->getCmsId($cmsIdent, 0);
                }
                if ($cmsId === false) {
                    // no block exists for the given identifier and store. Create one.
                    $cmsId = $this->_getResourceModel()->createCmsBlock($cmsIdent, $storeId);
                }
                try {
                    $url = $this->_buildApiUrl($itemDefinition['filename'], $storeId);
                    $content = $this->_getRemoteContent($url);
                } catch (Janolaw_Agb_Model_MissingConfigException $missingDataException) {
                    if ($storeId == 0) {
                        // for admin (default) store, ignore missing data and instead create a block with
                        // no content (to prevent missing block exceptions)
                        $content = '';
                    } else {
                        throw $missingDataException;
                    }
                }

                // handle pdf file...
                if (isset($itemDefinition['pdf_title'][$this->_getLanguageUrlPart($storeId)])) {
                    try {
                        $this->_downloadPdf($storeId, $itemDefinition['pdf_filename']);
                    } catch (Janolaw_Agb_Helper_HttpStatusNotSuccessfulException $e) {
                        // this is ok..
                    }
                }
                $this->_getResourceModel()->updateCmsContent($cmsId, $content);
            } catch (Exception $e) {
                $msg = 'Could not update data from ' . Mage::getStoreConfig(self::XML_PATH_API_BASE_URL, $storeId) . '.';
                $msg .= ' Store id = ' . $storeId . '; Item definition = ' . print_r($itemDefinition, true);
                $wrapperException = new Exception($msg, 0, $e);
                Mage::logException($wrapperException);
                $errors[] = $e->getMessage() . '; file = "' . $itemDefinition['filename'];
            }
        }
        return $errors;
    }

    protected function _getStoreCodeById($storeId)
    {
        return Mage::app()->getStore($storeId)->getCode();
    }

    /**
     * @param $storeId
     * @param $pdfFilename
     * @param $pdfTitle
     *
     * @return null|string Returns the filename where the pdf was saved to or null if it is not available
     * @throws Exception
     * @throws Janolaw_Agb_Helper_HttpStatusNotSuccessfulException
     */
    protected function _downloadPdf($storeId, $pdfFilename, $pdfTitle=null) {
        if (empty($pdfFilename)) {
            return null;
        }
        try {
            $url = $this->_buildApiUrl($pdfFilename, $storeId);

            $content = $this->_getHelper()->getRemoteContent($url);
            $pdf = Zend_Pdf::parse($content);

            if(!$pdfTitle) $pdfTitle = $pdfFilename;
            $filename = $this->getPdfPath($storeId, $pdfTitle);
            $pdf->save($filename);
            return $filename;
        } catch (Janolaw_Agb_Model_MissingConfigException $e) {
            return null; // ignore it, as we have logged that before...
        } catch (Zend_Http_Client_Exception $e) {
            return null; // ignore it, as we have logged that before...
        } // don't catch other exceptions (e.g. Zend_Pdf_Exception may occur)
    }

    /**
     * Download CSS file ans save it in /skin/frontend/base/default/css/agbdownloader.css
     * @return null
     * @throws Exception
     * @throws Janolaw_Agb_Helper_HttpStatusNotSuccessfulException
     */
    protected function _downloadCss() {
        try {
            $url = 'http://www.janolaw.de/agb-service/shops/janolaw.css';

            $content = $this->_getHelper()->getRemoteContent($url);

            $file = 'agbdownloader.css';
            $cssDir  = Mage::getBaseDir('skin') . DS . 'frontend' . DS . 'base' . DS . 'default' . DS . 'css';
            $cssFile = $cssDir . DS . $file;

            if (!is_dir($cssDir)) {
                mkdir($cssDir, true);
                chmod($cssDir, 0755);
            }

            if (!file_exists($cssFile)) {
                file_put_contents($cssFile, $content);
                chmod($cssFile, 0644);
            }
        } catch (Janolaw_Agb_Model_MissingConfigException $e) {
            return null; // ignore it, as we have logged that before...
        } catch (Zend_Http_Client_Exception $e) {
            return null; // ignore it, as we have logged that before...
        } catch (Janolaw_Agb_Helper_HttpStatusNotSuccessfulException $e) {
            return null; // ignore it, as we have logged that before...
        } // don't catch other exceptions (e.g. Zend_Pdf_Exception may occur)
    }

    /**
     * @param string $url
     *
     * @return string The content
     * @throws Janolaw_Agb_Helper_HttpStatusNotSuccessfulException
     */
    protected function _getRemoteContent($url)
    {
        if (!isset($this->_urlCache[$url])) {
            $this->_urlCache[$url] = $this->_getHelper()->getRemoteContent($url);
        }
        return $this->_urlCache[$url];
    }

    /**
     * Build url from Magento config (base url, shop id and user id) and filename.
     *
     * @param string $fileName
     * @param int    $storeId
     *
     * @throws Janolaw_Agb_Model_MissingConfigException
     * @return string
     */
    protected function _buildApiUrl($fileName, $storeId)
    {
        $baseUrl = Mage::getStoreConfig(self::XML_PATH_API_BASE_URL, $storeId);
        $userId = Mage::getStoreConfig(self::XML_PATH_USER_ID, $storeId);
        $shopId = Mage::getStoreConfig(self::XML_PATH_SHOP_ID, $storeId);
        $shopId = trim($shopId);
        $userId = trim($userId);
        $baseUrl = trim($baseUrl);

        if (!$baseUrl || !$userId || !$shopId) {
            throw new Janolaw_Agb_Model_MissingConfigException(
                'Unsufficient configuration data. Add base url, user id and shop id configuration.'
            );
        }

        $apiUrl = rtrim($baseUrl, '/\\') . '/' . urlencode($userId) . '/' . urlencode($shopId) . '/'
            . urlencode($this->_getLanguageUrlPart($storeId)) . '/' . $fileName;

        return $apiUrl;
    }

    /**
     * Currently we use only 'de' url part.
     * Ideas:
     *  - provide store specific config field (very flexible, but more configuration)
     *  - use data from locale (may be problematic if url does not exist on janolaw for given store)
     *
     * @param string $storeId
     *
     * @return string
     */
    protected function _getLanguageUrlPart($storeId)
    {
        $lang = Mage::getStoreConfig(self::XML_PATH_LANGUAGE, $storeId);
        return $lang;
    }

    /**
     * @return Janolaw_Agb_Helper_Data
     */
    protected function _getHelper()
    {
        if (is_null($this->_helper)) {
            $this->_helper = Mage::helper('agbdownloader');
        }
        return $this->_helper;
    }

    /**
     * @return Janolaw_Agb_Model_Resource_Downloader
     */
    protected function _getResourceModel()
    {
        if (is_null($this->_resource)) {
            $this->_resource = Mage::getResourceSingleton('agbdownloader/downloader');
        }
        return $this->_resource;
    }

    /**
     * Get contentItems
     * @return array
     */
    public function getContentItems(){
        return $this->_contentItems;
    }
}
