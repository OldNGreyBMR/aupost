<?php
declare(strict_types=1);
/*
 $Id:   aupost.php,v2.5.9 Dec 2025
        v2.5.8 2025-07-01 AustraliaPost Price and parcel changes for July 2025
        v2.5.8a 2025-07-05 Improved error msgs; output errors to log file; display dims as int as AP only shows as int now; improve handling of  MODULE_SHIPPING_AUPOST_COST_ON_ERROR
        v2.5.8b 2025-07-17 check for Constants on initial install
        v2.5.8c postcode validationlogic moved to its own method validate_au_postcode
                comment out all unused variables
        v2.5.8c 2025-07-29 correct configuration_group_id and change from 7 to 6 error introduced v256 
        v2.5.8d 2025-08-23 check for from postcode and API key; write to error log and return without processing to avoid crash
        v2.5.8d 2025-08-26 remove check for empty letter quote 
        v2.5.8e 2025-10-18 check for returned quote is error msg not in xml format eg insured value exceeds limit and requires signature 
        v2.5.8f 2025-10-20 use PHP __LINE__ for line numbers; global $maxcoverexceeded ;
        v2.5.9  2025-12-10 PHP 8.5 compatibility
*/
// BMHDEBUG switches // WARNING DO NOT ENABLE FOR PRODUCTION
define('BMHDEBUG1','No'); // No or Yes // BMH 2nd level debug
define('BMH_P_DEBUG2','No'); // No or Yes // BMH 3nd level debug to display all returned XML data from Aus Post
define('BMH_P_DEBUG3','No'); // No or Yes // BMH 4th level debug to display raw curl  returned XML data from Aus Post
//
define('BMH_L_DEBUG1', 'No'); // No or Yes // BMH 3nd level debug
define('BMH_L_DEBUG2', 'No'); // No or Yes // BMH 3nd level debug to display all returned XML data from Aus Post
define('USE_CACHE', 'No');     // BMH disable cache // set to 'No' for testing;
define('BMH_MIN_ORDER_VALUE_DEBUG', 'No');  // BMH set to yes to force extra cover on orders less than MINVALUEEXTRACOVER. For PRODUCTION SET TO "No' ln547
// **********************

//BMH declare constants
if (!defined('VERSION_AU')) { define('VERSION_AU', '2.5.9');}
if (!defined('MODULE_SHIPPING_AUPOST_TAX_CLASS')) { define('MODULE_SHIPPING_AUPOST_TAX_CLASS',''); }
if (!defined('MODULE_SHIPPING_AUPOST_TYPES1')) { define('MODULE_SHIPPING_AUPOST_TYPES1',''); }
if (!defined('MODULE_SHIPPING_AUPOST_TYPE_LETTERS')) { define('MODULE_SHIPPING_AUPOST_TYPE_LETTERS',''); }

if (!defined('MODULE_SHIPPING_AUPOST_HIDE_PARCEL')) { define('MODULE_SHIPPING_AUPOST_HIDE_PARCEL',''); }
if (!defined('MODULE_SHIPPING_AUPOST_CORE_WEIGHT')) { define('MODULE_SHIPPING_AUPOST_CORE_WEIGHT',''); }

if (!defined('MODULE_SHIPPING_AUPOST_STATUS')) { define('MODULE_SHIPPING_AUPOST_STATUS',''); }
if (!defined('MODULE_SHIPPING_AUPOST_SORT_ORDER')) { define('MODULE_SHIPPING_AUPOST_SORT_ORDER',''); }
if (!defined('MODULE_SHIPPING_AUPOST_ICONS')) { define('MODULE_SHIPPING_AUPOST_ICONS',''); }
if (!defined('MODULE_SHIPPING_AUPOST_TAX_BASIS')) {define('MODULE_SHIPPING_AUPOST_TAX_BASIS', 'Shipping');}

// +++++++++++++++++++++++++++++
define('AUPOST_MODE','PROD'); //Test OR PROD    // Test uses test URL and Test Authkey;
                                                    // PROD uses the key input via the admin shipping modules panel for "Australia Post"
// **********************

// ++++++++++++++++++++++++++
if (!defined('MODULE_SHIPPING_AUPOST_AUTHKEY')) { define('MODULE_SHIPPING_AUPOST_AUTHKEY','') ;}
if (!defined('AUPOST_TESTMODE_AUTHKEY')) { define('AUPOST_TESTMODE_AUTHKEY','28744ed5982391881611cca6cf5c240') ;} // DO NOT CHANGE
if (!defined('AUPOST_URL_TEST')) { define('AUPOST_URL_TEST','test.npe.auspost.com.au'); }       // No longer used - leave as prod url
if (!defined('AUPOST_URL_PROD')) { define('AUPOST_URL_PROD','digitalapi.auspost.com.au'); }
if (!defined('LETTER_URL_STRING')) { define('LETTER_URL_STRING','/postage/letter/domestic/service.xml?'); } //
if (!defined('LETTER_URL_STRING_CALC')) { define('LETTER_URL_STRING_CALC','/postage/letter/domestic/calculate.xml?'); } //
if (!defined('PARCEL_URL_STRING')) { define('PARCEL_URL_STRING','/postage/parcel/domestic/service.xml?from_postcode='); } //
if (!defined('PARCEL_URL_STRING_CALC')) { define('PARCEL_URL_STRING_CALC','/postage/parcel/domestic/calculate.xml?from_postcode='); }//

// $aupost_url_string = AUPOST_URL_PROD ; // UNUSED
// $lettersize = 0;    // UNUSED

// class constructor

class aupost extends base
{
    private $_logDir = DIR_FS_SQL_CACHE; //
    public $errorString;      //
    public $log_file_name = "AuPost.log"; //
    public $add;                // add on charges
    public $allowed_methods;    //
    public $allowed_methods_l;  //
    // public $FlatText;           // UNUSED
    public $aus_rate;           //
    public $_check;             //
    public $code;               // Declare shipping module alias code
    public $description;        // Shipping module display description
    public $dest_country;       // destination country
    public $dim_query;          //
    public $dims;               //
    public $enabled;            // Shipping module status
    public $error_msg_ap;       // 
    public $frompcode;          // source post code
    public $icon;               // Shipping module icon filename/path
    public $itemcube;          // cubic volume of item
    public $logo;               // au post logo
    // public $myarray = [];       // UNUSED
    // public $myorder;            // UNUSED
    public $maxcover;           //
    public $maxcoverexceeded;   //
    public $ordervalue;         // value of order
    public $ordervalue_ori;     // original value of order before any adjustments
    // public $parcel_cube;        // UNUSED cubic volume of parcel
    public $producttitle;       //
    public $quotes =[];         //
    public $shipping_num_boxes; //
    public $sort_order;         // sort order for quotes options
    public $tax_basis;          //
    public $tax_class;          //
    // public $testmethod;         // UNUSED
    public $title;              //
    public $topcode;            //
    public $usemod;             //
    public $usetitle;           //
    public $xml = [];           // xml array

    public function __construct()
    {
        global $order, $db, $template , $tax_basis,$messageStack;
        global $frompcode;

        $this->code = 'aupost';
        $this->title = MODULE_SHIPPING_AUPOST_TEXT_TITLE ;
        $this->description = MODULE_SHIPPING_AUPOST_TEXT_DESCRIPTION . ' V'. VERSION_AU;;
        $this->sort_order = MODULE_SHIPPING_AUPOST_SORT_ORDER;
        $this->icon = '';
        $this->logo = '';
        $this->tax_basis = MODULE_SHIPPING_AUPOST_TAX_BASIS;
        $this->tax_class = MODULE_SHIPPING_AUPOST_TAX_CLASS;
        $this->error_msg_ap = '';
        //$this->frompcode =   defined('MODULE_SHIPPING_AUPOST_SPCODE');
        
        if (IS_ADMIN_FLAG === true) {
            if ( MODULE_SHIPPING_AUPOST_STATUS == 'True' && (MODULE_SHIPPING_AUPOST_AUTHKEY == 'Add API Auth key from Australia Post' || strlen(MODULE_SHIPPING_AUPOST_AUTHKEY) < 31) ) {
                $this->title .=  '<span class="alert"> (Not Configured) check API key</span>';

            }elseif (MODULE_SHIPPING_AUPOST_STATUS == 'True' && MODULE_SHIPPING_AUPOST_AUTHKEY == '28744ed5982391881611cca6cf5c240') {
                    $this->title = MODULE_SHIPPING_AUPOST_TEXT_TITLE;
                    $this->title .=  '<span class="alert"> (Non-production Test API key)</span>';

            } else {
                $aupost_url_apiKey = MODULE_SHIPPING_AUPOST_AUTHKEY;
                $this->title = MODULE_SHIPPING_AUPOST_TEXT_TITLE;
            }
            $check_coe = FALSE;
            if (defined('MODULE_SHIPPING_AUPOST_COST_ON_ERROR')) {
                if ( trim(MODULE_SHIPPING_AUPOST_COST_ON_ERROR) == "TBA") { 
                    $check_coe = TRUE; 
                }
                if ( is_numeric(trim(MODULE_SHIPPING_AUPOST_COST_ON_ERROR)) ) { 
                    $check_coe = TRUE; 
                }
                if ($check_coe == FALSE) {
                    $this->title .=  '<span class="alert"> (Cost on Error has invalid value</span>';
                }
            }

            $lh1=defined('MODULE_SHIPPING_AUPOST_LETTER_HANDLING');
            $lh2= defined('MODULE_SHIPPING_AUPOST_LETTER_PRIORITY_HANDLING');
            $lh3= ('MODULE_SHIPPING_AUPOST_LETTER_EXPRESS_HANDLING');
            if (($lh1<0) || ($lh2<0)  || ($lh3<0)){
                echo '<br/> ln125 check handling fees';
            }
        } // end Admin section

        $shipping_num_boxes = 1;

        $this->tax_class = defined('MODULE_SHIPPING_AUPOST_TAX_CLASS') && MODULE_SHIPPING_AUPOST_TAX_CLASS;   // $this->tax_basis = 'Shipping' ;    // It'll always work this way, regardless of any global settings
        $this->tax_basis = defined('MODULE_SHIPPING_AUPOST_TAX_BASIS') && MODULE_SHIPPING_AUPOST_TAX_BASIS;   // disable only when entire cart is free shipping

        if (zen_get_shipping_enabled($this->code))
            $this->enabled = (defined('MODULE_SHIPPING_AUPOST_STATUS') && (MODULE_SHIPPING_AUPOST_STATUS == 'True') ? true : false); //BMH

        if (MODULE_SHIPPING_AUPOST_ICONS != "No" ) {
            $this->logo = $template->get_template_dir('aupost_logo.jpg', '','' ,DIR_WS_TEMPLATE . 'images/icons'). '/aupost_logo.jpg';
            $this->icon = $this->logo;                                                                                      // set the quote icon to the logo 
            if (zen_not_null($this->icon)) $this->quotes['icon'] = zen_image($this->icon, $this->title); 
        }
        // get letter and parcel methods defined
        $this->allowed_methods_l = explode(", ", MODULE_SHIPPING_AUPOST_TYPE_LETTERS); 
        $this->allowed_methods = explode(", ", MODULE_SHIPPING_AUPOST_TYPES1) ;
        $this->allowed_methods = $this->allowed_methods + $this->allowed_methods_l;                                         //  combine letters + parcels into one methods list
    }
    // class methods
        // functions

    public function quote($method = '')
    {
        global $db, $order, $currencies,  $parcelweight, $packageitems;
        global $customer_id, $frompcode;
        global$maxcoverexceeded;
        //    $module = substr($_SESSION['shipping'], 0,6);
        //    $method = substr($_SESSION['shipping'],7);
        // removed misguided attempt to retrieve user selection from session.
        // method argument is supplied to this module by Zen Cart if required (single quote).
        // see later comments on removing underscores from AusPost-defined shipping methods.

        if (zen_not_null($method) && (isset($_SESSION['aupostQuotes']))) {
            $testmethod = $_SESSION['aupostQuotes']['methods'] ;
            foreach($testmethod as $temp) {
                $search = array_search("$method", $temp) ;
                if ($search > 0 && $search >= 0) break ;
            }

            $usemod = $this->title ;
            $usetitle = $temp['title'] ;
            if (MODULE_SHIPPING_AUPOST_ICONS != "No" ) {                                                    // strip the icons //
                if (preg_match('/(title)=("[^"]*")/',$this->title, $module))  $usemod = trim($module[2], "\"") ;
                if (preg_match('/(title)=("[^"]*")/',$temp['title'], $title)) $usetitle = trim($title[2], "\"") ;
            }

            //  Initialise our quote array(s)  // quotes['id'] required in includes/classes/shipping.php
            // reset quotes['id'] as it is mandatory for shipping.php but not used anywhere else
            $methods = [] ;
            $this->quotes = [
                'id' => $this->code,
                'module' => $usemod,
                'methods' => [
                [
                'id' => $method,
                'title' => $usetitle,
                'cost' =>  $temp['cost']
                ]
              ]
            ];

            if ($this->tax_class >  0) {
                $this->quotes['tax'] = zen_get_tax_rate((int)$this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
            }
           if (zen_not_null($this->icon)) $this->quotes['icon'] = zen_image($this->icon, $this->title);
                return $this->quotes;                                                                   // return a single quote
        }  ///  Single Quote Exit Point ////

       /// LETTERS - values  ///

        if (MODULE_SHIPPING_AUPOST_TYPE_LETTERS  <> null) {

            $MAXLETTERFOLDSIZE = 15;                        // mm for edge of envelope
            $MAXLETTERPACKINGDIM = 4;                       // mm thickness of packing. Letter max height is 20mm including packing
            //$MAXWEIGHT_L = 500 ;                            // 500g
            $MAXLENGTH_L = (360 - $MAXLETTERFOLDSIZE);      // 360mm max letter length  less fold size on edges
            $MAXWIDTH_L =  (260 - $MAXLETTERFOLDSIZE);      // 260mm max letter width  less fold size on edges
            $MAXHEIGHT_L = (20 - $MAXLETTERPACKINGDIM);     // 20mm max letter height LESS packing thickness
            $MAXHEIGHT_L_SM = 5;                            // 5mm max small letter height
            $MAXLENGTH_L_SM = (240 - $MAXLETTERFOLDSIZE);   // 240mm
            $MAXWIDTH_L_SM = (130 - $MAXLETTERFOLDSIZE);    // 130mm
            $MAXWEIGHT_L_WT1 = 125;                         // weight 125
            $MAXWEIGHT_L_WT2 = 250;                         //
            //$MAXWEIGHT_L_WT3 = 500;                         //
            $MSGLETTERTRACKING = MSGLETTERTRACKING;         // label append formatted in language file
            //$MAXWIDTH_L_SM_EXP = 110;                       // DL envelope prepaid Express envelopes
            //$MAXLENGTH_L_SM_EXP = 220;                      // DL envelope prepaid Express envelopes
            //$MAXWIDTH_L_MED_EXP = 162;                      // C5 envelope prepaid Express envelopes
            //$MAXLENGTH_L_MED_EXP = 229;                     // C5 envelope prepaid Express envelopes
            //$MAXWIDTH_L_LRG_EXP = 250;                      // B4 envelope prepaid Express envelopes
            //$MAXLENGTH_L_LRG_EXP = 353;                     // B4 envelope prepaid Express envelopes
            $MINVALUEEXTRACOVER = 101;                      // Aust Post amount for min insurance charge
            $MINLETTERWEIGHT = 15;                          // minimum weight of letter container

            // initialise variables
            $letterwidth = 0.0 ;
            $letterwidthcheck = 0 ;
            //$letterwidthchecksmall = 0 ;
            $letterlength = 0.0 ;
            $letterlengthcheck = 0 ;
            //$letterlengthchecksmall = 0 ;
            $letterheight = 0.0 ;
            $letterheightcheck = 0 ;
            //$letterheightchecksmall = 0 ;
            $letterweight = 0 ;
            //$lettercube = 0 ;
            $letterchecksmall = 0 ;
            $lettercheck = 0 ;
            //$lettersmall = 0;
            $letterlargewt1 = 0;
            $letterlargewt2 = 0;
            $letterlargewt3 = 0;
            //$letterexp_small = 0;
            //$letterexp_med = 0;
            //$letterexp_lrg = 0;
            $letterprefix = 'LETTER ';               // prefix label to differentiate from parcel - include space after

        }
        // EOF LETTERS - values

        // PARCELS - values
        // Maximums - parcels
        $MAXWEIGHT_P = 22 ;     // BMH change from 20 to 22kg 2021-10-07
        $MAXLENGTH_P = 105 ;    // 105cm max parcel length
        //$MAXGIRTH_P =  140 ;    // 140cm max parcel girth  ( (width + height) * 2) // 2023 girth not used for local parcel
        $MAXCUBIC_P = 0.25 ;    // 0.25 cubic meters max dimensions (width * height * length)

        // default dimensions   // parcels
        $x = explode(',', MODULE_SHIPPING_AUPOST_DIMS) ;
        $defaultdims = array($x[0],$x[1],$x[2]) ;
        sort($defaultdims) ;    // length[2]. width[1], height=[0]

        // initialise  variables // parcels
        $parcelwidth = 0 ;
        $parcellength = 0 ;
        $parcelheight = 0 ;
        $parcelweight = 0 ;
        //$cube = 0 ;
        $details = ' ';
        $itemcube = 0;
        //$parcel_cube = 0;  // NOT USED YET

        $frompcode = (MODULE_SHIPPING_AUPOST_SPCODE);
        if (!isset($frompcode) || $frompcode == '') {
            $frompcode = '4121'; // default to Tarragindi
            $this->_log(msg: 'From postcode not set in module settings. Defaulting to 4121 Tarragindi'); // BMH write to log file
        }
        $dest_country=($order->delivery['country']['iso_code_2'] ?? '');    //


        // BMH Only proceed for AU addresses
        if ($dest_country != "AU") {
            return;
        }
        $topcode = str_replace(" ", "", ($order->delivery['postcode'] ?? ''));

        if (!$this->validate_au_postcode($topcode, $dest_country, $order)) {
            return;
        }
    // ...existing code...

        $aus_rate = (float)$currencies->get_value('AUD') ;      // get $AU exchange rate
        // EOF PARCELS - values

        if ($aus_rate == 0) {                                         // included to avoid possible divide  by zero error
            $aus_rate = (float)$currencies->get_value('AUS') ;  // if AUD zero/undefined then try AUS // BMH quotes added
            if ($aus_rate == 0) {
                $aus_rate = 1;                                        // if still zero initialise to 1.00 to avoid divide by zero error
            }
        }                                                             

        $ordervalue=$order->info['total'] / $aus_rate ;               // total cost for insurance
        $ordervalue = round($ordervalue,4) ;          // round to 2 decimal places
        $ordervalue_ori = $ordervalue ;                               // original order value before any adjustments

        $tare = MODULE_SHIPPING_AUPOST_TARE ;                         // percentage to add for packing etc

        if (($topcode == "") && ($dest_country == "AU")) {
            return;
        }           //  This will occur with guest user first quote where no postcode is available

       // BMH not developed $FlatText = " Using AusPost Flat Rate." ; // BMH Not in use

        // loop through cart extracting productIDs and qtys //
        $myorder = $_SESSION['cart']->get_products();

        for($x = 0 ; $x < count($myorder) ; $x++ )  {
            $producttitle = $myorder[$x]['id'] ;
            $q = $myorder[$x]['quantity'];
            $w = $myorder[$x]['weight'];

            $dim_query = "select products_length, products_height, products_width from " . TABLE_PRODUCTS . " where products_id='$producttitle' limit 1 ";
            $dims = $db->Execute($dim_query);

            // re-orientate //  //  longest becomes length
            $var = array($dims->fields['products_width'], $dims->fields['products_height'], $dims->fields['products_length']) ; sort($var) ;
            $dims->fields['products_length'] = $var[2] ; $dims->fields['products_width'] = $var[1] ;  $dims->fields['products_height'] = $var[0] ;

            // if no dimensions provided use the defaults
            if($dims->fields['products_height'] == 0) {
                $dims->fields['products_height'] = $defaultdims[0] ; 
            }
            if($dims->fields['products_width']  == 0) {
                $dims->fields['products_width']  = $defaultdims[1] ;
            }
            if($dims->fields['products_length'] == 0) {
                $dims->fields['products_length'] = $defaultdims[2] ; 
            }
            if($w == 0) {
                $w = 1 ; 
            }  // 1 gram minimum

            $parcelweight += $w * $q;

            // get the cube of these items
            $itemcube =  ($dims->fields['products_width'] * $dims->fields['products_height'] * $dims->fields['products_length'] * $q *0.000001 ) ; // item dims must be in cm
            // Increase widths and length of parcel as needed
            if ($dims->fields['products_width'] >  $parcelwidth)  { 
                $parcelwidth  = $dims->fields['products_width']  ; 
            }
            if ($dims->fields['products_length'] > $parcellength) { 
                $parcellength = $dims->fields['products_length'] ; 
            }
            // Stack on top on existing items
            $parcelheight =  ($dims->fields['products_height'] * ($q)) + $parcelheight  ;
            $packageitems =  $packageitems + $q ;

            // Useful debugging information // in formatted table display
           if (MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) {
                $dim_query = "select products_name from " . TABLE_PRODUCTS_DESCRIPTION . " where products_id='$producttitle' limit 1 ";
                $name = $db->Execute($dim_query);
                $parcellength = (int)$parcellength; $parcelwidth = (int)$parcelwidth; $parcelheight = (int)$parcelheight;
                echo "<center><table class=\"aupost-debug-table\" border=1><th colspan=8> Debugging information [aupost Flag set in Admin console | shipping | aupost] version:" . VERSION_AU . " </hr>
                <tr><th>Item " . ($x + 1) . "</th><td colspan=7>" . $name->fields['products_name'] . "</td>
                <tr><th width=15%>Attribute</th><th colspan=3>Item</th><th colspan=4>Parcel</th></tr>
                <tr><th>Qty</th><td>&nbsp; " . $q . "<th>Weight</th><td>&nbsp; " . $w . "</td>
                <th>Qty</th><td>&nbsp;$packageitems</td><th>Weight</th><td>&nbsp;" ; echo $parcelweight + (($parcelweight* $tare)/100) ; echo " " .  MODULE_SHIPPING_AUPOST_WEIGHT_FORMAT . "</td></tr>
                <tr><th>Dims L W H </th><td colspan=3>&nbsp; " . (int)$dims->fields['products_length'] . " x " . (int)$dims->fields['products_width'] . " x "  . (int)$dims->fields['products_height'] . "</td>
                <td colspan=4>&nbsp;$parcellength  x  $parcelwidth  x $parcelheight </td></tr>
                <tr><th>Cube</th><td colspan=3>&nbsp; itemcube=" . number_format($itemcube,3 ) . " cubic vol" . "</td><td colspan=4>&nbsp;" . number_format($itemcube,3 )  .  " cubic vol" . " </td></tr>
                <tr><th>CubicWeight</th><td colspan=3>&nbsp;" . ($itemcube *  250) . "Kgs  </td><td colspan=4>&nbsp;"
                    . ($itemcube  * 250) . "Kgs </td></tr>
                </table></center> " ;
                // NOTE: The chargeable weight is the greater of the physical or the cubic weight of your parcel
            }   // eof debug display table
        }

        // /////////////////////// LETTERS //////////////////////////////////
        // BMH for letter dimensions
        // letter height for starters
        $letterheight = $parcelheight *10;                      // letters are in mm
        $letterheight = $letterheight + $MAXLETTERPACKINGDIM;   // add packaging thickness to letter height
        $letterlength = $parcellength *10;                      // letters are in mm
        $letterwidth = $parcelwidth *10;

        // Reorientate the dimensions so largest  becomes length
        $var_l = array($letterheight, $letterlength, $letterwidth) ; sort($var_l) ;
        $letterheight = $var_l[0] ; $letterwidth = $var_l[1] ; $letterlength = $var_l[2] ;
        // reorientate

        if (($letterheight ) <= $MAXHEIGHT_L ){
            $letterheightcheck = 1;                             // maybe can be sent as letter by height limit
            $lettercheck = 1;
            // check letter height small
            if (($letterheight) <= $MAXHEIGHT_L_SM ) {
                $letterheightchecksmall = 1;
                $letterchecksmall = 1;                          
            }

            // letter length in range for small
            $letterlength = ($parcellength *10);
            if ($letterlength < $MAXLENGTH_L_SM ) {
                $letterlengthchecksmall = 1;
                $letterchecksmall = $letterchecksmall + 1;
            }

            // check letter length in range
            if (($letterlength  > $MAXLENGTH_L_SM ) || ($letterlength <= $MAXLENGTH_L ) ) {
                $letterlengthcheck = 1;
                $lettercheck = $lettercheck + 1;
            }
            // letter width in range
            $letterwidth = $parcelwidth *10;
            if ($letterwidth < $MAXWIDTH_L_SM ) {
                $letterwidthchecksmall = 1;
                $letterchecksmall = $letterchecksmall + 1;
            }

            if (($letterwidth > $MAXWIDTH_L_SM ) || (($parcelwidth *10) <= $MAXWIDTH_L) ) {
                $letterwidthcheck = 1;
                $lettercheck = $lettercheck + 1;
            }

            // check letter weight // in grams
            $letterweight = ($parcelweight + ($parcelweight* $tare/100));
            $letterweight = $letterweight + $MINLETTERWEIGHT;                   //add weight of envelope
            $letterweight = ceil($letterweight);                           // round up to integer
            if ((($letterweight ) <= $MAXWEIGHT_L_WT1 ) && ($letterchecksmall == 3) ){
                $lettersmall = 1;
            }
            if ((($letterweight ) <= $MAXWEIGHT_L_WT1 ) && ($lettercheck == 3) ) {
                $letterlargewt1 = 1;
            }
            if  (($letterweight  >= $MAXWEIGHT_L_WT1 ) && ($letterweight <= $MAXWEIGHT_L_WT2 ) && ($lettercheck == 3)  ) {
                $letterlargewt2 = 1;
            }

            // BMH DEBUG2 display the letter values ';
            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) &&(BMH_L_DEBUG2 == "Yes") )  {
                $this->_debug_output("n","<br>dl2 aupost ln" . __LINE__ . " \$lettercheck=" . $lettercheck . ' $letterchecksmall=' . $letterchecksmall . ' $letterlengthcheck = ' . $letterlengthcheck . ' $letterwidthcheck = ' . $letterwidthcheck . ' $letterheightcheck=' . $letterheightcheck, "");
                if ($letterchecksmall == 3) {
                    echo " <br> ln" . __LINE__ . "  it is a  small letter";
                if ($lettercheck == 3) {
                    echo " <br> ln" . __LINE__ . " it is a  large letter";
                }
                if ($letterlargewt1 == 1){
                    echo " <br> ln" . __LINE__ . " it is a  large letter(125g)";
                }
                if ($letterlargewt2 == 1){
                    echo " <br> ln" . __LINE__ . " it is a  large letter(250g)";
                }
                if ($letterlargewt3 == 1){
                    echo " <br> ln" . __LINE__ . " it is a  large letter(500g)";
                }
            }
                echo " </p>";
            } // BMH DEBUG2 eof display the letter values ';

            $aupost_url_string = AUPOST_URL_PROD;

            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMH_L_DEBUG1 == "Yes") )  {
                $this->_debug_output("n","<br>dl1 <strong> aupost ln" . __LINE__ . " URL = </strong> <br/>" . "https://" . $aupost_url_string . LETTER_URL_STRING .
                    "length=$letterlength&width=$letterwidth&thickness=$letterheight&weight=$letterweight" . " </p>","");
            } // eof debug URL

            // +++++++++++++++++ get the letter quote +++++++++++++++++++
            // letter quote request is different format to parcel quote request
            $quL = $this->get_auspost_api(
                'https://' . $aupost_url_string . LETTER_URL_STRING . "length=$letterlength&width=$letterwidth&thickness=$letterheight&weight=$letterweight") ;

            // If we have any results, parse them into an array
            $xmlquote_letter = ($quL == '') ? array() : new SimpleXMLElement($quL)  ;

            //  bof XML formatted output
            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMH_L_DEBUG2 == "Yes") ) {
                $this->_debug_output("x","<strong>>> Server Returned - LETTERS BMH_L_DEBUG1 ln" . __LINE__ . " << <br> </strong><textarea > " ,$xmlquote_letter);
            } //eof debug server return

            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMH_L_DEBUG1 == "Yes") ) {
                $this->_debug_output("x","<b>auPost - Server Returned BMH_L_DEBUG1 ln" . __LINE__ . " LETTERS: output \$quL</b><br>" . $quL ,"");
            } // BMH DEBUG eof XML formatted output

            // ======================================
            //  loop through the LETTER quotes retrieved //
            // create array
            $arrayquotes = array( array("qid" => "","qcost" => 0,"qdescription" => "") );

            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMH_L_DEBUG1 == "Yes") ) {
                $this->_debug_output("d"," aupost ln" . __LINE__ . " \$arrayquotes = <br/> ",$arrayquotes);
            }   // BMH debug eof array quotes

            $i = 0 ;  // counter
            $methods = [];                                              // BMH E
            foreach($xmlquote_letter as $foo => $bar) {
                $code = ($xmlquote_letter->service[$i]->code);          //BMH keep API code for label
                $servicecode = $code;                                   // fully formatted API $code required for later sub quote
                $code = str_replace("_", " ", strval($code));           //$code = substr($code,11); // replace underscores with spaces

                $id = str_replace("_", "", strval($xmlquote_letter->service[$i]->code));
                        // remove underscores from AusPost methods. Zen Cart uses underscore as delimiter between module and method.
                        // underscores must also be removed from case statements below.

                $cost = (float)($xmlquote_letter->service[$i]->price);

                $description =  ($code) ;                                       // BMH append name to code
                $descx = ucwords(strtolower($description));                     // make sentence case
                $description = $letterprefix . $descx . $MSGLETTERTRACKING;     // BMH Prepend LETTER to CODE to differentiate from Parcels code + ADD letter tracking note

                if (( MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMH_L_DEBUG1 == "Yes"))  {
                    $this->_debug_output("n"," ln" . __LINE__ . " LETTER ID= $id DESC= $description COST= $cost ","");
                }  // BMH Debug 2nd level debug each line of quote parsed /// 3rd

                $qqid = $id;
                $arrayquotes[$i]["qid"] = trim($qqid) ;             // BMH ** DEBUG echo '<br>ln 448 $arrayquotes[$i]["qid"]= ' . $arrayquotes[$i]["qid"] ;
                $arrayquotes[$i]["qcost"] = $cost;                  // BMH ** DEBUG echo '<br> ln 449 $arrayquotes[$i]["qcost"]= ' . $arrayquotes[$i]["qcost"];
                $arrayquotes[$i]["qdescription"] = $description;    // BMH ** DEBUG echo '<br> ln 450 $arrayquotes[$i]["qdescription"]= ' . $arrayquotes[$i]["qdescription"];

                $i++;   // increment the counter

                $add = 0 ; $f = 0 ; $info=0 ;

                switch ($id) {

                case  "AUSLETTEREXPRESSSMALL" :
                case  "AUSLETTEREXPRESSMEDIUM" :
                case  "AUSLETTEREXPRESSLARGE" :
                    if ((in_array("Aust Express", $this->allowed_methods_l))) {
                        $add = MODULE_SHIPPING_AUPOST_LETTER_EXPRESS_HANDLING ; $f = 1 ;

                        if
                            (in_array("Aust Express Insured (no sig)" , $this->allowed_methods_l) ||
                            in_array("Aust Express Insured +sig" , $this->allowed_methods_l) ||
                            in_array("Aust Express +sig", $this->allowed_methods_l))   {       // check for any options for express letter

                            $optioncode_ec = 'AUS_SERVICE_OPTION_STANDARD';
                            $suboptioncode = 'AUS_SERVICE_OPTION_EXTRA_COVER';
                            $optioncode_sig = 'AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY';
                            $optioncode = $optioncode_sig;          // BMH DEBUG
                            if ($ordervalue < $MINVALUEEXTRACOVER){
                                $ordervalue = $MINVALUEEXTRACOVER;
                            }
                            //BMH DEBUG mask for testing // setting value forces extra cover on receipt at Post office
                             if (BMH_MIN_ORDER_VALUE_DEBUG == "Yes") {  $ordervalue = $MINVALUEEXTRACOVER + 1;
                             } // BMH ** DEBUG to force extra cover value FOR TESTING ONLY; auto cover to $100

                            // ++++++ get special price for options available with Express letters +++++
                            $quL2 = $this->get_auspost_api(
                            'https://' . $aupost_url_string . LETTER_URL_STRING_CALC . "service_code=$servicecode&weight=$letterweight&option_code=$optioncode&suboption_code=$suboptioncode&extra_cover=$ordervalue") ;
                            $xmlquote_letter2 = ($quL2 == '') ? array() : new SimpleXMLElement($quL2); // XML format

                            $i2 = 0 ;  // counter for new xmlquote

                            // BMH DEBUG bof XML formatted output
                            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMH_L_DEBUG2 == "Yes") ) {
                                $this->_debug_output("x","<strong> >> Server Returned - LETTERS BMHDEBUG1+2 aupost ln566 << </strong><br><textarea rows=30 cols=100 style=\"margin:0;\"> ",$xmlquote_letter2);
                            }   // eof debug

                            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMH_L_DEBUG1 == "Yes")) {
                                $this->_debug_output("n","<b>n2 auPost - Server Returned BMH_L_DEBUG1 aupost ln572 LETTERS: output \$quL2</b><br>" . $quL2 ,"");
                            }
                            // -- BMH DEBUG eof XML formatted output----

                            $id_exc_sig = "AUSLETTEREXPRESS" . "AUSSERVICEOPTIONSTANDARD";
                            $id_exc = "AUSLETTEREXPRESS" . "AUSSERVICEOPTIONEXTRACOVER";
                            $id_sig = "AUSLETTEREXPRESS" . "AUSSERVICEOPTIONSIGNATUREONDELIVERY";

                            $codeitem = ($xmlquote_letter2->costs->cost[0]->item);    // postage type description
                            $desc2 = $codeitem;
                            $desc_sig = $xmlquote_letter2->costs->cost[1]->item ;     // find the name for sig
                            $desc_excover = $xmlquote_letter2->costs->cost[2]->item ; // find the name for extra cover
                            $desc_excover_sig = $desc_sig . " + " . $xmlquote_letter2->costs->cost[2]->item ; // find the name for sig plus extra cover

                            $cost_excover= ((float)($xmlquote_letter2->costs->cost[0]->cost) + ($xmlquote_letter2->costs->cost[2]->cost)); // add basic postage cost + extra cover cost

                            $cost_sig = (float)($xmlquote_letter2->costs->cost[0]->cost) + ($xmlquote_letter2->costs->cost[1]->cost);       // basic cost + signature
                            $cost_excover_sig = (float)($xmlquote_letter2->total_cost); // total cost for all options

                            $cost_excover_sig = $cost_excover_sig/11 *10;       // remove tax
                            $cost_excover =  $cost_excover /11*10;              // remove tax
                            $cost_sig= $cost_sig /11*10;                        // remove tax

                            // got all of the values // -----------
                            $desc_excover = trim(strval($desc2)) . ' + ' . $desc_excover;
                            $desc_sig = trim(strval($desc2)) . ' + ' . $desc_sig;
                            $desc_excover_sig = trim(strval($desc2)) . ' + ' . $desc_excover_sig;

                            // ---------------
                            $arraytoappend_excover = array("qid"=>$id_exc, "qcost"=>$cost_excover, "qdescription"=>$desc_excover );
                            $arraytoappend_sig = array("qid"=>$id_sig, "qcost"=>$cost_sig, "qdescription"=>$desc_sig );
                            $arraytoappend_ex_sig = array("qid"=>$id_exc_sig, "qcost"=>$cost_excover_sig, "qdescription"=> $desc_excover_sig );

                            // append allowed express option types to main array
                            $arrayquotes[] = $arraytoappend_excover;
                            $arrayquotes[] = $arraytoappend_sig;
                            $arrayquotes[] = $arraytoappend_ex_sig;

                            // // ++++++
                            $details= $this->_handling($details,$currencies,$add,$aus_rate,$info);  // check if handling rates included
                            // //  ++++++++

                            // update returned methods for each option
                            if (in_array("Aust Express Insured +sig", $this->allowed_methods_l)) {
                                if (strlen($id) >1) {
                                    $methods[] = array("id"=>$id_exc_sig,  "title"=>$letterprefix . ' '. $desc_excover_sig . ' ' . $details, "cost"=>$cost_excover_sig) ;
                                 }
                            }

                            if (in_array("Aust Express Insured (no sig)", $this->allowed_methods_l)) {
                                if (strlen($id) >1) {
                                    $methods[] = array('id' => $id_exc,  "title" => $letterprefix . ' '. $desc_excover . ' ' .$details, 'cost' => $cost_excover);
                                 }
                            }

                            if (in_array("Aust Express +sig", $this->allowed_methods_l)) {
                                if (strlen($id) >1) {
                                    $methods[] = array('id' => $id_sig,  "title" => $letterprefix . ' '. $desc_sig . ' ' .$details, 'cost' => $cost_sig);
                                 }
                            }
                            $description = $letterprefix . $descx; // set desc for express without the no tracking msg

                        }   // eof // Express plus options

                    }
                break;  //eof express

                case  "AUSLETTERPRIORITYSMALL" :    // normal own packaging + label
                case  "AUSLETTERPRIORITYLARGE125" : // normal own packaging + label
                case  "AUSLETTERPRIORITYLARGE250" : // normal own packaging + label
                case  "AUSLETTERPRIORITYLARGE500" : // normal own packaging + label
                    if ((in_array("Aust Priority", $this->allowed_methods_l)))
                    {
                        $add =  MODULE_SHIPPING_AUPOST_LETTER_PRIORITY_HANDLING ; $f = 1 ;
                    }
                    break;

                case  "AUSLETTERREGULARSMALL":      // normal mail - own packaging
                case  "AUSLETTERREGULARLARGE125":   // normal mail - own packaging
                case  "AUSLETTERREGULARLARGE250":   // normal mail - own packaging
                case  "AUSLETTERREGULARLARGE500":   // normal mail - own packaging
                    if (in_array("Aust Standard", $this->allowed_methods_l))
                    {
                        $add = MODULE_SHIPPING_AUPOST_LETTER_HANDLING ; $f = 1 ;
                    }
                    break;

                case  "AUSLETTERSIZEDL":  // This requires purchase of Aus Post packaging   // BMH Not processed
                case  "AUSLETTERSIZEC6":  // This requires purchase of Aus Post packaging   // BMH Not processed
                case  "AUSLETTERSIZEC5":  // This requires purchase of Aus Post packaging   // BMH Not processed
                case  "AUSLETTERSIZEC4":  // This requires purchase of Aus Post packaging   // BMH Not processed
                case  "AUSLETTERSIZEB4":  // This requires purchase of Aus Post packaging   // BMH Not processed
                case  "AUSLETTERSIZEOTH": // This requires purchase of Aus Post packaging   // BMH Not processed
                //case  "AUSLETTEREXPRESSDL"  // Same as AUSLETTEREXPRESSSMALL      // not returned by AusPost 2023-09
                //case  "AUSLETTEREXPRESSC5"  // Same as AUSLETTEREXPRESSMEDIUM     // not returned by AusPost 2023-09
                //case  "AUSLETTEREXPRESSB4"  // Same as AUSLETTEREXPRESSLARGE      // not returned by AusPost 2023-09
                $cost = 0;$f=0;
                    // echo "shouldn't be here"; //BMH debug
                    //do nothing - ignore the code
                break;

                }  // end switch

                // bof only list valid options without debug info // BMH
                 if ((($cost > 0) && ($f == 1)) ) { //
                    $cost = $cost + floatval($add) ;     // add handling fee   // string to float

                    // GST (tax) included in all prices in Aust
                    if (($dest_country == "AU") && (($this->tax_class) > 0)) {
                        $t = $cost - ($cost / (zen_get_tax_rate((int)$this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])+1)) ; // BMH add 1
                        if ($t > 0) $cost = $t ;
                    }

                    $details= $this->_handling($details,$currencies,$add,$aus_rate,$info);  // check if handling rates included
                     // //  ++++++++

                    // UPDATE THE RECORD FOR DISPLAY
                    $cost = $cost / $aus_rate;
                    // METHODS ADD to returned quote for letter
                     if (strlen($id) >1) {
                        $methods[] = array('id' => "$id",  'title' => $description .  $details, 'cost' => ($cost ));
                     }
                }  // end display output //////// only list valid options without debug info // BMH

            }  // eof foreach loop
/* before displaying error msg for no letter quotes check that letters have been enabled */ //BMH TODO: 
                 //  check to ensure we have at least one valid LETTER quote - produce error message if not.
                // if  (sizeof($methods) == 0) { BMH DEBUG
             //    if( (is_array($methods)) && (count($methods) == 0) ) { // use count and not sizeof
             //        $error_msg_ap = ERROR_NO_VALID_LETTER_QUOTE_MSG;  //BMH DEBUG 
             //        $cost = $this->_get_error_cost($dest_country,$error_msg_ap) ; // retrieve default rate
             //
             //       // BMH if ($cost == 0)  return  ;
             //       if ($this->enabled == FALSE ) return;
             //
             //       $methods[] = array( 'id' => "Error",  'title' =>MODULE_SHIPPING_AUPOST_TEXT_ERROR ,'cost' => $cost ) ;
             //    }
        }
        //// EOF LETTERS /////////

        //////////// // PACKAGE ADJUSTMENT FOR OPTIMAL PACKING // ////////////
        // package created, now re-orientate and check dimensions
        $parcelheight = ceil($parcelheight);  // round up to next integer // cm for accuracy in pricing
        $var = array($parcelheight, $parcellength, $parcelwidth) ; sort($var) ;
        $parcelheight = $var[0] ; $parcelwidth = $var[1] ; $parcellength = $var[2] ;
        $girth = ($parcelheight * 2) + ($parcelwidth * 2)  ;

        $parcelweight = $parcelweight + (($parcelweight*$tare)/100) ;

        if (MODULE_SHIPPING_AUPOST_WEIGHT_FORMAT == "gms") {
            $parcelweight = $parcelweight/1000 ; 
        }

        //  save dimensions for display purposes on quote form
        $_SESSION['swidth'] = $parcelwidth ; $_SESSION['sheight'] = $parcelheight ;
        $_SESSION['slength'] = $parcellength ;
        $_SESSION['boxes'] = $this->shipping_num_boxes ; 

        // Check for maximum length allowed
        if($parcellength >= $MAXLENGTH_P) {
            $this->error_msg_ap = ERROR_MAX_LENGTH_MSG;
            $cost = $this->_get_error_cost($dest_country,$this->error_msg_ap) ;
                           // BMH if ($cost == 0)  return  ;
               if ($this->enabled == FALSE ) return;    // no quote

            $methods[] = array('id' => $this->code,'title' =>  $this->error_msg_ap, 'cost' => $cost ) ; // update method //BMH issue#19
            $this->quotes['methods'] = $methods;   // set it
            $parcellength = 0;
            return $this->quotes;
        }  // exceeds AustPost maximum length. No point in continuing.

        // Check cubic volume
        if($itemcube > $MAXCUBIC_P ) {
            $this->error_msg_ap = ERROR_MAX_CUBIC_MSG;
            $cost = $this->_get_error_cost($dest_country,$this->error_msg_ap) ;
                         // BMH if ($cost == 0)  return  ;
            if ($this->enabled == FALSE ) return;   // no quote

            $methods[] = array('id' => $this->code,'title' => $this->error_msg_ap, 'cost' => $cost ) ; //BMH issue#19
            $this->quotes['methods'] = $methods;   // set it
            $itemcube=0;
            return $this->quotes;
        }  // exceeds AustPost maximum cubic volume. No point in continuing.

        if($parcelweight > $MAXWEIGHT_P) {
            $this->error_msg_ap = ERROR_MAX_WEIGHT_MSG;
            $cost = $this->_get_error_cost($dest_country,$this->error_msg_ap) ;
                          // BMH if ($cost == 0)  return  ;
               if ($this->enabled == FALSE ) return;   // no quote

            $methods[] = array('id' => $this->code,'title' => $this->error_msg_ap, 'cost' => $cost ) ; //BMH issue#19
            $this->quotes['methods'] = $methods;   // set it
            $parcelweight=0;
            return $this->quotes;
        }  // exceeds AustPost maximum weight. No point in continuing.

        // Check to see if cache is useful
        if (USE_CACHE == "Yes") {   //BMH DEBUG disable cache for testing
            if(isset($_SESSION['aupostParcel']))
            {
                $test = explode(",", $_SESSION['aupostParcel']) ;

                if (
                    ($test[0] == $dest_country) &&
                    ($test[1] == $topcode) &&
                    ($test[2] == $parcelwidth) &&
                    ($test[3] == $parcelheight) &&
                    ($test[4] == $parcellength) &&
                    ($test[5] == $parcelweight) &&
                    ($test[6] == $ordervalue)
                   )
                {
                    if (MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) {
                        $this->_debug_output("n","<center><table border=1 width=95% ><td align=center><font color=\"#FF0000\">Using Cached quotes </font></td></table></center>","");
                        echo "<center><table border=1 width=95% ><td align=center><font color=\"#FF0000\">Using Cached quotes </font></td></table></center>" ;
                    }

                    $this->quotes =  isset($_SESSION['aupostQuotes']) ;  //BMH
                    return $this->quotes ;
                    ///////////////////////////////////  Cache Exit Point //////////////////////////////////
                } // No cache match -  get new quote from server //
            }  // No cache session -  get new quote from server //
        } // end cache option //BMH DEBUG
        ///////////////////////////////////////////////////////////////////////////////////////////////

        // always save new session  CSV //
        $_SESSION['aupostParcel'] = implode(",", array($dest_country, $topcode, $parcelwidth, $parcelheight, $parcellength, $parcelweight, $ordervalue)) ;
        $shipping_weight = $parcelweight ;  // global value for zencart

        $dcode = ($dest_country == "AU") ? $topcode:$dest_country ; // Set destination code ( postcode if AU, else 2 char iso country code )

        if (!$dcode) $dcode =  SHIPPING_ORIGIN_ZIP ; // if no destination postcode - eg first run, set to local postcode

        $flags = ((MODULE_SHIPPING_AUPOST_HIDE_PARCEL == "No") || ( MODULE_SHIPPING_AUPOST_DEBUG == "Yes" )) ? 0:1 ;

        $aupost_url_string = AUPOST_URL_PROD ;  // Server query string //
        // if test mode replace with test variables - url + api key
        if (AUPOST_MODE == 'Test') {
            //$aupost_url_string = AUPOST_URL_TEST ; Aus Post say to use production servers (2022)
            $aupost_url_apiKey = AUPOST_TESTMODE_AUTHKEY;
        }
        if (MODULE_SHIPPING_AUPOST_DEBUG == "Yes") {
            echo "<center> <table class=\"aupost-debug-table\" border=1 >
                <tr >  <th width=15% > Parcel dims sent </th>
                    <td > Length sent=$parcellength; Width sent=$parcelwidth; Height sent=$parcelheight;
                </tr>       </table></center> ";
            echo  "<center> <table class=\"aupost-debug-table\" border=1>
                <tr >   <th width=15%> Handling fees</th>
                    <td colspan=7> Parcel=" . MODULE_SHIPPING_AUPOST_RPP_HANDLING . "; Parcel Exp=" . MODULE_SHIPPING_AUPOST_EXP_HANDLING . "; Prepaid=" . MODULE_SHIPPING_AUPOST_PPS_HANDLING . 
                    "; Prepaid Exp=" . MODULE_SHIPPING_AUPOST_PPSE_HANDLING . ";" .
                "</td>  </tr>   </table></center> ";
            if(BMH_MIN_ORDER_VALUE_DEBUG == "Yes" ) { 
                echo "<center> <table class=\"aupost-debug-table\" border=1>
                    <tr >   <th width=15%> Extra cover </th>
                        <td colspan=7> Forced on. Order value = " . $MINVALUEEXTRACOVER + 1 .
                    "</td>  </tr>   </table></center> ";
            } // eof DEBUG

        }
        if (MODULE_SHIPPING_AUPOST_DEBUG == "Yes" && BMH_P_DEBUG2 == "Yes") {
             $parcellength = (int)$parcellength; $parcelwidth = (int)$parcelwidth; $parcelheight = (int)$parcelheight;
            $this->_debug_output("n","<p class=\"aupost-debug\"> <br>aupost ln" . __LINE__ . " n2 parcels ***<br> " . 'https://' . $aupost_url_string . PARCEL_URL_STRING . $frompcode . "&to_postcode=$dcode&length=$parcellength&width=$parcelwidth&height=$parcelheight&weight=$parcelweight" . "</p> ", "");
        }
        //// ++++++++++++++++++++++++++++++
        // get parcel api
        $qu = $this->get_auspost_api(
          'https://' . $aupost_url_string . PARCEL_URL_STRING . $frompcode . "&to_postcode=$dcode&length=$parcellength&width=$parcelwidth&height=$parcelheight&weight=$parcelweight") ;
        // // +++++++++++++++++++++++++++++

        if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMH_P_DEBUG2 == "Yes")) {
            //$this->_debug_output("n","<table class='aupost-debug'><tr><td><b>n2 auPost - Server Returned BMH_P_DEBUG2 ln842:</b><br>" . $qu . "</td></tr></table> ","");
             $this->_debug_output("n","<b>n2 auPost - Server Returned BMH_P_DEBUG2 ln" . __LINE__ . ":</b><br>" ,$qu);
        }

        // Check for returned quote is really an error message
            if(str_starts_with($qu, "{" )) {
                if ($myerrorarray['status'] = "Failed") {
                    echo '<br> Australia Post connection ' . $myerrorarray['status'] . '. Please report error to site owner';
                    $this->_log("". $myerrorarray .   " Cust:". $customer_id); // BMH
                return $this->quotes;
                }
            }
            if(str_contains(strtolower($qu), "cubic" )) {  // trap for AP API allows >= for cubic measure // future maybe move all error traps here
                $this->error_msg_ap = ERROR_MAX_CUBIC_MSG;
                $cost = $this->_get_error_cost($dest_country,$this->error_msg_ap) ;
                if ($this->enabled == FALSE ) return;              // no quote
                
                $this->_log("" . $this->error_msg_ap  . " Cust:". $customer_id); // BMH write to log file
                $methods[] = array('id' => $this->code, 'title' => $this->error_msg_ap, 'cost' => $cost ) ; //BMH issue#19
                $this->quotes['methods'] = $methods;   // set it
                return $this->quotes;
            }
        // eof check for errors
        $xml = ($qu == '') ? [] : new SimpleXMLElement($qu) ; // If we have any results, parse them into an array

        if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {  //XML output
            $this->_debug_output("x","<p d2 class='aupost-debug' ><strong> >> Server Returned BMHDEBUG1+2 ln" . __LINE__ . " << <br> </strong> <textarea  > ",$xml );
        }

        $maxcover = ($xml->service[0]->max_extra_cover)  ; // BMH cast to int
        
        if ( $ordervalue_ori > $maxcover )  {  // BMH cast to int
            $maxcoverexceeded = True;
        }
            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes"))  {
                $this->_debug_output("n","<br>n2 ln" . __LINE__ . " Max extra cover available = " . $maxcover ,"");
                $this->_debug_output("n","<br>n2 ln" . __LINE__ . " Max cover exceed flag = " . $maxcoverexceeded ,"");
            }
        
         
        /////  Initialise our quotes['id'] required in includes/classes/shipping.php
        $this->quotes = array('id' => $this->code, 'module' => $this->title);

        ///////////////////////////////////////
        //  loop through the Parcel quotes retrieved //
        $i = 0 ;  // counter
        if (( MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes"))  {
            $this->_debug_output("d"," <br>d2 ln" . __LINE__ . ' $this->allowed_methods = <br> ',$this->allowed_methods ); // BMH ** DEBUG
        }
        if (BMH_MIN_ORDER_VALUE_DEBUG == "Yes") {  
            $ordervalue = $MINVALUEEXTRACOVER + 1;
        }                          // BMH ** DEBUG to force extra cover value FOR TESTING ONLY; auto cover to $100

        foreach($xml as $foo => $bar) 
        {
            $code = strval(($xml->service[$i]->code));                      // BMH strval 2023-12-18
            $code = str_replace("_", " ", $code);
            $code = substr($code,11); //strip first 11 chars;               //BMH keep API code for label

            $id = str_replace("_", "", strval($xml->service[$i]->code));    // BMH strval  // remove underscores from AusPost methods. Zen Cart uses underscore as delimiter between module and method. // underscores must also be removed from case statements below.
            $cost = (float)($xml->service[$i]->price);

             $description =  "PARCEL " . (ucwords(strtolower($code))) ; // BMH prepend PARCEL to code in sentence case

            if (( MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMH_P_DEBUG2 == "Yes"))  {
                $this->_debug_output("n","<br>n2 ln" . __LINE__ . " ID= $id  DESC= $description COST= $cost inc","") ;
              } // BMH 2nd level debug each line of quote parsed

              $add = 0 ; $f = 0 ; $info=0 ;

            switch ($id) {

                case  "AUSPARCELREGULARSATCHELEXTRALARGE": // fall through and treat as one block
                case  "AUSPARCELREGULARSATCHELLARGE":      // fall through and treat as one block
                case  "AUSPARCELREGULARSATCHELMEDIUM":     // fall through and treat as one block
                case  "AUSPARCELREGULARSATCHELSMALL":      // fall through and treat as one block
                case  "AUSPARCELREGULARSATCHELEXTRASMALL":      // fall through and treat as one block // BMH v2.5.8
                //case  "AUSPARCELREGULARSATCHEL500G":     // fall through and treat as one block

                    if (in_array("Prepaid Satchel", $this->allowed_methods,$strict = true)) {
                                                
                        if ($maxcoverexceeded = True ) {
                            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                                $this->_debug_output("n",'<p class="aupost-debug"> n2 ln' . __LINE__ . ' Prepaid Satchel $maxcoverexceeded reset',"" ); //BMH ** DEBUG
                            }
                           $ordervalue = $maxcover - 1 ;
                        } 
                        else { 
                            $ordervalue = $ordervalue_ori ;
                        }// reset if max extra cover exceeded


                        if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                        $this->_debug_output("n","<br> n2 ln" . __LINE__ . " allowed option = prepaid satchel","");
                        }

                        $optioncode =""; $optionservicecode = ""; $suboptioncode = ""; $allowed_option ="";
                        $add = MODULE_SHIPPING_AUPOST_PPS_HANDLING ;
                        $f = 1 ;

                        if ((($cost > 0) && ($f == 1))) { //
                            $cost = $cost + floatval($add) ;        // string to float
                            if ( MODULE_SHIPPING_AUPOST_CORE_WEIGHT == "Yes")  $cost = ($cost * $this->shipping_num_boxes) ;

                        // CALC TAX and remove from returned amt as tax is added back in on checkout
                        if (($dest_country == "AU") && (($this->tax_class) > 0)) {
                            $t = $cost - ($cost / (zen_get_tax_rate((int)$this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])+1)) ;
                            if ($t > 0) $cost = $t ;
                        }
                         $details= $this->_handling($details,$currencies,$add,$aus_rate,$info);  // check if handling rates included
                         }   // eof list option for normal operation
                         $cost = $cost / $aus_rate;

                        $methods[] = array('id' => "$id",  'title' => $description . " " . $details, 'cost' => $cost);   // update method
                    }

                    if ( in_array("Prepaid Satchel Insured +sig", $this->allowed_methods) ) {
        
                        if ($maxcoverexceeded = True ) {
                            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                                $this->_debug_output("n",'<p class="aupost-debug"> n2 ln' . __LINE__ . ' Prepaid Satchel $maxcoverexceeded NO CHANGE',"" ); //BMH ** DEBUG
                            }
                                                   
                            $ordervalue = $ordervalue_ori ;
                        } // reset if max extra cover exceeded

                       if ($ordervalue > $MINVALUEEXTRACOVER) {
                            $optioncode = 'AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY';
                            $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                            $suboptioncode = 'AUS_SERVICE_OPTION_EXTRA_COVER';

                            $id_option = $id . str_replace("_", "",$optioncode) . str_replace("_", "", $suboptioncode);

                            $allowed_option = "Prepaid Satchel Insured +sig";
                            $option_offset = 0;

                            $result_secondary_options = $this-> _get_secondary_options( $add, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER,
                                $dcode, $parcellength, $parcelwidth, $parcelheight, $parcelweight, $optionservicecode, $optioncode, $suboptioncode,
                                $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate,
                                $this->shipping_num_boxes);

                            if (strlen($id) >1) {
                                $methods[] = $result_secondary_options ;
                            }
                       }
                    }

                    if ( in_array("Prepaid Satchel +sig", $this->allowed_methods) ) {

                        if ($maxcoverexceeded = True ) {
                                if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                                    $this->_debug_output("n",'<p class="aupost-debug"> n2 ln' . __line__ . ' Prepaid Satchel +sig $maxcoverexceeded $ordervalue reset',"" ); //BMH ** DEBUG
                            }
                           $ordervalue = $maxcover - 1 ;
                        }
                        else { 
                            $ordervalue = $ordervalue_ori ;
                        } // reset if max extra cover exceeded

                        $optioncode = 'AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY';
                        $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                        $suboptioncode = '';

                        $id_option = $id . str_replace("_", "",$optioncode) . str_replace("_", "",$suboptioncode);
                        $allowed_option = "Prepaid Satchel +sig";

                        $option_offset = 0;

                        $result_secondary_options = $this-> _get_secondary_options( $add, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER,
                            $dcode, $parcellength, $parcelwidth, $parcelheight, $parcelweight, $optionservicecode, $optioncode, $suboptioncode,
                            $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate,
                            $this->shipping_num_boxes);

                        if (strlen($id) >1){
                            $methods[] = $result_secondary_options ;
                        }
                    }

                    if ( in_array("Prepaid Satchel Insured (no sig)", $this->allowed_methods) ) {
                        
                        if ($maxcoverexceeded = True ) {
                           if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                                $this->_debug_output("n",'<p class="aupost-debug"> n2 ln' . __line__ . ' Prepaid Satchel Insured (no sig) $maxcoverexceeded $ordervalue break ',"" ); //BMH ** DEBUG
                            }
                            break;
                        }
                        else { 
                            $ordervalue = $ordervalue_ori ;
                        } // reset if max extra cover exceeded

                        if ($ordervalue > $MINVALUEEXTRACOVER) {
                            $optioncode = 'AUS_SERVICE_OPTION_STANDARD';
                            $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                            $suboptioncode = 'AUS_SERVICE_OPTION_EXTRA_COVER';

                            $id_option = $id . str_replace("_", "",$optioncode) . str_replace("_", "",$suboptioncode);
                            $allowed_option = "Prepaid Satchel Insured (no sig)";
                            $option_offset1 = 0;

                            $result_secondary_options = $this-> _get_secondary_options( $add, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER,
                                $dcode, $parcellength, $parcelwidth, $parcelheight, $parcelweight, $optionservicecode, $optioncode, $suboptioncode,
                                $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate,
                                $this->shipping_num_boxes);

                            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                                $this->_debug_output("d",'d2<p class="aupost-debug"> ln' . __line__ . ' $result_secondary_options = ',$result_secondary_options) ; //BMH ** DEBUG
                            }

                            if (strlen($id) >1){
                                $methods[] = $result_secondary_options ;
                            }
                        }
                    }
                    break;

                case  "AUSPARCELEXPRESSSATCHELEXTRALARGE": // fall through and treat as one block
                case  "AUSPARCELEXPRESSSATCHELLARGE":      // fall through and treat as one block
                case  "AUSPARCELEXPRESSSATCHELMEDIUM":     // fall through and treat as one block
                case  "AUSPARCELEXPRESSSATCHELSMALL" :      // fall through and treat as one block
                case  "AUSPARCELEXPRESSSATCHELEXTRASMALL" :      // fall through and treat as one block

                    if ((in_array("Prepaid Express Satchel", $this->allowed_methods))) {
                        if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                            $this->_debug_output("n","<br>ln1043 n2 allowed option = parcel express satchel","");
                        }
                        
                        if ($maxcoverexceeded = True ) {
                           if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                                $this->_debug_output("n",'<p class="aupost-debug"> n2 ln' . __line__ . ' Prepaid Express Satchel $maxcoverexceeded $ordervalue reset',"" ); //BMH ** DEBUG
                            }
                           $ordervalue = $maxcover - 1 ;
                        }                                             
                        else { 
                            $ordervalue = $ordervalue_ori ;
                        }; // reset if max extra cover exceeded

                        $optioncode =""; $optionservicecode = ""; $suboptioncode = "";
                        $add =  MODULE_SHIPPING_AUPOST_PPSE_HANDLING ;
                        $f = 1 ;

                        if ((($cost > 0) && ($f == 1))) { //
                            $cost = $cost + floatval($add) ;        // string to float
                            if ( MODULE_SHIPPING_AUPOST_CORE_WEIGHT == "Yes")  $cost = ($cost * $this->shipping_num_boxes) ;

                            // CALC TAX and remove from returned amt as tax is added back in on checkout
                            if (($dest_country == "AU") && (($this->tax_class) > 0)) {
                                $t = $cost - ($cost / (zen_get_tax_rate((int)$this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])+1)) ;
                                if ($t > 0) $cost = $t ;
                            }
                            $details= $this->_handling($details,$currencies,$add,$aus_rate,$info);  // check if handling rates included
                         }   // eof list option for normal operation
                         $cost = $cost / $aus_rate;

                        $methods[] = array('id' => "$id",  'title' => $description . " " . $details, 'cost' => $cost);   // update method
                    }
                    if ( in_array("Prepaid Express Satchel Insured +sig", $this->allowed_methods) ) {
                        if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                            $this->_debug_output("n","<br>n2 ln" . __line__ . " allowed option = parcel express satchel ins+sig",""); 
                            $this->_debug_output("n","<br>n2 ln" . __line__ . " ordervalue = ",$ordervalue); 
                        }
                                                
                           if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                                $this->_debug_output("n",'<p class="aupost-debug"> n2 ln' . __line__ . ' Prepaid Express Satchel $maxcoverexceeded $ordervalue reset',"" ); //BMH ** DEBUG
                            }
                            $ordervalue = $ordervalue_ori ;
                            // reset if max extra cover exceeded

                       if ($ordervalue > $MINVALUEEXTRACOVER) {
                            $optioncode = 'AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY';
                            $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                            $suboptioncode = 'AUS_SERVICE_OPTION_EXTRA_COVER';

                           $id_option = $id . str_replace("_", "",$optioncode) . str_replace("_", "",$suboptioncode);
                            $allowed_option = "Prepaid Express Satchel Insured +sig";
                            $option_offset = 0;

                            $result_secondary_options = $this-> _get_secondary_options( $add, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER,
                                $dcode, $parcellength, $parcelwidth, $parcelheight, $parcelweight, $optionservicecode, $optioncode, $suboptioncode,
                                $id_option, $description, $details,$dest_country, $order, $currencies, $aus_rate,
                                $this->shipping_num_boxes);

                            if (strlen($id) >1) {
                                $methods[] = $result_secondary_options ;
                            }
                       }
                    }

                    if ( in_array("Prepaid Express Satchel +sig", $this->allowed_methods) ) {
                                                
                        if ($maxcoverexceeded = True ) {
                           if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                                $this->_debug_output("n",'<p class="aupost-debug"> n2 ln' . __line__ . ' Prepaid Express Satchel +sig $maxcoverexceeded $ordervalue break',"" ); //BMH ** DEBUG
                            }
                            break;
                        }                                   
                        else { 
                            $ordervalue = $ordervalue_ori ;
                        }; // reset if max extra cover exceeded

                        $allowed_option = "Prepaid Express Satchel +sig";
                        $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                        $optioncode = 'AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY';
                        $suboptioncode = '';

                        $id_option = $id . str_replace("_", "",$optioncode) . str_replace("_", "",$suboptioncode);

                        $result_secondary_options = $this-> _get_secondary_options( $add, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER,
                            $dcode, $parcellength, $parcelwidth, $parcelheight, $parcelweight, $optionservicecode, $optioncode, $suboptioncode,
                            $id_option, $description, $details,$dest_country, $order, $currencies, $aus_rate,
                            $this->shipping_num_boxes);

                        if (strlen($id) >1) {
                            $methods[] = $result_secondary_options ;
                        }
                    }

                    if ( in_array("Prepaid Express Satchel Insured (no sig)", $this->allowed_methods) ) {
                                                   
                        if ($maxcoverexceeded = True ) {
                            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                                $this->_debug_output("n",'<p class="aupost-debug"> n2 ln' . __line__ . ' Prepaid Express Satchel Insured (no sig) $maxcoverexceeded break',"" ); //BMH ** DEBUG
                            }
                            break;
                        }                    
                        else { 
                            $ordervalue = $ordervalue_ori ;
                        } // reset if max extra cover exceeded

                        if ($ordervalue > $MINVALUEEXTRACOVER) {
                            $allowed_option = "Prepaid Express Satchel Insured (no sig)";
                            $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                            $optioncode = 'AUS_SERVICE_OPTION_STANDARD';
                            $suboptioncode = 'AUS_SERVICE_OPTION_EXTRA_COVER';

                            $id_option = $id . str_replace("_", "", $optioncode) . str_replace("_", "", $suboptioncode);

                            $result_secondary_options = $this-> _get_secondary_options( $add, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER,
                                $dcode, $parcellength, $parcelwidth, $parcelheight, $parcelweight, $optionservicecode, $optioncode, $suboptioncode,
                                $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate,
                                $this->shipping_num_boxes);

                            if (strlen($id) >1) {
                                $methods[] = $result_secondary_options ;
                            }
                        }
                    }
                    break;

                //case  "AUSPARCELREGULARPACKAGESMALL":        // requires additonal AP packaging
                //case  "AUSPARCELREGULARPACKAGE":             // requires additional AP packaging normal mail
                case  "AUSPARCELREGULAR":                       // normal mail - own packaging
                    if (in_array("Regular Parcel", $this->allowed_methods,$strict = true)) {

                        if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                            $this->_debug_output("n",'<br>n2 ln' . __line__ . ' allowed option = parcel regular',"");
                        }
                                                
                         if ($maxcoverexceeded = True ) {
                             if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                                 $this->_debug_output("n",'<p class="aupost-debug"> n2 ln' . __line__ . ' Regular Parcel $maxcoverexceeded reset',"" ); //BMH ** DEBUG
                             }
                            $ordervalue = $maxcover - 1 ;
                         }                        
                         else { 
                            $ordervalue = $ordervalue_ori ;
                        }                           // reset if max extra cover exceeded

                        $optioncode =""; $optionservicecode = ""; $suboptioncode = ""; $allowed_option ="";
                        $add = MODULE_SHIPPING_AUPOST_RPP_HANDLING ;
                        $f = 1 ;
                        $apr = 1;

                        if ((($cost > 0) && ($f == 1))) { //
                            $cost = $cost + floatval($add) ;        // string to float
                            if ( MODULE_SHIPPING_AUPOST_CORE_WEIGHT == "Yes")  $cost = ($cost * $this->shipping_num_boxes) ;

                        // CALC TAX and remove from returned amt as tax is added back in on checkout
                        if (($dest_country == "AU") && (($this->tax_class) > 0)) {
                            $t = $cost - ($cost / (zen_get_tax_rate((int)$this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])+1)) ;
                            if ($t > 0) $cost = $t ;
                        }
                         $details= $this->_handling($details,$currencies,$add,$aus_rate,$info);  // check if handling rates included
                         }   // eof list option for normal operation
                         $cost = $cost / $aus_rate;

                         $methods[] = array('id' => "$id",  'title' => $description . " " . $details, 'cost' => $cost);   // update method
                    }

                    if ( in_array("Regular Parcel Insured +sig", $this->allowed_methods) ) {
                        if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                            $this->_debug_output("n",'<br>ln' . __line__ . ' n2 allowed option = parcel regular ins + sig',"");
                        }
                                                
                        if ($maxcoverexceeded = True ) {
                            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                                $this->_debug_output("n",'<p class="aupost-debug"> n2 ln' . __line__ . ' Regular Parcel Insured +sig $maxcoverexceeded NO CHANGE',"" ); //BMH ** DEBUG
                            }
                            $ordervalue = $ordervalue_ori ;
                        } // reset if max extra cover exceeded
                         
                        if ($ordervalue > $MINVALUEEXTRACOVER) {
                            $optioncode = 'AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY';
                            $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                            $suboptioncode = 'AUS_SERVICE_OPTION_EXTRA_COVER';
                            $id_option = $id . $optioncode . $suboptioncode;
                            $id_option = $id . str_replace("_", "",$optioncode) . str_replace("_", "",$suboptioncode);
                            $allowed_option = "Regular Parcel Insured +sig";
                            $option_offset = 0;

                            $result_secondary_options = $this-> _get_secondary_options( $add, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER,
                                $dcode, $parcellength, $parcelwidth, $parcelheight, $parcelweight, $optionservicecode, $optioncode, $suboptioncode,
                                $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate,
                                $this->shipping_num_boxes);

                            if (strlen($id) >1) {
                                $methods[] = $result_secondary_options ;
                            }
                        }
                    }

                    if ( in_array("Regular Parcel +sig", $this->allowed_methods) ) {
                       if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                           $this->_debug_output("n",'<br>ln' . __line__ . ' n2 allowed option = parcel regular + sig',"");
                        }
                                                
                        if ($maxcoverexceeded = True ) {
                            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                                $this->_debug_output("n",'<p class="aupost-debug"> n2 ln' . __line__ . ' Regular Parcel +sig $maxcoverexceeded reset',"" ); //BMH ** DEBUG
                            }
                           $ordervalue = $maxcover - 1 ;
                        }                        
                        else { 
                            $ordervalue = $ordervalue_ori ;
                        } // reset if max extra cover exceeded

                        $optioncode = 'AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY';
                        $optionservicecode = ($xml->service[$i]->code);     // get api code for this option

                        $suboptioncode = '';
                        $id_option = $id . str_replace("_", "",$optioncode) . str_replace("_", "",$suboptioncode);
                        $allowed_option = "Regular Parcel +sig";
                        $option_offset = 0;

                        $result_secondary_options = $this-> _get_secondary_options( $add, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER,
                            $dcode, $parcellength, $parcelwidth, $parcelheight, $parcelweight, $optionservicecode, $optioncode, $suboptioncode,
                            $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate,
                            $this->shipping_num_boxes);

                        if (strlen($id) >1){
                            $methods[] = $result_secondary_options ;
                        }
                    }

                    if ( in_array("Regular Parcel Insured (no sig)", $this->allowed_methods) ) {
                                                
                        if ($maxcoverexceeded = True ) {
                            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                                $this->_debug_output("n",'<p class="aupost-debug"> n2 ln' . __line__ . ' Regular Parcel Insured (no sig) $maxcoverexceeded break',"" ); //BMH ** DEBUG
                            }
                            break;
                        }                        
                        else { 
                            $ordervalue = $ordervalue_ori ;
                        } // reset if max extra cover exceeded

                        if ($ordervalue > $MINVALUEEXTRACOVER) {
                            $optioncode = 'AUS_SERVICE_OPTION_STANDARD';
                            $optionservicecode = ($xml->service[$i]->code);     // get api code for this option
                            $suboptioncode = 'AUS_SERVICE_OPTION_EXTRA_COVER';
                            $id_option = $id . str_replace("_", "",$optioncode) . str_replace("_", "",$suboptioncode);
                            $allowed_option = "Regular Parcel Insured (no sig)";
                            $option_offset1 = 0;

                            $result_secondary_options = $this-> _get_secondary_options( $add, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER,
                                $dcode, $parcellength, $parcelwidth, $parcelheight, $parcelweight, $optionservicecode, $optioncode, $suboptioncode,
                                $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate,
                                $this->shipping_num_boxes);

                            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                                $this->_debug_output("d",'<br>ln' . __line__ . ' d2 $result_secondary_options = ',$result_secondary_options);
                            }
                            if (strlen($id) >1){
                                $methods[] = $result_secondary_options ;
                            }
                        }
                    }
                break;

                case  "AUSPARCELEXPRESS":              // express mail - own packaging
                    if (in_array("Express Parcel", $this->allowed_methods,$strict = true)) {

                        if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                            $this->_debug_output("n", '<br>ln' . __line__ . ' n2 allowed option = parcel express', "");
                        }
                        $optioncode =""; $optionservicecode = ""; $suboptioncode = ""; $allowed_option ="";
                        $add = MODULE_SHIPPING_AUPOST_EXP_HANDLING ;

                        $f = 1 ;
                        // got all of the values // -----------

                        if ((($cost > 0) && ($f == 1))) { //
                            $cost = $cost + floatval($add) ;        // string to float
                            if ( MODULE_SHIPPING_AUPOST_CORE_WEIGHT == "Yes")  $cost = ($cost * $this->shipping_num_boxes) ;

                        // CALC TAX and remove from returned amt as tax is added back in on checkout
                            if (($dest_country == "AU") && (($this->tax_class) > 0)) {
                                $t = $cost - ($cost / (zen_get_tax_rate((int)$this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])+1)) ;
                                if ($t > 0) $cost = $t ;
                                }
                                // //  ++++

                            $details= $this->_handling($details,$currencies,$add,$aus_rate,$info);  // check if handling rates included
                            // //  ++++

                        }   // eof list option for normal operation
                        $cost = $cost / $aus_rate;
                        $methods[] = array('id' => "$id",  'title' => $description . " " . $details, 'cost' => $cost);   // update method
                    }

                    if ( in_array("Express Parcel Insured +sig", $this->allowed_methods, $strict = true) ) {
                        if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                            $this->_debug_output("n",'<br>ln' . __line__ . ' n2 allowed option = parcel express ins + sig',"");
                        }
                        if ($ordervalue > $MINVALUEEXTRACOVER) {
                            $optioncode = 'AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY';
                            $add = MODULE_SHIPPING_AUPOST_EXP_HANDLING ;
                            $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                            $suboptioncode = 'AUS_SERVICE_OPTION_EXTRA_COVER';
                            //$id_option = "AUSPARCELEXPRESS" . "AUSSERVICEOPTIONSIGNATUREONDELIVERYEXTRACOVER";
                            $id_option = $id . str_replace("_", "", $optioncode) . str_replace("_", "", $suboptioncode);
                            $allowed_option = "Express Parcel Insured +sig";
                            $option_offset = 0;

                            $result_secondary_options = $this-> _get_secondary_options( $add, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER,
                                $dcode, $parcellength, $parcelwidth, $parcelheight, $parcelweight, $optionservicecode, $optioncode, $suboptioncode,
                                $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate,
                                $this->shipping_num_boxes);

                            if (strlen($id) >1){
                                $methods[] = $result_secondary_options ;
                            }
                        }
                    }

                    if ( in_array("Express Parcel +sig", $this->allowed_methods, $strict = true) ) {
                                                
                        if ($maxcoverexceeded = True ) {
                            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                                $this->_debug_output("n",'<p class="aupost-debug"> n2 ln' . __line__ . ' Prepaid Express Satchel Insured (no sig) $maxcoverexceeded break',"" ); //BMH ** DEBUG
                            }
                           $ordervalue = $maxcover - 1 ;
                        }                        
                        else { 
                            $ordervalue = $ordervalue_ori ;
                        } // reset if max extra cover exceeded


                        $optioncode = 'AUS_SERVICE_OPTION_SIGNATURE_ON_DELIVERY';
                        $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                        $suboptioncode = '';
                        $id_option = "AUSPARCELEXPRESS" . "AUSSERVICEOPTIONSIGNATUREONDELIVERY";
                        $allowed_option = "Express Parcel +sig";

                        $result_secondary_options = $this-> _get_secondary_options( $add, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER,
                            $dcode, $parcellength, $parcelwidth, $parcelheight, $parcelweight, $optionservicecode, $optioncode, $suboptioncode,
                            $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate,
                            $this->shipping_num_boxes);

                        if (strlen($id) >1){
                            $methods[] = $result_secondary_options ;
                        }

                    }

                    if ( in_array("Express Parcel Insured (no sig)", $this->allowed_methods) )
                    {
                                               
                        if ($maxcoverexceeded = True ) {
                            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                                $this->_debug_output("n",'<p class="aupost-debug"> n2 ln' . __line__ . ' Prepaid Express Satchel Insured (no sig) $maxcoverexceeded break',"" ); //BMH ** DEBUG
                            }
                            break;
                        }                        
                        else { 
                            $ordervalue = $ordervalue_ori ;
                        } // reset if max extra cover exceeded

                        if ($maxcoverexceeded = True ) {
                            $ordervalue = $maxcover - 1 ;
                        };  // skip if max extra cover exceeded as signature required for high value

                        if ($ordervalue > $MINVALUEEXTRACOVER) {
                            $optioncode = 'AUS_SERVICE_OPTION_STANDARD';
                            $optionservicecode = ($xml->service[$i]->code);  // get api code for this option
                            $suboptioncode = 'AUS_SERVICE_OPTION_EXTRA_COVER';
                            $id_option = $id .str_replace("_", "",$suboptioncode);
                            $allowed_option = "Express Parcel Insured (no sig)";

                            $result_secondary_options = $this-> _get_secondary_options( $add, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER,
                                $dcode, $parcellength, $parcelwidth, $parcelheight, $parcelweight, $optionservicecode, $optioncode, $suboptioncode,
                                $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate, $this->shipping_num_boxes);

                            if (strlen($id) >1){
                                $methods[] = $result_secondary_options ;
                            }
                        }
                    }
                break;

                case  "AUSPARCELEXPRESSSATCHEL5KG" :        // superceded
                case  "AUSPARCELEXPRESSSATCHEL3KG" :        // superceded
                case  "AUSPARCELEXPRESSSATCHEL1KG" :        // superceded
                case  "AUSPARCELEXPRESSSATCHEL500G":        // superceded by AUSPARCELEXPRESSSATCHELSMALL
                //
                case  "AUSPARCELREGULARSATCHEL5KG" :        // superceded by
                case  "AUSPARCELREGULARSATCHEL3KG" :        // superceded by AUSPARCELREGULARSATCHELLARGE
                case  "AUSPARCELREGULARSATCHEL1KG" :        // superceded
                case  "AUSPARCELREGULARSATCHEL500G":        // still returned but superceded by AUSPARCELREGULARSATCHELSMALL
                //
                //case  "AUSPARCELEXPRESSPACKAGESMALL":     // This is cheaper but requires extra purchase of Aus Post packaging
                //
                //case  "AUSPARCELREGULARPACKAGESMALL":     // This is cheaper but requires extra purchase of Aus Post packaging
                //case  "AUSPARCELREGULARPACKAGEMEDIUM":    // This is cheaper but requires extra purchase of Aus Post packaging
                //case  "AUSPARCELREGULARPACKAGELARGE":     // This is cheaper but requires extra purchase of Aus Post packaging
                      // $optioncode =""; $optionservicecode = ""; $suboptioncode = "";

                $cost = 0; $f=0; $add= 0;
                // echo "shouldn't be here"; //BMH debug
                //do nothing - ignore the code
                break;

                if (( MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes"))  {
                    $this->_debug_output("n",'ln' . __line__ . ' d1 ID= $id  DESC= $description COST= $cost',"");
                } // BMH 2nd level debug each line of quote parsed
            }  // eof switch

            ////    only list valid options without debug info // BMH
            if ((($cost > 0) && ($f == 1))) { //&& ( MODULE_SHIPPING_AUPOST_DEBUG == "No" )) { //BMH DEBUG = ONLY if not debug mode
                $cost = $cost + floatval($add) ;        // string to float
                if ( MODULE_SHIPPING_AUPOST_CORE_WEIGHT == "Yes")  $cost = ($cost * $this->shipping_num_boxes) ;

                $details= $this->_handling($details,$currencies,$add,$aus_rate,$info);  // check if handling rates included
            }   // eof list option for normal operation

            $cost = $cost / $aus_rate;

            if (( MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes"))  {
                    $this->_debug_output("n",'ln' . __line__ . ' n2 $i= ',$i);
                } // BMH 3rd level debug each line of quote parsed

            $i++; // increment the counter to match XML array index
        }  // end foreach loop

        //  //  ///////////////////////////////////////////////////////////////////
        //  check to ensure we have at least one valid quote - produce error message if not.
         if ((is_array($methods)) && (count($methods) == 0) ) {                // no valid methods
               $error_msg_ap = ERROR_NO_VALID_PARCEL_QUOTE_MSG;                // 
               $cost = $this->_get_error_cost($dest_country,$error_msg_ap) ;   // give default cost

                              // BMH if ($cost == 0)  return  ;
               if ($this->enabled == FALSE ) return;                      //

                $methods[] = array( 'id' => "Error",  'title' =>MODULE_SHIPPING_AUPOST_TEXT_ERROR ,'cost' => $cost ) ; // display reason
            }

        // // // sort array by cost       // // //
        $sarray[] = array() ;
        $resultarr = array() ;

        foreach($methods as $key => $value) {
            $sarray[ $key ] = $value['cost'] ;
        }
        asort( $sarray ) ;

        foreach($sarray as $key => $value)

        //  remove zero values from postage options
        foreach ($sarray as $key => $value) {
            if ($value == 0 ) {
            }
            else
            {
                $resultarr[ $key ] = $methods [ $key ] ;
            }
        } // BMH eof remove zero values

        $resultarrunique = array_unique($resultarr,SORT_REGULAR);      // remove duplicates

        $this->quotes['methods'] = array_values($resultarrunique) ;    // set it

        if ($this->tax_class >  0) {
          $this->quotes['tax'] = zen_get_tax_rate((int)$this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
        }
        $parcellength = (int)$parcellength; $parcelwidth = (int)$parcelwidth; $parcelheight = (int)$parcelheight;
        if (BMH_P_DEBUG2 == "Yes") {
            $this->_debug_output("n",'<br>ln' . __line__ . ' n2 parcels ***<br>aupost l ' .'https://' . $aupost_url_string . PARCEL_URL_STRING .
                $frompcode . "&to_postcode=$dcode&length=$parcellength&width=$parcelwidth&height=$parcelheight&weight=$parcelweight" . '</p>',"");
        }
        if (zen_not_null($this->icon)) $this->quotes['icon'] = zen_image($this->icon, $this->title);
        $_SESSION['aupostQuotes'] = $this->quotes  ; // save as session to avoid reprocessing when single method required

        return $this->quotes;   //  all done //

        //  //  ///////////////////////////////  Final Exit Point //////////////////////////////////
    } // eof function quote method

    /**
     * Validates an Australian postcode for AU addresses. Resets postcode in $order if invalid.
     * @param string $postcode
     * @param string $country
     * @param array|object $order (passed by reference)
     * @return bool
     */
    private function validate_au_postcode($postcode, $country, &$order)
    {
        if ($country != "AU") {
            return false;
        }
        if ($postcode === "") {
            return false;
        }
        if (strlen($postcode) !== 4 || preg_match("/\D/", $postcode)) {
            $order->delivery['postcode'] = "";
            return false;
        }
        $pattern = "/^(0[289][0-9]{2})|([1345689][0-9]{3})|(2[0-8][0-9]{2})|(290[0-9])|(291[0-4])|(7[0-4][0-9]{2})|(7[8-9][0-9]{2})$/";
        if (!preg_match($pattern, $postcode)) {
            $order->delivery['postcode'] = "";
            return false;
        }
        return true;
    }

private function _get_secondary_options( $add, $allowed_option, $ordervalue, $MINVALUEEXTRACOVER,
    $dcode, $parcellength, $parcelwidth, $parcelheight, $parcelweight, $optionservicecode, $optioncode, $suboptioncode,
    $id_option, $description, $details, $dest_country, $order, $currencies, $aus_rate, $shipping_num_boxes)
    {
        global $frompcode;
        global $maxcoverexceeded;
        $aupost_url_string = AUPOST_URL_PROD ;  // Server query string //
        
    //    if ($maxcoverexceeded = True ) {
    //        // break;
    //        $ordervalue = $maxcover - 1 ;
    //    }; // skip if max extra cover exceeded

        if ((in_array($allowed_option, $this->allowed_methods))) {
            //$add = MODULE_SHIPPING_AUPOST_RPP_HANDLING ;
            $f = 1 ;

            $ordervalue = ceil($ordervalue);  // round up to next integer
            $parcellength = (int)$parcellength; $parcelwidth = (int)$parcelwidth; $parcelheight = (int)$parcelheight;
            
            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                $this->_debug_output("n",'<br>n2 ln' . __line__ . ' allowed option = ' . $allowed_option .  PARCEL_URL_STRING_CALC . $frompcode .
                    "&to_postcode=$dcode&length=$parcellength&width=$parcelwidth&height=$parcelheight&weight=$parcelweight
                    &service_code=$optionservicecode&option_code=$optioncode&suboption_code=$suboptioncode&extra_cover=
                    $ordervalue" ,"");
            }
            $parcellength = (int)$parcellength; $parcelwidth = (int)$parcelwidth; $parcelheight = (int)$parcelheight;   // make integers as passed to AP
            $qu2 = $this->get_auspost_api( 'https://' . $aupost_url_string . PARCEL_URL_STRING_CALC. $frompcode . "&to_postcode=$dcode&length=$parcellength&width=$parcelwidth&height=$parcelheight&weight=$parcelweight&service_code=$optionservicecode&option_code=$optioncode&suboption_code=$suboptioncode&extra_cover=$ordervalue") ;

            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                $this->_debug_output("n",'<br>ln' . __line__ . ' n2  $qu2 = ' . $qu2   ,"");
            }

            $xmlquote_2 = ($qu2 == '') ? array() : new SimpleXMLElement($qu2); // XML format

            if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes")) {
                $this->_debug_output("x",'<br>ln' . __line__ . ' d2  $allowed_option = ' . $allowed_option . ' <br> ' . 'Server Returned BMHDEBUG1+2 ln1434 options<< <br> <textarea>',$xmlquote_2);
            }

            $invalid_option = $xmlquote_2->errorMessage;

            if (empty($invalid_option)) {
                // -- BMH DEBUG eof XML formatted output----
                $desc_option = $allowed_option;
                $cost_option = (float)($xmlquote_2->total_cost);

                // got all of the option values // -----------
                $cost = $cost_option;

                if ((($cost > 0) && ($f == 1))) { //
                    $cost = $cost + floatval($add) ;        // string to float
                    if ( MODULE_SHIPPING_AUPOST_CORE_WEIGHT == "Yes")  $cost = ($cost * $shipping_num_boxes) ;

                // CALC TAX and remove from returned amt as tax is added back in on checkout
                if (($dest_country == "AU") && (($this->tax_class) > 0)) {
                    $t = $cost - ($cost / (zen_get_tax_rate((int)$this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])+1)) ;
                    if ($t > 0) $cost = $t ;
                    }
                // //  ++++
                    $info = 0;  // BMH Dummy used for REG POST - MAY BE REDUNDANT

                    $details= $this->_handling($details,$currencies,$add,$aus_rate,$info);  // check if handling rates included
                    // //  ++++

                }   // eof list option for normal operation
                $cost = $cost / $aus_rate;

                $desc_option = "[" . $desc_option . "]";         // delimit option in square brackets
                $result_secondary_options = array("id"=>$id_option,  "title"=>$description . ' ' . $desc_option . ' ' .$details, "cost"=>$cost) ;
            } // valid result
            else {      // pass back a zero value as not a valid option from Australia Post eg extra cover may require a signature as well
             $cost = 0;
                $result_secondary_options = array("id"=> '',  "title"=>'', "cost"=>$cost) ;  // invalid result
            }
        }   // eof // Express plus options

        return $result_secondary_options;
    } // eof function _get_secondary_options //

    // // //
    private function _get_error_cost($dest_country,$error_msg_ap)  // BMH 
    {   
        global $messageStack;
        global $cost;       
        
        if (is_array(MODULE_SHIPPING_AUPOST_COST_ON_ERROR)) {
            $x = explode(',', MODULE_SHIPPING_AUPOST_COST_ON_ERROR) ;
            if (in_array("TBA", $x)) {
                $this->error_msg_ap = $this->error_msg_ap . " price TBA";
                $cost = '0';                            // BMH reset $x price on error to numeric 
            }
        }
        else {
            unset($_SESSION['aupostParcel']) ;          // don't cache errors.
            // $cost = $dest_country == "AU" ?  $x[0]:$x[1] ; // check multiple options // not used
            $cost = MODULE_SHIPPING_AUPOST_COST_ON_ERROR;
            if ($cost == 0) {           // disable cost on error
                $this->enabled = FALSE ;
                unset($_SESSION['aupostQuotes']) ;
                return $cost;
            }  // disabled - no further processing
            
            if ($cost == 'TBA') {
                $this->error_msg_ap = $this->error_msg_ap . " price TBA";
                $cost = '0';  // BMH reset $x price on error to numeric 
            }
        
            if ($cost !== 0) {           // disable cost on error
                $this->quotes = array('id' => $this->code, 'module' => 'Australia Post');
                // BMH bof output to logfile
                $messageStack->add_session('aupost_error', $error_msg_ap, 'error');
                $customer_id = $_SESSION['customer_id'] ?? '';                                  // include customer id if set
                $this->_log("" . $this->error_msg_ap . " #"  . " Cust:". $customer_id); // BMH
                // BMH eof output to log file
            }
        }
        return $cost;
    }
// // // extra functions
    //// auspost API
    function get_auspost_api($url)
    {   
        $xml = []; global $customer_id;
        // BMH changed to allow test key
        if (AUPOST_MODE == 'Test') {
            $aupost_url_apiKey = AUPOST_TESTMODE_AUTHKEY;
            }
            else {
            $aupost_url_apiKey = MODULE_SHIPPING_AUPOST_AUTHKEY;
            if($aupost_url_apiKey   ==   '' || $aupost_url_apiKey   ==   '0') {
                echo '<br><strong>Australia Post API Key is not set.</strong> Please notify the administrator to set the API Key in the module settings.';
                $this->_log("Australia Post API Key is not set. Please set the API Key  in the module settings. Cust:". $customer_id); // BMH write to log file
                return; 
            }
        }
            if ((BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")) {
            // echo '<br> ln1635 get_auspost_api $url= ' . $url;
            // echo '<br> ln1636 $aupost_url_apiKey= ' . $aupost_url_apiKey;
        }
        $crl = curl_init();
        $timeout = 5;
        //
        curl_setopt ($crl, CURLOPT_HTTPHEADER, array('AUTH-KEY:' . $aupost_url_apiKey)); // BMH new
        //$dump_curl = array('AUTH-KEY:' . $aupost_url_apiKey); // BMH DEBUG

        curl_setopt ($crl, CURLOPT_URL, $url);
        curl_setopt ($crl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
        $ret = curl_exec($crl);

        // Check the response: if the body is empty then an error occurred
        if (( BMHDEBUG1 == "Yes") && (BMH_P_DEBUG2 == "Yes") && (BMH_P_DEBUG3 == "Yes")) {
            $this->_debug_output("x",'ln' . __line__ . ' x2 get_auspost_api curl $ret= <br>' , $ret); //
        }

        $xml = ($ret == '') ? array() : new SimpleXMLElement($ret) ; // If we have any results, parse them into an array
        if ($xml->errorMessage) {
            $ret = 'Error ' . $ret;
            $this->_log("" . $xml->errorMessage  . " Cust:". $customer_id); // BMH write to log file
            $cost= "";
            $methods[] = array('id' => $this->code,'title '. $xml->errorMessage, 'cost' => $cost ) ; //BMH issue#19
            $this->quotes['methods'] = $methods;   // set it
            return $ret;
        }
        //BMH bof  code for when Australia Post is down 
        $edata = curl_exec($crl);
        $errtext = curl_error($crl);
        $errnum = curl_errno($crl);
        $commInfo = curl_getinfo($crl);
        if ($edata === "Access denied") {
            $errtext = "<strong>" . $edata . ".</strong> Please report this error to <strong>System Owner ";
        }
        //BMH eof
        if(!$ret){
            die('<br>Error: "' . curl_error($crl) . '" - Code: ' . curl_errno($crl) .
                ' <br>Major Fault - Cannot contact Australia Post .
                Please report this error to System Owner. Then try the back button on your browser.');
        }

        // PHP8.5 only required for  prior to PHP 8.0 curl_close($crl);
        return $ret;
    }
    // end auspost API

    public function _handling($details,$currencies,$add,$aus_rate,$info)
    {
        if  (MODULE_SHIPPING_AUPOST_HIDE_HANDLING !='Yes') {
            if ( is_string($add) ) {
                $add = (float)$add;
            }
            $details = ' (Inc ' . $currencies->format( $add / $aus_rate ). ' P &amp; H';  // Abbreviated for space saving in final quote format

            if ($info > 0)  {
            $details = $details." +$".$info." fee)." ;
            }
            else {
                $details = $details.")" ;
            }
        }
        return $details;
    }

// write to log file
    private function _log($msg, $suffix = '')
    {
        global $purchaseOrderId;
        $file = $this->_logDir . '/' . $this->log_file_name;
        if ($fp = @fopen($file, 'a'))
        {
            $today = date("Y-m-d_H:i:s");         // BMH
            @fwrite($fp, "".time().": ".$today . ": " .$msg . " " . $purchaseOrderId ."\r\n"); // stores epoch time + date
            // BMH @fwrite($fp, "".time().": ".$msg); // stores time as epoch time
            @fclose($fp);
        }
    }
// format on screen debug statements
    public function _debug_output($x,$debug_message,$dump)
    {
        switch ($x) {
        case "x":            // x=xml dump
            echo '<p class="aupost-debug">';
            echo $debug_message ;
            echo '<br><textarea rows="15" cols="80" readonly="readonly">';
            print_r($dump);
            echo "</textarea> </p>";
            break;

        case "d":               // d=detailed dump
            echo '<table class="aupost-debug"><tr><td>' ;
            echo $debug_message ;
            var_dump ($dump);
            echo "</td></tr></table>" ;
            break;

        case "n":               // n=normal message
            echo '<table class="aupost-debug"><tr><td>' ;
            echo $debug_message . " " . $dump ;
            echo "</td></tr></table>" ;
            break;
        }
        return;
    }   // end _debug_output

    //////////////////////////////////////////////////////////////
    // parts for admin module
    //
    // Check to see if module is installed
    public function check()         // 
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_AUPOST_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }

    //  //  ////////////////////////////////////////////////////////////////////////
    public function install()       // 
    {
        global $db;
        global $messageStack;
        // check for XML // BMH
        if (!class_exists('SimpleXMLElement')) {
            $messageStack->add('aupost', 'Installation FAILED. AusPpost requires SimpleXMLElement to be installed on the system ');
            //$messageStack->add(sprintf('Installation FAILED. AusPpost requires SimpleXMLElement to be installed on the system ', 'info'));
        echo "<br/> This module requires SimpleXMLElement to work. Most Web hosts will support this.<br>Installation will NOT continue.<br>Press your back-page to continue ";
        exit;
        }

        $result = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'SHIPPING_ORIGIN_ZIP'"  ) ;
        $pcode = $result->fields['configuration_value'] ;

        if (!$pcode) $pcode = "4121" ;  // default if not configured in Admin console
        //

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
            VALUES ('Enable this module?', 'MODULE_SHIPPING_AUPOST_STATUS', 'True', 'Enable this Module', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

       $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
           VALUES ('Auspost API Key:', 'MODULE_SHIPPING_AUPOST_AUTHKEY', 'Add API Auth key from Australia Post', 'To use this module, you must obtain a 36 digit API Key from the <a href=\"https:\\developers.auspost.com.au\" target=\"_blank\">Auspost Development Centre</a>', '6', '2', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
            VALUES ('Dispatch Postcode', 'MODULE_SHIPPING_AUPOST_SPCODE', $pcode, 'Dispatch Postcode?', '6', '2', now())");
        // BMH bof LETTERS

        $db->Execute(
            "insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function,  date_added)
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
        // BMH eof LETTERS

        // bof PARCELS
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
            VALUES ('Shipping Methods for Australia', 'MODULE_SHIPPING_AUPOST_TYPES1', 'Regular Parcel, Regular Parcel +sig, Regular Parcel Insured +sig, Regular Parcel Insured (no sig), Prepaid Satchel, Prepaid Satchel +sig, Prepaid Satchel Insured +sig, Prepaid Satchel Insured (no sig), Express Parcel, Express Parcel +sig, Express Parcel Insured +sig, Express Parcel Insured (no sig), Prepaid Express Satchel, Prepaid Express Satchel +sig, Prepaid Express Satchel Insured +sig, Prepaid Express Satchel Insured (no sig)',
                'Select the methods you wish to allow', '6', '4',
                'zen_cfg_select_multioption(array(\'Regular Parcel\',\'Regular Parcel +sig\',\'Regular Parcel Insured +sig\',\'Regular Parcel Insured (no sig)\',\'Prepaid Satchel\',\'Prepaid Satchel +sig\',\'Prepaid Satchel Insured +sig\',\'Prepaid Satchel Insured (no sig)\',\'Express Parcel\',\'Express Parcel +sig\',\'Express Parcel Insured +sig\',\'Express Parcel Insured (no sig)\',\'Prepaid Express Satchel\',\'Prepaid Express Satchel +sig\',\'Prepaid Express Satchel Insured +sig\',\'Prepaid Express Satchel Insured (no sig)\'), ',
                now())") ;

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
            VALUES ('Handling Fee - Regular parcels', 'MODULE_SHIPPING_AUPOST_RPP_HANDLING', '2.00', 'Handling Fee Regular parcels', '6', '6', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
            VALUES ('Handling Fee - Prepaid Satchels', 'MODULE_SHIPPING_AUPOST_PPS_HANDLING', '2.00', 'Handling Fee for Prepaid Satchels.', '6', '7', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
            VALUES ('Handling Fee - Prepaid Satchels - Express', 'MODULE_SHIPPING_AUPOST_PPSE_HANDLING', '2.00', 'Handling Fee for Prepaid Express Satchels.', '6', '8', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
            VALUES ('Handling Fee - Express parcels', 'MODULE_SHIPPING_AUPOST_EXP_HANDLING', '2.00', 'Handling Fee for Express parcels.', '6', '9', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)    
            VALUES ('Hide Handling Fees?', 'MODULE_SHIPPING_AUPOST_HIDE_HANDLING', 'No', 'The handling fees are still in the total shipping cost but the Handling Fee is not itemised on the invoice.', '6', '16', 'zen_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
            VALUES ('Default Product /Parcel Dimensions', 'MODULE_SHIPPING_AUPOST_DIMS', '10,10,2', 'Default Product /Parcel dimensions (in cm). Three comma separated values (eg 10,10,2 = 10cm x 10cm x 2cm). These are used if the dimensions of individual products are not set', '6', '40', now())");
        
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
            VALUES ('Cost on Error', 'MODULE_SHIPPING_AUPOST_COST_ON_ERROR', '99.99', 'If an error occurs this Flat Rate fee will be used. If TBA is entered an error msg will be displayed on the postage rate and Zero value postage displayed.</br> A value of zero will disable this module on error.', '6', '20', now())");
        /*    
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
            VALUES ('Cost on Error', 'MODULE_SHIPPING_AUPOST_COST_ON_ERROR', '99', 'If an error occurs this Flat Rate fee will be used.</br> A value of zero will disable this module on error.', '6', '20', now())");
        */
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
            VALUES ('Parcel Weight format', 'MODULE_SHIPPING_AUPOST_WEIGHT_FORMAT', 'gms', 'Are your store items weighted by grams or Kilos? (required so that we can pass the correct weight to the server).', '6', '25', 'zen_cfg_select_option(array(\'gms\', \'kgs\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
            VALUES ('Show AusPost logo?', 'MODULE_SHIPPING_AUPOST_ICONS', 'Yes', 'Show Auspost logo in place of text?', '6', '19', 'zen_cfg_select_option(array(\'No\', \'Yes\'), ', now())");
            
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
            VALUES ('Enable Debug?', 'MODULE_SHIPPING_AUPOST_DEBUG', 'No', 'See how parcels are created from individual items.</br>Shows all methods returned by the server, including possible errors. <strong>Do not enable in a production environment</strong>', '6', '40', 'zen_cfg_select_option(array(\'No\', \'Yes\'), ', now())");
            
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
            VALUES ('Tare percent.', 'MODULE_SHIPPING_AUPOST_TARE', '10', 'Add this percentage of the items total weight as the tare weight. (This module ignores the global settings that seems to confuse many users. 10% seems to work pretty well.).', '6', '50', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
            VALUES ('Sort order of display.', 'MODULE_SHIPPING_AUPOST_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '55', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) 
            VALUES ('Tax Class', 'MODULE_SHIPPING_AUPOST_TAX_CLASS', '1', 'Set Tax class or -none- if not registered for GST.', '6', '60', 'zen_get_tax_class_title', 'zen_cfg_pull_down_tax_classes(', now())");
        // eof parcels

        /////////////////////////  update tables //////

        $inst = 1 ;
        $sql = "show fields from " . TABLE_PRODUCTS;
        $result = $db->Execute($sql);
        while (!$result->EOF) {
          if  ($result->fields['Field'] == 'products_length') {
           unset($inst) ;
              break;
          }
          $result->MoveNext();
        }

        if(isset($inst)) {
          //  echo "new" ;
            $db->Execute("ALTER TABLE " .TABLE_PRODUCTS. " ADD `products_length` FLOAT(6,2) NULL AFTER `products_weight`, ADD `products_height` FLOAT(6,2) NULL AFTER `products_length`, ADD `products_width` FLOAT(6,2) NULL AFTER `products_height`" ) ;
        }
        else
        {
          //  echo "update" ;
            $db->Execute("ALTER TABLE " .TABLE_PRODUCTS. " CHANGE `products_length` `products_length` FLOAT(6,2), CHANGE `products_height` `products_height` FLOAT(6,2), CHANGE `products_width`  `products_width`  FLOAT(6,2)" ) ;
        }
    }       // eof install

    // // BMH removal of module in admin
    public function remove() // 
    {
        global $db;
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE_SHIPPING_AUPOST_%' ");
    }
    
    //  //  // order of options loaded into admin-shipping
    public function keys()  // 
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
            'MODULE_SHIPPING_AUPOST_TAX_CLASS'
        );
    }
///////////////////////////////////////////////////////////////////////////////////////////
}  // end class
