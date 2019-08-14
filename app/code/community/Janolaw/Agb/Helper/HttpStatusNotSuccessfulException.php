<?php


class Janolaw_Agb_Helper_HttpStatusNotSuccessfulException extends Exception
{

    /**
     * @var Zend_Http_Response
     */
    public $response;

    public function __construct(Zend_Http_Response $response, $msg = null)
    {
        if (is_null($msg)) {
            $msg = 'Unexpected Http status code: ' . $response->getStatus();
        }
        parent::__construct($msg);
        $this->response = $response;
    }
} 