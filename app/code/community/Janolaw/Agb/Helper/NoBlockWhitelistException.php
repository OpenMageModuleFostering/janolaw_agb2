<?php

// indicates that there is no block whitelist, i.e. the patch supee 6788
// is not installed (and magento version is less than 1.9.2.2)
class Janolaw_Agb_Helper_NoBlockWhitelistException extends Exception
{
}