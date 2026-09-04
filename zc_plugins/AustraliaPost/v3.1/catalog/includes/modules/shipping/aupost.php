<?php
declare(strict_types=1);
/**
 * $Id:   aupost.php,v3.1 September 2026
 * 2026-09-02 - v3.1 - Conversion from XML API to JSON API
 *   Try to call API to show ap boxes
 *   use message stack to show admin configuration errors instead of just appending to title. Define error messages in lang file.
 *             set domestic check earlier; parcel calc moved to function; parcel size optimised, letter size optimised; check topcode not blank;
 *   improved postcode validation; regrouped  AuPost parcel type description codes that keep changing;
 *  css for debuggingpulled in from encapsulated folder via observer auto.aupost_css_loader.php
 *  2026-07-11 add log entry for invalid_option
 *  2026-07-22 updated result options sorting; optimal packaging to check footprint first; allow for flat rate packages
 *  2026-08-11  ln2091 Undefined array key "postage_result"
 *  2026-09-02 allow Flat Rate packaging; condensed repeated code into function calls; cleaned debugging lines
 *  2026-09-03 normalise 'services/service' when Australia Post returns a single service as an object rather than an
 *              array, so the $i-indexed loop handles both single and multiple service responses without
 *              "Undefined array key 0"
 */

/* version */
if (!defined('VERSION_AU')) {
    define('VERSION_AU', '3.1');
}

// ---- BMHDEBUG switches // WARNING DO NOT ENABLE FOR PRODUCTION
define('BMHDEBUG1', 'No');         // No or Yes // BMH 2nd level debug
define('BMH_P_DEBUG2', 'No');       // No or Yes // BMH 3nd level debug to display returned values  from AP
define('BMH_P_DEBUG3', 'No');       // No or Yes // BMH 4th level debug to display allowed options
define('BMH_P_DEBUG4', 'No');       // No or Yes // BMH 5th level debug to display functions and raw curl
define('BMH_P_DEBUG4_BOXES', 'No'); // No or Yes // BMH level debug to display BOXES Code - future development
//
define('BMH_L_DEBUG1', 'No');       // No or Yes // BMH 3nd level debug
define('BMH_L_DEBUG2', 'No');       // No or Yes // BMH 3nd level debug to display all returned json data from Aus Post

define('USE_CACHE', 'Yes');          // BMH disable cache // set to 'No' for testing; Using cache prevents excessive calls to AP if data not changed

define('BMH_MIN_ORDER_VALUE_DEBUG', 'No');  // BMH set to yes to force extra cover on orders less than $MINVALUEEXTRACOVER for testing. For PRODUCTION SET TO "No'

define('CALL_FOR_BOXES', 'No'); // BMH set to Yes to call API to show boxes in checkout. Set to No to skip API call and hide boxes. NOT USED
define('PARCEL_URL_SIZE_STRING', '/postage/parcel/domestic/size.json');

// declare constants

if (!defined('MODULE_SHIPPING_AUPOST_TAX_CLASS')) {
    define('MODULE_SHIPPING_AUPOST_TAX_CLASS', '');
}
if (!defined('MODULE_SHIPPING_AUPOST_TYPES1')) {
    define('MODULE_SHIPPING_AUPOST_TYPES1', '');
}
if (!defined('MODULE_SHIPPING_AUPOST_TYPE_LETTERS')) {
    define('MODULE_SHIPPING_AUPOST_TYPE_LETTERS', '');
}

if (!defined('MODULE_SHIPPING_AUPOST_HIDE_PARCEL')) {
    define('MODULE_SHIPPING_AUPOST_HIDE_PARCEL', '');
}
if (!defined('MODULE_SHIPPING_AUPOST_CORE_WEIGHT')) {
    define('MODULE_SHIPPING_AUPOST_CORE_WEIGHT', '');
}

if (!defined('MODULE_SHIPPING_AUPOST_STATUS')) {
    define('MODULE_SHIPPING_AUPOST_STATUS', '');
}
if (!defined('MODULE_SHIPPING_AUPOST_SORT_ORDER')) {
    define('MODULE_SHIPPING_AUPOST_SORT_ORDER', '');
}
if (!defined('MODULE_SHIPPING_AUPOST_ICONS')) {
    define('MODULE_SHIPPING_AUPOST_ICONS', '');
}
if (!defined('MODULE_SHIPPING_AUPOST_TAX_BASIS')) {
    define('MODULE_SHIPPING_AUPOST_TAX_BASIS', 'Shipping');
}

// +++++++++++++++++++++++++++++
define('AUPOST_MODE', 'PROD'); //Test OR PROD    // Test uses test URL and Test Authkey;
// PROD uses the key input via the admin shipping modules panel for "Australia Post" TODO Drop in next version
// **********************

// ++++++++++++++++++++++++++
if (!defined('MODULE_SHIPPING_AUPOST_AUTHKEY')) {
    define('MODULE_SHIPPING_AUPOST_AUTHKEY', '');
}
if (!defined('AUPOST_TESTMODE_AUTHKEY')) {
    define('AUPOST_TESTMODE_AUTHKEY', '28744ed5982391881611cca6cf5c240');
} // DO NOT CHANGE

if (!defined('AUPOST_URL_PROD')) {
    define('AUPOST_URL_PROD', 'digitalapi.auspost.com.au');
}

if (!defined('LETTER_URL_STRING')) {
    define('LETTER_URL_STRING', '/postage/letter/domestic/service.json?');
} //
if (!defined('LETTER_URL_STRING_CALC')) {
    define('LETTER_URL_STRING_CALC', '/postage/letter/domestic/calculate.json?');
} ////
if (!defined('PARCEL_URL_STRING')) {
    define('PARCEL_URL_STRING', '/postage/parcel/domestic/service.json?from_postcode=');
} //
//if (!defined('PARCEL_URL_STRING')) { define('PARCEL_URL_STRING','/postage/parcel/domestic/service.json?from_postcode='); } //
//if (!defined('PARCEL_URL_STRING_CALC')) { define('PARCEL_URL_STRING_CALC','/postage/parcel/domestic/calculate.json?from_postcode='); }//
if (!defined('PARCEL_URL_STRING_CALC')) {
    define('PARCEL_URL_STRING_CALC', '/postage/parcel/domestic/calculate.json?from_postcode=');
}//

/**
 * class constructor
 */
class aupost extends base
{
    private ?string $_logDir = DIR_FS_SQL_CACHE;    //
    public ?string $errorString;                    //
    public string $log_file_name = "AuPost.log";    //
    public ?float $add;                             // add on charges
    public ?array $allowed_methods;                 //
    public ?array $allowed_methods_l;               //
    public ?float $aus_rate;                        //
    public ?int $_check;                            //
    public ?string $code;                           // Declare shipping module alias code
    public ?string $description;                    // Shipping module display description
    public ?string $dest_country;                   // destination country
    public ?string $dim_query;                      //
    public ?array $dims;                            //
    public ?bool $enabled;                          // Shipping module status
    public ?string $error_msg_ap;                   //
    public ?string $frompcode;                      // source post code
    public ?string $icon;                           // Shipping module icon filename/path
    public ?float $itemcube;                        // cubic volume of item
    public ?string $logo;                           // au post logo
    public ?float $maxcover;                        //
    public ?bool $maxcoverexceeded;                  //
    public ?float $ordervalue;                      // value of order
    public ?float $ordervalue_ori;                  // original value of order before any adjustments
    public ?string $producttitle;                   //
    public ?array $quotes = [];                     //
    public ?int $ap_shipping_num_boxes;             //
    public ?string $sort_order;                     // sort order for quotes options
    public ?string $tare;                                   //
    public ?int $q;
    public mixed $w;
    public ?string $tax_basis;                      //
    public ?string $tax_class;                      //
    public ?string $title;                          //

    public ?string $topcode;                        //
    public ?bool $usemod;                           //
    public $json = [];                              // json array
    public ?bool $usetitle;                         //

    // Maximums - parcels
    public $MAXWEIGHT_P = 22;     // 22kg max parcel weight within Aust
    public $MAXLENGTH_P = 105;    // 105cm max parcel length
    public $MAXCUBIC_P = 0.25;    // 0.25 cubic meters max dimensions (width * height * length)

    // Modes for handling order value when it exceeds Australia Post's max extra cover limit
    private const MAXCOVER_NONE = 0;      // leave order value untouched
    private const MAXCOVER_NOCHANGE = 1;  // keep order value at its original
    private const MAXCOVER_RESET = 2;     // reset order value to max cover minus 1
    private const MAXCOVER_BREAK = 3;     // skip the option and break out of the switch

    /**
     * Summary of __construct
     */
    public function __construct()
    {
        global $order, $db, $template, $tax_basis, $messageStack;
        global $frompcode;
        global $MAXWEIGHT_P, $MAXLENGTH_P, $MAXCUBIC_P;
        global $maxcoverexceeded;
        global $maxcover;
        global $customer_id;
        $this->code = 'aupost';
        $this->title = MODULE_SHIPPING_AUPOST_TEXT_TITLE;
        $this->description = MODULE_SHIPPING_AUPOST_TEXT_DESCRIPTION . ' V' . VERSION_AU;
        ;
        $this->sort_order = MODULE_SHIPPING_AUPOST_SORT_ORDER;
        $this->icon = '';
        $this->logo = '';
        $this->tax_basis = MODULE_SHIPPING_AUPOST_TAX_BASIS;
        $this->tax_class = MODULE_SHIPPING_AUPOST_TAX_CLASS;
        $this->error_msg_ap = '';

        // ----- Admin configuration page ------------------------ //
        // ---- check for API key and other settings in admin and display warnings if not set or invalid //
        //
        if (IS_ADMIN_FLAG === true) {
            if (MODULE_SHIPPING_AUPOST_STATUS == 'True' && (MODULE_SHIPPING_AUPOST_AUTHKEY == 'Add API Auth key from Australia Post' ||
                strlen(MODULE_SHIPPING_AUPOST_AUTHKEY) < 31)) {
                $this->title .= '<span class="alert"> (Not Configured) check API key</span>';
                //$messageStack->add_session( '<span class="alert"> (Not Configured) check API key</span>','error');
                $messageStack->add_session(MODULE_SHIPPING_AUPOST_ERROR_ADMIN_API_KEY, 'error');

            } elseif (MODULE_SHIPPING_AUPOST_STATUS == 'True' && MODULE_SHIPPING_AUPOST_AUTHKEY == '28744ed5982391881611cca6cf5c240') {
                $this->title = MODULE_SHIPPING_AUPOST_TEXT_TITLE;
                $this->title .= '<span class="alert"> (Non-production Test API key)</span>';

            } else {
                $aupost_url_apiKey = MODULE_SHIPPING_AUPOST_AUTHKEY;
                $this->title = MODULE_SHIPPING_AUPOST_TEXT_TITLE;
            }
            if (USE_CACHE == 'No') {
                $this->title .= '<span class="alert"> (Cache set to off)</span>';
            }
            $check_coe = FALSE;
            if (defined('MODULE_SHIPPING_AUPOST_COST_ON_ERROR')) {
                if (trim(MODULE_SHIPPING_AUPOST_COST_ON_ERROR) == "TBA") {
                    $check_coe = TRUE;
                }
                if (is_numeric(trim(MODULE_SHIPPING_AUPOST_COST_ON_ERROR))) {
                    $check_coe = TRUE;
                }
                if ($check_coe == FALSE) {
                    // $this->title .= '<span class="alert"> (Cost on Error has invalid value)</span>';
                    //$messageStack->add_session( '<span class="alert"> (Cost on Error has invalid value)</span>','error');
                    $messageStack->add_session(MODULE_SHIPPING_AUPOST_ERROR_ADMIN_COST_ON_ERROR, 'error');

                }
            }

            $lh1 = defined('MODULE_SHIPPING_AUPOST_LETTER_HANDLING');
            $lh2 = defined('MODULE_SHIPPING_AUPOST_LETTER_PRIORITY_HANDLING');
            $lh3 = ('MODULE_SHIPPING_AUPOST_LETTER_EXPRESS_HANDLING');
            if (($lh1 < 0) || ($lh2 < 0) || ($lh3 < 0)) {
                echo '<br/> ln125 check handling fees';
            }

            if (SHIPPING_MAX_WEIGHT <> ($this->MAXWEIGHT_P)) {

                $msg = MODULE_SHIPPING_AUPOST_ERROR_ADMIN_MAX_SHIPPING_WT . $this->MAXWEIGHT_P . 'kg';
                $messageStack->add_session($msg, 'error');
            }

        } // end Admin section
        // +++++++++++++++++++++++++++++++++++++++

        $this->ap_shipping_num_boxes = 1;

        // ---- use ZC tax class -------------------------------------------//
        $this->tax_class = defined('MODULE_SHIPPING_AUPOST_TAX_CLASS') ? MODULE_SHIPPING_AUPOST_TAX_CLASS : null;

        if (zen_get_shipping_enabled($this->code))
            $this->enabled = (defined('MODULE_SHIPPING_AUPOST_STATUS') && (MODULE_SHIPPING_AUPOST_STATUS == 'True') ? true : false);
        if (MODULE_SHIPPING_AUPOST_ICONS != "No") {
            $this->logo = $template->get_template_dir('aupost_logo.jpg', '', '', DIR_WS_TEMPLATE . 'images/icons') . '/aupost_logo.jpg';
            $this->icon = $this->logo;                                                                      // set the quote icon to the logo
            if (zen_not_null($this->icon))
                $this->quotes['icon'] = zen_image($this->icon, $this->title);
        }
        // get letter and parcel methods defined
        $this->allowed_methods_l = explode(", ", MODULE_SHIPPING_AUPOST_TYPE_LETTERS);
        $this->allowed_methods = explode(", ", MODULE_SHIPPING_AUPOST_TYPES1);
        $this->allowed_methods = $this->allowed_methods + $this->allowed_methods_l;                           //  combine letters + parcels into one methods list
    }
    // eof class methods

    /* bof functions */

    /**
     * Summary of quote
     * @param mixed $method
     * @return array|mixed|null
     */
    public function quote($method = '')
    {
        global $db, $order, $currencies, $parcelweight, $packageitems;
        global $customer_id;
        global $frompcode;
        global $maxcoverexceeded;
        global $maxcover;
        global $producttitle, $tare;
        global $methods;

        $maxcover = 0;                  // initialise max cover exceeded flag to false

        // see later comments on removing underscores from AusPost-defined shipping methods.

        if (zen_not_null($method) && (isset($_SESSION['aupostQuotes']))) {
            $testmethod = $_SESSION['aupostQuotes']['methods'];
            foreach ($testmethod as $temp) {
                $search = array_search("$method", $temp);
                if ($search > 0 && $search >= 0)
                    break;
            }

            $usemod = $this->title;
            $usetitle = $temp['title'];

            // ---- strip the icons ---------------------------------------- //
            if (MODULE_SHIPPING_AUPOST_ICONS != "No") {
                if (preg_match('/(title)=("[^"]*")/', $this->title, $module))
                    $usemod = trim($module[2], "\"");
                if (preg_match('/(title)=("[^"]*")/', $temp['title'], $title))
                    $usetitle = trim($title[2], "\"");
            }

            //  Initialise our quote array(s) ;quotes['id'] required in includes/classes/shipping.php
            // reset quotes['id'] as it is mandatory for shipping.php but not used anywhere else
            $methods = [];
            $this->quotes = [
                'id' => $this->code,
                'module' => $usemod,
                'methods' => [
                    [
                        'id' => $method,
                        'title' => $usetitle,
                        'cost' => $temp['cost']
                    ]
                ]
            ];

            if ($this->tax_class > 0) {
                $this->quotes['tax'] = zen_get_tax_rate((int) $this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
            }
            if (zen_not_null($this->icon))
                $this->quotes['icon'] = zen_image($this->icon, $this->title);
            return $this->quotes;                                                                   // return a single quote
        }  // ---- Single Quote Exit Point --------------------------------- //

        /* check from postcode and only posting to Australia */
        $frompcode = (MODULE_SHIPPING_AUPOST_SPCODE);
        if (!isset($frompcode) || $frompcode == '') {
            $frompcode = '4121';                            // default to Tarragindi, Qld 4121
            $this->_log(msg: 'ln' . __LINE__ . 'From postcode not set in module settings. Defaulting to 4121 Tarragindi'); // write to log file
        }
        $dest_country = ($order->delivery['country']['iso_code_2'] ?? '');    //

        // Only proceed for AU addresses
        if ($dest_country != "AU") {
            return;
        }
        $topcode = str_replace(" ", "", ($order->delivery['postcode'] ?? ''));

        if (!$this->validate_au_postcode($topcode, $dest_country, $order)) {
            return;
        }

        // ..end domestic checking.

        /// LETTERS - values  ///

        if (MODULE_SHIPPING_AUPOST_TYPE_LETTERS <> null) {

            $MAXLETTERFOLDSIZE = 15;                       // mm for edge of envelope
            $MAXLETTERPACKINGDIM = 4;                      // mm thickness of packing. Letter max height is 20mm including packing
            //$MAXWEIGHT_L = 500 ;                          // 500g max weight of letter
            $MAXLENGTH_L = (360 - $MAXLETTERFOLDSIZE);     // 360mm max letter length  less fold size on edges
            $MAXWIDTH_L = (260 - $MAXLETTERFOLDSIZE);      // 260mm max letter width  less fold size on edges
            $MAXHEIGHT_L = (20 - $MAXLETTERPACKINGDIM);     // 20mm max letter height LESS packing thickness
            $MAXHEIGHT_L_SM = 5;                            // 5mm max small letter height
            $MAXLENGTH_L_SM = (240 - $MAXLETTERFOLDSIZE);   // 240mm
            $MAXWIDTH_L_SM = (130 - $MAXLETTERFOLDSIZE);    // 130mm
            $MAXWEIGHT_L_WT1 = 125;                         // weight 125
            $MAXWEIGHT_L_WT2 = 250;                         // weight 250
            //$MAXWEIGHT_L_WT3 = 500;                       // weight 500 no t used , default to  parcel for extra padding
            $MSGLETTERTRACKING = MSGLETTERTRACKING;         // label append formatted in language file
            //$MAXWIDTH_L_SM_EXP = 110;                     // DL envelope prepaid Express envelopes
            //$MAXLENGTH_L_SM_EXP = 220;                    // DL envelope prepaid Express envelopes
            //$MAXWIDTH_L_MED_EXP = 162;                    // C5 envelope prepaid Express envelopes
            //$MAXLENGTH_L_MED_EXP = 229;                   // C5 envelope prepaid Express envelopes
            //$MAXWIDTH_L_LRG_EXP = 250;                    // B4 envelope prepaid Express envelopes
            //$MAXLENGTH_L_LRG_EXP = 353;                   // B4 envelope prepaid Express envelopes
            $MINVALUEEXTRACOVER = 101;                      // Aust Post amount for min insurance charge
            $MINLETTERWEIGHT = 15;                          // minimum weight of letter container

            // initialise variables
            $letterwidth = 0.0;
            $letterwidthcheck = 0;                          // logical check if width is in range for letter
            //$letterwidthchecksmall = 0 ;
            $letterlength = 0.0;
            $letterlengthcheck = 0;                         // logical check if length is in range for letter
            //$letterlengthchecksmall = 0 ;
            $letterheight = 0.0;
            $letterheightcheck = 0;                         // logical check if height is in range for letter
            //$letterheightchecksmall = 0 ;
            $letterweight = 0;                              // weight of letter in grams
            //$lettercube = 0 ;
            $letterchecksmall = 0;
            $lettercheck = 0;                               // logical check if dimensions are in range for letter
            //$lettersmall = 0;
            $letterlargewt1 = 0;                            // logical check if letter weight is in range for large letter weight 1
            $letterlargewt2 = 0;                            // logical check if letter weight is in range for large letter weight 2
            $letterlargewt3 = 0;                            // logical check if letter weight is in range for large letter weight 3
            //$letterexp_small = 0;
            //$letterexp_med = 0;
            //$letterexp_lrg = 0;
            $letterprefix = 'LETTER ';               // prefix label to differentiate from parcel - include space after

        }
        // ---- EOF LETTERS - values --------------------------------------- //

        // PARCELS - values
        // Maximums - parcels
        $MINVALUEEXTRACOVER = 101;                  // Aust Post amount for min insurance charge
        $MAXWEIGHT_P = 22;                                  // change from 20 to 22kg 2021-10-07


        $MAXLENGTH_P = 105;                                 // 105cm max parcel length
        $MAXCUBIC_P = 0.25;                                 // 0.25 cubic meters max dimensions (width * height * length)

        // default dimensions   -- parcels
        $expl = explode(',', MODULE_SHIPPING_AUPOST_DIMS);
        $defaultdims = array($expl[0], $expl[1], $expl[2]);
        sort($defaultdims);    // length[2]. width[1], height=[0]

        // initialise  variables for parcels
        $parcelwidth = 0;
        $parcellength = 0;
        $parcelheight = 0;
        $parcelweight = 0;
        $details = ' ';
        $itemcube = 0;

        $frompcode = (MODULE_SHIPPING_AUPOST_SPCODE);
        if (!isset($frompcode) || $frompcode == '') {
            $frompcode = '4121';                        // default to Tarragindi Qld 4121
            $this->_log(msg: 'From postcode not set in module settings. Defaulting to 4121 Tarragindi'); // write to log file
        }
        $dest_country = ($order->delivery['country']['iso_code_2'] ?? '');    //

        // Only proceed for AU addresses
        if ($dest_country != "AU") {
            return;
        }
        $topcode = str_replace(" ", "", ($order->delivery['postcode'] ?? ''));

        if (!$this->validate_au_postcode($topcode, $dest_country, $order)) {
            return;
        }
        //

        $aus_rate = (float) $currencies->get_value('AUD');      // get $AU exchange rate
        // EOF PARCELS - values

        if ($aus_rate == 0) {                                   // included to avoid possible divide  by zero error
            $aus_rate = (float) $currencies->get_value('AUS');  // if AUD zero/undefined then try AUS
            if ($aus_rate == 0) {
                $aus_rate = 1;                                   // if still zero initialise to 1.00 to avoid divide by zero error
            }
        }

        $ordervalue = $order->info['total'] / $aus_rate;        // total cost for insurance
        $ordervalue = round($ordervalue, 2);                    // round to 2 decimal places
        $ordervalue_ori = $ordervalue;                          // original order value before any adjustments

        /* set ordervalue  to  minimum insurable value +1 */
        if ((BMH_MIN_ORDER_VALUE_DEBUG === "Yes") && ($ordervalue_ori <= $MINVALUEEXTRACOVER)) {
            $ordervalue_ori = $MINVALUEEXTRACOVER + 1;
        }

        $tare = MODULE_SHIPPING_AUPOST_TARE;                         // percentage to add for packing etc

        if (($topcode == "") && ($dest_country == "AU")) {          // check destination postcode is provided ($topcode)
            return;
        }                           //  This will occur with guest user first quote where no postcode is available

        // ---- loop through cart extracting productIDs and qtys ----------- //
        $myorder = $_SESSION['cart']->get_products();

        $result = $this->calculateOptimalParcel($_SESSION['cart'], $db, [5, 10, 15]);

        $parcelwidth = $result['width'];             // cm  (widest row � 1.02)
        $parcellength = $result['length'];
        $parcelheight = $result['height'];
        $itemcube = $result['cube'];
        $packageitems = $result['items'];
        $parcelweight = $result['weight'];

        if ($parcelheight > $MAXHEIGHT_L) {
            $letterheightcheck = 0; // cannot be sent as letter by height limit
            echo 'ln' . __LINE__ . '$letterheightcheck=' . $letterheightcheck;
        }
        // ---- error msg to user if parcel too heavy ---------------------- //
        // moved to weight check section and combined with returned quote


        $aupost_url_string = AUPOST_URL_PROD;

        // ----- parcel sizing base don flat rate boxes --------------------- //
        /* start parcel size */

        /*
         * Boxes not used as flat rate returns available AP sizes
        if (CALL_FOR_BOXES == 'Yes') {              // retrieving flat rate box sizes

            $qu_box = $this->get_auspost_api('https://' . $aupost_url_string . PARCEL_URL_SIZE_STRING );

            $json_boxes = ($qu_box == '') ? [] : json_decode($qu_box, true); // If we have any results, parse them into a JSON array

            if (MODULE_SHIPPING_AUPOST_DEBUG == 'Yes' && BMH_P_DEBUG4_BOXES == 'Yes') {
                $this->_debug_output("x", '<br>ln' . __LINE__ . ' x4 auPost - Server Returned BMH_P_DEBUG4_BOXES ln:<br>', $json_boxes);
            }
        } else {
            if (MODULE_SHIPPING_AUPOST_DEBUG == 'Yes' && BMH_P_DEBUG4_BOXES == 'Yes') {
            echo '<br>ln' . __LINE__ . ' CALL_FOR_BOXES is set to No - using default box sizes'; //BMH debug REMOVE THIS OR EXPAND LOGIC FOR FLAT RATE
            }
           // $json_boxes = $this->get_default_box_sizes(); // get default box sizes from function
        }
         */

        // -- eof - parcel sizing base don flat rate boxes --------------------- //

        // ---- LETTERS ---------------------------------------------------- //

        if (MODULE_SHIPPING_AUPOST_TYPE_LETTERS <> null) //&& ($letterheightcheck <> 0)
        {
            // only calculate letter dimensions if letter service is enabled and height is in allowed range

            // for letter dimensions
            // letter height for starters
            $letterheight = $parcelheight * 10;                      // letters are in mm
            $letterheight = $letterheight + $MAXLETTERPACKINGDIM;   // add packaging thickness to letter height
            $letterlength = $parcellength * 10;                      // letters are in mm
            $letterwidth = $parcelwidth * 10;

            // Reorientate the dimensions so largest  becomes length
            $var_l = array($letterheight, $letterlength, $letterwidth);
            sort($var_l);
            $letterheight = $var_l[0];
            $letterwidth = $var_l[1];
            $letterlength = $var_l[2];
            // reorientate

            if (($letterheight) <= $MAXHEIGHT_L) {
                $letterheightcheck = 1;                             // maybe can be sent as letter by height limit
                $lettercheck = 1;                                   // dims in range of letter size
                // check letter height small
                if (($letterheight) <= $MAXHEIGHT_L_SM) {
                    $letterheightchecksmall = 1;
                    $letterchecksmall = 1;
                }

                // letter length in range for small
                $letterlength = ($parcellength * 10);
                if ($letterlength < $MAXLENGTH_L_SM) {
                    $letterlengthchecksmall = 1;
                    $letterchecksmall = $letterchecksmall + 1;
                }

                // check letter length in range
                if (($letterlength > $MAXLENGTH_L_SM) || ($letterlength <= $MAXLENGTH_L)) {
                    $letterlengthcheck = 1;
                    $lettercheck = $lettercheck + 1; // letter = 2nd size
                }
                // letter width in range
                $letterwidth = $parcelwidth * 10;
                if ($letterwidth < $MAXWIDTH_L_SM) {
                    $letterwidthchecksmall = 1;
                    $letterchecksmall = $letterchecksmall + 1;
                }

                if (($letterwidth > $MAXWIDTH_L_SM) || (($parcelwidth * 10) <= $MAXWIDTH_L)) {
                    $letterwidthcheck = 1;
                    $lettercheck = $lettercheck + 1;
                }

                // check letter weight // in grams
                $letterweight = ($parcelweight + ($parcelweight * $tare / 100));
                $letterweight = $letterweight + $MINLETTERWEIGHT;                   //add weight of envelope
                $letterweight = ceil($letterweight);                                // round up to integer
                if ((($letterweight) <= $MAXWEIGHT_L_WT1) && ($letterchecksmall == 3)) {
                    $lettersmall = 1;
                }
                if ((($letterweight) <= $MAXWEIGHT_L_WT1) && ($lettercheck == 3)) {
                    $letterlargewt1 = 1;
                }
                if (($letterweight >= $MAXWEIGHT_L_WT1) && ($letterweight <= $MAXWEIGHT_L_WT2) && ($lettercheck == 3)) {
                    $letterlargewt2 = 1;
                }
                // do not send 500g letters, default to parcel for extra packing

                // ---- DEBUG2 display the letter values -------------------- //

                if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMH_L_DEBUG2 == "Yes")) {
                    $this->_debug_output("n", "<br>n2 aupost ln" . __LINE__ . " \$lettercheck=" . $lettercheck . ' $letterchecksmall=' . $letterchecksmall . ' $letterlengthcheck = ' . $letterlengthcheck . ' $letterwidthcheck = ' . $letterwidthcheck . ' $letterheightcheck=' . $letterheightcheck, "");
                    if ($letterchecksmall == 3) {
                        echo " <br> ln" . __LINE__ . "  it is a  small letter";
                        if ($lettercheck == 3) {
                            echo " <br> ln" . __LINE__ . " it is a  large letter";
                        }
                        if ($letterlargewt1 == 1) {
                            echo " <br> ln" . __LINE__ . " it is a  large letter(125g)";
                        }
                        if ($letterlargewt2 == 1) {
                            echo " <br> ln" . __LINE__ . " it is a  large letter(250g)";
                        }
                        if ($letterlargewt3 == 1) {
                            echo " <br> ln" . __LINE__ . " it is a  large letter(500g)";
                        }
                    }
                    echo " </p>";
                } // L_DEBUG2 eof display the letter values ';

                // moved up        $aupost_url_string = AUPOST_URL_PROD;

                if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMH_L_DEBUG1 == "Yes")) {
                    $this->_debug_output("n", "<br>n1 <strong> aupost ln" . __LINE__ . " URL = </strong> <br/>" . "https://" . $aupost_url_string . LETTER_URL_STRING .
                        "length=$letterlength&width=$letterwidth&thickness=$letterheight&weight=$letterweight" . " </p>", "");
                } // eof debug URL

                // +++++++++++++++++ get the letter quote +++++++++++++++++++
                // letter quote request is different format to parcel quote request
                $quL = $this->get_auspost_api(
                    'https://' . $aupost_url_string . LETTER_URL_STRING . "length=$letterlength&width=$letterwidth&thickness=$letterheight&weight=$letterweight"
                );

                // If we have any results, parse them into an array
                $jsonquote_letter = ($quL == '') ? [] : json_decode($quL, true);

                //  bof json formatted output
                if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMH_L_DEBUG2 == "Yes")) {
                    $this->_debug_output("x", "<strong>>> Server Returned - LETTERS BMH_L_DEBUG1 ln" . __LINE__ . " << <br> </strong><textarea > ", $jsonquote_letter);
                } //eof debug server return

                // ======================================
                //  loop through the LETTER quotes retrieved //
                // create array
                $arrayquotes = array(array("qid" => "", "qcost" => 0, "qdescription" => ""));

                if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMH_L_DEBUG1 == "Yes")) {
                    $this->_debug_output("d", " aupost ln" . __LINE__ . " \$arrayquotes = <br/> ", $arrayquotes);
                }   // debug eof array quotes

                $i = 0;  // counter
                $methods = [];                                                          // initialise methods array for quotes
                // ---- normalise: Australia Post returns a single service as an object rather than an array ---- //
                if (isset($jsonquote_letter['services']['service']) && !isset($jsonquote_letter['services']['service'][0])) {
                    $jsonquote_letter['services']['service'] = [$jsonquote_letter['services']['service']];
                }
                foreach ($jsonquote_letter['services']['service'] as $foo => $bar) {
                    $code = ($jsonquote_letter['services']['service'][$i]['code']);     // keep API code for label
                    $servicecode = $code;                                               // fully formatted API $code required for later sub quote
                    $code = str_replace("_", " ", strval($code));                       //$code = substr($code,11); // replace underscores with spaces

                    $id = str_replace("_", "", strval($jsonquote_letter['services']['service'][$i]['code']));
                    // remove underscores from AusPost methods. Zen Cart uses underscore as delimiter between module and method.
                    // underscores must also be removed from case statements below.

                    $cost = (float) ($jsonquote_letter['services']['service'][$i]['price']);  // convert to float for calculations

                    $description = ($code);                                         // append name to code
                    $descx = ucwords(strtolower($description));                     // make sentence case
                    $description = $letterprefix . $descx . $MSGLETTERTRACKING;     // Prepend LETTER to CODE to differentiate from Parcels code + ADD letter tracking note

                    if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMH_L_DEBUG1 == "Yes")) {
                        $this->_debug_output("n", " ln" . __LINE__ . " LETTER ID= $id DESC= $description COST= $cost ", "");
                    }  // Debug 2nd level debug each line of quote parsed //

                    $qqid = $id;
                    $arrayquotes[$i]["qid"] = trim($qqid);              //
                    $arrayquotes[$i]["qcost"] = $cost;                  //
                    $arrayquotes[$i]["qdescription"] = $description;    //

                    $i++;   // increment the counter

                    $add = 0;
                    $f = 0;
                    $info = 0;

                    switch ($id) {

                        case "AUSLETTEREXPRESSSMALL":
                        case "AUSLETTEREXPRESSMEDIUM":
                        case "AUSLETTEREXPRESSLARGE":
                            if ((in_array("Aust Express", $this->allowed_methods_l))) {
                                $add = MODULE_SHIPPING_AUPOST_LETTER_EXPRESS_HANDLING;
                                $f = 1;
                                /* signature and extra cover only available for Express letters */
                                if
                                (
                                    in_array("Aust Express Insured (no sig)", $this->allowed_methods_l) ||
                                    in_array("Aust Express Insured +sig", $this->allowed_methods_l) ||
                                    in_array("Aust Express +sig", $this->allowed_methods_l)
                                ) {       // check for any options for express letter

                                    $optioncode_ec = 'AUS_SERVICE_OPTION_STANDARD';
                                    $suboptioncode = 'AUS_SERVICE_OPTION_EXTRA_COVER';
                                    $optioncode_sig = 'AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY';
                                    $optioncode = $optioncode_sig;          //
                                    if ($ordervalue < $MINVALUEEXTRACOVER) {
                                        $ordervalue = $MINVALUEEXTRACOVER;
                                    }
                                    // DEBUG mask for testing // setting value forces extra cover on receipt at Post office
                                    if (BMH_MIN_ORDER_VALUE_DEBUG == "Yes") {
                                        $ordervalue = $MINVALUEEXTRACOVER + 1;
                                    } // ** DEBUG to force extra cover value FOR TESTING ONLY; auto cover to $100

                                    // ++++++ get special price for options available with Express letters +++++
                                    $quL2 = $this->get_auspost_api(
                                        'https://' . $aupost_url_string . LETTER_URL_STRING_CALC . "service_code=$servicecode&weight=$letterweight&option_code=$optioncode&suboption_code=$suboptioncode&extra_cover=$ordervalue"
                                    );

                                    $jsonquote_letter2 = ($quL2 == '') ? array() : json_decode($quL2, true);

                                    $i2 = 0;  // counter for new jsonquote

                                    //  DEBUG bof json formatted output
                                    if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMH_L_DEBUG2 == "Yes")) {
                                        $this->_debug_output("x", "<strong> >> Server Returned - LETTERS BMHDEBUG1+2 aupost ln566 << </strong><br><textarea rows=30 cols=100 style=\"margin:0;\"> ", $jsonquote_letter2);
                                    }   // eof debug

                                    if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMH_L_DEBUG1 == "Yes")) {
                                        $this->_debug_output("n", "<b>n2 auPost - Server Returned BMH_L_DEBUG1 aupost ln572 LETTERS: output \$quL2</b><br>" . $quL2, "");
                                    }
                                    // --  DEBUG eof json formatted output----

                                    $id_exc_sig = "AUSLETTEREXPRESS" . "AUSSERVICEOPTIONSTANDARD";
                                    $id_exc = "AUSLETTEREXPRESS" . "AUSSERVICEOPTIONEXTRACOVER";
                                    $id_sig = "AUSLETTEREXPRESS" . "AUSSERVICEOPTIONSIGNATUREONDELIVERY";

                                    $codeitem = ($jsonquote_letter2['postage_result']['costs']['cost'][0]['item']);    // postage type description
                                    $desc2 = $codeitem;
                                    $desc_sig = $jsonquote_letter2['postage_result']['costs']['cost'][1]['item'];     // find the name for sig
                                    $desc_excover = $jsonquote_letter2['postage_result']['costs']['cost'][2]['item']; // find the name for extra cover

                                    $desc_excover_sig = $desc_sig . " + " . $jsonquote_letter2['postage_result']['costs']['cost'][2]['item']; // find the name for sig plus extra cover

                                    $cost_excover = ((float) ($jsonquote_letter2['postage_result']['costs']['cost'][0]['cost']) + ($jsonquote_letter2['postage_result']['costs']['cost'][2]['cost'])); // add basic postage cost + extra cover cost

                                    $cost_sig = (float) ($jsonquote_letter2['postage_result']['costs']['cost'][0]['cost']) + ($jsonquote_letter2['postage_result']['costs']['cost'][1]['cost']);       // basic cost + signature
                                    $cost_excover_sig = (float) ($jsonquote_letter2['postage_result']['total_cost']); // total cost for all options

                                    $cost_excover_sig = $cost_excover_sig / 11 * 10;       // remove tax
                                    $cost_excover = $cost_excover / 11 * 10;               // remove tax
                                    $cost_sig = $cost_sig / 11 * 10;                       // remove tax

                                    // got all of the values // -----------
                                    $desc_excover = trim(strval($desc2)) . ' + ' . $desc_excover;
                                    $desc_sig = trim(strval($desc2)) . ' + ' . $desc_sig;
                                    $desc_excover_sig = trim(strval($desc2)) . ' + ' . $desc_excover_sig;

                                    // ---------------
                                    $arraytoappend_excover = array("qid" => $id_exc, "qcost" => $cost_excover, "qdescription" => $desc_excover);
                                    $arraytoappend_sig = array("qid" => $id_sig, "qcost" => $cost_sig, "qdescription" => $desc_sig);
                                    $arraytoappend_ex_sig = array("qid" => $id_exc_sig, "qcost" => $cost_excover_sig, "qdescription" => $desc_excover_sig);

                                    // append allowed express option types to main array
                                    $arrayquotes[] = $arraytoappend_excover;
                                    $arrayquotes[] = $arraytoappend_sig;
                                    $arrayquotes[] = $arraytoappend_ex_sig;


                                    $details = $this->_handling($details, $currencies, $add, $aus_rate, $info);  // check if handling rates included


                                    // update returned methods for each option
                                    if (in_array("Aust Express Insured +sig", $this->allowed_methods_l)) {
                                        if (strlen($id) > 1) {
                                            $methods[] = array("id" => $id_exc_sig, "title" => $letterprefix . ' ' . $desc_excover_sig . ' ' . $details, "cost" => $cost_excover_sig);
                                        }
                                    }

                                    if (in_array("Aust Express Insured (no sig)", $this->allowed_methods_l)) {
                                        if (strlen($id) > 1) {
                                            $methods[] = array('id' => $id_exc, "title" => $letterprefix . ' ' . $desc_excover . ' ' . $details, 'cost' => $cost_excover);
                                        }
                                    }

                                    if (in_array("Aust Express +sig", $this->allowed_methods_l)) {
                                        if (strlen($id) > 1) {
                                            $methods[] = array('id' => $id_sig, "title" => $letterprefix . ' ' . $desc_sig . ' ' . $details, 'cost' => $cost_sig);
                                        }
                                    }
                                    $description = $letterprefix . $descx; // set desc for express without the no tracking msg

                                }   // eof // Express plus options

                            }
                            break;  //eof express

                        case "AUSLETTERPRIORITYSMALL":    // normal own packaging + label
                        case "AUSLETTERPRIORITYLARGE125": // normal own packaging + label
                        case "AUSLETTERPRIORITYLARGE250": // normal own packaging + label
                        case "AUSLETTERPRIORITYLARGE500": // normal own packaging + label
                            if ((in_array("Aust Priority", $this->allowed_methods_l))) {
                                $add = MODULE_SHIPPING_AUPOST_LETTER_PRIORITY_HANDLING;
                                $f = 1;
                            }
                            break;

                        case "AUSLETTERREGULARSMALL":      // normal mail - own packaging
                        case "AUSLETTERREGULARLARGE125":   // normal mail - own packaging
                        case "AUSLETTERREGULARLARGE250":   // normal mail - own packaging
                        case "AUSLETTERREGULARLARGE500":   // normal mail - own packaging
                            if (in_array("Aust Standard", $this->allowed_methods_l)) {
                                $add = MODULE_SHIPPING_AUPOST_LETTER_HANDLING;
                                $f = 1;
                            }
                            break;

                        case "AUSLETTERSIZEDL":  // This requires purchase of Aus Post packaging   // BMH Not processed
                        case "AUSLETTERSIZEC6":  // This requires purchase of Aus Post packaging   // BMH Not processed
                        case "AUSLETTERSIZEC5":  // This requires purchase of Aus Post packaging   // BMH Not processed
                        case "AUSLETTERSIZEC4":  // This requires purchase of Aus Post packaging   // BMH Not processed
                        case "AUSLETTERSIZEB4":  // This requires purchase of Aus Post packaging   // BMH Not processed
                        case "AUSLETTERSIZEOTH": // This requires purchase of Aus Post packaging   // BMH Not processed

                            $cost = 0;
                            $f = 0;
                            // echo "shouldn't be here" do nothing - ignore the code
                            break;

                    }  // end switch

                    // bof only list valid options without debug info - this is where we add handling and adjust for tax if applicable before displaying the quote
                    if ((($cost > 0) && ($f == 1))) { //
                        $cost = $cost + floatval($add);     // add handling fee   string to float

                        // GST (tax) included in all prices in Aust
                        if (($dest_country == "AU") && (($this->tax_class) > 0)) {
                            $t = $cost - ($cost / (zen_get_tax_rate((int) $this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']) + 1)); //  add 1
                            if ($t > 0)
                                $cost = $t;
                        }

                        $details = $this->_handling($details, $currencies, $add, $aus_rate, $info);  // check if handling rates included

                        // UPDATE THE RECORD FOR DISPLAY
                        $cost = $cost / $aus_rate;
                        // METHODS ADD to returned quote for letter
                        if (strlen($id) > 1) {
                            $methods[] = array('id' => "$id", 'title' => $description . $details, 'cost' => ($cost));
                        }
                    }  // end display output //////// only list valid options without debug info

                } // end letter height

            } // end of if for letters

            //// EOF LETTERS /////////

            // ---- PACKAGE ADJUSTMENT FOR OPTIMAL PACKING --------------------- //
            // ---- package created, now re-orientate and check dimensions - //
            $parcelheight = ceil($parcelheight);  // round up to next integer // cm for accuracy in pricing
            $var = array($parcelheight, $parcellength, $parcelwidth);
            sort($var);
            $parcelheight = $var[0];
            $parcelwidth = $var[1];
            $parcellength = $var[2];
            $girth = ($parcelheight * 2) + ($parcelwidth * 2);

            $parcelweight = $parcelweight + (($parcelweight * $tare) / 100);

            if (MODULE_SHIPPING_AUPOST_WEIGHT_FORMAT == "gms") {
                $parcelweight = $parcelweight / 1000;
            }

            // ---- save dimensions for display purposes on quote form --------- //
            $_SESSION['swidth'] = $parcelwidth;
            $_SESSION['sheight'] = $parcelheight;
            $_SESSION['slength'] = $parcellength;
            $_SESSION['boxes'] = $this->ap_shipping_num_boxes;

            // ---- Check for maximum length allowed ----------------------- //

            if ($parcellength >= $MAXLENGTH_P) {
                echo '<br>ln' . __LINE__ . ' parcel length check - length=' . $parcellength . ' max length=' . $this->MAXLENGTH_P; // debug
                $this->error_msg_ap = ERROR_MAX_LENGTH_MSG;
                $cost = $this->_get_error_cost($dest_country, $this->error_msg_ap);

                if ($this->enabled == FALSE)
                    return;    // no quote

                $methods[] = array('id' => $this->code, 'title' => $this->error_msg_ap, 'cost' => $cost); // update method
                $this->quotes['methods'] = $methods;   // set it
                $parcellength = 0;
                return $this->quotes;
            }  // ---- exceeds AustPost maximum length. No point in continuing. //

            // ---- Check cubic volume ------------------------------------- //
            if ($itemcube > $MAXCUBIC_P) {
                $this->error_msg_ap = ERROR_MAX_CUBIC_MSG;
                $cost = $this->_get_error_cost($dest_country, $this->error_msg_ap);

                if ($this->enabled == FALSE)
                    return;   // no quote

                $methods[] = array('id' => $this->code, 'title' => $this->error_msg_ap, 'cost' => $cost);   // update method
                $this->quotes['methods'] = $methods;   // set it
                $itemcube = 0;
                return $this->quotes;
            }  // ---- exceeds AustPost maximum cubic volume. No point in continuing.  //

            if ($parcelweight > $this->MAXWEIGHT_P) {
                $this->error_msg_ap = ERROR_MAX_WEIGHT_MSG . ' of ' . $this->MAXWEIGHT_P . 'kg'; // append parcel weight
                $cost = $this->_get_error_cost($dest_country, $this->error_msg_ap);

                if ($this->enabled == FALSE)
                    return;   // no quote

                $methods[] = array('id' => $this->code, 'title' => $this->error_msg_ap, 'cost' => $cost);   // update method
                $this->quotes['methods'] = $methods;   // set it
                $parcelweight = 0;
                return $this->quotes;
            }  // ---- exceeds AustPost maximum weight. No point in continuing. //

            // ---- Check to see if cache is useful ------------------------ //
            if (USE_CACHE == "Yes") {   // DEBUG  NOTE disable cache for testing
                if (isset($_SESSION['aupostParcel'])) {
                    $test = explode(",", $_SESSION['aupostParcel']);

                    if (
                        ($test[0] == $dest_country) &&
                        ($test[1] == $topcode) &&
                        ($test[2] == $parcelwidth) &&
                        ($test[3] == $parcelheight) &&
                        ($test[4] == $parcellength) &&
                        ($test[5] == $parcelweight) &&
                        ($test[6] == $ordervalue)
                    ) {
                        if (MODULE_SHIPPING_AUPOST_DEBUG == "Yes") {
                            $this->_debug_output("n", "<center><table border=1 width=95% ><td align=center><font color=\"#FF0000\">Using Cached quotes </font></td></table></center>", "");
                            echo "<center><table border=1 width=95% ><td align=center><font color=\"#FF0000\">Using Cached quotes </font></td></table></center>";
                        }

                        $this->quotes = isset($_SESSION['aupostQuotes']) ? $_SESSION['aupostQuotes'] : null;
                        return $this->quotes;
                        // ---- Cache Exit Point ------------------------------- //
                    } // No cache match -  get new quote from server //
                }  // No cache session -  get new quote from server //
            } // end cache option

            // ---- always save new session ------------------------------------ //
            $_SESSION['aupostParcel'] = implode(",", array($dest_country, $topcode, $parcelwidth, $parcelheight, $parcellength, $parcelweight, $ordervalue));
            $shipping_weight = $parcelweight;  // global value for zencart

            $dcode = ($dest_country == "AU") ? $topcode : $dest_country; // Set destination code ( postcode if AU, else 2 char iso country code )

            if (!$dcode)
                $dcode = SHIPPING_ORIGIN_ZIP;           // if no destination postcode - eg first run, set to local postcode

            $flags = ((MODULE_SHIPPING_AUPOST_HIDE_PARCEL == "No") || (MODULE_SHIPPING_AUPOST_DEBUG == "Yes")) ? 0 : 1;

            $aupost_url_string = AUPOST_URL_PROD;   // Server query string //
            // if test mode replace with test variables - url + api key
            if (AUPOST_MODE == 'Test') {
                $aupost_url_apiKey = AUPOST_TESTMODE_AUTHKEY;
            }
            if (MODULE_SHIPPING_AUPOST_DEBUG == "Yes") {            // debug  display table
                echo ' <center> <table class="aupost-debug-table" border=1 >
                    <tr>
                        <th width=15% > ln' . __LINE__ . ' Parcel dims sent </th>
                        <td> Length sent=' . $parcellength . '; Width sent=' . $parcelwidth . '; Height sent=' . $parcelheight . '; Weight sent=' . $parcelweight . '; Order value sent=' . $ordervalue . ' </td>
                    </tr> </table></center> ';                      // parcel height has been rounded up above for pricing accuracy in  packet adjustment

                echo '<center> <table class="aupost-debug-table" border=1>
                    <tr> <th width=15%> Handling fees</th>
                        <td colspan=7> Parcel=' . MODULE_SHIPPING_AUPOST_RPP_HANDLING . '; Parcel Exp=' . MODULE_SHIPPING_AUPOST_EXP_HANDLING . '; Prepaid=' . MODULE_SHIPPING_AUPOST_PPS_HANDLING . '; Prepaid Exp=' . MODULE_SHIPPING_AUPOST_PPSE_HANDLING . ';' . '</td>
                    </tr> </table></center> <br>';

                if (BMH_MIN_ORDER_VALUE_DEBUG == "Yes") {
                    echo '<center> <table class="aupost-debug-table" border=1>
                    <tr > <th width=15%> Extra cover </th>
                        <td colspan=7> Forced on. Order value = ' . $MINVALUEEXTRACOVER + 1 .
                        '</td>  </tr>   </table></center> <br>';
                } // eof DEBUG display

            }

            if ((MODULE_SHIPPING_AUPOST_DEBUG == 'Yes') && (BMHDEBUG1 == 'Yes') && (BMH_P_DEBUG2 == 'Yes') && (BMH_P_DEBUG3 == 'Yes')) {
                $this->_debug_output("n", '<p class="aupost-debug"> <br> ln' . __LINE__ . ' n3 aupost parcels ***<br> ' . 'https://' . $aupost_url_string . PARCEL_URL_STRING . $frompcode . "&to_postcode=$dcode&length=$parcellength&width=$parcelwidth&height=$parcelheight&weight=$parcelweight" . '</p> ', "");
            }
            //
            // ---- get parcel api --------------------------------------------- //
            $qu = $this->get_auspost_api(
                'https://' . $aupost_url_string . PARCEL_URL_STRING . $frompcode . "&to_postcode=$dcode&length=$parcellength&width=$parcelwidth&height=$parcelheight&weight=$parcelweight"
            );

            $json = ($qu == '') ? [] : json_decode($qu, true); // If we have any results, parse them into a JSON array

            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == 'Yes') && (BMH_P_DEBUG2 == "Yes")) {
                $this->_debug_output("x", 'ln' . __LINE__ . ' x2 auPost - Server Returned BMH_P_DEBUG2:', $json);
            }

            if (str_contains(strtolower($qu ?? ''), "cubic")) {
                $this->error_msg_ap = ERROR_MAX_CUBIC_MSG;
                $cost = $this->_get_error_cost($dest_country, $this->error_msg_ap);
                if ($this->enabled == FALSE)
                    return;              // no quote

                $this->_log("ln" . __LINE__ . ' ' . $this->error_msg_ap . " Cust:" . $customer_id); // write to log file
                $methods[] = array('id' => $this->code, 'title' => $this->error_msg_ap, 'cost' => $cost);
                $this->quotes['methods'] = $methods;   // set it
                return $this->quotes;
            }
            // eof check for errors

            $maxcover = ($json['services']['service'][0]['max_extra_cover']);

            if ((int) $ordervalue_ori > (int) $maxcover) {  // cast to int
                $maxcoverexceeded = True; // set flag to indicate order value exceeds max extra cover for this option
            }

            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")) {
                $this->_debug_output("n", "ln " . __LINE__ . "n3 Max extra cover available = " . $maxcover, "");
                $this->_debug_output("n", "ln" . __LINE__ . "n3 Max cover exceed flag = " . $maxcoverexceeded, "");
            }

            // ---- Initialise our quotes['id'] required in includes/classes/shipping.php //
            $this->quotes = array('id' => $this->code, 'module' => $this->title);


            // ---- loop through the Parcel quotes retrieved --------------- //
            $i = 0;  // counter
            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")) {
                $this->_debug_output("d", "ln" . __LINE__ . 'd3 $this->allowed_methods = <br> ', $this->allowed_methods);
            }
            if (BMH_MIN_ORDER_VALUE_DEBUG == "Yes") {
                $ordervalue = $MINVALUEEXTRACOVER + 1;
            }                          //  to force extra cover value FOR TESTING ONLY; auto cover to $100

            //
            // ---- loop through each returned options ----------------------
            //
            // ---- normalise: Australia Post returns a single service as an object rather than an array ---- //
            if (isset($json['services']['service']) && !isset($json['services']['service'][0])) {
                $json['services']['service'] = [$json['services']['service']];
            }
            foreach ($json['services']['service'] as $foo => $bar) {
                $code = strval(($json['services']['service'][$i]['code']));
                $code = str_replace("_", " ", $code);
                $code = substr($code, 11);

                /* strip first 11 chars;  keep API code for label remove underscores from AusPost methods. Zen Cart uses
                *    underscore as delimiter between module and method. underscores must also be removed from case statements below.
                */
                $id = str_replace("_", "", strval($json['services']['service'][$i]['code']));

                $cost = (float) ($json['services']['service'][$i]['price']);

                $description = "PARCEL " . (ucwords(strtolower($code))); // prepend PARCEL to code in sentence case to identify as parcel and NOT a letter

                if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")) {
                    $this->_debug_output("n", "ln" . __LINE__ . " n3 ID= $id  DESC= $description, COST= $cost inc", "");
                } //  2nd level debug each line of quote parsed

                $add = 0;
                $f = 0;
                $info = 0;

                //
                // loop context shared by the common secondary-option logic (_process_parcel_option)
                //
                $pctx = [
                    'json' => $json,
                    'i' => $i,
                    'id' => $id,
                    'description' => $description,
                    'dest_country' => $dest_country,
                    'order' => $order,
                    'currencies' => $currencies,
                    'aus_rate' => $aus_rate,
                    'parcellength' => $parcellength,
                    'parcelwidth' => $parcelwidth,
                    'parcelheight' => $parcelheight,
                    'parcelweight' => $parcelweight,
                    'dcode' => $dcode,
                    'MINVALUEEXTRACOVER' => $MINVALUEEXTRACOVER,
                    'ordervalue_ori' => $ordervalue_ori,
                ];

            //
            // ---- case staements to match  returned options with owner allowed options set in admin
            //

                switch ($id) {
                    //
                    // Prepaid Satchels -- these come across from AP as Regular Satchel
                    //      ---- fall through and treat as one block
                    //
                    case "AUSPARCELREGULARSATCHELEXTRASMALL":           // Extra small
                    case "AUSPARCELREGULARSATCHEL250G":                 // Extra small satchel
                    case "AUSPARCELREGULARSATCHEL1KG":                  // Medium satchel
                    case "AUSPARCELREGULARSATCHELMEDIUM":               // Medium
                    case "AUSPARCELREGULARSATCHELLARGE":                // Large
                    //case "AUSPARCELREGULARSATCHEL3KG":                // Medium satchel
                    case "AUSPARCELREGULARSATCHELEXTRALARGE":           // Extra large
                    //case "AUSPARCELREGULARSATCHEL5KG":            // Large satchel

                        $parceltype_descriptor = "Prepaid Satchel";
                        //if (in_array("Prepaid Satchel", $this->allowed_methods, $strict = true)) {
                        if (in_array($parceltype_descriptor, $this->allowed_methods, true)) {
                            // check if  order amount exceeds AP maximum cover available for  postage type
                            if ($maxcoverexceeded === True) {
                                if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")  && (BMH_P_DEBUG3 == "Yes")) {
                                    $this->_debug_output("n", 'ln' . __LINE__ . ' n3 ' . $parceltype_descriptor . ' $maxcoverexceeded is True reset', ""); //
                                }
                                $ordervalue = $maxcover - 1;
                            } else {
                                $ordervalue = $ordervalue_ori;
                            }// reset if max extra cover exceeded

                            if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")) {
                                $this->_debug_output("n", "<br>ln" . __LINE__ . " n3 allowed option = " . $parceltype_descriptor, "");
                            }

                            $optioncode = "";
                            $optionservicecode = "";
                            $suboptioncode = "";
                            $allowed_option = "";
                            $add = MODULE_SHIPPING_AUPOST_PPS_HANDLING;
                            $f = 1;

                            if ((($cost > 0) && ($f == 1))) {
                                $cost = $cost + floatval($add);                         // string to float
                                if (MODULE_SHIPPING_AUPOST_CORE_WEIGHT == "Yes")
                                    $cost = ($cost * $this->ap_shipping_num_boxes);

                                // CALC TAX and remove from returned amt as tax is added back in on checkout
                                if (($dest_country == "AU") && (($this->tax_class) > 0)) {
                                    $t = $cost - ($cost / (zen_get_tax_rate((int) $this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']) + 1));
                                    if ($t > 0)
                                        $cost = $t;
                                }
                                $details = $this->_handling($details, $currencies, $add, $aus_rate, $info);  // check if handling rates included
                            }   // ---- eof list option for normal operation //
                            $cost = $cost / $aus_rate;

                            $methods[] = array('id' => "$id", 'title' => $description . " " . $details, 'cost' => $cost);   // update method
                        }

                        if ($this->_insured_plus_sig($methods, $ordervalue, $add, $details, $parceltype_descriptor, $pctx)) {
                            break;
                        }
                        if ($this->_plus_sig($methods, $ordervalue, $add, $details, $parceltype_descriptor, $pctx)) {
                            break;
                        }
                        if ($this->_insured_nosig($methods, $ordervalue, $add, $details, $parceltype_descriptor, $pctx)) {
                            break;
                        }
                        break;

                    //
                    // Prepaid express satchels -- these come across from AP as Express Satchel
                    //      ---- fall through and treat as one block
                    //
                    case "AUSPARCELEXPRESSSATCHELEXTRASMALL":           // Extra small satchel
                    //case "AUSPARCELEXPRESSSATCHEL250G":               // Extra small satchel - still returned sometimes
                    case "AUS_PARCELEXPRESSSATCHEL1KG":                 // Small-Medium satchel
                    case "AUSPARCELEXPRESSSATCHELMEDIUM":               // Medium
                    case "AUSPARCELEXPRESSSATCHELLARGE":                // Large satchel
                    //case "AUSPARCELEXPRESSSATCHEL3KG":                // Medium satchel
                    case "AUSPARCELEXPRESSSATCHELEXTRALARGE":           // Extra large
                    //case "AUSPARCELEXPRESSSATCHEL5KG":                // Large satchel

                        $parceltype_descriptor = 'Prepaid Express Satchel';
                        if ((in_array($parceltype_descriptor, $this->allowed_methods))) {
                            if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")  && (BMH_P_DEBUG3 == "Yes")) {
                                $this->_debug_output("n", 'ln' . __LINE__ . ' n3 allowed option = parcel express satchel', "");
                            }

                            if ($maxcoverexceeded === True) {
                                if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")) {
                                    $this->_debug_output("n", 'ln' . __LINE__ . ' n3' . $parceltype_descriptor . ' $maxcoverexceeded $ordervalue reset', "");
                                }
                                $ordervalue = $maxcover - 1;
                            } else {
                                $ordervalue = $ordervalue_ori;
                            }
                            ; // reset if max extra cover exceeded

                            $optioncode = "";
                            $optionservicecode = "";
                            $suboptioncode = "";
                            $add = MODULE_SHIPPING_AUPOST_PPSE_HANDLING;
                            $f = 1;

                            if ((($cost > 0) && ($f == 1))) { //
                                $cost = $cost + floatval($add);        // string to float
                                if (MODULE_SHIPPING_AUPOST_CORE_WEIGHT == "Yes")
                                    $cost = ($cost * $this->ap_shipping_num_boxes);

                                // CALC TAX and remove from returned amt as tax is added back in on checkout
                                if (($dest_country == "AU") && (($this->tax_class) > 0)) {
                                    $t = $cost - ($cost / (zen_get_tax_rate((int) $this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']) + 1));
                                    if ($t > 0)
                                        $cost = $t;
                                }
                                $details = $this->_handling($details, $currencies, $add, $aus_rate, $info);  // check if handling rates included
                            }   // eof list option for normal operation
                            $cost = $cost / $aus_rate;

                            $methods[] = array('id' => "$id", 'title' => $description . " " . $details, 'cost' => $cost);   // update method
                        }

                        if ($this->_insured_plus_sig($methods, $ordervalue, $add, $details, $parceltype_descriptor, $pctx)) {
                            break;
                        }
                        if ($this->_plus_sig($methods, $ordervalue, $add, $details, $parceltype_descriptor, $pctx, self::MAXCOVER_BREAK)) {
                            break;
                        }
                        if ($this->_insured_nosig($methods, $ordervalue, $add, $details, $parceltype_descriptor, $pctx, self::MAXCOVER_BREAK, false)) {
                            break;
                        }
                        break;

                    //
                    // Regular parcels - own packaging --
                    //          --- fall through and treat as one block
                    //
                    case "AUSPARCELREGULAR":                                // normal mail - own packaging

                        $parceltype_descriptor = 'Regular Parcel';
                        if (in_array($parceltype_descriptor, $this->allowed_methods, $strict = true)) {

                            if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")) {
                                $this->_debug_output("n", '<br>ln' . __LINE__ . ' n3 allowed option = parcel regular', "");
                            }

                            if ($maxcoverexceeded === True) {
                                if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")) {
                                    $this->_debug_output("n", '<p class="aupost-debug">ln' . __LINE__ . ' n3 ' . $parceltype_descriptor . ' $maxcoverexceeded reset', ""); // ** DEBUG
                                }
                                $ordervalue = $maxcover - 1;
                            } else {
                                $ordervalue = $ordervalue_ori;
                            }                           // reset if max extra cover exceeded
                            $ordervalue = $ordervalue_ori;

                            $optioncode = "";
                            $optionservicecode = "";
                            $suboptioncode = "";
                            $allowed_option = "";
                            $add = MODULE_SHIPPING_AUPOST_RPP_HANDLING;
                            $f = 1;
                            $apr = 1;

                            if ((($cost > 0) && ($f == 1))) { //
                                $cost = $cost + floatval($add);        // string to float
                                if (MODULE_SHIPPING_AUPOST_CORE_WEIGHT == "Yes")
                                    $cost = ($cost * $this->ap_shipping_num_boxes);

                                // CALC TAX and remove from returned amt as tax is added back in on checkout
                                if (($dest_country == "AU") && (($this->tax_class) > 0)) {
                                    $t = $cost - ($cost / (zen_get_tax_rate((int) $this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']) + 1));
                                    if ($t > 0)
                                        $cost = $t;
                                }
                                 $details = $this->_handling($details, $currencies, $add, $aus_rate, $info);  // check if handling rates included
                            }   // eof list option for normal operation
                            $cost = $cost / $aus_rate;

                            $methods[] = array('id' => "$id", 'title' => $description . " " . $details, 'cost' => $cost);   // update method
                        }

                        if ($this->_insured_plus_sig($methods, $ordervalue, $add, $details, $parceltype_descriptor, $pctx)) {
                            break;
                        }
                        if ($this->_plus_sig($methods, $ordervalue, $add, $details, $parceltype_descriptor, $pctx)) {
                            break;
                        }
                        if ($this->_insured_nosig($methods, $ordervalue, $add, $details, $parceltype_descriptor, $pctx)) {
                            break;
                        }

                        break;

                    // Express  parcels - own packaging
                    //      --- fall through and treat as one block
                    //
                    case "AUSPARCELEXPRESS":                                // express mail - own packaging

                        $parceltype_descriptor = 'Express Parcel';
                        if (in_array($parceltype_descriptor, $this->allowed_methods, $strict = true)) {

                            if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")  && (BMH_P_DEBUG3 == "Yes")) {
                                $this->_debug_output("n", '<br>ln' . __LINE__ . ' n3 allowed option = ' . $parceltype_descriptor, "");
                            }

                            if ($maxcoverexceeded === True) {
                                if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")) {
                                    $this->_debug_output("n", '<p class="aupost-debug">ln' . __LINE__ . ' n3 ' . $parceltype_descriptor . ' $maxcoverexceeded reset', ""); // ** DEBUG
                                }
                                $ordervalue = $maxcover - 1;
                            } else {
                                $ordervalue = $ordervalue_ori;
                            }                           // reset if max extra cover exceeded
                            $ordervalue = $ordervalue_ori;


                            $optioncode = "";
                            $optionservicecode = "";
                            $suboptioncode = "";
                            $allowed_option = "";
                            $add = MODULE_SHIPPING_AUPOST_RPP_HANDLING;
                            $f = 1;
                            $apr = 1;

                            if ((($cost > 0) && ($f == 1))) { //
                                $cost = $cost + floatval($add);        // string to float
                                if (MODULE_SHIPPING_AUPOST_CORE_WEIGHT == "Yes")
                                    $cost = ($cost * $this->ap_shipping_num_boxes);

                                // CALC TAX and remove from returned amt as tax is added back in on checkout
                                if (($dest_country == "AU") && (($this->tax_class) > 0)) {
                                    $t = $cost - ($cost / (zen_get_tax_rate((int) $this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']) + 1));
                                    if ($t > 0)
                                        $cost = $t;
                                }
                                 $details = $this->_handling($details, $currencies, $add, $aus_rate, $info);  // check if handling rates included
                            }   // eof list option for normal operation
                            $cost = $cost / $aus_rate;

                            $methods[] = array('id' => "$id", 'title' => $description . " " . $details, 'cost' => $cost);   // update method
                        }

                        if ($this->_insured_plus_sig($methods, $ordervalue, $add, $details, $parceltype_descriptor, $pctx)) {
                            break;
                        }
                        if ($this->_plus_sig($methods, $ordervalue, $add, $details, $parceltype_descriptor, $pctx)) {
                            break;
                        }
                        if ($this->_insured_nosig($methods, $ordervalue, $add, $details, $parceltype_descriptor, $pctx)) {
                            break;
                        }
                        break;

                        // eof express parcels
                        //


                    // NEW CODE for flat rate packaging range  which require AP MyPost Business Flat Rate satchels - only available in bulk
                    // Described as 'Flat Rate packaging' but returned from Aust Post API as  ' ... regular package ...'
                    //
                    // Flat rate package
                    //      ---- fall through and treat as one block
                    //

                    case "AUSPARCELREGULARPACKAGE":                         // requires additional AP packaging normal mail
                    case "AUSPARCELREGULARPACKAGEEXTRASMALL":               // Extra small + requires additonal AP packaging takes up to 5kg; Int Dim:270 x 180mm
                    case "AUSPARCELREGULARPACKAGESMALL":                    // small + requires additonal AP packaging takes up to 5kg; Dim:240 x 340mm
                    case "AUSPARCELREGULARPACKAGEMEDIUM":                   // Medium
                    case "AUSPARCELREGULARPACKAGELARGE":                    // Large

                        $parceltype_descriptor = 'Flat rate packaging';
                        if (in_array($parceltype_descriptor, $this->allowed_methods, $strict = true)) {

                            if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")  ) {
                                $this->_debug_output("n", '<br>ln' . __LINE__ . ' n3 allowed option = FR package regular', "");
                            }

                            if ($maxcoverexceeded === True) {
                                if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")) {
                                    $this->_debug_output("n", '<p class="aupost-debug">ln' . __LINE__ . ' n3 ' . $parceltype_descriptor . ' $maxcoverexceeded reset', ""); // ** DEBUG
                                }
                                $ordervalue = $maxcover - 1;
                            } else {
                                $ordervalue = $ordervalue_ori;
                            }                           // reset if max extra cover exceeded
                            $ordervalue = $ordervalue_ori;


                            $optioncode = "";
                            $optionservicecode = "";
                            $suboptioncode = "";
                            $allowed_option = $parceltype_descriptor;
                            $add = MODULE_SHIPPING_AUPOST_FRP_HANDLING;
                            $f = 1;
                            $apr = 1;

                            if ((($cost > 0) && ($f == 1))) { //
                                $cost = $cost + floatval($add);        // string to float
                                if (MODULE_SHIPPING_AUPOST_CORE_WEIGHT == "Yes")
                                    $cost = ($cost * $this->ap_shipping_num_boxes);

                                // CALC TAX and remove from returned amt as tax is added back in on checkout
                                if (($dest_country == "AU") && (($this->tax_class) > 0)) {
                                    $t = $cost - ($cost / (zen_get_tax_rate((int) $this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']) + 1));
                                    if ($t > 0)
                                        $cost = $t;
                                }

                                 $details = $this->_handling($details, $currencies, $add, $aus_rate, $info);  // check if handling rates included
                            }   // eof list option for normal operation
                            $cost = $cost / $aus_rate;

                            $methods[] = array('id' => "$id", 'title' => $description . " " . $details, 'cost' => $cost);   // update method

                            if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")  && (BMH_P_DEBUG3 == "Yes")) {
                                $array_dump = array('id' => "$id", 'title' => $description . " " . $details , 'cost' => $cost);
                                $this->_debug_output("d", '<br>ln' . __LINE__ . 'd3 FRP method= ', $array_dump);
                            }
                        }

                        //
                        // Flat rate packaging Insured +sig
                        if ($this->_insured_plus_sig($methods, $ordervalue, $add, $details, $parceltype_descriptor, $pctx)) {
                            break;
                        }
                        if ($this->_plus_sig($methods, $ordervalue, $add, $details, $parceltype_descriptor, $pctx)) {
                            break;
                        }
                        if ($this->_insured_nosig($methods, $ordervalue, $add, $details, $parceltype_descriptor, $pctx)) {
                            break;
                        }
                    break;

                    // eof NEW CODE for flat rate


                    //
                    // Express Flat rate
                    // Described as 'Express Flat Rate packaging' but returned from Aust Post API as  ' ... express package ...'
                    // uses same physical packaging as AP MyPost Business Flat Rate packaging - (only available in bulk) + with Express label attached at time of post
                    // ---- fall through and treat as one block ------------ //
                    //
                    case "AUSPARCELEXPRESSPACKAGEEXTRASMALL":             // Extra small
                    case "AUSPARCELEXPRESSPACKAGE":                                // Express Post - own packaging
                    case "AUSPARCELEXPRESSPACKAGESMALL":                    // small - really flat rate small + express label
                    case "AUSPARCELEXPRESSPACKAGEMEDIUM":             // Medium
                    case "AUSPARCELEXPRESSPACKAGELARGE":              // Large

                        $parceltype_descriptor = 'Express Flat rate packaging';
                        if (in_array($parceltype_descriptor, $this->allowed_methods, $strict = true)) {

                            if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")) {
                                $this->_debug_output("n", '<br>ln' . __LINE__ . ' n3 allowed option = ' . $parceltype_descriptor, "");
                            }
                            $optioncode = "";
                            $optionservicecode = "";
                            $suboptioncode = "";
                            $allowed_option = "";
                            $add = MODULE_SHIPPING_AUPOST_EXP_FRP_HANDLING;

                            $f = 1;
                            // got all of the values // -----------

                            if ((($cost > 0) && ($f == 1))) { //
                                $cost = $cost + floatval($add);        // string to float
                                if (MODULE_SHIPPING_AUPOST_CORE_WEIGHT == "Yes")
                                    $cost = ($cost * $this->ap_shipping_num_boxes);

                                // CALC TAX and remove from returned amt as tax is added back in on checkout
                                if (($dest_country == "AU") && (($this->tax_class) > 0)) {
                                    $t = $cost - ($cost / (zen_get_tax_rate((int) $this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']) + 1));
                                    if ($t > 0)
                                        $cost = $t;
                                }
                                // //  ++++

                                 $details = $this->_handling($details, $currencies, $add, $aus_rate, $info);  // check if handling rates included
                                // //  ++++

                            }   // eof list option for normal operation
                            $cost = $cost / $aus_rate;
                            $methods[] = array('id' => "$id", 'title' => $description . " " . $details, 'cost' => $cost);   // update method

                            if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")) {
                                $this->_debug_output("n", '<br>ln' . __LINE__ . ' n3 method = ' . json_encode(array('id' => "$id", 'title' => $description . " " . $details, 'cost' => $cost)), "");
                            }
                        }

                        if ($this->_insured_plus_sig($methods, $ordervalue, $add, $details, $parceltype_descriptor, $pctx, self::MAXCOVER_NONE, true)) {
                            break;
                        }
                        if ($this->_plus_sig($methods, $ordervalue, $add, $details, $parceltype_descriptor, $pctx, self::MAXCOVER_RESET, true, "AUSPARCELEXPRESS" . "AUSSERVICEOPTIONSIGNATUREONDELIVERY")) {
                            break;
                        }
                        if ($this->_insured_nosig($methods, $ordervalue, $add, $details, $parceltype_descriptor, $pctx, self::MAXCOVER_BREAK, false, true)) {
                            break;
                        }
                    break;

                    //
                    // Left overs
                    //
                    case "AUSPARCELEXPRESSSATCHEL1KG":          // still returned but superceded by AUSPARCELEXPRESSSATCHELSMALL
                    case "AUSPARCELEXPRESSSATCHEL500G":         // still returned but superceded by AUSPARCELEXPRESSSATCHELSMALL
                    case "AUSPARCELREGULARSATCHEL500G":         // still returned but superceded by AUSPARCELREGULARSATCHELSMALL

                        $parceltype_descriptor = "";           // reset
                        $optioncode = "";
                        $optionservicecode = "";
                        $suboptioncode = "";

                        $cost = 0;
                        $f = 0;
                        $add = 0;
                        // echo "shouldn't be here";
                        //do nothing - ignore the code

                    if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")) {
                            $this->_debug_output("n", 'ln' . __LINE__ . ' d3 ID= $id  DESC= $description COST= $cost', "");

                        } //  2nd level debug each line of quote parsed
                    break;
                }  // ---- eof switch -------------------------------------- //

                // ---- only list valid options without debug info --------- //
                if ((($cost > 0) && ($f == 1))) {               //
                    $cost = $cost + floatval($add);             // string to float
                    if (MODULE_SHIPPING_AUPOST_CORE_WEIGHT == "Yes")
                        $cost = ($cost * $this->ap_shipping_num_boxes);

                    $details = $this->_handling($details, $currencies, $add, $aus_rate, $info);  // check if handling rates included
                }   // eof list option for normal operation

                $cost = $cost / $aus_rate;

                if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")) {
                    //$this->_debug_output("n", '<br>ln' . __LINE__ . ' n2 $i= ', $i);
                } // ---- 3rd level debug each line of quote parsed -------- //

                $i++; // ---- increment the counter to match json array index //
            }  // ---- end foreach loop ------------------------------------ //

            //
            // ---- check to ensure we have at least one valid quote - produce error message if not.
            //
            if ((is_array($methods)) && (count($methods) == 0)) {                   // no valid methods
                $error_msg_ap = ERROR_NO_VALID_PARCEL_QUOTE_MSG;                    //
                $cost = $this->_get_error_cost($dest_country, $error_msg_ap);       // give default cost

                if ($this->enabled == FALSE)
                    return;                      // if not enabled then exit with no quote
                $methods[] = array('id' => "Error", 'title' => MODULE_SHIPPING_AUPOST_TEXT_ERROR, 'cost' => $cost); // display reason
            }

            //
            // ---- sort array by cost, remove zeros and duplicates ---------- //
            /*
            * Zero removal: Now filters inline — only adds entries where $value['cost'] > 0.
            * removes entries where the cost value matches an earlier entry, keeping only the first occurrence.
            * Changed from the two-pass asort() on a separate array to a single usort() directly on the methods array .
            * The pipeline is now: filter zeros → sort by cost → remove full-entry duplicates → remove same-cost dupliates → re-index.*
            */
            $resultarr = [];

            foreach ($methods as $key => $value) {
                if ($value['cost'] > 0) {
                    $resultarr[$key] = $value;
                }
            }

            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == "Yes")   && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes") && (BMH_P_DEBUG4 == "Yes")) {
                $this->_debug_output("d", 'ln' . __LINE__ . ' d4 $resultarr= ', $resultarr);
            } //  4th level debug of unsorted quotes


            usort($resultarr, fn($a, $b) => $a['cost'] <=> $b['cost']); // new sort

            $resultarr = array_unique($resultarr, SORT_REGULAR);  // remove full-entry duplicates

            $seenCosts = [];
            foreach ($resultarr as $key => $value) {
                $ck = (string) $value['cost'];
                if (isset($seenCosts[$ck])) {
                    unset($resultarr[$key]);
                } else {
                    $seenCosts[$ck] = true;
                }
            }

            // ---- end sort ---------------------------------

            $this->quotes['methods'] = array_values($resultarr);  // re-index
            /**
             * Change "Regular Package" to "Flat Rate"  ie the AP returned description to the AP advertised description
             *  a loop now iterates over each method in $this->quotes['methods'] and replaces " Regular Package" with " Flat rate"
             * in the title, leaving the rest of the string intact. The &$method reference and unset($method) ensure clean
             * modification without side effects.
             */
            foreach ($this->quotes['methods'] as &$method) {
                if (isset($method['title']) && strpos($method['title'], ' Regular Package') !== false) {
                    $method['title'] = str_replace(' Regular Package', ' Flat rate', $method['title']);
                }
            }
            unset($method);

            if ($this->tax_class > 0) {
                $this->quotes['tax'] = zen_get_tax_rate((int) $this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
            }

            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMH_P_DEBUG3 == "Yes")) {
                $this->_debug_output("n", '<br>ln' . __LINE__ . ' n3 parcels ***<br>aupost l ' . 'https://' . $aupost_url_string . PARCEL_URL_STRING .
                    $frompcode . "&to_postcode=$dcode&length=$parcellength&width=$parcelwidth&height=$parcelheight&weight=$parcelweight" . '</p>', "");
            }
            if (zen_not_null($this->icon))
                $this->quotes['icon'] = zen_image($this->icon, $this->title);
            $_SESSION['aupostQuotes'] = $this->quotes;                  // save as session to avoid reprocessing when single method required

            return $this->quotes;                                       // all done //

            // ---- Final Exit Point --------------------------------------- //
        } // ---- eof function quote method -------------------------------- //
    }

    /**
     * Validates an Australian postcode for AU addresses. Resets postcode in $order if invalid.
     * @param string $postcode
     * @param string $country
     * @param array|object $order (passed by reference)
     * @return bool
     */
    protected function validate_au_postcode(string $postcode, string $country, object &$order): bool
    {
        if ($country !== 'AU') {
            return false;
        }
        // Strip spaces/whitespace early and write back so the field reflects it on reload
        $postcode = preg_replace('/\s+/', '', $postcode);
        $order->delivery['postcode'] = $postcode;

        if ($postcode === '') {
            return false;
        }

        // Must be exactly 4 digits
        if (!preg_match('/^\d{4}$/', $postcode)) {
            $order->delivery['postcode'] = '';
            return false;
        }
        // Valid Australian postcode ranges per Australia Post:
        // ACT:      0200–0299, 2600–2618, 2900–2920
        // NSW:      1000–1999, 2000–2599, 2619–2899, 2921–2999
        // NT:       0800–0899, 0900–0999
        // QLD:      4000–4999, 9000–9999
        // SA:       5000–5999
        // TAS:      7000–7999
        // VIC:      3000–3999, 8000–8999
        // WA:       6000–6999
        $intPostcode = (int) $postcode;

        $validRanges = [
            [200, 299],   // ACT (unique PO boxes/locked bags)
            [800, 999],   // NT
            [1000, 1999],  // NSW (LVRs/PO boxes)
            [2000, 2599],  // NSW
            [2600, 2618],  // ACT
            [2619, 2899],  // NSW
            [2900, 2920],  // ACT
            [2921, 2999],  // NSW
            [3000, 3999],  // VIC
            [4000, 4999],  // QLD
            [5000, 5999],  // SA
            [6000, 6999],  // WA
            [7000, 7999],  // TAS
            [8000, 8999],  // VIC (LVRs/PO boxes)
            [9000, 9999],  // QLD (LVRs/PO boxes)
        ];

        foreach ($validRanges as [$min, $max]) {
            if ($intPostcode >= $min && $intPostcode <= $max) {
                return true;
            }
        }

        $order->delivery['postcode'] = '';
        return false;
    } /* end validate_au_postcode */

    /**
     * Summary of _get_secondary_options
     * @param mixed $add
     * @param mixed $allowed_option
     * @param mixed $ordervalue
     * @param mixed $MINVALUEEXTRACOVER
     * @param mixed $dcode
     * @param mixed $parcellength
     * @param mixed $parcelwidth
     * @param mixed $parcelheight
     * @param mixed $parcelweight
     * @param mixed $optionservicecode
     * @param mixed $optioncode
     * @param mixed $suboptioncode
     * @param mixed $id_option
     * @param mixed $description
     * @param mixed $details
     * @param mixed $dest_country
     * @param mixed $order
     * @param mixed $currencies
     * @param mixed $aus_rate
     * @param mixed $shipping_num_boxes
     * @return array{cost: float, id: mixed, title: string|array{cost: int, id: string, title: string}}
     */
    private function _get_secondary_options(
        $add,
        $allowed_option,
        $ordervalue,
        $MINVALUEEXTRACOVER,
        $dcode,
        $parcellength,
        $parcelwidth,
        $parcelheight,
        $parcelweight,
        $optionservicecode,
        $optioncode,
        $suboptioncode,
        $id_option,
        $description,
        $details,
        $dest_country,
        $order,
        $currencies,
        $aus_rate,
        $shipping_num_boxes
    ) {
        global $frompcode;
        global $customer_id;
        global $maxcoverexceeded;
        global $maxcover;
        $aupost_url_string = AUPOST_URL_PROD;  // Server query string //

        // Ensure we always return a well-formed result even if no secondary options are applicable
        $result_secondary_options = array("id" => '', "title" => '', "cost" => 0.0);

        //if ($maxcoverexceeded === True) {
        //    $ordervalue = $maxcover - 1;
        //}
        //; // skip if max extra cover exceeded


        if ((in_array($allowed_option, $this->allowed_methods))) {
            //$add = MODULE_SHIPPING_AUPOST_RPP_HANDLING ;
            $f = 1;

            $ordervalue = ceil($ordervalue);  // round up to next integer
            //$parcellength = (int) $parcellength;
            //$parcelwidth = (int) $parcelwidth;
            //$parcelheight = (int) $parcelheight;

            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")) {
                $this->_debug_output("n", '<br>ln' . __LINE__ . ' n3 [get_secondary_options] allowed option = ' . $allowed_option . PARCEL_URL_STRING_CALC . $frompcode .
                    "&to_postcode=$dcode&length=$parcellength&width=$parcelwidth&height=$parcelheight&weight=$parcelweight
&service_code=$optionservicecode&option_code=$optioncode&suboption_code=$suboptioncode&extra_cover=$ordervalue", "");
            }

            $qu2 = $this->get_auspost_api('https://' . $aupost_url_string . PARCEL_URL_STRING_CALC . $frompcode . "&to_postcode=$dcode&length=$parcellength&width=$parcelwidth&height=$parcelheight&weight=$parcelweight&service_code=$optionservicecode&option_code=$optioncode&suboption_code=$suboptioncode&extra_cover=$ordervalue");

            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes") && (BMH_P_DEBUG4 == "Yes")) {
                $this->_debug_output("n", '<br> ln' . __LINE__ . ' n4 $qu2 = ' . $qu2, "");
                $this->_debug_output("n", '<br> ln' . __LINE__ . ' n4 $details = ' . $details, "");
            }

            $jsonquote_2 = ($qu2 == '') ? array() : json_decode($qu2, true); // JSON format

            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")  && (BMH_P_DEBUG4 == "Yes")) {
                $this->_debug_output("x", '<br> ln' . __LINE__ . ' x4  $allowed_option = ' . $allowed_option . ' <br> ' . 'Server Returned options :', $jsonquote_2);
            }

            $invalid_option = isset($jsonquote_2->errorMessage) ? $jsonquote_2->errorMessage : null;
            // trap error  Undefined array key "postage_result"

            if (!empty($invalid_option)) {
                $this->_log("ln" . __LINE__ . ' $invalid_option=' . $invalid_option . " #" . " Cust:" . $customer_id);
            }

            if (empty($invalid_option)) {

                if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes") && (BMH_P_DEBUG4 == "Yes")) {
                    $this->_debug_output("x", '<br> ln' . __LINE__ . ' x4 $invalid_option is empty  $jsonquote_2 = ', $jsonquote_2);
                }

                $desc_option = $allowed_option;

                $cost_option = $jsonquote_2['postage_result']['total_cost']; // check secondary JSON format for cost inc option

                // got all of the option values ---------------------------- //
                $cost = $cost_option;

                if ((($cost > 0) && ($f == 1))) { //
                    $cost = $cost + floatval($add);        // string to float
                    if (MODULE_SHIPPING_AUPOST_CORE_WEIGHT == "Yes")
                        $cost = ($cost * $shipping_num_boxes);

                    // CALC TAX and remove from returned amt as tax is added back in on checkout
                    if (($dest_country == "AU") && (($this->tax_class) > 0)) {
                        $t = $cost - ($cost / (zen_get_tax_rate((int) $this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']) + 1));
                        if ($t > 0)
                            $cost = $t;
                    }

                    $info = 0;  // Dummy used for REG POST - MAY BE REDUNDANT

                    $details = $this->_handling($details, $currencies, $add, $aus_rate, $info);  // check if handling rates included

                }   // ---- eof list option for normal operation ----------- //
                $cost = $cost / $aus_rate;
                // round the cost to 2 decimal places
                $rounded = round($cost, 2);
                $cost = $rounded;


                $desc_option = "[" . $desc_option . "]";         // delimit option in square brackets

                /**
                 * change returned description line to remove main description option
                 */
                $result_secondary_options = array("id" => $id_option, "title" => $description . ' ' . $desc_option . ' ' . $details, "cost" => $cost);

            } // valid result
            else {      // pass back a zero value as not a valid option from Australia Post eg extra cover may require a signature as well
                $cost = 0;
                $result_secondary_options = array("id" => '', "title" => '', "cost" => $cost);  // invalid result
            }
        }   // eof // Express plus options

        return $result_secondary_options;
    } // ---- eof function _get_secondary_options -------------------------- //

    /**
     * Shared logic for the three secondary service-option quote blocks (Insured +sig / +sig / Insured (no sig)).
     * These were previously duplicated inside every parcel-type case of quote(). The parcel-type specific
     * behaviour is supplied by $option_suffix, $optioncode, $suboptioncode and $mode.
     *
     * @return bool true when the caller should break out of the switch (max extra cover exceeded)
     */
    private function _process_parcel_option(
        array &$methods,
        &$ordervalue,
        $add,
        string $details,
        string $parceltype_descriptor,
        string $option_suffix,
        string $optioncode,
        string $suboptioncode,
        array $pctx,
        int $mode,
        bool $gate = true,
        bool $strict = false,
        ?string $override_id_option = null,
        bool $dump_result = false,
        bool $omit_optioncode = false
    ): bool {
        global $maxcoverexceeded, $maxcover;

        $allowed_option = $parceltype_descriptor . $option_suffix;

        // skip when the admin has not selected this optional method
        if (!in_array($allowed_option, $this->allowed_methods, $strict)) {
            return false;
        }

        if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")  && (BMH_P_DEBUG3 == "Yes")  && (BMH_P_DEBUG4 == "Yes")) {
            $this->_debug_output("n", '<br>ln' . __LINE__ . " n4 secondary option = " . $allowed_option, "");
        }

        switch ($mode) {
            case self::MAXCOVER_NONE:
                break;

            case self::MAXCOVER_NOCHANGE:
                $ordervalue = $pctx['ordervalue_ori'];
                if ($maxcoverexceeded === True) {
                    if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")  && (BMH_P_DEBUG4 == "Yes")) {
                        $this->_debug_output("n", ' n4 ln' . __LINE__ . ' ' . $allowed_option . ' max extra cover exceeded - order value held at original', ""); // ** DEBUG
                    }
                }
                break;

            case self::MAXCOVER_RESET:
                if ($maxcoverexceeded === True) {
                    if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")  && (BMH_P_DEBUG4 == "Yes")) {
                        $this->_debug_output("n", ' n4 ln' . __LINE__ . ' ' . $allowed_option . ' max extra cover exceeded - order value reset to max cover', ""); // ** DEBUG
                    }
                    $ordervalue = $maxcover - 1;
                } else {
                    $ordervalue = $pctx['ordervalue_ori'];
                }
                break;

            case self::MAXCOVER_BREAK:
                if ($maxcoverexceeded === True) {
                    if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")  && (BMH_P_DEBUG4 == "Yes")) {
                        $this->_debug_output("n", ' n4 ln' . __LINE__ . ' ' . $allowed_option . ' max extra cover exceeded - option skipped', ""); // ** DEBUG
                    }
                    return true;
                }
                $ordervalue = $pctx['ordervalue_ori'];
                break;
        }

        // some options are only offered above the minimum extra-cover order value
        if ($gate && $ordervalue <= $pctx['MINVALUEEXTRACOVER']) {
            return false;
        }

        $optionservicecode = $pctx['json']['services']['service'][$pctx['i']]['code'];

        if ($override_id_option !== null) {
            $id_option = $override_id_option;
        } else {
            $id_option = ($omit_optioncode ? $pctx['id'] : $pctx['id'] . str_replace("_", "", $optioncode)) . str_replace("_", "", $suboptioncode);
        }

        $result_secondary_options = $this->_get_secondary_options(
            $add,
            $allowed_option,
            $ordervalue,
            $pctx['MINVALUEEXTRACOVER'],
            $pctx['dcode'],
            $pctx['parcellength'],
            $pctx['parcelwidth'],
            $pctx['parcelheight'],
            $pctx['parcelweight'],
            $optionservicecode,
            $optioncode,
            $suboptioncode,
            $id_option,
            $pctx['description'],
            $details,
            $pctx['dest_country'],
            $pctx['order'],
            $pctx['currencies'],
            $pctx['aus_rate'],
            $this->ap_shipping_num_boxes
        );

        if ($dump_result && (MODULE_SHIPPING_AUPOST_DEBUG == "Yes") && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")  && (BMH_P_DEBUG4 == "Yes")) {
            $this->_debug_output("d", '<br>ln' . __LINE__ . ' d4 $result_secondary_options = ', $result_secondary_options);
        }

        if (strlen($pctx['id']) > 1) {
            $methods[] = $result_secondary_options;
        }

        return false;
    }

    /**
     * 'Insured +sig' secondary option for the current parcel type.
     */
    private function _insured_plus_sig(array &$methods, &$ordervalue, $add, string $details, string $parceltype_descriptor, array $pctx, int $mode = self::MAXCOVER_NOCHANGE, bool $strict = false): bool
    {
        return $this->_process_parcel_option(
            $methods,
            $ordervalue,
            $add,
            $details,
            $parceltype_descriptor,
            " Insured +sig",
            'AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY',
            'AUS_SERVICE_OPTION_EXTRA_COVER',
            $pctx,
            $mode,
            true,
            $strict
        );
    }

    /**
     * '+sig' secondary option for the current parcel type.
     */
    private function _plus_sig(array &$methods, &$ordervalue, $add, string $details, string $parceltype_descriptor, array $pctx, int $mode = self::MAXCOVER_RESET, bool $strict = false, ?string $override_id_option = null): bool
    {
        return $this->_process_parcel_option(
            $methods,
            $ordervalue,
            $add,
            $details,
            $parceltype_descriptor,
            " +sig",
            'AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY',
            '',
            $pctx,
            $mode,
            false,
            $strict,
            $override_id_option
        );
    }

    /**
     * 'Insured (no sig)' secondary option for the current parcel type.
     */
    private function _insured_nosig(array &$methods, &$ordervalue, $add, string $details, string $parceltype_descriptor, array $pctx, int $mode = self::MAXCOVER_BREAK, bool $dump_result = true, bool $omit_optioncode = false): bool
    {
        return $this->_process_parcel_option(
            $methods,
            $ordervalue,
            $add,
            $details,
            $parceltype_descriptor,
            " Insured (no sig)",
            'AUS_SERVICE_OPTION_STANDARD',
            'AUS_SERVICE_OPTION_EXTRA_COVER',
            $pctx,
            $mode,
            true,
            false,
            null,
            $dump_result,
            $omit_optioncode
        );
    }

    /**
     * Summary of _get_error_cost
     * @param mixed $dest_country
     * @param mixed $error_msg_ap
     */

    private function _get_error_cost($dest_country, $error_msg_ap)
    {
        global $messageStack;
        global $cost;

        if (is_array(MODULE_SHIPPING_AUPOST_COST_ON_ERROR)) {
            $excost = explode(',', MODULE_SHIPPING_AUPOST_COST_ON_ERROR);
            if (in_array("TBA", $excost)) {
                $this->error_msg_ap = $this->error_msg_ap . " price TBA";
                $cost = '0';                            //  reset $cost price on error to numeric
            }
        } else {
            unset($_SESSION['aupostParcel']);          // don't cache errors.

            $cost = MODULE_SHIPPING_AUPOST_COST_ON_ERROR;
            if ($cost == 0) {           // disable cost on error
                $this->enabled = FALSE;
                unset($_SESSION['aupostQuotes']);
                return $cost;
            }  // disabled - no further processing

            if ($cost == 'TBA') {
                $this->error_msg_ap = $this->error_msg_ap . " price TBA";
                $cost = '0';                            //  reset $costprice on error to numeric
            }

            if ($cost !== 0) {           // disable cost on error
                $this->quotes = array('id' => $this->code, 'module' => 'Australia Post');
                // bof output to logfile
                $messageStack->add_session('aupost_error', $error_msg_ap, 'error');
                $customer_id = $_SESSION['customer_id'] ?? '';                                  // include customer id if set
                $this->_log("ln" . __LINE__ . ' ' . $this->error_msg_ap . " #" . " Cust:" . $customer_id);
                // eof output to log file
            }
        }
        return $cost;
    }       // ---- extra functions ------------------------------------------------ //

    /**
     * auspost API
     * @param mixed $url
     * @return bool|string
     */
    private function get_auspost_api($url)
    {
        if ((BMHDEBUG1 == 'Yes') && (BMH_P_DEBUG2 == 'Yes') && (BMH_P_DEBUG3 == 'Yes') && (BMH_P_DEBUG4 == 'Yes')) {
            //$this->_debug_output("n", 'ln' . __LINE__ . ' n4 get_auspost_api called', '');
        }

        $json = [];
        global $customer_id;
        //  ---- changed to allow test key --------------------------------- //
        // Note Test mode and  environment is  redundant all quote requests are  made to live servers
        if (AUPOST_MODE == 'Test') {
            $aupost_url_apiKey = AUPOST_TESTMODE_AUTHKEY;
        } else {
            $aupost_url_apiKey = MODULE_SHIPPING_AUPOST_AUTHKEY;
            if ($aupost_url_apiKey == '' || $aupost_url_apiKey == '0') {
                echo '<br><strong>Australia Post API Key is not set.</strong> Please notify the administrator to set the API Key in the module settings.';
                $this->_log('ln' . __LINE__ . " Australia Post API Key is not set. Please set the API Key  in the module settings. Cust:" . $customer_id); //  write to log file
                return(false);   // todo check this
            }
        }
        if ((BMHDEBUG1 == 'Yes') && (BMH_P_DEBUG2 == 'Yes') && (BMH_P_DEBUG3 == 'Yes') && (BMH_P_DEBUG4 == 'Yes')) {
            $this->_debug_output("n", 'ln' . __LINE__ . ' x4 get_auspost_api $url= ', $url);
            //$this->_debug_output("n", 'ln' . __LINE__ . 'x4 $aupost_url_apiKey= ', $aupost_url_apiKey);
        }

        $crl = curl_init();
        $timeout = 5;

        curl_setopt($crl, CURLOPT_HTTPHEADER, array('AUTH-KEY:' . $aupost_url_apiKey)); //

        curl_setopt($crl, CURLOPT_URL, $url);
        curl_setopt($crl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
        $ret = curl_exec($crl);

        // ---- Check the response: if the body is empty then an error occurred //
        if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes") && (BMH_P_DEBUG4 == "Yes")) {
            $this->_debug_output("x", '<br>ln' . __LINE__ . ' x4 get_auspost_api curl $ret= ', json_decode($ret, true)); // will display empty box
        }

        // ---- bof code for when Australia Post is down ------------------- //
        $edata = curl_exec($crl);
        $errtext = curl_error($crl);
        $errnum = curl_errno($crl);
        $commInfo = curl_getinfo($crl);
        if ($edata === "Access denied") {
            $errtext = "<strong>" . $edata . ".</strong> Please report this error to <strong>System Owner ";
        }

        if (!$ret) {
            die('<p><br><b>An Error occurred:</b> "' . curl_error($crl) . '" - Code: ' . curl_errno($crl) .
                ' <br><b>Major Fault - Cannot contact Australia Post. </b>
                Please report this error to the System Owner. Then try the back button on your browser.</p>');
        }
        // ---- eof code for when Australia Post is down ------------------- //

        $json = ($ret == '') ? array() : json_decode($ret);

        //if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes") && (BMH_P_DEBUG4 == "Yes")) {
        //    $this->_debug_output("x", 'ln' . __LINE__ . ' x2  $ret= <br>', json_decode($ret)); // will display empty box
        // }

        if (property_exists($json, 'errorMessage') && !empty($json->errorMessage)) {
            $ret = 'Error ' . $ret;

            $this->_log("" . $json->errorMessage . " Cust:" . $customer_id); //  write to log file
            $cost = "";

            $methods[] = array('id' => $this->code, 'title ' . $json->errorMessage, 'cost' => $cost);
            $this->quotes['methods'] = $methods;   // set the method
            return $ret;
        }


        return $ret;
    }   // ---- end auspost API ------------------------------------------------ //


    /**
     * Summary of _handling
     *  - add handling fee to returned postage charge allowing for currency exchange rates and tax if included
     * @param mixed $details
     * @param mixed $currencies
     * @param mixed $add
     * @param mixed $aus_rate
     * @param mixed $info
     */
    private function _handling($details, $currencies, $add, $aus_rate, $info)
    {
        if (MODULE_SHIPPING_AUPOST_HIDE_HANDLING != 'Yes') {
            if (is_string($add)) {
                $add = (float) $add;
            }
            $details = ' (Inc ' . $currencies->format($add / $aus_rate) . ' P &amp; H';  // Abbreviated for space saving in final quote format

            if ($info > 0) {
                $details = $details . " +$" . $info . " fee).";
            } else {
                $details = $details . ")";
            }
        }
        return $details;
    }

    /**
     * Calculate optimal parcel dimensions by simulating 3D packing.
     *
     * Strategy:
     *  - Items are sorted largest-first (by volume) for better packing efficiency.
     *  - Each item quantity generates candidate block arrangements: flat grids
     *    (single layer) and vertical stacks (multi-layer), tried in both
     *    width/length orientations.
     *  - Candidates are trialled against existing layers; if a block's (w, l)
     *    fits within a layer's footprint it is placed there, otherwise it
     *    starts a new layer on top.
     *  - For new layers, the candidate with the smallest footprint area is
     *    selected (tiebroken by squareness). Final parcel = max(width, length)
     *    per axis across layers, height summed across layers.
     *
     * @param object $cart        Cart object with get_products() method
     * @param object $db          Database object with Execute() method
     * @param array  $defaultdims Default [height, width, length] in cm
     *
     * @return array ['weight' => float, 'width' => float, 'length' => float,
     *                'height' => float, 'cube' => float, 'items' => int,
     *                'packing' => array]
     */
    private function calculateOptimalParcel(object $cart, object $db, array $defaultdims): array
    {
        $parcelweight = 0;
        $packageitems = 0;
        $packinglog = [];

        // ------------------------------------------------------------------ //
        // 1. Fetch all products and their dimensions                         //
        // ------------------------------------------------------------------ //
        $products = [];
        foreach ($cart->get_products() as $item) {
            $producttitle = (int) $item['id'];
            $q = (int) $item['quantity'];
            $w = max((float) $item['weight'], 1);

            $dims = $db->Execute(
                "SELECT products_length, products_height, products_width
                 FROM " . TABLE_PRODUCTS . "
                 WHERE products_id = $producttitle
                 LIMIT 1"
            );

            /*
            $sides = [
                (float) $dims->fields['products_width'],
                (float) $dims->fields['products_height'],
                (float) $dims->fields['products_length'],
            ];
            */
            $row = $dims->fields ?: [];
            $sides = [
                (float) ($row['products_width'] ?? $defaultdims[1]),
                (float) ($row['products_height'] ?? $defaultdims[0]),
                (float) ($row['products_length'] ?? $defaultdims[2]),
                ];

            sort($sides);

            $h = $sides[0] ?: $defaultdims[0];
            $w_dim = $sides[1] ?: $defaultdims[1];
            $l = $sides[2] ?: $defaultdims[2];

            $products[] = [
                'id' => $item['id'],
                'qty' => $q,
                'weight' => $w,
                'h' => $h,
                'w' => $w_dim,
                'l' => $l,
                'volume' => $h * $w_dim * $l,
            ];
        }

        // ------------------------------------------------------------------ //
        // 2. Sort largest volume first for better packing                     //
        // ------------------------------------------------------------------ //
        usort($products, fn($a, $b) => $b['volume'] <=> $a['volume']);

        // ------------------------------------------------------------------ //
        // 3. Shelf-packing: multiple products can share a layer              //
        //    Each layer has a footprint (width x length) and height.         //
        //    A new product block fits in an existing layer if its (w, l)     //
        //    fits within the layer's footprint. Otherwise it starts a new    //
        //    layer on top.                                                   //
        // ------------------------------------------------------------------ //
        $layers = [];

        foreach ($products as $product) {
            $q = $product['qty'];
            $h = $product['h'];
            $w = $product['w'];
            $l = $product['l'];

            $parcelweight += $product['weight'] * $q;
            $packageitems += $q;

            // ---- Generate all candidate block arrangements -------------- //
            // Try flat grids (single layer) and vertical stacks (multi-layer).
            $candidates = [];
            $maxCols = min($q, 50);
            for ($cols = 1; $cols <= $maxCols; $cols++) {
                $rows = (int) ceil($q / $cols);
                $candidates[] = ['w' => $w * $cols, 'l' => $l * $rows, 'h' => $h, 'cols' => $cols, 'rows' => $rows, 'layers' => 1];
                $candidates[] = ['w' => $l * $cols, 'l' => $w * $rows, 'h' => $h, 'cols' => $cols, 'rows' => $rows, 'layers' => 1];
                if ($rows > 1) {
                    $candidates[] = ['w' => $w * $cols, 'l' => $l, 'h' => $h * $rows, 'cols' => $cols, 'rows' => 1, 'layers' => $rows];
                    $candidates[] = ['w' => $l * $cols, 'l' => $w, 'h' => $h * $rows, 'cols' => $cols, 'rows' => 1, 'layers' => $rows];
                }
            }

            // Sort by footprint area ascending (smaller = better)
            usort($candidates, fn($a, $b) => ($a['w'] * $a['l']) <=> ($b['w'] * $b['l']));

            // ---- Try to fit into an existing layer --------------------- //
            $placed = false;
            foreach ($layers as &$layer) {
                foreach ($candidates as $c) {
                    if ($c['w'] <= $layer['w'] && $c['l'] <= $layer['l']) {
                        $layer['h'] = max($layer['h'], $c['h']);
                        $packinglog[] = [
                            'id' => $product['id'],
                            'qty' => $q,
                            'arrangement' => "{$c['cols']} wide x {$c['rows']} deep x {$c['layers']} high (shared layer)",
                            'block_w' => round($c['w'], 2),
                            'block_l' => round($c['l'], 2),
                            'block_h' => round($c['h'], 2),
                        ];
                        $placed = true;
                        break 2;
                    }
                    if ($c['l'] <= $layer['w'] && $c['w'] <= $layer['l']) {
                        $layer['h'] = max($layer['h'], $c['h']);
                        $packinglog[] = [
                            'id' => $product['id'],
                            'qty' => $q,
                            'arrangement' => "{$c['cols']} wide x {$c['rows']} deep x {$c['layers']} high (shared, rotated)",
                            'block_w' => round($c['l'], 2),
                            'block_l' => round($c['w'], 2),
                            'block_h' => round($c['h'], 2),
                        ];
                        $placed = true;
                        break 2;
                    }
                }
            }

            if (!$placed) {
                usort($candidates, fn($a, $b) =>
                    ($a['w'] * $a['l']) <=> ($b['w'] * $b['l'])
                    ?: max($a['w'], $a['l']) <=> max($b['w'], $b['l'])
                );
                $best = $candidates[0];
                $layers[] = ['w' => $best['w'], 'l' => $best['l'], 'h' => $best['h']];
                $packinglog[] = [
                    'id' => $product['id'],
                    'qty' => $q,
                    'arrangement' => "{$best['cols']} wide x {$best['rows']} deep x {$best['layers']} high (new layer)",
                    'block_w' => round($best['w'], 2),
                    'block_l' => round($best['l'], 2),
                    'block_h' => round($best['h'], 2),
                ];
            }

            if (MODULE_SHIPPING_AUPOST_DEBUG == "Yes") {
                $pw = 0; $pl = 0; $ph = 0;
                foreach ($layers as $ly) {
                    $pw = max($pw, $ly['w']);
                    $pl = max($pl, $ly['l']);
                    $ph += $ly['h'];
                }
                $n = $db->Execute(
                    "select products_name from " . TABLE_PRODUCTS_DESCRIPTION
                    . " where products_id=" . (int)$product['id'] . " limit 1"
                );
                $ic = $product['h'] * $product['w'] * $product['l'] * 0.000001;
                $pc = $pw * $pl * $ph / 1000000;
                echo "<center><table class=\"aupost-debug-table\" border=1>
                    <th colspan=8> Debugging [aupost] ver:" . VERSION_AU . ' ln ' . __LINE__ ."</hr>
                    <tr>
                        <th>Item</th>
                        <td colspan=7>" . $n->fields['products_name'] . "</td>
                    </tr>
                    <tr>
                        <th width=15%>Attribute</th>
                        <th colspan=3>Item</th>
                        <th colspan=4>Parcel</th>
                    </tr>
                    <tr>
                        <th>Qty</th><td>&nbsp;" . $product['qty'] . "<th>Weight</th><td>&nbsp;" . round($product['weight'], 2) . "</td>
                        <th>Qty</th><td>&nbsp;$packageitems</td><th>Weight</th><td>&nbsp;"
                        . round($parcelweight + (($parcelweight * (int)MODULE_SHIPPING_AUPOST_TARE) / 100), 2)
                        . " " . MODULE_SHIPPING_AUPOST_WEIGHT_FORMAT . "</td>
                    </tr>
                    <tr>
                        <th>Dims L W H</th>
                        <td colspan=3>&nbsp;" . round($product['l'], 2) . " x " . round($product['w'], 2) . " x " . round($product['h'], 2) . "</td>
                        <td colspan=4>" . round($pl, 2) . " x " . round($pw, 2) . " x " . round($ph, 2) . "</td>
                    </tr>
                    <tr>
                        <th>Cube</th>
                        <td colspan=3>&nbsp;" . number_format($ic, 4) . "</td>
                        <td colspan=4>" . number_format($pc, 4) . "</td>
                    </tr>
                    <tr>
                        <th>CubicWt</th>
                        <td colspan=3>&nbsp;" . round($ic * 250, 2) . "Kgs</td>
                        <td colspan=4>" . round($pc * 250, 2) . "Kgs</td>
                    </tr>
                    </table></center>";
            }
        }

        // ------------------------------------------------------------------ //
        // 4. Compute final parcel dimensions from layers                      //
        // ------------------------------------------------------------------ //
        $parcelwidth = 0;
        $parcellength = 0;
        $parcelheight = 0;
        foreach ($layers as $layer) {
            $parcelwidth = max($parcelwidth, $layer['w']);
            $parcellength = max($parcellength, $layer['l']);
            $parcelheight += $layer['h'];
        }

        // Add 2 % packing tolerance
        $tolerance = 1.02;
        $parcelwidth = ceil($parcelwidth * $tolerance * 10) / 10;     // rounded to one decimal place
        $parcellength = ceil($parcellength * $tolerance * 10) / 10;         // rounded to one decimal place
        $parcelheight = ceil($parcelheight * $tolerance * 10) / 10;         // rounded to one decimal place

        return [
            'weight' => round($parcelweight, 2),
            'width' => $parcelwidth,
            'length' => $parcellength,
            'height' => $parcelheight,
            'cube' => round($parcelwidth * $parcellength * $parcelheight / 1000000, 6),
            'items' => $packageitems,
            'packing' => $packinglog,
        ];

    }   // ---- end optimal parcel dimensions ------------------------------ //

    /**
     * Write to log file
     *  Prints error with purchase order id and time + date
     * @param  string $msg          error message
     * @param  string $suffix
     */
    private function _log($msg, $suffix = '')
    {
        global $purchaseOrderId;
        $file = $this->_logDir . '/' . $this->log_file_name;
        if ($fp = @fopen($file, 'a')) {
            $today = date("Y-m-d_H:i:s");         //
            @fwrite($fp, "" . time() . ": " . $today . ": " . $msg . " " . $purchaseOrderId . "\r\n"); // store epoch time + date
            @fclose($fp);
        }
    }

    /**
     *  format on screen debug statements
     * Strategy: output varies by type eg XML is shown in a box, formatted for readabilty
     * @param string $x                 code for type of message
     * @param string $debug_message     message
     * @param mixed  $dump              value | array | string
     *
     */
    private function _debug_output($x, $debug_message, $dump)
    {
        switch ($x) {
            case "x":            // x=xml dump
                echo '<p class="aupost-debug">';
                echo $debug_message;
                echo '<br><textarea rows="15" cols="80" > <pre>';
                print_r($dump);
                echo "</pre> </textarea> ";
                echo "</textarea> </p>";
                break;

            case "d":               // d=detailed dump
                echo '<table class="aupost-debug"><tr><td>';
                echo $debug_message;
                echo '<pre>';
                var_dump($dump);
                echo "</pre> </td></tr></table>";
                break;

            case "n":               // n=normal message
                echo '<table class="aupost-debug"><tr><td>';
                echo $debug_message . " " . $dump;
                echo "</td></tr></table>";
                break;
        }
        return;
    }   // end _debug_output function

    // --------------------------------------------------------------------- //
    // parts for admin module                                                //
    // --------------------------------------------------------------------- //


    // ---- Check to see if module is installed ---------------------------- //
    public function check()
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_AUPOST_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    // ----- install ------------------------------------------------------- //
    /**
     * Summary of install
     * @return void
     */
    public function install()       //
    {
        global $db;
        global $messageStack;

        $result = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'SHIPPING_ORIGIN_ZIP'");
        $pcode = $result->fields['configuration_value'];

        if (!$pcode)
            $pcode = "4121";  // default if not configured in Admin console
        //

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
            VALUES ('Enable this module?', 'MODULE_SHIPPING_AUPOST_STATUS', 'True', 'Enable this Module', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
           VALUES ('Auspost API Key:', 'MODULE_SHIPPING_AUPOST_AUTHKEY', 'Add API Auth key from Australia Post', 'To use this module, you must obtain a 36 digit API Key from the <a href=\"https:\\developers.auspost.com.au\" target=\"_blank\">Auspost Development Centre</a>', '6', '2', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
            VALUES ('Dispatch Postcode', 'MODULE_SHIPPING_AUPOST_SPCODE', $pcode, 'Dispatch Postcode?', '6', '2', now())");
        //  bof LETTERS

        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function,  date_added)
                VALUES ('<hr>AustPost Letters (and small parcels@letter rates)', 'MODULE_SHIPPING_AUPOST_TYPE_LETTERS',
                    'Aust Standard, Aust Priority, Aust Express, Aust Express +sig, Aust Express Insured +sig, Aust Express Insured (no sig)',
                    'Select the methods you wish to allow',
                    '6','3',
                    'zen_cfg_select_multioption(array(\'Aust Standard\',\'Aust Priority\',\'Aust Express\',\'Aust Express +sig\',\'Aust Express Insured +sig\',\'Aust Express Insured (no sig)\',), ',
                    now())"
        );

        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
             VALUES ('Handling Fee - Standard Letters',
             'MODULE_SHIPPING_AUPOST_LETTER_HANDLING', '2.00', 'Handling Fee for Standard letters.', '6', '13', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
             VALUES ('Handling Fee - Priority Letters',
             'MODULE_SHIPPING_AUPOST_LETTER_PRIORITY_HANDLING', '2.00', 'Handling Fee for Priority letters.', '6', '13', now())"
        );
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
             VALUES ('Handling Fee - Express Letters',
             'MODULE_SHIPPING_AUPOST_LETTER_EXPRESS_HANDLING', '2.00', 'Handling Fee for Express letters.', '6', '13', now())"
        );
        //  eof LETTERS

        // bof PARCELS
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
            VALUES ('Shipping Methods for Australia', 'MODULE_SHIPPING_AUPOST_TYPES1',
                'Regular Parcel, Regular Parcel +sig, Regular Parcel Insured +sig, Regular Parcel Insured (no sig), Prepaid Satchel, Prepaid Satchel +sig, Prepaid Satchel Insured +sig, Prepaid Satchel Insured (no sig), Flat rate packaging, Flat rate packaging +sig, Flat rate packaging Insured +sig, Flat rate packaging Insured (no sig), Express Parcel, Express Parcel +sig, Express Parcel Insured +sig, Express Parcel Insured (no sig), Prepaid Express Satchel, Prepaid Express Satchel +sig, Prepaid Express Satchel Insured +sig, Prepaid Express Satchel Insured (no sig), Express Flat rate packaging, Express Flat rate packaging +sig, Express Flat rate packaging Insured +sig, Express Flat rate packaging Insured (no sig)',
                'Select the methods you wish to allow', '6', '4',
                'zen_cfg_select_multioption(array(
                    \'Regular Parcel\',\'Regular Parcel +sig\',\'Regular Parcel Insured +sig\',\'Regular Parcel Insured (no sig)\',
                    \'Prepaid Satchel\',\'Prepaid Satchel +sig\',\'Prepaid Satchel Insured +sig\',\'Prepaid Satchel Insured (no sig)\',
                    \'Express Parcel\',\'Express Parcel +sig\',\'Express Parcel Insured +sig\',\'Express Parcel Insured (no sig)\',
                    \'Prepaid Express Satchel\',\'Prepaid Express Satchel +sig\',\'Prepaid Express Satchel Insured +sig\',\'Prepaid Express Satchel Insured (no sig)\',
                    \'Flat rate packaging\', \'Flat rate packaging +sig\', \'Flat rate packaging Insured +sig\', \'Flat rate packaging Insured (no sig)\',
                    \'Express Flat rate packaging\', \'Express Flat rate packaging +sig\', \'Express Flat rate packaging Insured +sig\', \'Express Flat rate packaging Insured (no sig)\' ), ',
                now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
            VALUES ('Handling Fee - Regular parcels', 'MODULE_SHIPPING_AUPOST_RPP_HANDLING', '2.00', 'Handling Fee Regular parcels', '6', '6', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
            VALUES ('Handling Fee - Prepaid Satchels', 'MODULE_SHIPPING_AUPOST_PPS_HANDLING', '2.00', 'Handling Fee for Prepaid Satchels.', '6', '7', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
            VALUES ('Handling Fee - Flat rate packaging', 'MODULE_SHIPPING_AUPOST_FRP_HANDLING', '2.00', 'Handling Fee for Flat rate packaging.', '6', '8', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
            VALUES ('Handling Fee - Prepaid Satchels - Express', 'MODULE_SHIPPING_AUPOST_PPSE_HANDLING', '2.00', 'Handling Fee for Prepaid Express Satchels.', '6', '9', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
            VALUES ('Handling Fee - Express parcels', 'MODULE_SHIPPING_AUPOST_EXP_HANDLING', '2.00', 'Handling Fee for Express parcels.', '6', '10', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
            VALUES ('Handling Fee - Express Flat rate packaging', 'MODULE_SHIPPING_AUPOST_EXP_FRP_HANDLING', '2.00', 'Handling Fee for Express Flat rate packaging.', '6', '11', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
            VALUES ('Hide Handling Fees?', 'MODULE_SHIPPING_AUPOST_HIDE_HANDLING', 'No', 'The handling fees are still in the total shipping cost but the Handling
                Fee is not itemised on the invoice.', '6', '16', 'zen_cfg_select_option(array(\'Yes\', \'No\'), ', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
            VALUES ('Default Product /Parcel Dimensions', 'MODULE_SHIPPING_AUPOST_DIMS', '10,10,2', 'Default Product /Parcel dimensions (in cm). Three comma separated values (eg 10,10,2 = 10cm x 10cm x 2cm). These are used if the dimensions of individual products are not set', '6', '40', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
            VALUES ('Cost on Error', 'MODULE_SHIPPING_AUPOST_COST_ON_ERROR', '99.99', 'If an error occurs this Flat Rate fee will be used. If TBA is entered an error msg will be displayed on the postage rate and Zero value postage displayed.</br> A value of zero will disable this module on error.', '6', '20', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
            VALUES ('Parcel Weight format', 'MODULE_SHIPPING_AUPOST_WEIGHT_FORMAT', 'gms', 'Are your store items weighted by grams or Kilos? (required so that we can pass the correct weight to the server).', '6', '25', 'zen_cfg_select_option(array(\'gms\', \'kgs\'), ', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
            VALUES ('Show AusPost logo?', 'MODULE_SHIPPING_AUPOST_ICONS', 'Yes', 'Show Auspost logo in place of text?', '6', '19', 'zen_cfg_select_option(array(\'No\', \'Yes\'), ', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
            VALUES ('Enable Debug?', 'MODULE_SHIPPING_AUPOST_DEBUG', 'No', 'See how parcels are created from individual items.</br>Shows all methods returned by the server, including possible errors. <strong>Do not enable in a production environment</strong>', '6', '40', 'zen_cfg_select_option(array(\'No\', \'Yes\'), ', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
            VALUES ('Tare percent.', 'MODULE_SHIPPING_AUPOST_TARE', '10', 'Add this percentage of the items total weight as the tare weight. (This module ignores the global settings that seems to confuse many users. 10% seems to work pretty well.).', '6', '50', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
            VALUES ('Sort order of display.', 'MODULE_SHIPPING_AUPOST_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '55', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added)
            VALUES ('Tax Class', 'MODULE_SHIPPING_AUPOST_TAX_CLASS', '1', 'Set Tax class or -none- if not registered for GST.', '6', '60', 'zen_get_tax_class_title', 'zen_cfg_pull_down_tax_classes(', now())");
        // eof parcels

        // ----  end update tables ------------------------------------------ //

        $inst = 1;
        $sql = "show fields from " . TABLE_PRODUCTS;
        $result = $db->Execute($sql);
        while (!$result->EOF) {
            if ($result->fields['Field'] == 'products_length') {
                unset($inst);
                break;
            }
            $result->MoveNext();
        }

        if (isset($inst)) {
            //  echo "new" ;
            $db->Execute("ALTER TABLE " . TABLE_PRODUCTS . " ADD `products_length` FLOAT(6,2) NULL AFTER `products_weight`,
                ADD `products_height` FLOAT(6,2) NULL AFTER `products_length`, ADD `products_width` FLOAT(6,2) NULL AFTER `products_height`");
        } else {
            //  echo "update" ;
            $db->Execute("ALTER TABLE " . TABLE_PRODUCTS . " CHANGE `products_length` `products_length` FLOAT(6,2),
                CHANGE `products_height` `products_height` FLOAT(6,2), CHANGE `products_width`  `products_width`  FLOAT(6,2)");
        }
    }       // eof install

    // ----- removal of module in admin ------------------------------------ //
    /**
     * Summary of remove
     * @return void
     */
    public function remove() //
    {
        global $db;
        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    // ----- order of options loaded into admin-shipping ------------------- //
    /**
     * Summary of keys
     * @return string[]
     */
    public function keys()  //lo
    {
        return array
        (
            'MODULE_SHIPPING_AUPOST_STATUS',
            'MODULE_SHIPPING_AUPOST_AUTHKEY',
            'MODULE_SHIPPING_AUPOST_SPCODE',
            'MODULE_SHIPPING_AUPOST_TYPE_LETTERS',
            'MODULE_SHIPPING_AUPOST_LETTER_HANDLING',
            'MODULE_SHIPPING_AUPOST_LETTER_PRIORITY_HANDLING',
            'MODULE_SHIPPING_AUPOST_LETTER_EXPRESS_HANDLING',
            'MODULE_SHIPPING_AUPOST_TYPES1',
            'MODULE_SHIPPING_AUPOST_RPP_HANDLING',
            'MODULE_SHIPPING_AUPOST_EXP_HANDLING',
            'MODULE_SHIPPING_AUPOST_PPS_HANDLING',
            'MODULE_SHIPPING_AUPOST_FRP_HANDLING',
            'MODULE_SHIPPING_AUPOST_EXP_FRP_HANDLING',
            'MODULE_SHIPPING_AUPOST_PPSE_HANDLING',
            'MODULE_SHIPPING_AUPOST_PLAT_HANDLING',
            'MODULE_SHIPPING_AUPOST_PLATSATCH_HANDLING',
            'MODULE_SHIPPING_AUPOST_COST_ON_ERROR',
            'MODULE_SHIPPING_AUPOST_HIDE_HANDLING',
            'MODULE_SHIPPING_AUPOST_DIMS',
            'MODULE_SHIPPING_AUPOST_WEIGHT_FORMAT',
            'MODULE_SHIPPING_AUPOST_ICONS',
            'MODULE_SHIPPING_AUPOST_DEBUG',
            'MODULE_SHIPPING_AUPOST_TARE',
            'MODULE_SHIPPING_AUPOST_SORT_ORDER',
            'MODULE_SHIPPING_AUPOST_TAX_CLASS',
        );
    }
    // ----- end admin section --------------------------------------------- //
    // end class
}
