<?php
declare(strict_types=1);
/**
 * $Id:   aupost.php,v3.2.2 November 2026
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
 *  2026-11-04  hide letterheight check msg ln470; parcel length check msg ln856
 *  2026-11-04  product_ships_in_own_box support and optional over-limit parcel splitting
 *              (MODULE_SHIPPING_AUPOST_SPLIT_OVERLIMIT); split quotes merged by service id.
 *  2026-11-05  split quotes allocate order value across parcels for extra cover.
 * 2026-11-05 3.2.1 AUPOST_SPLIT_OVERLIMIT moved to admin settings as MODULE_SHIPPING_AUPOST_SPLIT_OVERLIMIT
 * 2026-11-07  split orders can append the parcel number to orders_products.products_name for
 *             packing/picking slips (MODULE_SHIPPING_AUPOST_PARCEL_NAME_SUFFIX, Yes/No); the
 *             session allocation is written by rememberParcelAssignments().
 *          ln3463 Expected type 'string|int'. Found 'null'. // BMH 2026-11-06
 */

/* version */
if (!defined('VERSION_AU')) {
    define('VERSION_AU', '3.2.2');
}

// ---- BMHDEBUG switches // WARNING DO NOT ENABLE FOR PRODUCTION
define('BMHDEBUG1', 'No');          // No or Yes // BMH 2nd level debug
define('BMH_P_DEBUG2', 'No');        // No or Yes // BMH 3nd level debug to display returned values  from AP
define('BMH_P_DEBUG3', 'No');        // No or Yes // BMH 4th level debug to display allowed options
define('BMH_P_DEBUG4', 'No');        // No or Yes // BMH 5th level debug to display functions and raw curl
define('BMH_P_DEBUG4_BOXES', 'No'); // No or Yes // BMH level debug to display BOXES Code - future development
//
define('BMH_L_DEBUG1', 'No');       // No or Yes // BMH 3nd level debug
define('BMH_L_DEBUG2', 'No');       // No or Yes // BMH 3nd level debug to display all returned json data from Aus Post

define('USE_CACHE', 'No');          // BMH disable cache // set to 'No' for testing; Using cache prevents excessive calls to AP if data not changed

// Set to 'Yes' in the admin shipping settings to split an order that exceeds
// Australia Post's maximum weight or dimensions into multiple parcels (each
// quoted separately and combined into one shipping total). Set to 'No' to keep
// the existing over-limit error quote. The fallback below is used only when
// the module is loaded outside the normal admin-configured environment.
if (!defined('MODULE_SHIPPING_AUPOST_SPLIT_OVERLIMIT')) {
    define('MODULE_SHIPPING_AUPOST_SPLIT_OVERLIMIT', 'Yes');
}

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
    private ?array $precomputedNormalParcel = null;  // packed once, reused by quoteSingle()
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

    /** Packing tolerance applied to the final parcel dimensions. */
    private const PACKING_TOLERANCE = 1.02;

    /** Smallest parcel value that passes the module's extra-cover gate (AUD). */
    private const MIN_INSURED_VALUE = 102;

    /** Product ids already shown in the cart debug table for this page. */
    private array $debuggedCartProducts = [];

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
        // ---- these checks only occur if module is installed AND activated //
        //
        if ((IS_ADMIN_FLAG === true)  && (MODULE_SHIPPING_AUPOST_STATUS === 'True' )) {
            if (MODULE_SHIPPING_AUPOST_AUTHKEY === 'Add API Auth key from Australia Post' ||
                strlen(MODULE_SHIPPING_AUPOST_AUTHKEY) < 31) {
                $this->title .= '<span class="alert"> (Not Configured) check API key</span>';
                //$messageStack->add_session( '<span class="alert"> (Not Configured) check API key</span>','error');
                $messageStack->add_session(MODULE_SHIPPING_AUPOST_ERROR_ADMIN_API_KEY, 'error');

            } elseif ( MODULE_SHIPPING_AUPOST_AUTHKEY === '28744ed5982391881611cca6cf5c240') {
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

            if (defined('MODULE_SHIPPING_AUPOST_COST_ON_ERROR') ) {
                if  (trim(MODULE_SHIPPING_AUPOST_COST_ON_ERROR) === "TBA")    {
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
            /*
            $lh1 = defined('MODULE_SHIPPING_AUPOST_LETTER_HANDLING');
            $lh2 = defined('MODULE_SHIPPING_AUPOST_LETTER_PRIORITY_HANDLING');
            $lh3 = ('MODULE_SHIPPING_AUPOST_LETTER_EXPRESS_HANDLING');
            if (($lh1 < 0) || ($lh2 < 0) || ($lh3 < 0)) {
                echo '<br/> ln125 check handling fees';
            }
*/
            if (SHIPPING_MAX_WEIGHT <> ($this->MAXWEIGHT_P)) {

                $msg = MODULE_SHIPPING_AUPOST_ERROR_ADMIN_MAX_SHIPPING_WT . $this->MAXWEIGHT_P . 'kg';
                $messageStack->add_session($msg, 'error');
            }

        } // end Admin section
        // +++++++++++++++++++++++++++++++++++++++

        $this->ap_shipping_num_boxes = 1;  // BMH WHAT IS THIS??? _todo_

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
        global $order;

        $this->precomputedNormalParcel = null;

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

        // Only proceed for AU addresses (preserves non-AU behaviour before splitting).
        $dest_country = $order->delivery['country']['iso_code_2'] ?? '';
        if ($dest_country != "AU") {
            return $this->quoteSingle();
        }

        // ---- Split when items ship in their own box or the order is over-limit //
        $shipments = $this->buildSplitShipments();
        if ($shipments === null) {
            return $this->quoteSingle();
        }

        return $this->quoteSplitShipments($shipments);
    }

    /**
     * Price a single shipment. Called directly for normal carts and once per
     * parcel when an order is split for products that ship in their own box.
     *
     * @param array|null $forcedParcel Pre-calculated parcel (own-box items); null to pack the cart
     * @param bool       $skipLetters  Do not offer letter services for this shipment
     * @param bool       $rawMethods   Keep every service (do not de-duplicate by cost)
     * @param bool       $isSplit      Part of a split order; do not touch the single-parcel cache
     * @param bool       $applyTare    Add packaging tare to the parcel weight
     * @param float|null $orderValueOverride Value to insure instead of the full order total
     * @return array|mixed|null
     */
    private function quoteSingle(?array $forcedParcel = null, bool $skipLetters = false, bool $rawMethods = false, bool $isSplit = false, bool $applyTare = true, ?float $orderValueOverride = null)
    {
        global $db, $order, $currencies, $parcelweight, $packageitems;
        global $customer_id;
        global $frompcode;
        global $maxcoverexceeded;
        global $maxcover;
        global $producttitle, $tare;
        global $methods;

        $this->quotes = [];
        $methods = [];

        $maxcover = 0;                  // initialise max cover exceeded flag to false

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
        $defaultdims = $this->defaultParcelDims();    // length[2]. width[1], height=[0]

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

        $order_total = $orderValueOverride ?? (float) $order->info['total'];
        $ordervalue = $order_total / $aus_rate;                 // total cost for insurance
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

        // ---- Use a pre-calculated own-box parcel, or pack the cart -------- //
        if ($forcedParcel !== null) {
            $result = $forcedParcel;
        } elseif ($this->precomputedNormalParcel !== null) {
            // buildSplitShipments() already packed the normal cart items while
            // checking whether the order needs to be split.
            $result = $this->precomputedNormalParcel;
        } else {
            $result = $this->calculateOptimalParcel($_SESSION['cart'], $db, $defaultdims);
        }

        $parcelwidth = $result['width'];             // cm  (widest row � 1.02)
        $parcellength = $result['length'];
        $parcelheight = $result['height'];
        $itemcube = $result['cube'];
        $packageitems = $result['items'];
        $parcelweight = $result['weight'];

        if ($parcelheight > $MAXHEIGHT_L) {
            $letterheightcheck = 0; // cannot be sent as letter by height limit
           // debug echo 'ln' . __LINE__ . '$letterheightcheck=' . $letterheightcheck;
            $this->_log(msg: 'ln' . __LINE__ . 'Parcel letter height exceeds maximum allowed of '. $MAXHEIGHT_L ); // write to log file
        }
        // ---- error msg to user if parcel too heavy ---------------------- //
        // moved to weight check section and combined with returned quote


        $aupost_url_string = AUPOST_URL_PROD;

        // ----- parcel sizing based on flat rate boxes --------------------- //
        /* start parcel size */

        /*
         * Boxes not used as flat rate returns available AP sizes
         * */
        if (CALL_FOR_BOXES == 'Yes' && !$isSplit) {              // retrieving flat rate box sizes (not needed per split parcel)

            $qu_box = $this->get_auspost_api('https://' . $aupost_url_string . PARCEL_URL_SIZE_STRING );

            $json_boxes = ($qu_box == '') ? [] : json_decode($qu_box, true); // If we have any results, parse them into a JSON array

            if (MODULE_SHIPPING_AUPOST_DEBUG == 'Yes' && BMH_P_DEBUG4_BOXES == 'Yes') {
                $this->_debug_output("x", '<br>ln' . __LINE__ . ' x4 auPost - Server Returned BMH_P_DEBUG4_BOXES ln:<br>', $json_boxes);
            }
        } elseif (CALL_FOR_BOXES != 'Yes') {
            if (MODULE_SHIPPING_AUPOST_DEBUG == 'Yes' && BMH_P_DEBUG4_BOXES == 'Yes') {
            echo '<br>ln' . __LINE__ . ' CALL_FOR_BOXES is set to No - using default box sizes'; //BMH debug REMOVE THIS OR EXPAND LOGIC FOR FLAT RATE
            }
            //$json_boxes = $this->get_default_box_sizes(); // get default box sizes from function
        }
         /* */

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

            if (!$skipLetters && (($letterheight) <= $MAXHEIGHT_L)) {
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

            // Own-box parcels are shipped as-is, so no packaging tare is added.
            if ($applyTare) {
                $parcelweight = $parcelweight + (($parcelweight * $tare) / 100);
            }

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
                // debug echo '<br>ln' . __LINE__ . ' parcel length check - length=' . $parcellength . ' max length=' . $this->MAXLENGTH_P; // debug
                $this->_log(msg: 'ln' . __LINE__ . 'Parcel length exceeds maximum allowed of '. $this->MAXLENGTH_P . '. Parcel length: ' . $parcellength . ' ; Width sent=' . $parcelwidth . '; Height sent=' . $parcelheight . '; Weight sent=' . $parcelweight . ';'); // write to log file
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
                $this->_log(msg: 'ln' . __LINE__ . 'Parcel cubic volume exceeds maximum allowed. Parcel cubic volume: ' . $itemcube . ', Max cubic volume: ' . $this->MAXCUBIC_P); // write to log file
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
                $this->_log(msg: 'ln' . __LINE__ . 'Parcel weight exceeds maximum allowed. Parcel weight: ' . $parcelweight . ', Max weight: ' . $this->MAXWEIGHT_P); // write to log file
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
            if (USE_CACHE == "Yes" && !$isSplit) {   // DEBUG  NOTE disable cache for testing
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

            // ---- always save new session (not for split parcels) ----------- //
            if (!$isSplit) {
                $_SESSION['aupostParcel'] = implode(",", array($dest_country, $topcode, $parcelwidth, $parcelheight, $parcellength, $parcelweight, $ordervalue));
            }
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

            // Split orders must keep every service so quotes can be matched
            // across parcels by service id; normal quotes keep the de-duplication.
            if (!$rawMethods) {
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
            if (!$isSplit) {
                $_SESSION['aupostQuotes'] = $this->quotes;              // save as session to avoid reprocessing when single method required
            }

            return $this->quotes;                                       // all done //

            // ---- Final Exit Point --------------------------------------- //
        } // ---- eof function quote method -------------------------------- //
    }

    /**
     * Default parcel dimensions from the module configuration, sorted
     * [height, width, length] in cm.
     *
     * @return array
     */
    private function defaultParcelDims(): array
    {
        $expl = array_pad(explode(',', (string) MODULE_SHIPPING_AUPOST_DIMS), 3, '');
        $defaultdims = [
            ((float) $expl[0] > 0) ? (float) $expl[0] : 2.0,
            ((float) $expl[1] > 0) ? (float) $expl[1] : 10.0,
            ((float) $expl[2] > 0) ? (float) $expl[2] : 10.0,
        ];
        sort($defaultdims);

        return $defaultdims;
    }

    /**
     * Whether a cart item is flagged to ship in its own box.
     *
     * @param array $item Cart item from shopping_cart::get_products()
     * @return bool
     */
    private function isOwnBoxItem(array $item): bool
    {
        $flag = $item['ships_in_own_box'] ?? null;
        if ($flag === null) {
            return false;
        }
        if (is_bool($flag)) {
            return $flag;
        }

        $flag = trim((string) $flag);
        if ($flag === '') {
            return false;
        }

        return !in_array(strtolower($flag), ['0', 'false', 'no', 'off', 'n'], true);
    }

    /**
     * Fetch a product's raw dimensions, sorted [height, width, length] in cm.
     * Falls back to the configured default dimensions when any side is missing.
     *
     * @param int    $productId
     * @param object $db
     * @param array  $defaultdims
     * @return array
     */
    private function fetchProductDims(int $productId, object $db, array $defaultdims): array
    {
        $dims = $db->Execute(
            "SELECT products_length, products_height, products_width
             FROM " . TABLE_PRODUCTS . "
             WHERE products_id = $productId
             LIMIT 1"
        );

        $row = $dims->fields ?: [];
        $raw = [
            (float) ($row['products_width'] ?? 0),
            (float) ($row['products_height'] ?? 0),
            (float) ($row['products_length'] ?? 0),
        ];

        if (min($raw) <= 0) {
            // If any side is missing/zero, use the complete default set
            $raw = [
                (float) $defaultdims[1],
                (float) $defaultdims[0],
                (float) $defaultdims[2],
            ];
        }

        sort($raw);

        return $raw;
    }

    /**
     * Dimensions for a cart item, sorted [height, width, length] in cm.
     * Uses the dimensions already loaded with the cart item and falls back
     * to the products table when any side is missing.
     *
     * @param array  $item
     * @param object $db
     * @param array  $defaultdims
     * @return array
     */
    private function itemDims(array $item, object $db, array $defaultdims): array
    {
        $raw = [
            (float) ($item['width'] ?? 0),
            (float) ($item['height'] ?? 0),
            (float) ($item['length'] ?? 0),
        ];

        if (min($raw) <= 0) {
            return $this->fetchProductDims((int) $item['id'], $db, $defaultdims);
        }

        sort($raw);

        return $raw;
    }

    /**
     * Build a raw parcel for one own-box unit: product dimensions and weight
     * exactly as supplied, with no packing tolerance and no packaging tare.
     *
     * @param array  $item        Cart item
     * @param object $db
     * @param array  $defaultdims
     * @return array
     */
    private function ownBoxParcel(array $item, object $db, array $defaultdims): array
    {
        [$h, $w, $l] = $this->itemDims($item, $db, $defaultdims);
        // Keep the product's raw weight. Only fall back to 1 when no usable
        // weight is available, so a missing weight cannot produce a free parcel.
        $weight = (float) ($item['weight'] ?? 0);
        if ($weight <= 0) {
            $weight = 1;
        }

        return [
            'weight' => round($weight, 2),
            'width' => $w,
            'length' => $l,
            'height' => $h,
            'cube' => round($w * $l * $h / 1000000, 6),
            'items' => 1,
            'packing' => [],
        ];
    }

    /**
     * Split the cart into shipments when an item ships in its own box, or
     * when the normal packed order exceeds Australia Post's weight/dimension
     * limits and MODULE_SHIPPING_AUPOST_SPLIT_OVERLIMIT is enabled.
     *
     * @return array|null Null when the cart can be quoted as a single parcel
     */
    private function buildSplitShipments(): ?array
    {
        global $db, $order;

        $this->precomputedNormalParcel = null;

        // The parcel section in quoteSingle() is only reached when letters are
        // configured, so do not introduce split quotes when it would not run.
        if (MODULE_SHIPPING_AUPOST_TYPE_LETTERS == null) {
            return null;
        }

        if (!isset($_SESSION['cart']) || !is_object($_SESSION['cart'])) {
            return null;
        }

        $items = $_SESSION['cart']->get_products();
        if (!is_array($items) || empty($items)) {
            return null;
        }

        $defaultdims = $this->defaultParcelDims();
        $shipments = [];
        $ownCount = 0;
        $normalItems = [];
        $cartValue = 0.0;

        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $unitValue = (float) ($item['final_price'] ?? 0);
            $cartValue += $unitValue * $qty;

            if ($this->isOwnBoxItem($item)) {
                $parcel = $this->ownBoxParcel($item, $db, $defaultdims);
                $signature = 'own:' . $item['id'] . ':' . $this->parcelSignature($parcel)
                    . ':v' . round($unitValue, 2);

                // Each unit is its own parcel and needs its own quote.
                for ($n = 0; $n < $qty; $n++) {
                    $shipments[] = [
                        'type' => 'own',
                        'parcel' => $parcel,
                        'signature' => $signature,
                        'apply_tare' => false,
                        'value_raw' => $unitValue,
                        'items' => [
                            ['id' => $item['id'], 'qty' => 1],
                        ],
                    ];
                }
                $ownCount += $qty;
            } else {
                $normalItems[] = $item;
            }
        }

        $needsSplit = $ownCount > 0;

        if (!empty($normalItems)) {
            $normalSignature = [];
            $normalValue = 0.0;
            foreach ($normalItems as $item) {
                $normalSignature[] = $item['id'] . ':' . (int) $item['quantity'] . ':' . ($item['weight'] ?? 0);
                $normalValue += (float) ($item['final_price'] ?? 0) * (int) ($item['quantity'] ?? 0);
            }
            $normalSignature = 'normal:' . implode(',', $normalSignature) . ':v' . round($normalValue, 2);

            $records = $this->recordsFromItems($normalItems, $db, $defaultdims);
            $normalParcel = $this->packRecords($records, $db, $defaultdims);
            $this->precomputedNormalParcel = $normalParcel;
            $overLimit = !$this->parcelFitsLimits($normalParcel);

            $splitParcels = null;
            if ($overLimit && defined('MODULE_SHIPPING_AUPOST_SPLIT_OVERLIMIT')
                && in_array(strtolower(trim((string) MODULE_SHIPPING_AUPOST_SPLIT_OVERLIMIT)), ['yes', 'true', '1', 'on'], true)) {
                $splitParcels = $this->buildOverLimitParcels($records, $db, $defaultdims);
            }

            if (!empty($splitParcels)) {
                foreach ($splitParcels as $parcel) {
                    $parcelValue = (float) ($parcel['value'] ?? 0);
                    $shipments[] = [
                        'type' => 'normal',
                        'parcel' => $parcel,
                        'signature' => 'normal-split:' . $this->parcelSignature($parcel)
                            . ':v' . round($parcelValue, 2),
                        'apply_tare' => true,
                        'value_raw' => $parcelValue,
                        'items' => $parcel['items'] ?? [],
                    ];
                }
                $needsSplit = true;
            } else {
                // Not splittable (or splitting disabled): keep the normal
                // shipment so quoteSingle() can produce the existing quote or
                // over-limit error.
                $shipments[] = [
                    'type' => 'normal',
                    'parcel' => null,
                    'signature' => $normalSignature,
                    'apply_tare' => true,
                    'value_raw' => $normalValue,
                    'items' => $records,
                ];
            }
        }

        $this->allocateSplitOrderValues($shipments, $cartValue);

        if ($needsSplit) {
            $this->rememberParcelAssignments($shipments);
        }

        return $needsSplit ? $shipments : null;
    }

    /**
     * Remember which parcel each cart item was assigned to.
     *
     * The parcel split is calculated while quoting. At order creation the
     * shipping quote is not recalculated, so the allocation is kept in the
     * session and picked up by the aupost parcel-names observer, which appends
     * the parcel number to orders_products.products_name.
     *
     * The cart hash makes sure a stale allocation (for example after the cart
     * was edited or a different shipping method was chosen) is not applied.
     *
     * @param array $shipments Result of buildSplitShipments()
     * @return void
     */
    private function rememberParcelAssignments(array $shipments): void
    {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            return;
        }
        if (!isset($_SESSION['cart']) || !is_object($_SESSION['cart'])) {
            return;
        }

        $cartItems = $_SESSION['cart']->get_products();
        if (!is_array($cartItems) || empty($cartItems)) {
            return;
        }

        $hashParts = [];
        foreach ($cartItems as $item) {
            $hashParts[] = (string) ($item['id'] ?? '') . ':' . (int) ($item['quantity'] ?? 0);
        }
        sort($hashParts);

        $items = [];
        foreach ($shipments as $index => $shipment) {
            $parcelNo = $index + 1;
            foreach (($shipment['items'] ?? []) as $item) {
                $id = (string) ($item['id'] ?? '');
                $qty = (int) ($item['qty'] ?? 0);
                if ($id === '' || $qty <= 0) {
                    continue;
                }
                if (!isset($items[$id])) {
                    $items[$id] = [];
                }
                $items[$id][$parcelNo] = ($items[$id][$parcelNo] ?? 0) + $qty;
            }
        }

        if (empty($items)) {
            return;
        }

        $_SESSION['aupostParcelAssignments'] = [
            'cart_hash' => md5(implode('|', $hashParts)),
            'parcel_count' => count($shipments),
            'items' => $items,
            'created' => time(),
        ];
    }

    /**
     * Allocate the order total across split shipments in proportion to item
     * value, so extra cover is not charged on the full order value for every
     * parcel. When the order total allows, each paid parcel is given at least
     * the minimum extra-cover value so insured services stay available.
     *
     * Zero-value shipments (free products, gifts or 100% discounts) are
     * excluded from the minimum rule and from the paid value pool. They get a
     * declared value of 0, so only uninsured services are quoted for them and
     * they never consume cover from paid parcels.
     *
     * @param array $shipments Modified in place; each gets a 'value' key
     * @param float $cartValue Sum of item values in the cart
     * @return void
     */
    private function allocateSplitOrderValues(array &$shipments, float $cartValue): void
    {
        global $order;

        $orderTotal = (float) ($order->info['total'] ?? 0);

        if (empty($shipments)) {
            return;
        }

        $rawValues = [];
        $paidIndexes = [];
        $paidRawTotal = 0.0;
        foreach ($shipments as $index => $shipment) {
            $rawValues[$index] = max(0.0, (float) ($shipment['value_raw'] ?? 0));
            if ($rawValues[$index] > 0) {
                $paidIndexes[] = $index;
                $paidRawTotal += $rawValues[$index];
            }
        }

        // Nothing paid to insure (all-free cart, or no order total): quote
        // every parcel at zero declared value so only uninsured services are
        // offered. Do not fall back to the full order total here.
        if (empty($paidIndexes) || $paidRawTotal <= 0 || $orderTotal <= 0) {
            foreach ($shipments as &$shipment) {
                $shipment['value'] = 0.0;
            }
            unset($shipment);
            return;
        }

        // Paid parcels share the order total by item value; zero-value
        // parcels are deliberately left at zero.
        $values = [];
        foreach ($shipments as $index => $shipment) {
            $values[$index] = $rawValues[$index] > 0
                ? $orderTotal * $rawValues[$index] / $paidRawTotal
                : 0.0;
        }

        // Keep insured services available for every paid parcel when the
        // order total can cover the minimum on each paid parcel. The extra
        // amount is taken back only from the paid parcels above the minimum.
        $paidCount = count($paidIndexes);
        if ($orderTotal >= self::MIN_INSURED_VALUE * $paidCount) {
            $excess = 0.0;
            foreach ($paidIndexes as $index) {
                if ($values[$index] < self::MIN_INSURED_VALUE) {
                    $excess += self::MIN_INSURED_VALUE - $values[$index];
                    $values[$index] = (float) self::MIN_INSURED_VALUE;
                }
            }

            if ($excess > 0) {
                $capacity = 0.0;
                foreach ($paidIndexes as $index) {
                    $capacity += max(0.0, $values[$index] - self::MIN_INSURED_VALUE);
                }
                if ($capacity > 0) {
                    foreach ($paidIndexes as $index) {
                        $values[$index] -= $excess
                            * max(0.0, $values[$index] - self::MIN_INSURED_VALUE)
                            / $capacity;
                    }
                }
            }
        }

        // Absorb any rounding drift in the largest paid share.
        $drift = round($orderTotal - array_sum($values), 2);
        if (abs($drift) >= 0.01) {
            $largest = null;
            foreach ($paidIndexes as $index) {
                if ($largest === null || $values[$index] > $values[$largest]) {
                    $largest = $index;
                }
            }
            if ($largest !== null) {
                $values[$largest] = round($values[$largest] + $drift, 2);
            }
        }

        foreach ($shipments as $index => &$shipment) {
            $shipment['value'] = round($values[$index], 2);
        }
        unset($shipment);
    }

    /**
     * Build packable product records (with quantities) from cart items.
     *
     * @param array  $items
     * @param object $db
     * @param array  $defaultdims
     * @return array
     */
    private function recordsFromItems(array $items, object $db, array $defaultdims): array
    {
        $records = [];
        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            [$h, $w, $l] = $this->itemDims($item, $db, $defaultdims);
            $records[] = [
                'id' => $item['id'],
                'qty' => $qty,
                'weight' => max((float) ($item['weight'] ?? 0), 1),
                'h' => $h,
                'w' => $w,
                'l' => $l,
                'volume' => $h * $w * $l,
                'unit_value' => (float) ($item['final_price'] ?? 0),
            ];
        }

        return $records;
    }

    /**
     * Pack a set of product records into a single parcel.
     *
     * @param array  $records
     * @param object $db
     * @param array  $defaultdims
     * @return array
     */
    private function packRecords(array $records, object $db, array $defaultdims): array
    {
        // Merge identical records so a product split across bin additions is
        // packed as one quantity block instead of several smaller blocks.
        $merged = [];
        foreach ($records as $r) {
            $key = ($r['id'] ?? '') . '|' . ($r['weight'] ?? 0) . '|'
                . ($r['h'] ?? 0) . '|' . ($r['w'] ?? 0) . '|' . ($r['l'] ?? 0)
                . '|' . ($r['unit_value'] ?? 0);
            if (isset($merged[$key])) {
                $merged[$key]['qty'] += (int) ($r['qty'] ?? 0);
            } else {
                $merged[$key] = $r;
            }
        }

        $items = [];
        foreach ($merged as $r) {
            $items[] = [
                'id' => $r['id'],
                'quantity' => $r['qty'],
                'weight' => $r['weight'],
                'length' => $r['l'],
                'width' => $r['w'],
                'height' => $r['h'],
            ];
        }

        $cart = new class($items) {
            private array $items;

            public function __construct(array $items)
            {
                $this->items = $items;
            }

            public function get_products(): array
            {
                return $this->items;
            }
        };

        return $this->calculateOptimalParcel($cart, $db, $defaultdims);
    }

    /**
     * Stable signature for a parcel's dimensions and raw weight.
     *
     * @param array $parcel
     * @return string
     */
    private function parcelSignature(array $parcel): string
    {
        return ($parcel['length'] ?? 0) . 'x' . ($parcel['width'] ?? 0) . 'x'
            . ($parcel['height'] ?? 0) . ':' . ($parcel['weight'] ?? 0);
    }

    /**
     * Convert a raw parcel weight to kilograms, including packaging tare.
     *
     * @param float $rawWeight Weight in the cart's configured unit
     * @return float
     */
    private function parcelWeightKg(float $rawWeight): float
    {
        $weight = $rawWeight + (($rawWeight * (float) MODULE_SHIPPING_AUPOST_TARE) / 100);
        if (MODULE_SHIPPING_AUPOST_WEIGHT_FORMAT == 'gms') {
            $weight /= 1000;
        }

        return $weight;
    }

    /**
     * Whether a packed parcel is within Australia Post's limits.
     *
     * @param array $parcel
     * @return bool
     */
    private function parcelFitsLimits(array $parcel): bool
    {
        // Mirror quoteSingle(): round the height up, re-orient the parcel so
        // its longest side is the length, then apply the same limits.
        $sides = [
            ceil((float) ($parcel['height'] ?? 0)),
            (float) ($parcel['width'] ?? 0),
            (float) ($parcel['length'] ?? 0),
        ];
        $longest = max($sides);

        return $this->parcelWeightKg((float) ($parcel['weight'] ?? 0)) <= (float) $this->MAXWEIGHT_P
            && $longest < (float) $this->MAXLENGTH_P
            && (float) ($parcel['cube'] ?? 0) <= (float) $this->MAXCUBIC_P;
    }

    /**
     * Distribute normal cart items over as few parcels as possible while
     * keeping every parcel within Australia Post's limits.
     *
     * @param array  $records Product records from recordsFromItems()
     * @param object $db
     * @param array  $defaultdims
     * @return array|null Parcel arrays, or null when an item cannot fit at all
     */
    private function buildOverLimitParcels(array $records, object $db, array $defaultdims): ?array
    {
        if (empty($records)) {
            return null;
        }

        // Largest items first gives a better first-fit result.
        usort($records, fn($a, $b) => $b['volume'] <=> $a['volume']);

        $bins = [];

        foreach ($records as $product) {
            $remaining = (int) $product['qty'];

            // Fill existing parcels first.
            foreach ($bins as $index => $bin) {
                if ($remaining <= 0) {
                    break;
                }
                $fit = $this->maxQtyThatFits($bin, $product, $remaining, $db, $defaultdims);
                if ($fit > 0) {
                    $bins[$index][] = array_merge($product, ['qty' => $fit]);
                    $remaining -= $fit;
                }
            }

            // Start new parcels for anything that did not fit.
            while ($remaining > 0) {
                $fit = $this->maxQtyThatFits([], $product, $remaining, $db, $defaultdims);
                if ($fit <= 0) {
                    return null; // A single unit is too large or too heavy.
                }
                $bins[] = [array_merge($product, ['qty' => $fit])];
                $remaining -= $fit;
            }
        }

        $parcels = [];
        foreach ($bins as $bin) {
            $parcel = $this->packRecords($bin, $db, $defaultdims);
            if (!$this->parcelFitsLimits($parcel)) {
                return null;
            }

            // Keep the item value with the parcel so split quotes can allocate
            // insurance cover proportionally instead of insuring the full
            // order value on every parcel.
            $parcelValue = 0.0;
            foreach ($bin as $r) {
                $parcelValue += (float) ($r['unit_value'] ?? 0) * (int) ($r['qty'] ?? 0);
            }
            $parcel['value'] = $parcelValue;

            // Keep the item allocation so the packing slip can show which
            // parcel each ordered product belongs to.
            $parcel['items'] = array_map(
                static fn(array $r): array => ['id' => $r['id'], 'qty' => (int) $r['qty']],
                $bin
            );

            $parcels[] = $parcel;
        }

        return $parcels;
    }

    /**
     * Largest quantity of $product that can be added to $bin and still fit
     * within Australia Post's limits.
     *
     * @param array  $bin         Product records already in the parcel
     * @param array  $product     Product record to add
     * @param int    $maxQty      Upper bound to test
     * @param object $db
     * @param array  $defaultdims
     * @return int
     */
    private function maxQtyThatFits(array $bin, array $product, int $maxQty, object $db, array $defaultdims): int
    {
        if ($maxQty <= 0) {
            return 0;
        }

        // A single unit must fit on its own before quantities are considered.
        $longest = max((float) $product['h'], (float) $product['w'], (float) $product['l']);
        if ($longest * self::PACKING_TOLERANCE >= (float) $this->MAXLENGTH_P) {
            return 0;
        }

        // Quick capacity estimate (weight and volume) to bound the search.
        $tareFactor = 1 + ((float) MODULE_SHIPPING_AUPOST_TARE / 100);
        $maxWeightRaw = ((float) $this->MAXWEIGHT_P) / $tareFactor;
        if (MODULE_SHIPPING_AUPOST_WEIGHT_FORMAT == 'gms') {
            $maxWeightRaw *= 1000;
        }
        $maxCubeRaw = ((float) $this->MAXCUBIC_P) * 1000000 / (self::PACKING_TOLERANCE ** 3);

        $binWeight = 0.0;
        $binVolume = 0.0;
        foreach ($bin as $r) {
            $binWeight += (float) $r['weight'] * (int) $r['qty'];
            $binVolume += (float) $r['volume'] * (int) $r['qty'];
        }

        $unitWeight = (float) $product['weight'];
        $unitVolume = (float) $product['volume'];
        if ($unitWeight <= 0 || $unitVolume <= 0) {
            return 0;
        }

        $byWeight = (int) floor(($maxWeightRaw - $binWeight) / $unitWeight);
        $byVolume = (int) floor(($maxCubeRaw - $binVolume) / $unitVolume);
        $cap = min($maxQty, $byWeight, $byVolume);
        if ($cap <= 0) {
            return 0;
        }

        // Fast path: the whole estimated quantity fits.
        if ($this->recordsFit($bin, $product, $cap, $db, $defaultdims)) {
            return $cap;
        }

        // Binary search below the estimate.
        $lo = 0;
        $hi = $cap - 1;
        while ($lo < $hi) {
            $mid = (int) ceil(($lo + $hi) / 2);
            if ($this->recordsFit($bin, $product, $mid, $db, $defaultdims)) {
                $lo = $mid;
            } else {
                $hi = $mid - 1;
            }
        }

        return $lo;
    }

    /**
     * Whether adding $qty of $product to $bin produces a valid parcel.
     *
     * @param array  $bin
     * @param array  $product
     * @param int    $qty
     * @param object $db
     * @param array  $defaultdims
     * @return bool
     */
    private function recordsFit(array $bin, array $product, int $qty, object $db, array $defaultdims): bool
    {
        if ($qty <= 0) {
            return true;
        }

        $records = $bin;
        $records[] = array_merge($product, ['qty' => $qty]);

        return $this->parcelFitsLimits($this->packRecords($records, $db, $defaultdims));
    }


    /**
     * Quote every shipment and combine the results by Australia Post service id.
     * Only services available for every parcel are offered; costs are summed.
     *
     * @param array $shipments Result of buildSplitShipments()
     * @return array|mixed|null
     */
    private function quoteSplitShipments(array $shipments)
    {
        global $order;

        // Mirror quoteSingle()'s destination checks before any API calls.
        $dest_country = $order->delivery['country']['iso_code_2'] ?? '';
        $topcode = str_replace(' ', '', $order->delivery['postcode'] ?? '');
        if ($dest_country != "AU" || $topcode === ''
            || !$this->validate_au_postcode($topcode, $dest_country, $order)) {
            return null;
        }

        // A split cart must not reuse the single-parcel cache.
        unset($_SESSION['aupostParcel']);

        $signature = $this->splitCacheSignature($shipments);

        if (USE_CACHE == "Yes"
            && isset($_SESSION['aupostSplitParcel'], $_SESSION['aupostSplitQuotes'])
            && $_SESSION['aupostSplitParcel'] === $signature
            && is_array($_SESSION['aupostSplitQuotes'])) {
            $this->quotes = $_SESSION['aupostSplitQuotes'];
            $_SESSION['aupostQuotes'] = $this->quotes;
            return $this->quotes;
        }

        $paidMethods = [];
        $zeroMethods = [];
        $quotesBySignature = [];

        foreach ($shipments as $shipment) {
            // Identical own-box units cost the same, so quote them once.
            $key = $shipment['signature'] ?? md5(serialize($shipment['parcel']));

            if (isset($quotesBySignature[$key])) {
                $quote = $quotesBySignature[$key];
            } else {
                // Letters are not offered when an order is split across parcels;
                // every shipment must be quoted with the same parcel service set.
                $quote = $this->quoteSingle(
                    $shipment['parcel'],
                    true,
                    true,
                    true,
                    $shipment['apply_tare'] ?? true,
                    isset($shipment['value']) ? (float) $shipment['value'] : null
                );
                $quotesBySignature[$key] = $quote;
            }

            if (!is_array($quote) || empty($quote['methods']) || !is_array($quote['methods'])) {
                return $this->splitErrorQuote();
            }

            // Zero-value shipments only offer uninsured services; keep them
            // apart so they do not remove insured services from paid parcels.
            if ((float) ($shipment['value'] ?? 0) > 0) {
                $paidMethods[] = $quote['methods'];
            } else {
                $zeroMethods[] = $quote['methods'];
            }
        }

        $methods = $this->mergeSplitMethods($paidMethods, $zeroMethods, count($shipments));
        if (empty($methods)) {
            return $this->splitErrorQuote();
        }

        $this->quotes = [
            'id' => $this->code,
            'module' => $this->title,
            'methods' => $methods,
        ];

        if ($this->tax_class > 0) {
            $this->quotes['tax'] = zen_get_tax_rate((int) $this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
        }
        if (zen_not_null($this->icon)) {
            $this->quotes['icon'] = zen_image($this->icon, $this->title);
        }

        if (USE_CACHE == "Yes") {
            $_SESSION['aupostSplitParcel'] = $signature;
            $_SESSION['aupostSplitQuotes'] = $this->quotes;
        }
        $_SESSION['aupostQuotes'] = $this->quotes;

        return $this->quotes;
    }

    /**
     * Combine per-parcel method lists by Australia Post service id.
     *
     * Paid parcels must all offer the exact same service, and their costs are
     * summed. Zero-value parcels are handled separately: they only offer
     * uninsured services, so each paid service is matched to the most specific
     * service in the same family whose options are a subset of the paid
     * service. That keeps insured services available for the paid parcels
     * without insuring a free parcel.
     *
     * @param array $paidMethods One array of methods per paid parcel
     * @param array $zeroMethods One array of methods per zero-value parcel
     * @param int   $parcelCount Total number of parcels, for the title suffix
     * @return array Merged methods, cheapest first; empty when no common service
     */
    private function mergeSplitMethods(array $paidMethods, array $zeroMethods = [], int $parcelCount = 0): array
    {
        // All-free cart: every parcel is a zero-value parcel, so fall back to
        // an exact intersection across them.
        if (empty($paidMethods)) {
            $paidMethods = $zeroMethods;
            $zeroMethods = [];
        }

        $merged = null;

        foreach ($paidMethods as $methods) {
            $valid = $this->normaliseSplitMethods($methods);
            if (empty($valid)) {
                return [];
            }

            if ($merged === null) {
                $merged = $valid;
                continue;
            }

            // Keep only services available for every paid parcel and add costs.
            $merged = array_intersect_key($merged, $valid);
            foreach ($merged as $id => &$method) {
                $method['cost'] = (float) $method['cost'] + (float) $valid[$id]['cost'];
            }
            unset($method);
        }

        if (empty($merged)) {
            return [];
        }

        // Add a compatible uninsured service for every zero-value parcel.
        foreach ($zeroMethods as $methods) {
            $valid = $this->normaliseSplitMethods($methods);
            if (empty($valid)) {
                return [];
            }

            $remove = [];
            foreach ($merged as $id => &$method) {
                $match = $this->matchZeroValueService((string) $id, $valid);
                if ($match === null) {
                    $remove[] = $id;
                    continue;
                }
                $method['cost'] = (float) $method['cost'] + (float) $match['cost'];
            }
            unset($method);

            foreach ($remove as $id) {
                unset($merged[$id]);
            }

            if (empty($merged)) {
                return [];
            }
        }

        $methods = array_values($merged);
        usort($methods, fn($a, $b) => $a['cost'] <=> $b['cost']);

        if ($parcelCount <= 0) {
            $parcelCount = count($paidMethods) + count($zeroMethods);
        }

        // Make it clear that the total covers more than one parcel.
        if ($parcelCount > 1) {
            foreach ($methods as &$method) {
                $method['title'] = ($method['title'] ?? '') . ' (' . $parcelCount . ' parcels)';
            }
            unset($method);
        }

        return $methods;
    }

    /**
     * Normalise one parcel's method list into service id => cheapest method.
     *
     * @param array $methods
     * @return array
     */
    private function normaliseSplitMethods(array $methods): array
    {
        $valid = [];
        foreach ($methods as $method) {
            if (!isset($method['id'], $method['cost'])) {
                continue;
            }
            if (in_array($method['id'], ['Error', $this->code], true)) {
                continue;
            }
            if ((float) $method['cost'] <= 0) {
                continue;
            }
            // Keep the cheapest option when a service id appears more than
            // once for the same parcel.
            if (!isset($valid[$method['id']])
                || (float) $method['cost'] < (float) $valid[$method['id']]['cost']) {
                $valid[$method['id']] = $method;
            }
        }

        return $valid;
    }

    /**
     * Find the most specific zero-value service compatible with a paid
     * service. Compatibility means the same base service and a subset of its
     * option codes, so an insured paid service can use the zero-value parcel's
     * uninsured (or signature-only) service.
     *
     * @param string $paidId
     * @param array  $zeroMethods
     * @return array|null
     */
    private function matchZeroValueService(string $paidId, array $zeroMethods): ?array
    {
        [$paidBase, $paidOptions] = $this->splitServiceParts($paidId);

        $best = null;
        $bestOptions = -1;

        foreach ($zeroMethods as $zeroId => $method) {
            [$zeroBase, $zeroOptions] = $this->splitServiceParts((string) $zeroId);

            if ($zeroBase !== $paidBase) {
                continue;
            }

            // Every option on the zero-value service must also be on the paid
            // service (for example signature on an insured +sig service).
            if (!empty(array_diff_key($zeroOptions, $paidOptions))) {
                continue;
            }

            if (count($zeroOptions) > $bestOptions) {
                $best = $method;
                $bestOptions = count($zeroOptions);
            }
        }

        return $best;
    }

    /**
     * Split an Australia Post service id into its base service and option
     * codes. Option codes are appended to the base id with underscores
     * removed, e.g. AUSPARCELREGULAR + AUSSERVICEOPTIONEXTRACOVER.
     *
     * @param string $id
     * @return array [base id, option codes keyed by code]
     */
    private function splitServiceParts(string $id): array
    {
        $optionCodes = [
            'AUSSERVICEOPTIONSTANDARD',
            'AUSSERVICEOPTIONSIGNATUREONDELIVERY',
            'AUSSERVICEOPTIONEXTRACOVER',
        ];

        $base = $id;
        $options = [];

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($optionCodes as $code) {
                $length = strlen($code);
                if ($length > 0 && substr($base, -$length) === $code) {
                    $base = substr($base, 0, -$length);
                    $options[$code] = true;
                    $changed = true;
                    break;
                }
            }
        }

        return [$base, $options];
    }

    /**
     * Cache signature for a split order: destination, order value and every
     * shipment's contents/parcel dimensions.
     *
     * @param array $shipments
     * @return string
     */
    private function splitCacheSignature(array $shipments): string
    {
        global $order, $currencies;

        $dest_country = $order->delivery['country']['iso_code_2'] ?? '';
        $topcode = str_replace(' ', '', $order->delivery['postcode'] ?? '');

        $aus_rate = (float) $currencies->get_value('AUD');
        if ($aus_rate == 0) {
            $aus_rate = (float) $currencies->get_value('AUS');
            if ($aus_rate == 0) {
                $aus_rate = 1;
            }
        }
        $ordervalue = round(($order->info['total'] ?? 0) / $aus_rate, 2);

        $parts = [$dest_country, $topcode, $ordervalue];
        foreach ($shipments as $shipment) {
            $parts[] = ($shipment['signature'] ?? '') . ':v' . round((float) ($shipment['value'] ?? 0), 2);
        }

        return implode('|', $parts);
    }

    /**
     * Error quote when no service is available for every parcel.
     *
     * @return array|mixed|null
     */
    private function splitErrorQuote()
    {
        global $order;

        $dest_country = $order->delivery['country']['iso_code_2'] ?? '';
        $this->error_msg_ap = ERROR_NO_VALID_PARCEL_QUOTE_MSG;
        $cost = $this->_get_error_cost($dest_country, $this->error_msg_ap);

        if ($this->enabled == FALSE) {
            return null;
        }

        $this->quotes = [
            'id' => $this->code,
            'module' => $this->title,
            'methods' => [[
                'id' => 'Error',
                'title' => MODULE_SHIPPING_AUPOST_TEXT_ERROR,
                'cost' => $cost,
            ]],
        ];

        if ($this->tax_class > 0) {
            $this->quotes['tax'] = zen_get_tax_rate((int) $this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
        }
        if (zen_not_null($this->icon)) {
            $this->quotes['icon'] = zen_image($this->icon, $this->title);
        }

        return $this->quotes;
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
     *  - Each item quantity generates candidate 3D block arrangements
     *    (a x b x c >= q), not just flat grids or single-column stacks.
     *    Counts are paired largest-to-smallest dimension so the longest
     *    side stays as short as possible.
     *  - Candidates are ranked by bounding volume (cubic weight), then
     *    longest side, then girth, then wasted cells. Volume comes first
     *    because Australia Post bills cubic weight.
     *  - Candidates are trialled against existing layers using a 2D shelf
     *    packer that tracks used floor space. A block is placed in an
     *    existing shelf, a new shelf, or a new layer, whichever gives the
     *    smallest cube while staying within the maximum parcel length.
     *  - Final parcel = max(width, length) per axis across layers, height
     *    summed across layers.
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
            // Own-box items are quoted separately as their own parcels.
            if ($this->isOwnBoxItem($item)) {
                continue;
            }

            $q = (int) $item['quantity'];
            $w = max((float) $item['weight'], 1);

            // Prefer dimensions already loaded with the cart item; fall back
            // to the products table when any side is missing.
            [$h, $w_dim, $l] = $this->itemDims($item, $db, $defaultdims);

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
        //    Each layer is a horizontal slab with a footprint (width x       //
        //    length) and a height. Shelves are rows within that slab; each   //
        //    shelf tracks how much of its width is already used, so blocks   //
        //    placed side by side cannot overlap.                             //
        //    A block goes into an existing shelf, a new shelf, or a new      //
        //    layer - whichever produces the best overall parcel.             //
        // ------------------------------------------------------------------ //
        $layers = [];

        foreach ($products as $product) {
            $q = $product['qty'];
            $h = $product['h'];
            $w = $product['w'];
            $l = $product['l'];

            $parcelweight += $product['weight'] * $q;
            $packageitems += $q;

            // ---- Place the quantity, splitting it when that helps ------ //
            // A product may be split across several blocks so part of it
            // can fill an existing shelf's free floor space instead of
            // starting a new shelf or layer.
            $remaining = $q;
            $attempt = 0;

            while ($remaining > 0) {
                $attempt++;

                // Safety valve: never loop forever on pathological splits.
                if ($attempt > 60) {
                    $fallback = $this->buildBlockCandidates($remaining, [$h, $w, $l]);
                    usort($fallback, fn($a, $b) =>
                        $this->blockScore($a) <=> $this->blockScore($b)
                    );
                    $best = $fallback[0];
                    $layers[] = $this->newLayer($best['w'], $best['l'], $best['h']);
                    $packinglog[] = [
                        'id' => $product['id'],
                        'qty' => $remaining,
                        'arrangement' => "{$best['cols']} wide x {$best['rows']} deep x {$best['layers']} high (new layer, fallback)",
                        'block_w' => round($best['w'], 2),
                        'block_l' => round($best['l'], 2),
                        'block_h' => round($best['h'], 2),
                        'layer' => 'new',
                        'x' => 0.0,
                        'y' => 0.0,
                        'z' => 0.0,
                    ];
                    break;
                }

                // ---- Generate all candidate 3D block arrangements -------- //
                // a x b x c >= q, where a is the number of layers along the
                // item's thinnest axis and b x c is the footprint grid.
                // This includes blocks such as 2 x 2 x 25 for 100 x 12x12x1.
                $candidates = $this->buildBlockCandidates($remaining, [$h, $w, $l]);

                // Add blocks sized to fill the free width of an existing
                // shelf. They hold fewer items, so the rest of the
                // quantity is placed in a later pass.
                foreach ($this->buildGapCandidates($layers, [$h, $w, $l], $remaining) as $gapCandidate) {
                    $candidates[] = $gapCandidate;
                }

                // Best = smallest cube (cubic weight), then shortest longest
                // side, then smallest girth, then least wasted cells.
                usort($candidates, fn($a, $b) =>
                    $this->blockScore($a) <=> $this->blockScore($b)
                );

                // ---- Choose the best placement across all layers --------- //
                // For every candidate block and both footprint orientations,
                // try each existing layer with the shelf packer, and also try
                // starting a new layer. Keep the option that gives the best
                // overall parcel dimensions; on a tie, consume more items.
                $bestOption = null;
                $bestScore = null;

                foreach ($candidates as $c) {
                    $candidateQty = max(1, min($remaining, (int) ($c['qty'] ?? $remaining)));
                    $orientations = [[$c['w'], $c['l']]];
                    if ($c['w'] != $c['l']) {
                        $orientations[] = [$c['l'], $c['w']];
                    }

                    foreach ($orientations as [$bw, $bl]) {
                        // Try each existing layer. A block can go on the
                        // floor or be stacked on a shorter placement, so
                        // score every variant the layer offers.
                        foreach ($layers as $li => $layer) {
                            foreach ($this->placeVariantsInLayer($layer, $bw, $bl, $c['h']) as $trialLayer) {
                                $score = $this->scorePlacement($layers, $li, $trialLayer, null);

                                if ($bestScore === null || $score < $bestScore
                                    || ($score === $bestScore && $candidateQty > $bestOption['qty'])) {
                                    $bestScore = $score;
                                    $bestOption = [
                                        'new' => false,
                                        'layer_index' => $li,
                                        'layer' => $trialLayer,
                                        'candidate' => $c,
                                        'bw' => $bw,
                                        'bl' => $bl,
                                        'qty' => $candidateQty,
                                    ];
                                }
                            }
                        }

                        // Try a new layer.
                        $newLayer = $this->newLayer($bw, $bl, $c['h']);
                        $score = $this->scorePlacement($layers, null, null, $newLayer);

                        if ($bestScore === null || $score < $bestScore
                            || ($score === $bestScore && $candidateQty > $bestOption['qty'])) {
                            $bestScore = $score;
                            $bestOption = [
                                'new' => true,
                                'layer_index' => null,
                                'layer' => $newLayer,
                                'candidate' => $c,
                                'bw' => $bw,
                                'bl' => $bl,
                                'qty' => $candidateQty,
                            ];
                        }
                    }
                }

                // Defensive fallback; the new-layer option above should
                // always have set $bestOption.
                if ($bestOption === null) {
                    $best = $candidates[0];
                    $bestOption = [
                        'new' => true,
                        'layer_index' => null,
                        'layer' => $this->newLayer($best['w'], $best['l'], $best['h']),
                        'candidate' => $best,
                        'bw' => $best['w'],
                        'bl' => $best['l'],
                        'qty' => $remaining,
                    ];
                }

                // Apply the chosen placement.
                if ($bestOption['new']) {
                    $layers[] = $bestOption['layer'];
                } else {
                    $layers[$bestOption['layer_index'] ?? 0] = $bestOption['layer']; // BMH 2026-11-06 Expected type 'string|int'. Found 'null'.
                }

                $best = $bestOption['candidate'];
                $consumed = $bestOption['qty'];
                $placements = $bestOption['layer']['placements'];
                $placement = end($placements);
                $packinglog[] = [
                    'id' => $product['id'],
                    'qty' => $consumed,
                    'arrangement' => "{$best['cols']} wide x {$best['rows']} deep x {$best['layers']} high ("
                        . ($bestOption['new'] ? 'new layer' : 'shared layer')
                        . ', placed ' . round($bestOption['bw'], 2) . ' x ' . round($bestOption['bl'], 2) . ')',
                    'block_w' => round($bestOption['bw'], 2),
                    'block_l' => round($bestOption['bl'], 2),
                    'block_h' => round($best['h'], 2),
                    'layer' => $bestOption['new'] ? 'new' : $bestOption['layer_index'],
                    'x' => round($placement['x'], 2),
                    'y' => round($placement['y'], 2),
                    'z' => round($placement['z'] ?? 0.0, 2),
                ];

                $remaining -= $consumed;
            }

            if (MODULE_SHIPPING_AUPOST_DEBUG == "Yes"
                && !isset($this->debuggedCartProducts[(string) $product['id']])) {
                $this->debuggedCartProducts[(string) $product['id']] = true;
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
        $tolerance = self::PACKING_TOLERANCE;
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
     * Generate candidate 3D blocks for $q identical items.
     *
     * Counts are returned in descending order a >= b >= c with
     * a * b * c >= q. The largest count is paired with the item's
     * smallest dimension, so the longest side is kept as short as
     * possible. The block is:
     *   height = a * d0   (layers along the thinnest axis)
     *   width  = b * d1
     *   length = c * d2
     *
     * For 100 items of 12 x 12 x 1 this includes 2 x 2 x 25,
     * i.e. 24 x 24 x 25, instead of only 12 x 12 x 100 or
     * 120 x 120 x 1.
     *
     * @param int   $q     Quantity (>= 1)
     * @param array $dims  [smallest, middle, largest] item dims in cm
     * @return array
     */
    private function buildBlockCandidates(int $q, array $dims): array
    {
        $q = max(1, $q);
        sort($dims);
        $d0 = max((float) ($dims[0] ?? 1), 0.01);
        $d1 = max((float) ($dims[1] ?? 1), 0.01);
        $d2 = max((float) ($dims[2] ?? 1), 0.01);

        $candidates = [];
        $seen = [];

        // The smallest count never needs to exceed the cube root of q.
        // Beyond that, every count is larger than a balanced block, so
        // the block is bigger on every axis.
        $cMax = max(1, (int) ceil(pow($q, 1 / 3)) + 1);

        for ($c = 1; $c <= $cMax; $c++) {
            for ($b = $c; $b <= $q; $b++) {
                $a = (int) ceil($q / ($b * $c));
                if ($a < $b) {
                    break; // increasing b only makes a smaller
                }

                $height = $a * $d0;          // stack along the thinnest axis
                $f1 = $b * $d1;
                $f2 = $c * $d2;
                $width = min($f1, $f2);
                $length = max($f1, $f2);

                $key = round($width, 3) . 'x' . round($length, 3) . 'x' . round($height, 3);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $candidates[] = [
                    'w' => $width,
                    'l' => $length,
                    'h' => $height,
                    'cols' => $c,
                    'rows' => $b,
                    'layers' => $a,
                    'waste' => ($a * $b * $c) - $q,
                    'qty' => $q,
                ];
            }
        }

        return $candidates;
    }

    /**
     * Generate blocks sized to fill the unused width of an existing shelf.
     *
     * These hold fewer than $qty items, so the caller can place the rest of
     * the quantity in a later pass. Without this a whole-quantity block
     * either fits a gap or starts a new shelf/layer, leaving the gap empty.
     *
     * Only the free width of each shelf is used. The shelf depth and the
     * layer height are respected, so a candidate can always be placed in
     * that shelf without overlapping an existing block.
     *
     * @param array $layers Current layers
     * @param array $dims   [smallest, middle, largest] item dims in cm
     * @param int   $qty    Items still to place
     * @return array
     */
    private function buildGapCandidates(array $layers, array $dims, int $qty): array
    {
        $qty = max(1, $qty);
        sort($dims);
        $d0 = max((float) ($dims[0] ?? 1), 0.01);
        $d1 = max((float) ($dims[1] ?? 1), 0.01);
        $d2 = max((float) ($dims[2] ?? 1), 0.01);

        $eps = 1e-6;
        $maxHeight = (float) ($this->MAXLENGTH_P ?? 105);
        $heightCap = max(1, (int) floor($maxHeight / $d0));

        $candidates = [];
        $seen = [];

        foreach ($layers as $layer) {
            foreach ($layer['shelves'] as $shelf) {
                $freeW = $layer['w'] - $shelf['used_w'];
                $depth = $shelf['depth'];

                if ($freeW < $d1 - $eps || $depth < $d1 - $eps) {
                    continue;
                }

                foreach ([[$d1, $d2], [$d2, $d1]] as [$iw, $il]) {
                    $cols = (int) floor(($freeW + $eps) / $iw);
                    $rows = (int) floor(($depth + $eps) / $il);

                    if ($cols < 1 || $rows < 1) {
                        continue;
                    }

                    $base = $cols * $rows;                 // cells per layer
                    $fullLayers = intdiv($qty, $base);
                    $maxLayers = $fullLayers + (($qty % $base) > 0 ? 1 : 0);
                    if ($maxLayers < 1) {
                        continue;
                    }
                    $maxLayers = min($maxLayers, $heightCap);

                    // Try one layer, enough layers to match the layer's
                    // current height (free height), and as many as the
                    // remaining quantity allows.
                    $counts = [1];
                    $fitHeight = (int) floor(($layer['h'] + $eps) / $d0);
                    if ($fitHeight > 1) {
                        $counts[] = min($fitHeight, $maxLayers);
                    }
                    $counts[] = $maxLayers;

                    foreach (array_unique($counts) as $n) {
                        $n = min($n, $maxLayers);
                        if ($n < 1) {
                            continue;
                        }

                        $cap = min($qty, $base * $n);
                        $bw = $cols * $iw;
                        $bl = $rows * $il;
                        $bh = $n * $d0;

                        $key = round($bw, 3) . 'x' . round($bl, 3) . 'x' . round($bh, 3);
                        if (isset($seen[$key])) {
                            continue;
                        }
                        $seen[$key] = true;

                        $candidates[] = [
                            'w' => $bw,
                            'l' => $bl,
                            'h' => $bh,
                            'cols' => $cols,
                            'rows' => $rows,
                            'layers' => $n,
                            'waste' => ($base * $n) - $cap,
                            'qty' => $cap,
                        ];
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * Score a block; lower is better.
     *
     * Order: bounding volume, longest side, girth, wasted cells.
     * Volume comes first because Australia Post bills cubic weight.
     *
     * @param array $block
     * @return array
     */
    private function blockScore(array $block): array
    {
        $sides = [$block['h'], $block['w'], $block['l']];
        sort($sides);

        return [
            $sides[0] * $sides[1] * $sides[2],  // bounding volume
            $sides[2],                          // longest side
            2 * ($sides[0] + $sides[1]),        // girth of the two shorter sides
            $block['waste'] ?? 0,               // prefer exact blocks on ties
        ];
    }

    /**
     * Create a new layer containing a single block.
     *
     * @param float $bw Block footprint width
     * @param float $bl Block footprint length
     * @param float $bh Block height
     * @return array
     */
    private function newLayer(float $bw, float $bl, float $bh): array
    {
        return [
            'w' => $bw,
            'l' => $bl,
            'h' => $bh,
            'shelves' => [
                ['y' => 0.0, 'depth' => $bl, 'used_w' => $bw],
            ],
            'placements' => [
                ['x' => 0.0, 'y' => 0.0, 'z' => 0.0, 'w' => $bw, 'l' => $bl, 'h' => $bh],
            ],
        ];
    }

    /**
     * Place a block in a layer using shelf packing.
     *
     * A layer is a horizontal slab divided into shelves. Each shelf is a
     * row of blocks with a fixed depth. A block is placed in the shelf
     * with the least wasted width/depth, otherwise a new shelf is started
     * at the end of the layer.
     *
     * Returns a new layer array; the original layer is not modified.
     *
     * @param array $layer Layer to place into
     * @param float $bw    Block footprint width
     * @param float $bl    Block footprint length
     * @param float $bh    Block height
     * @return array
     */
    private function tryPlaceInLayer(array $layer, float $bw, float $bl, float $bh): array
    {
        // 1. Try an existing shelf. Use best-fit (least waste).
        //    The epsilon avoids rejecting an exact fit because of float drift.
        $eps = 1e-6;
        $bestShelf = null;
        $bestWaste = INF;

        foreach ($layer['shelves'] as $i => $shelf) {
            $freeW = $layer['w'] - $shelf['used_w'];
            if ($bw <= $freeW + $eps && $bl <= $shelf['depth'] + $eps) {
                $waste = ($freeW - $bw) + ($shelf['depth'] - $bl);
                if ($waste < $bestWaste) {
                    $bestWaste = $waste;
                    $bestShelf = $i;
                }
            }
        }

        if ($bestShelf !== null) {
            $shelf = $layer['shelves'][$bestShelf];
            $layer['placements'][] = [
                'x' => $shelf['used_w'],
                'y' => $shelf['y'],
                'z' => 0.0,
                'w' => $bw,
                'l' => $bl,
                'h' => $bh,
            ];
            $layer['shelves'][$bestShelf]['used_w'] = $shelf['used_w'] + $bw;
            $layer['h'] = max($layer['h'], $bh);
            return $layer;
        }

        // 2. Start a new shelf at the end of the layer.
        $y = $layer['l'];
        $layer['placements'][] = [
            'x' => 0.0,
            'y' => $y,
            'z' => 0.0,
            'w' => $bw,
            'l' => $bl,
            'h' => $bh,
        ];
        $layer['shelves'][] = [
            'y' => $y,
            'depth' => $bl,
            'used_w' => $bw,
        ];
        $layer['w'] = max($layer['w'], $bw);
        $layer['l'] = $y + $bl;
        $layer['h'] = max($layer['h'], $bh);

        return $layer;
    }

    /**
     * Return every distinct way a block can be added to a layer.
     *
     * The first variant is the normal shelf/floor placement. The rest
     * stack the block on top of an existing placement whose footprint
     * fully supports it. Stacking uses the free height above shorter
     * blocks instead of starting a new layer, which recovers the void
     * that would otherwise be billed as cubic weight.
     *
     * Each variant is a complete copy of the layer, so the caller can
     * score them without changing the original.
     *
     * @param array $layer Layer to place into
     * @param float $bw    Block footprint width
     * @param float $bl    Block footprint length
     * @param float $bh    Block height
     * @return array<int,array>
     */
    private function placeVariantsInLayer(array $layer, float $bw, float $bl, float $bh): array
    {
        // Always offer the shelf/floor placement.
        $variants = [$this->tryPlaceInLayer($layer, $bw, $bl, $bh)];

        $eps = 1e-6;

        // Stacking is the expensive part of the search (every support is
        // tested against every placement). On very large layers only try
        // supports that still have free height above them, which are the
        // ones that actually recover wasted space.
        $supports = $layer['placements'];
        if (count($supports) > 40) {
            $free = [];
            foreach ($supports as $p) {
                if (($p['z'] ?? 0.0) + $p['h'] < $layer['h'] - $eps) {
                    $free[] = $p;
                }
            }
            $supports = array_slice($free, 0, 40);
        }

        foreach ($supports as $support) {
            // The block must sit fully on the support, so it cannot
            // overhang and cannot collide with a neighbouring stack.
            if ($bw > $support['w'] + $eps || $bl > $support['l'] + $eps) {
                continue;
            }

            $z = ($support['z'] ?? 0.0) + $support['h'];
            $box = [
                'x' => $support['x'],
                'y' => $support['y'],
                'z' => $z,
                'w' => $bw,
                'l' => $bl,
                'h' => $bh,
            ];

            if ($this->boxOverlapsPlacements($box, $layer['placements'])) {
                continue;
            }

            $variant = $layer;
            $variant['placements'][] = $box;
            $variant['h'] = max($variant['h'], $z + $bh);
            $variants[] = $variant;
        }

        return $variants;
    }

    /**
     * Test a 3D box against a list of placements for intersection.
     *
     * Boxes that only touch (share a face) do not overlap.
     *
     * @param array $box        ['x','y','z','w','l','h']
     * @param array $placements Placements to test
     * @return bool
     */
    private function boxOverlapsPlacements(array $box, array $placements): bool
    {
        $eps = 1e-6;

        foreach ($placements as $p) {
            $pz = $p['z'] ?? 0.0;
            if ($box['x'] < $p['x'] + $p['w'] - $eps
                && $p['x'] < $box['x'] + $box['w'] - $eps
                && $box['y'] < $p['y'] + $p['l'] - $eps
                && $p['y'] < $box['y'] + $box['l'] - $eps
                && $box['z'] < $pz + $p['h'] - $eps
                && $pz < $box['z'] + $box['h'] - $eps) {
                return true;
            }
        }

        return false;
    }

    /**
     * Score the parcel that would result from a placement.
     *
     * Lower is better:
     *  1. parcels within the maximum length limit (so a quote is not
     *     rejected when a slightly larger but legal parcel exists)
     *  2. smallest cube / bounding volume (Australia Post cubic weight)
     *  3. shortest longest side
     *  4. smallest girth
     *  5. fewest layers
     *
     * @param array      $layers       Current layers
     * @param int|null   $replaceIndex Layer index to replace, or null
     * @param array|null $replacement  Replacement layer
     * @param array|null $extra        Extra layer to append
     * @return array
     */
    private function scorePlacement(array $layers, ?int $replaceIndex, ?array $replacement, ?array $extra): array
    {
        $w = 0.0;
        $l = 0.0;
        $h = 0.0;

        foreach ($layers as $i => $layer) {
            if ($replaceIndex !== null && $replacement !== null && $i === $replaceIndex) {
                $layer = $replacement;
            }
            $w = max($w, $layer['w']);
            $l = max($l, $layer['l']);
            $h += $layer['h'];
        }

        if ($extra !== null) {
            $w = max($w, $extra['w']);
            $l = max($l, $extra['l']);
            $h += $extra['h'];
        }

        $sides = [$w, $l, $h];
        sort($sides);
        [$s0, $s1, $s2] = $sides;

        // Compare against the pre-tolerance limit so the final dimensions
        // (after PACKING_TOLERANCE is applied) still pass the length check.
        $maxLength = ((float) ($this->MAXLENGTH_P ?? 105)) / self::PACKING_TOLERANCE;
        $overLength = max(0.0, $s2 - $maxLength);

        return [
            $overLength > 1e-6 ? 1 : 0,         // prefer a legal parcel length
            $overLength,                        // then the least over-length
            $s0 * $s1 * $s2,                    // then smallest cube
            $s2,                                // then shortest longest side
            2 * ($s0 + $s1),                    // then smallest girth
            count($layers) + ($extra !== null ? 1 : 0), // then fewest layers
        ];
    }

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

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
            VALUES ('Split large orders', 'MODULE_SHIPPING_AUPOST_SPLIT_OVERLIMIT', 'Yes', 'Set to Yes to split oversize orders into multiple parcels.', '6', '65', 'zen_cfg_select_option(array(\'Yes\', \'No\'), ', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
            VALUES ('Parcel number in product name', 'MODULE_SHIPPING_AUPOST_PARCEL_NAME_SUFFIX', 'Yes', 'Set to Yes to append the parcel number to each product name on split Australia Post orders, e.g. <em>Widget [Parcel 2/3]</em>. Helps packing and picking slips identify which parcel an item belongs to.', '6', '66', 'zen_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
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
            'MODULE_SHIPPING_AUPOST_SPLIT_OVERLIMIT',
            'MODULE_SHIPPING_AUPOST_PARCEL_NAME_SUFFIX',
        );
    }
    // ----- end admin section --------------------------------------------- //
    // end class
}
