<?php

// get mage basedir

$mageBaseDir = null;

// check if magento base dir is defined as argument (magebase=/path/to/magento)...
// this is useful if this script is symlinked into shell directory (or is not in shell
// directory at all).
if (is_array($argv)) {
    foreach ($argv as $a) {
        $matches = array();
        if (preg_match('/^magebase=(.*)$/', $a, $matches)) {
            $mageBaseDir = realpath($matches[1]);
            if (!is_dir($mageBaseDir)) {
                throw new Exception($mageBaseDir . ' is not a directory');
            }
        }
    }
}

if (is_null($mageBaseDir)) {
    if (is_file('app' . DIRECTORY_SEPARATOR . 'Mage.php')) {
        $mageBaseDir = realpath(getcwd());
    } elseif (is_file('..' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Mage.php')) {
        $mageBaseDir = realpath(dirname(getcwd()));
    }
}

if (is_null($mageBaseDir)) {
    throw new Exception('Could not determine magento base directory');
}

$abstractFile = $mageBaseDir . DIRECTORY_SEPARATOR . 'shell' . DIRECTORY_SEPARATOR . 'abstract.php';
include_once $abstractFile;

class Janolaw_Shelp extends Mage_Shell_Abstract
{

    public function run()
    {
        if ($this->getArg('update')) {
            Mage::getModel('agbdownloader/downloader')->download();
            echo 'Successfully synchronized data' . PHP_EOL;
        } else {
            echo $this->usageHelp();
        }
    }

    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f janolaw.php -- command [options]

  commands:
    update                        Synchronize content (legal texts, pdf) from
                                  janolaw.
                                  CAUTION: This will replace content of the cms
                                  blocks or pages defined in the configuration
                                  of the Janolaw Module
                                  (System -> config -> Tab "General"
                                                       -> Janoloaw AGB Hosting)

  options:
    magebase="/path/to/magento"   relative or absoulte path to magento base
                                  directory. This is required if this script is
                                  symlinked into the shell directory or is not
                                  in the (expected) shell directory at all...

USAGE;
    }
}

$shell = new Janolaw_Shelp();
$shell->run();
