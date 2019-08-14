<?php

/**
 * Specialization of Zend Mime Part for pdf files used as email
 * attachment.
 *
 * Solves encoding problem of attachment filename
 */
class Janolaw_Agb_Model_Email_Attachment_Pdf extends Zend_Mime_Part
{

    public function __construct(
        $body,
        $filename,
        $encoding = Zend_Mime::ENCODING_BASE64
    )
    {
        parent::__construct($body);

        $this->encoding = $encoding;
        $this->type = 'application/pdf';
        $this->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
        $this->filename = $filename;
    }

    /**
     * Create and return the array of headers for this MIME part
     *
     * @access public
     * @return array
     */
    public function getHeadersArray($EOL = Zend_Mime::LINEEND)
    {
        $headers = array();

        $contentType = $this->_renderContentTypeHeader($EOL);
        $headers[] = array('Content-Type', $contentType);

        if ($this->encoding) {
            $headers[] = array('Content-Transfer-Encoding', $this->encoding);
        }

        if ($this->id) {
            $headers[]  = array('Content-ID', '<' . $this->id . '>');
        }

        if ($this->disposition) {
            $disposition = $this->_renderDispositionHeader($EOL);
            $headers[] = array('Content-Disposition', $disposition);
        }

        if ($this->description) {
            $headers[] = array('Content-Description', $this->description);
        }

        if ($this->location) {
            $headers[] = array('Content-Location', $this->location);
        }

        if ($this->language){
            $headers[] = array('Content-Language', $this->language);
        }

        return $headers;
    }

    /**
     * @param $EOL
     *
     * @return string
     */
    protected function _renderContentTypeHeader($EOL)
    {
        $contentType = $this->type;
        if ($this->charset) {
            $contentType .= '; charset=' . $this->charset;
        }

        if ($this->filename) {
            $qEncodedFilename = Zend_Mime::encodeQuotedPrintableHeader(
                $this->filename,
                'UTF-8',
                Zend_Mime::LINELENGTH - strlen(' name=""')
            );
            $contentType .= ';' . $EOL . ' name="' . $qEncodedFilename . '"';
        }

        if ($this->boundary) {
            $contentType .= ';' . $EOL
                . " boundary=\"" . $this->boundary . '"';
        }

        return $contentType;
    }

    /**
     * @param $EOL
     * @return string
     */
    protected function _renderDispositionHeader($EOL)
    {
        if (!$this->disposition) {
            Mage::log('empty disposition', Zend_Log::ERR);
            return '';
        }
        $disposition = $this->disposition;
        if ($this->filename) {
            try {
                $filename = $helper = Mage::helper('agbdownloader/email')
                    ->encodeHeaderParam('filename', $this->filename);
                $disposition .= ';' . $EOL . $filename;
            } catch (Exception $e) {
                Mage::logException($e);
            }
        }
        return $disposition;
    }

}