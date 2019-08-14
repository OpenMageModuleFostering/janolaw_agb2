<?php
// This script will clean up old config values (becase config fields have changed in this version).
// Escpecially we need to make sure that any cms *page* id is deleted from database config
// as we only use cms *blocks* to store janolaw data (and use the same config fields for that).


Mage::app()->reinitStores();
$stores = Mage::app()->getStores(true);
$config = Mage::getConfig();

$configPaths = array(
    'revocation' => array(
        'type' => 'agbdownload/janoloaw_agb_cms/widerruf_page',
        'id' => 'agbdownload/janoloaw_agb_cms/wiederrufid',
    ),
    'tac' => array(
        'type' => 'agbdownload/janoloaw_agb_cms/agb_page',
        'id' => 'agbdownload/janoloaw_agb_cms/agbid',
    ),
    'privacy' => array(
        'type' => 'agbdownload/janoloaw_agb_cms/datenschutz_page',
        'id' => 'agbdownload/janoloaw_agb_cms/datenschutzid',
    ),
    'imprint' => array(
        'type' => 'agbdownload/janoloaw_agb_cms/impressum_page',
        'id' => 'agbdownload/janoloaw_agb_cms/impressumid',
    )
);

$websites = array();

/* @var $store Mage_Core_Model_Store */
foreach($stores as $store) {
    if (!in_array($store->getWebsiteId(), $websites)) {
        $websites[] = $store->getWebsiteId();
    }
    foreach ($configPaths as $textType => $paths) {
        $type = $store->getConfig($paths['type']);
        if ($type == 1) { // type page
            $store->setConfig($paths['id'], ''); // reset to empty string, as we change type from page to block
            $config->deleteConfig($paths['id']); // default scope
            $config->deleteConfig($paths['id'], 'stores', $store->getId());
            $config->deleteConfig($paths['id'], 'websites', $store->getWebsiteId());
        } // else: type was block... so we can use that further...
    }
}


foreach ($configPaths as $textType => $paths) {
    $config->deleteConfig($paths['type']); // default scope

    foreach ($websites as $websiteId) {
        $config->deleteConfig($paths['type'], 'websites', $websiteId);
    } // stores should not be defined, as it was not visible in store config before and it does no harm if
    // there were some configs left in the database...
}

// =========================
// create admin notification
// =========================

/* @var $notice Mage_AdminNotification_Model_Inbox */
$notice = Mage::getModel('adminnotification/inbox');
$notice->setSeverity(Mage_AdminNotification_Model_Inbox::SEVERITY_MAJOR);
$notice->setTitle('Bitte konfigurieren Sie Janolaw AGB unter System -> Janolaw AGB Hosting -> Setup');
$notice->setDescription('Bitte konfigurieren Sie Ihr System auf der Janolaw Setup Seite (Im Menü unter System -> Janolaw AGB Hosting -> Setup)');
$notice->save();