<?php

class Janolaw_Agb_Adminhtml_Janolaw_SetupController extends Mage_Adminhtml_Controller_Action
{

    protected $_lastError = '';

    /**
     * Show setup page
     */
    public function indexAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * create / update an agreement based on type.
     * Expected input data (POST):
     *   name
     *   type (one of Janolaw_Agb_Model_CmsAssistant::TYPE_REVOCATION and
     *          Janolaw_Agb_Model_CmsAssistant::TYPE_TAC)
     *   overwrite Whether we should overwrite existing agreements with the same name or print an error in this case
     *
     */
    public function saveAgreementAction()
    {
        $r = $this->getRequest();
        $name = $r->getPost('name');
        $type = $r->getPost('type'); // tac or revocation
        $overwriteExisting = (bool) trim($r->getPost('overwrite'));

        $content = $this->_getBlockIncludeSnippet($type);
        $checkboxText = $this->getCheckboxText($type);
        if (is_null($content) || is_null($checkboxText)) {
            $this->_endRequest(false, $this->_lastError);
            return;
        }

        $model = $this->_getAgreementModel($name, $overwriteExisting);
        if (is_null($model)) {
            $this->_endRequest(false, $this->_lastError);
            return;
        }

        $model->setIsActive(true);
        $model->setIsHtml(true);
        $model->setCheckboxText($checkboxText);
        $model->setContent($content);
        $model->setName($name);
        $model->setStores(array(0));
        $model->save();

        $successMsg = $this->_getHelper()->__('Successfully saved agreement %s', $name);
        $this->_endRequest(true, $successMsg);
    }

    /**
     * Saves block identifier to janolaw configuration. Expected data (POST):
     * - type
     * - block_identifier
     */
    public function setBlockIdentifierAction()
    {
        $type = $this->getRequest()->getPost('type');
        $blockId = $this->getRequest()->getPost('block_identifier');

        try {
            $this->_getCmsAssistant()->setBlockConfig($type, $blockId);
            Mage::getConfig()->cleanCache();
            $successMsg = $this->_getHelper()->__('Successfully set block identifer %s', $blockId);
            $this->_endRequest(true, $successMsg);
        } catch (InvalidArgumentException $e) {
            $this->_lastError = $this->_getHelper()->__('Could not save block identifier to configuration. Wrong type given (%s)', $type);
            $this->_endRequest(false, $this->_lastError);
        } catch (Exception $e) {
            $this->_lastError = $this->_getHelper()->__('Could not save block identifier to configuration. Details: %s', $e->getMessage());
            $this->_endRequest(false, $this->_lastError);
        }
    }

    /**
     * If request has a parameter 'is_ajax', then a json response is sendt.
     * Otherwise, the message is added to the session (as success or error message, depending on $success)
     *
     * @param bool    $success
     * @param string  $message If empty, no message is added to the session.
     * @param string  $redirectTarget If request is not an ajax request, a redirect to this route is done
     */
    protected function _endRequest($success, $message, $redirectTarget = '*/*/index')
    {
        $isAjax = (bool) trim($this->getRequest()->getParam('is_ajax'));
        if ($isAjax) {
            $response = array(
                'success' => (bool) $success,
                'message' => $message,
            );
            $this->_sendJson($response);
        } else {
            if (!empty($message)) {
                if ($success) {
                    $this->_getSession()->addSuccess($message);
                } else {
                    $this->_getSession()->addError($message);
                }
            }
            if ($redirectTarget) {
                $this->_redirect($redirectTarget);
            }
        }
    }

    protected function _sendJson(array $data)
    {
        $jsonData = json_encode($data);
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody($jsonData);
    }

    /**
     * Returns null if an agreement with given name does exist already, and $overwriteExisting is false
     *
     * @param $name
     * @param $overwriteExisting
     *
     * @return null|Mage_Checkout_Model_Agreement
     */
    protected function _getAgreementModel($name, $overwriteExisting)
    {
        /* @var $model Mage_Checkout_Model_Agreement */
        $existingItems = $this->_getHelper()->getAgreements($name);
        if ($existingItems->count() > 0) {
            if ($overwriteExisting) {
                return $existingItems->getFirstItem();
            } else {
                $this->_lastError = $this->_getHelper()->__('Agreement with name %s does already exist in default store', $name);
                return null;
            }
        } else {
            return Mage::getModel('checkout/agreement');
        }
    }

    /**
     * @param string $type One of the constants defined in Janolaw_Agb_Model_CmsAssistant
     *
     * @return null|string Null if type is not revocation or terms of condition
     *                      (only this two are allowed for agreements)
     */
    protected function _getBlockIncludeSnippet($type)
    {
        switch ($type) {
            case \Janolaw_Agb_Model_CmsAssistant::TYPE_REVOCATION:
                $blockId = Mage::getStoreConfig(\Janolaw_Agb_Model_Downloader::XML_PATH_REVOCATION_ID, 0);
                break;
            case \Janolaw_Agb_Model_CmsAssistant::TYPE_TAC:
                $blockId = Mage::getStoreConfig(\Janolaw_Agb_Model_Downloader::XML_PATH_TAC_ID, 0);
                break;
            default:
                $this->_lastError = $this->_getHelper()->__('Error on save agreement action. Unknown type %s given.', $type);
                return null;
        }
        $content = $this->_getCmsAssistant()->getCmsDirectiveSnippet($blockId);
        return $content ? $content : null;
    }

    protected function getCheckboxText($type)
    {
        switch ($type) {
            case \Janolaw_Agb_Model_CmsAssistant::TYPE_REVOCATION:
                return 'Ich habe die Widerrufsbelehrung gelesen.';
            case \Janolaw_Agb_Model_CmsAssistant::TYPE_TAC:
                return 'Ich habe die Allgemeinen Geschäftsbedingungen gelesen und stimme diesen ausdrücklich zu.';
            default:
                $this->_lastError = $this->_getHelper()->__('Error on save agreement action. Unknown type %s given.', $type);
                return null;
        }
    }

    /**
     * @param bool $singleton
     *
     * @return Janolaw_Agb_Model_CmsAssistant
     */
    protected function _getCmsAssistant($singleton = true)
    {
        if ($singleton) {
            return Mage::getSingleton('agbdownloader/cmsAssistant');
        } else {
            return Mage::getModel('agbdownloader/cmsAssistant');
        }
    }

    /**
     * @return Janolaw_Agb_Helper_Data
     */
    protected function _getHelper()
    {
        return Mage::helper('agbdownloader');
    }
}
