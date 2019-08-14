<?php

/**
 * Class Janolaw_Agb_Model_Resource_Downloader
 *
 * Used for general database interaction
 */
class Janolaw_Agb_Model_Resource_Downloader extends Mage_Core_Model_Resource_Abstract
{

    /**
     * @var Varien_Db_Adapter_Interface
     */
    protected $_read = null;

    /**
     * @var Varien_Db_Adapter_Interface
     */
    protected $_write = null;

    protected $_coreResource = null;

    /**
     * Resource initialization
     */
    protected function _construct()
    {
        // nothing to do here...
    }

    /**
     * @param string $identifier
     * @param int    $storeId
     *
     * @return int|bool False if element was not found, block or page id if found (as integer value)
     */
    public function getCmsId($identifier, $storeId)
    {
        return $this->_getBlockCmsId($identifier, $storeId);
    }

    /**
     * Updates the block content.
     * Does also set the state of the block to 'active'
     *
     * @param int|string $blockId If string, it must be numeric
     * @param string     $content
     */
    public function updateCmsContent($blockId, $content)
    {
        $this->_getWriteAdapter()->update(
            $this->_getTableName('cms/block'),
            array('content' => $content, 'is_active' => 1),
            array('block_id = ?' => $blockId)
        );
    }

    /**
     * @param $blockIdentifier
     * @param $storeId
     *
     * @throws Exception
     * @return int the id of the newly created block
     */
    public function createCmsBlock($blockIdentifier, $storeId)
    {
        $modelAlias = 'cms/block';

        /* @var $model Mage_Cms_Model_Block */
        $model = Mage::getModel($modelAlias);
        $model->setIdentifier($blockIdentifier);
        $model->setContent('');
        $model->setTitle(str_replace('_', ' ', $blockIdentifier));
        $model->setIsActive(1);
        $model->setStores(array($storeId));
        $model->save();

        if (!$model->getId()) {
            throw new Exception('Error on creation of cms block or page');
        }

        return (int) $model->getId();
    }

    /**
     * @param string     $identifier block code
     * @param int|string $storeId    (numerical string if it's a string)
     *
     * @return bool|int False if no matching block was found, integer if found
     */
    protected function _getBlockCmsId($identifier, $storeId)
    {
        $blockTable = $this->_getTableName('cms/block');
        $blockStores = $this->_getTableName('cms/block_store');

        $select = $this->_getReadAdapter()->select();
        $select->from(array('main_table' => $blockTable), 'block_id')
            ->joinInner(array('stores' => $blockStores), 'stores.block_id = main_table.block_id', '')
            ->where('stores.store_id = ?', $storeId)
            ->where('main_table.identifier = ?', $identifier);

        $result = $this->_getReadAdapter()->fetchOne($select);
        if ($result && is_numeric($result)) {
            return intval($result);
        }
        return false;
    }

    /**
     * @return Varien_Db_Adapter_Interface
     */
    protected function _getReadAdapter()
    {
        if (is_null($this->_read)) {
            $this->_read = $this->_getCoreResource()->getConnection(Mage_Core_Model_Resource::DEFAULT_READ_RESOURCE);
        }
        return $this->_read;
    }

    /**
     * @return Varien_Db_Adapter_Interface
     */
    protected function _getWriteAdapter()
    {
        if (is_null($this->_write)) {
            $this->_write = $this->_getCoreResource()->getConnection(Mage_Core_Model_Resource::DEFAULT_WRITE_RESOURCE);
        }
        return $this->_write;
    }

    /**
     * Wrapper method
     * @see \Mage_Core_Model_Resource::getTableName
     *
     * @param string $mageTableAlias
     *
     * @return string
     */
    protected function _getTableName($mageTableAlias)
    {
        return $this->_getCoreResource()->getTableName($mageTableAlias);
    }

    /**
     * @return Mage_Core_Model_Resource
     */
    protected function _getCoreResource()
    {
        if (is_null($this->_coreResource)) {
            $this->_coreResource = Mage::getSingleton('core/resource');
        }
        return $this->_coreResource;
    }
}