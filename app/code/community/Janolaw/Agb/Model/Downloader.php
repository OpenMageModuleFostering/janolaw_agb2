<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */


class Janolaw_Agb_Model_Downloader
{

    const XML_PATH_ENABLED = 'agbdownload/janoloaw_agb_user/active';

    // api settings
    const XML_PATH_SHOP_ID = 'agbdownload/janoloaw_agb_user/shopid';
    const XML_PATH_USER_ID = 'agbdownload/janoloaw_agb_user/userid';
    const XML_PATH_API_BASE_URL = 'agbdownload/janoloaw_agb_user/api_base_url';

    // cms block/page definitions
    const XML_PATH_TAC_ID = 'agbdownload/janoloaw_agb_cms/agbid';

    const XML_PATH_IMPRINT_ID = 'agbdownload/janoloaw_agb_cms/impressumid';

    const XML_PATH_REVOCATION_ID = 'agbdownload/janoloaw_agb_cms/wiederrufid';

    const XML_PATH_PRIVACY_ID = 'agbdownload/janoloaw_agb_cms/datenschutzid';

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
                'pdf_filename' => null, // currently we don't support pdf's, so we just set it to null
            ),
            Janolaw_Agb_Model_CmsAssistant::TYPE_IMPRINT => array(
                'filename' => 'legaldetails_include.html',
                'config_id' => self::XML_PATH_IMPRINT_ID,
                'pdf_filename' => null,
            ),
            Janolaw_Agb_Model_CmsAssistant::TYPE_REVOCATION => array(
                'filename' => 'revocation_include.html',
                'config_id' => self::XML_PATH_REVOCATION_ID,
                'pdf_filename' => null,
            ),
            Janolaw_Agb_Model_CmsAssistant::TYPE_PRIVACY => array(
                'filename' => 'datasecurity_include.html',
                'config_id' => self::XML_PATH_PRIVACY_ID,
                'pdf_filename' => null,
            )
        );
    }

    /**
     * "Downloads" the content from remote server, and updates cms blocks/pages accordingly.
     */
    public function download()
    {
        $stores = Mage::app()->getStores();

        // first fetch content for the default store
        // note: this just creates blocks for all texts in default scope to
        // prevent errors due to missing blocks. We therefor do not check if
        // synchronization is enabled in global (default) scope in the configuration.
        // (using the block is another -- independant -- topic)
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
        return $this->_pdfBasePath . DIRECTORY_SEPARATOR . $storeCode . DIRECTORY_SEPARATOR . $pdfName;
    }

    /**
     * @param $storeId
     * @param $type
     *
     * @return null|string Returns null if pdf was not found
     */
    public function getPdfPathByType($storeId, $type)
    {
        if (!in_array($type, $this->_contentItems)) {
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
     *
     * @return array
     */
    protected function _updateAllContent($storeId)
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
                if (isset($itemDefinition['pdf_filename'])) {
                    $pdfFile = $this->_downloadPdf($storeId, $itemDefinition['pdf_filename']);
                    if ($pdfFile) {
                        $pathRelToMedia = $this->_getStoreCodeById($storeId) . '/' . $itemDefinition['pdf_filename'];
                        $pdfIncludeMarkup = '<p class="janolaw_pdf"><a href="{{media url=\'' . $pathRelToMedia . '\'}}">'
                            . $pdfFile . '</a></p>';
                        $content .= "\n" . $pdfIncludeMarkup;
                    }
                }
                $this->_getResourceModel()->updateCmsContent($cmsId, $content);
            } catch (Exception $e) {
                $msg = 'Could not update data from ' . Mage::getStoreConfig(self::XML_PATH_API_BASE_URL, $storeId) . '.';
                $msg .= ' Store id = ' . $storeId . '; Item definition = ' . print_r($itemDefinition, true);
                $wrapperException = new Exception($msg, 0, $e);
                Mage::logException($wrapperException);
                $errors[] = $e->getMessage();
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
     *
     * @return null|string Returns the filename where the pdf was saved to or null if it is not available
     * @throws Exception
     * @throws Janolaw_Agb_Helper_HttpStatusNotSuccessfulException
     */
    protected function _downloadPdf($storeId, $pdfFilename) {
        if (empty($pdfFilename)) {
            return null;
        }
        try {
            $url = $this->_buildApiUrl($pdfFilename, $storeId);
            $content = $this->_getHelper()->getRemoteContent($url);
            $pdf = Zend_Pdf::parse($content);
            $filename = $this->getPdfPath($storeId, $pdfFilename);
            $pdf->save($filename);
            return $filename;
        } catch (Janolaw_Agb_Model_MissingConfigException $e) {
            return null; // ignore it, as we have logged that before...
        } catch (Janolaw_Agb_Helper_HttpStatusNotSuccessfulException $e) {
            if ($e->response->getStatus() == 404) {
                // document not available, that's ok.
                return null;
            } // other return codes are not expected. Redirects are handled automatically by Zend_Http_Client.
            throw $e;
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

        if (!$baseUrl || !$userId || !$shopId) {
            throw new Janolaw_Agb_Model_MissingConfigException(
                'Unsufficient configuration data. Add base url, user id and shop id configuration.'
            );
        }

        return rtrim($baseUrl, '/\\') . '/' . urlencode($userId) . '/' . urlencode($shopId) . '/'
            . urlencode($this->_getLanguageUrlPart($storeId)) . '/' . $fileName;
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
        return 'de';
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
}
