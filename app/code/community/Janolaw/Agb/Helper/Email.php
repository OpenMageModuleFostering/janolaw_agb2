<?php


class Janolaw_Agb_Helper_Email
{

    /**
     * According to rfc5987
     *
     * See http://stackoverflow.com/questions/4968272/how-can-i-encode-a-filename-in-php-according-to-rfc-2231
     * See http://tools.ietf.org/html/rfc5987
     *
     * @param string $paramName  The header parameter's name, e.g. "filename"
     * @param string $paramValue The header parameter's value, e.g. "Doc.pdf"
     * @param string $eol        End of line character
     * @param string $charset    Charset of the $paramValue string
     * @param string $lang       Optional the language of the value, e.g. 'en'
     * @param int    $lineLength The maximal line length of the email header
     *
     * @return bool|string
     */
    public function encodeHeaderParam(
        $paramName,
        $paramValue,
        $eol = "\n",
        $charset='utf-8',
        $lang='',
        $lineLength=78
    ) {
        if (strlen($paramName) === 0
            || preg_match('/[\x00-\x20*\'%()<>@,;:\\\\"\/[\]?=\x80-\xFF]/', $paramName)
        ) {
            throw new InvalidArgumentException('Invalid param name: ' . $paramName);
        }
        // HEXDIG representation, preceded by charset and language
        $paramValue = strtoupper(bin2hex($paramValue));
        if (strlen($paramValue) / 2 * 3 + strlen($paramName)
            + strlen($charset) + strlen($lang) + 5 <= $lineLength
        ) {
            // everything fits into one line
            return " $paramName*=$charset'$lang'"
                . '%' . implode('%', str_split($paramValue, 2));
        }

        // else: create multiple lines
        $prefixPattern = " $paramName*%s*="; // %s will be replaced with index

        // start the first line with meta data and prefix
        $currentLine = sprintf($prefixPattern, 0) . "$charset'$lang'";
        $lines = array();
        if (strlen($currentLine) + 4 > $lineLength) { // 4 = 3 characters for 3 pct-encoded entity + 1 trailing ";"
            throw new InvalidArgumentException('Line length is not enough for given paramenters.');
        }
        do {
            if (null === $currentLine) {
                $currentLine = sprintf($prefixPattern, count($lines));
            }
            if (strlen($currentLine) + 3 > $lineLength) {
                // split line
                $lines[] = $currentLine;
                $currentLine = null;
            } else {
                // add an entity (note: $paramValue must always be a multiple of 2,
                // as it was created by bin2hex)
                $currentLine .= '%' . substr($paramValue, 0, 2);
                $paramValue = substr($paramValue, 2);
            }
        } while (strlen($paramValue));
        if ($currentLine !== null) {
            $lines[] = $currentLine;
        }
        return implode(";$eol", $lines);
    }
} 