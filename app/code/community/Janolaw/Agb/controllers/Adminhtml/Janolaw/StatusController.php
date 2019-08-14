<?php


class Janolaw_Agb_Adminhtml_Janolaw_StatusController extends Mage_Adminhtml_Controller_Action
{

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('admin/system/janolaw/status');
    }

    /**
     * Print info about state of janolaw module..
     */
    public function indexAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * This is an ajax session, returning json
     */
    public function runSynchronisationAction()
    {
        /* @var $model Janolaw_Agb_Model_Downloader */
        $model = Mage::getModel('agbdownloader/downloader');

        try {
            $model->download();
            $messages = $this->_getSession()->getMessages(true);
            $errors = $messages->getErrors();

            $sessionMessages = array();
            foreach ($errors as $m) {
                /* @var $m Mage_Core_Model_Message_Abstract */
                $sessionMessages[] = $m->getText();
            }

            $result = array(
                'success' => count($errors) <= 0,
                'messages' => $sessionMessages,
            );
        } catch (Exception $e) {
            $result = array(
                'success' => false,
                'messages' => array($e->getMessage())
            );
            Mage::logException($e);
        }
        $this->_sendJson($result);
    }

    protected function _sendJson(array $data)
    {
        $jsonData = json_encode($data);
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody($jsonData);
    }
} 