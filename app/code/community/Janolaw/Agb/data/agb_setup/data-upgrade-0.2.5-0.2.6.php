<?php

// this blocks should be allowed...
$blocks = array(
    'cms/block',
);

// check if block whitelist exists
if (false === Mage::getModel('admin/block')) {
    return; // cannot add block type to non-existant whitelist
}


// load collection of all previously defined blocks to prevent publicates (block_name is unique)

$existingBlocksByName = array();
foreach (Mage::getModel('admin/block')->getCollection() as $b) {
    /* @var $b Mage_Admin_Model_Block */
    $existingBlocksByName[$b->getData('block_name')] = $b;
}

foreach ($blocks as $allowedBlock) {
    if (\array_key_exists($allowedBlock, $existingBlocksByName)) {
        $block = $existingBlocksByName[$allowedBlock];
    } else {
        $block = Mage::getModel('admin/block');
        $block->setData('block_name', $allowedBlock);
    }
    $block->setData('is_allowed', 1);
    $block->save();
}
