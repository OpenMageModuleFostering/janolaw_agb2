<?php


class Janolaw_Agb_Block_Adminhtml_Setup extends Mage_Adminhtml_Block_Abstract
{

    public function getInstalledMrgModules()
    {
        $modulesDir = Mage::getBaseDir('etc') . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR;

        $dirHandle = @opendir($modulesDir);
        if (false === $dirHandle) {
            Mage::log('Error opening modules dir at ' . $modulesDir, Zend_Log::ERR);
            return array();
        }
        $modules = array();
        while (false !== ($file = readdir($dirHandle))) {
            if (!preg_match('/^Symmetrics_.*\.xml$/', $file)) {
                continue;
            }
            $fullPath = rtrim($modulesDir, '/\\') . DIRECTORY_SEPARATOR . $file;
            try {
                $xml = new SimpleXMLElement($fullPath, 0, true);
                foreach ($xml->modules->children() as $m) {
                    $moduleName = $m->getName();
                    if (Mage::helper('core')->isModuleEnabled($moduleName)) {
                        $modules[] = $m->getName();
                    }
                }
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }

        return $modules;
    }

} 