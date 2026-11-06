<?php
/*   $Id: aupost.php,v3.0.1 Apr 2026 */
// BMH 2026-04-29
$define = [
    'MODULE_SHIPPING_AUPOST_TEXT_TITLE' => 'Australia Post',
    'MODULE_SHIPPING_AUPOST_TEXT_DESCRIPTION' => 'Australia Post Shipping Module',
    'MODULE_SHIPPING_AUPOST_TEXT_ERROR' => '<font color="#FF0000">Estimate only:</font> We were unable to obtain a valid quote from the Australia Post Server.<br />You may still checkout using this method or contact us for accurate postage costs.',
    'MSGLETTERTRACKING' => ' <b>(No tracking)</b>',
    'ERROR_MAX_LENGTH_MSG' => 'Exceeds maximum length',  // limit to 30 chars length for presentation
    'ERROR_MAX_CUBIC_MSG' => 'Exceeds maximum cubic vol /girth',  // limit to 30 chars length for presentation
    'ERROR_MAX_WEIGHT_MSG' => 'Exceeds maximum weight',  // limit to 30 chars length for presentation
    'ERROR_NO_VALID_PARCEL_QUOTE_MSG' => 'No valid parcel quote  from Australia Post',  // limit to 30 chars length for presentation
    'ERROR_NO_VALID_LETTER_QUOTE_MSG' => 'No valid letter quote from Australia Post',  // limit to 30 chars length for presentation];
    // Admin error message
    'MODULE_SHIPPING_AUPOST_ERROR_ADMIN_CONFIGURATION' => 'Error: Please configure the Australia Post Shipping Module properly in Admin. Check API key and other settings.',
    'MODULE_SHIPPING_AUPOST_ERROR_ADMIN_COST_ON_ERROR' => 'Error: Cost on Error has an invalid value. ',
    'MODULE_SHIPPING_AUPOST_ERROR_ADMIN_API_KEY' => 'Error: Invalid API key. ',
    'MODULE_SHIPPING_AUPOST_ERROR_ADMIN_MAX_SHIPPING_WT' => 'SHIPPING_MAX_WEIGHT <> Aus Post max wt: ',
];
return $define;
