<?php

class Janolaw_Agb_Model_Setup extends Mage_Eav_Model_Entity_Setup
{

    /**
     * Create a cms page with given data.
     * Additionally, this method defines an layout update which sets robot tag's data to
     * NOINDEX,NOFOLLOW,NOARCHIVE
     *
     * @param array $pageData
     */
    public function createCmsPage($pageData)
    {
        if (is_array($pageData)) {
            foreach ($pageData as $key => $value) {
                $data[$key] = $value;
            }
            $data['stores'] = array('0');
            $data['is_active'] = '1';
            $data['layout_update_xml']
                = '<reference name="head"><action method="setRobots"><value>NOINDEX,NOFOLLOW,NOARCHIVE</value></action></reference>';
        } else {
            return;
        }

        $model = Mage::getModel('cms/page');
        $page = $model->load($pageData['identifier']);

        if (!$page->getId()) {
            $model->setData($data)->save();
        } else {
            $data['page_id'] = $page->getId();
            $model->setData($data)->save();
        }
    }

    /**
     * @param array $blockData
     */
    public function createCmsBlock($blockData)
    {
        $blockData['stores'] = array('0');
        $blockData['is_active'] = '1';

        /* $model Mage_Cms_Model_Block */
        $blockModel = Mage::getModel('cms/block');
        $blockModel->load($blockData['identifier']);

        if (!$blockModel->getId()) {
            $blockModel->setData($blockData)->save();
        } else {
//            $data['block_id'] = $block->getId();
            $blockModel->delete();
            $blockModel = Mage::getModel('cms/block');
            $blockModel->load($blockData['identifier']);
            $blockModel->setData($blockData)->save();
        }
    }
}