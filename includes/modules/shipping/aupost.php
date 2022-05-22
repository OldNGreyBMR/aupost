<?php
/*
  Original Copyright (c) 2007-2009 Rod Gasson / VCSWEB
 
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

 $Id: aupost.php,v2.4.1 May 2022
 V2.4.1_0516A

  BMH 2022-02-13    line 23 declare constants
                    line 154 abort if NOT AU address
                    heavily modded return codes to remove zero values and eliminate potential duplicate postage rates with alternative names
                    line 606 corrected AusPost URL
                    updated coding standards tabs => 4 spaces; { etc
    2022-04-01    rechecked all codes returned from Aus Post
                    separated out 2nd level debug WITH Constant BMHDEBUG1
    2022-05-06    use variables for url and API key to allow test mode
                    3rd level debug BMHDEBUG2
                    MySQL keywords changed to uppercase , VALUES, INSERT INTO
                    check for XML on install
    2022-05-14      Letters all reg priority express
*/
// BMHDEBUG switches
define('BMHDEBUG1','Yes'); // BMH 2nd level debug to display all returned data from Aus Post // No or Yes
define('BMHDEBUG2','No'); // BMH 3nd level debug to display all returned XML data from Aus Post // No or Yes
// **********************

//BMH declare constants
if (!defined('MODULE_SHIPPING_AUPOST_TAX_CLASS')) { define('MODULE_SHIPPING_AUPOST_TAX_CLASS',''); } // line 66
if (!defined('MODULE_SHIPPING_AUPOST_TYPES1')) { define('MODULE_SHIPPING_AUPOST_TYPES1',''); } // line 74
if (!defined('MODULE_SHIPPING_AUPOST_TYPE_LETTERS')) { define('MODULE_SHIPPING_AUPOST_TYPE_LETTERS',''); }

if (!defined('MODULE_SHIPPING_AUPOST_HIDE_PARCEL')) { define('MODULE_SHIPPING_AUPOST_HIDE_PARCEL',''); }  // line 302
if (!defined('MODULE_SHIPPING_AUPOST_CORE_WEIGHT')) { define('MODULE_SHIPPING_AUPOST_CORE_WEIGHT',''); } // line 398

if (!defined('MODULE_SHIPPING_AUPOST_STATUS')) { define('MODULE_SHIPPING_AUPOST_STATUS',''); }
if (!defined('MODULE_SHIPPING_AUPOST_SORT_ORDER')) { define('MODULE_SHIPPING_AUPOST_SORT_ORDER',''); }
if (!defined('MODULE_SHIPPING_AUPOST_ICONS')) { define('MODULE_SHIPPING_AUPOST_ICONS',''); }


// +++++++++++++++++++++++++++++
define('AUPOST_MODE','Test'); //Test OR PROD
// **********************

// ++++++++++++++++++++++++++
if (!defined('MODULE_SHIPPING_AUPOST_AUTHKEY')) { define('MODULE_SHIPPING_AUPOST_AUTHKEY','') ;}
if (!defined('AUPOST_TESTMODE_AUTHKEY')) { define('AUPOST_TESTMODE_AUTHKEY','28744ed5982391881611cca6cf5c240') ;} // DO NOT CHANGE
define('AUPOST_URL_TEST','test.npe.auspost.com.au'); // No longer used - leave as prod url
define('AUPOST_URL_PROD','digitalapi.auspost.com.au');
define('LETTER_URL_STRING','/postage/letter/domestic/service.xml?'); // 
define('PARCEL_URL_STRING','/postage/parcel/domestic/service.xml?from_postcode='); // 

// set product variables
$aupost_url_string = AUPOST_URL_PROD ;
// BMH DEBUG echo 'line 64 $aupost_url_string = ' . $aupost_url_string;
$aupost_url_apiKey = MODULE_SHIPPING_AUPOST_AUTHKEY;
$lettersize = 0; //set flag for letters

        if (BMHDEBUG2 == "Yes") {
                //echo ' <br>line65 MODE= ' . AUPOST_MODE . ' //$aupost_url_string = ' .$aupost_url_string . ' aupost_url_apiKey= ' . $aupost_url_apiKey ;
        } 
/// if test mode replace with test variables - url + api key // move to line 343 inside function quote(method)
if (AUPOST_MODE == 'Test') { 
    $aupost_url_string = AUPOST_URL_TEST ;
    $aupost_url_apiKey = AUPOST_TESTMODE_AUTHKEY;
}
        if (BMHDEBUG2 == "Yes") {
            //    echo '<br>line73 MODE= ' . AUPOST_MODE . ' aupost_url_apiKey= ' . $aupost_url_apiKey ;
        } 

// class constructor

class aupost extends base
{

    // Declare shipping module alias code
   var $code;
   // Shipping module display name
   var $title;
    // Shipping module display description
    var $description;
    // Shipping module icon filename/path
   var $icon;
    // Shipping module status
   var $enabled;

    function __construct()
    {
        global $order, $db, $template ;

        // disable only when entire cart is free shipping
        if (zen_get_shipping_enabled($this->code))  $this->enabled = ((MODULE_SHIPPING_AUPOST_STATUS == 'True') ? true : false);

        $this->code = 'aupost';
        $this->title = MODULE_SHIPPING_AUPOST_TEXT_TITLE ;
        $this->description = MODULE_SHIPPING_AUPOST_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_SHIPPING_AUPOST_SORT_ORDER;
        $this->icon = $template->get_template_dir('aupost.jpg', '' ,'','images/icons'). '/aupost.jpg';
        if (zen_not_null($this->icon)) $this->quotes['icon'] = zen_image($this->icon, $this->title);
        $this->logo = $template->get_template_dir('aupost_logo.jpg', '','' ,'images/icons'). '/aupost_logo.jpg';
        $this->tax_class = MODULE_SHIPPING_AUPOST_TAX_CLASS;
        $this->tax_basis = 'Shipping' ;    // It'll always work this way, regardless of any global settings

        if (MODULE_SHIPPING_AUPOST_ICONS != "No" ) {
            if (zen_not_null($this->logo)) $this->title = zen_image($this->logo, $this->title) ;
        }
        // get letter and parcel methods defined
        $this->allowed_methods_l = explode(", ", MODULE_SHIPPING_AUPOST_TYPE_LETTERS); // BMH
        $this->allowed_methods = explode(", ", MODULE_SHIPPING_AUPOST_TYPES1) ;
        $this->allowed_methods = $this->allowed_methods + $this->allowed_methods_l;  // BMH combine letters + parcels into one methods list
    }

    // class methods
    //////////////////////////////////////////////////////////////

    function quote($method = '')
    {
        global $db, $order, $cart, $currencies, $template, $parcelweight, $packageitems;
    //    $module = substr($_SESSION['shipping'], 0,6);
    //    $method = substr($_SESSION['shipping'],7);
    // removed misguided attempt to retrieve user selection from session.
    // method argument is supplied to this module by Zen Cart if required (single quote).
    // see later comments on removing underscores from AusPost-defined shipping methods.

        if (zen_not_null($method) && (isset($_SESSION['aupostQuotes']))) {
            $testmethod = $_SESSION['aupostQuotes']['methods'] ;

            foreach($testmethod as $temp) {
                $search = array_search("$method", $temp) ;
                if (strlen($search) > 0 && $search >= 0) break ; 
            }

            $usemod = $this->title ; 
            $usetitle = $temp['title'] ;
            if (MODULE_SHIPPING_AUPOST_ICONS != "No" ) {  // strip the icons //
                if (preg_match('/(title)=("[^"]*")/',$this->title, $module))  $usemod = trim($module[2], "\"") ;
                if (preg_match('/(title)=("[^"]*")/',$temp['title'], $title)) $usetitle = trim($title[2], "\"") ;
            }

             $this->quotes = array
            (
                'id' => $this->code,
                'module' => $usemod,
                'methods' => array
                (
                    array
                    (
                        'id' => $method,
                        'title' => $usetitle,
                        'cost' =>  $temp['cost']
                    )
                )
            );

            if ($this->tax_class >  0) {
                $this->quotes['tax'] = zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
            }
            // echo ' aupost line 129 single quote ' . implode(', ',$this->quotes); //BMH DEBUG
            return $this->quotes;   // return a single quote
        }  ////////////////////////////  Single Quote Exit Point //////////////////////////////////
        
/////// LETTERS
    // BMH DEBUG echo ' <br> line174 MODULE_SHIPPING_AUPOST_TYPE_LETTERS= '. MODULE_SHIPPING_AUPOST_TYPE_LETTERS;
    if (MODULE_SHIPPING_AUPOST_TYPE_LETTERS  <> null) {
        
        $MAXLETTERFOLDSIZE = 15;                        // mm for edge of envelope
        $MAXLETTERPACKINGDIM = 3;                       // mm thickness of packing. Letter max height is 20mm including packing
        $MAXWEIGHT_L = 500 ;                            // 500g
        $MAXLENGTH_L = (360 - $MAXLETTERFOLDSIZE);      // 360mm max letter length  less fold size on edges
        $MAXWIDTH_L =  (260 - $MAXLETTERFOLDSIZE);      // 260mm max letter width  less fold size on edges
        $MAXHEIGHT_L = (20 - $MAXLETTERPACKINGDIM);     // 20mm max letter height LESS packing thickness
        $MAXHEIGHT_L_SM = 5;                            // 5mm max small letter height
        $MAXLENGTH_L_SM = (240 - $MAXLETTERFOLDSIZE);   // 240mm
        $MAXWIDTH_L_SM = (130 - $MAXLETTERFOLDSIZE);    // 130mm
        $MAXWEIGHT_L_WT1 = 125;                         // weight 125
        $MAXWEIGHT_L_WT2 = 250;                         //
        $MAXWEIGHT_L_WT3 = 500;                         //
        $MSGLETTERTRACKING =  " (No tracking)";          // label append
        
        // initialise variables  
        $letterwidth = 0 ;
        $letterwidthcheck = 0 ;
        $letterwidthchecksmall = 0 ;
        $letterlength = 0 ;
        $letterlengthcheck = 0 ;
        $letterlengthchecksmall = 0 ;
        $letterheight = 0 ;
        $letterheightcheck = 0 ;
        $letterheightchecksmall = 0 ;
        $letterweight = 0 ;
        $lettercube = 0 ;
        $letterchecksmall = 0 ;
        $lettercheck = 0 ;
        $lettersmall = 0;
        $letterlargewt1 = 0;
        $letterlargewt2 = 0;
        $letterlargewt3 = 0;
    }
    // EOF LETTERS
    
    // PARCELS
      // Maximums - parcels
        $MAXWEIGHT_P = 22 ;     // BMH change from 20 to 22kg 2021-10-07
        $MAXLENGTH_P = 105 ;    // 105cm max parcel length
        $MAXGIRTH_P =  140 ;    // 140cm max parcel girth  ( (width + height) * 2)

        // default dimensions   // parcels
        $x = explode(',', MODULE_SHIPPING_AUPOST_DIMS) ;
        $defaultdims = array($x[0],$x[1],$x[2]) ;
        sort($defaultdims) ;    // length[2]. width[1], height=[0]

        // initialise  variables // parcels
        $parcelwidth = 0 ;
        $parcellength = 0 ;
        $parcelheight = 0 ;
        $parcelweight = 0 ;
        $cube = 0 ;

        $frompcode = MODULE_SHIPPING_AUPOST_SPCODE;
        $dest_country=$order->delivery['country']['iso_code_2'];
        $topcode = str_replace(" ","",($order->delivery['postcode']));
        $aus_rate = (float)$currencies->get_value('AUD') ;
        $ordervalue=$order->info['total'] / $aus_rate ;                 // total cost for insurance
        $tare = MODULE_SHIPPING_AUPOST_TARE ;                           // percentage to add for packing etc
        // BMH Only proceed for AU addresses
        if ($dest_country <> "AU") {
         return $this->quotes ;     // BMH exit if NOT AU
        } 
         
        $FlatText = " Using AusPost Flat Rate." ;

        // loop through cart extracting productIDs and qtys //
        $myorder = $_SESSION['cart']->get_products();
     
        for($x = 0 ; $x < count($myorder) ; $x++ ) {
            $producttitle = $myorder[$x]['id'] ; 
            $q = $myorder[$x]['quantity'];
            $w = $myorder[$x]['weight'];
     
            $dim_query = "select products_length, products_height, products_width from " . TABLE_PRODUCTS . " where products_id='$producttitle' limit 1 ";
            $dims = $db->Execute($dim_query);

            // re-orientate //
            $var = array($dims->fields['products_width'], $dims->fields['products_height'], $dims->fields['products_length']) ; sort($var) ;
            $dims->fields['products_length'] = $var[2] ; $dims->fields['products_width'] = $var[1] ;  $dims->fields['products_height'] = $var[0] ;
            // if no dimensions provided use the defaults
            if($dims->fields['products_height'] == 0) {$dims->fields['products_height'] = $defaultdims[0] ; }
            if($dims->fields['products_width']  == 0) {$dims->fields['products_width']  = $defaultdims[1] ; }
            if($dims->fields['products_length'] == 0) {$dims->fields['products_length'] = $defaultdims[2] ; }
            if($w == 0) {$w = 1 ; }  // 1 gram minimum
            
            $parcelweight += $w * $q;
            
            // get the cube of these items
            $itemcube =  ($dims->fields['products_width'] * $dims->fields['products_height'] * $dims->fields['products_length'] * $q) ;
            // Increase widths and length of parcel as needed
            if ($dims->fields['products_width'] >  $parcelwidth)  { $parcelwidth  = $dims->fields['products_width']  ; }
            if ($dims->fields['products_length'] > $parcellength) { $parcellength = $dims->fields['products_length'] ; }
            // Stack on top on existing items
            $parcelheight =  ($dims->fields['products_height'] * ($q)) + $parcelheight  ;
            $packageitems =  $packageitems + $q ;
            
// /////////////////////// LETTERS //////////////////////////////////
            // BMH for letter dimensions 
            // letter height for starters
            $letterheight = $parcelheight *10;      // letters are in mm
            
            if (($letterheight ) <= $MAXHEIGHT_L ){ 
                $letterheightcheck = 1;             // maybe can be sent as letter by height limit
                $lettercheck = 1;
                // check letter height small
                if (($letterheight) <= $MAXHEIGHT_L_SM ) {
                    $letterheightchecksmall = 1;
                    $letterchecksmall = 1;
                     // BMH DEBUG echo '<br> ln286 $letterlengthcheckSmall=' . $letterlengthcheckSmall;
                }
                // report letter length
                // BMH DEBUG echo '<br> ln292 parcellength *10= ' . $parcellength *10 ; 
                // BMH DEBUG echo '<br> ln293 $MAXLENGTH_L_SM =' . $MAXLENGTH_L_SM ;
                
                // letter length in range for small
                $letterlength = ($parcellength *10);
                if ($letterlength < $MAXLENGTH_L_SM ) {
                    $letterlengthchecksmall = 1;
                    $letterchecksmall = $letterchecksmall + 1;
                     // BMH DEBUG echo '<br> ln300 $letterlengthchecksmall=' . $letterlengthchecksmall;
                }
                // BMH DEBUG echo '<br> ln302 $MAXLENGTH_L =' . $MAXLENGTH_L . ' $letterlength=' . $letterlength;
                // check letter length in range
                if (($letterlength  > $MAXLENGTH_L_SM ) || ($letterlength <= $MAXLENGTH_L ) ) {
                    $letterlengthcheck = 1;
                    $lettercheck = $lettercheck + 1;
                    // BMH DEBUG echo '<br> ln307 $letterlengthcheck=' . $letterlengthcheck . ' $lettercheck=' . $lettercheck . ' $letterlength=' . $letterlength;
                }
                // letter width in range
                $letterwidth = $parcelwidth *10;
                if ($letterwidth < $MAXWIDTH_L_SM ) {
                    $letterwidthchecksmall = 1;
                    $letterchecksmall = $letterchecksmall + 1;
                     // BMH DEBUG echo '<br> ln311 $letterwidthchecksmall=' . $letterwidthchecksmall . '$letterchecksmall=' . $letterchecksmall ;
                }
                
                // BMH DEBUG echo '<br> ln311 $parcelwidth *10 =' . ($parcelwidth * 10) . ' $MAXWIDTH_L =' . $MAXWIDTH_L . ' $MAXWIDTH_L_SM =' . $MAXWIDTH_L_SM ;

                if (($letterwidth > $MAXWIDTH_L_SM ) || (($parcelwidth *10) <= $MAXWIDTH_L) ) { 
                    $letterwidthcheck = 1;
                    $lettercheck = $lettercheck + 1;
                    // BMH DEBUG echo '<br> ln318 $letterwidthcheck=' . $letterwidthcheck . ' $lettercheck=' . $lettercheck ;
                } 
                
                // check letter weight // in grams
                // BMH DEBUG echo '<br> ln326 $w=' . $w . ' $parcelweight=' . $parcelweight . ' $tare=' . $tare;
                $letterweight = ($parcelweight + ($parcelweight* $tare/100));
                // BMH DEBUG echo '<br> ln328 $letterweight = ' . $letterweight;
                if ((($letterweight ) <= $MAXWEIGHT_L_WT1 ) && ($letterchecksmall == 3) ){ 
                    $lettersmall = 1;
                    // BMH DEBUG echo '<br> ln 324 $lettersmall =' . $lettersmall;
                }
                // BMH DEBUG echo '<br> ln326 $parcelweight=' . $parcelweight . ' $lettercheck=' . $lettercheck . ' $MAXWEIGHT_L_WT1=' . $MAXWEIGHT_L_WT1;
                if ((($letterweight ) <= $MAXWEIGHT_L_WT1 ) && ($lettercheck == 3) ) { 
                    $letterlargewt1 = 1;
                    // BMH DEBUG echo '<br> ln329 $letterlargewt1 =' . $letterlargewt1;
                }
                if  (($letterweight  >= $MAXWEIGHT_L_WT1 ) && ($letterweight <= $MAXWEIGHT_L_WT2 ) && ($lettercheck == 3)  ) { 
                    $letterlargewt2 = 1;
                    // BMH DEBUG echo '<br> ln333 $letterlargewt2 ='. $letterlargewt2;
                }
               
                //   
                // BMH DEBUG display the letter values ';
                if (BMHDEBUG2 == "Yes") {
                    echo ' <br> ln338 $lettercheck=' . $lettercheck . ' $letterchecksmall=' . $letterchecksmall .
                         ' $letterlengthcheck = ' . $letterlengthcheck . ' $letterwidthcheck = ' . $letterwidthcheck . ' $letterheightcheck=' . $letterheightcheck;
                    if ($letterchecksmall == 3) {
                        echo ' <br> ln 345 it is a  s letter';
                    }
                    if ($lettercheck == 3) {
                        echo ' <br> ln 348 it is a  large letter';
                    }
                    if ($letterlargewt1 == 1){
                        echo ' <br> ln 351 it is a  large letter(125g)';
                    }
                    if ($letterlargewt2 == 1){
                        echo ' <br> ln 354 it is a  large letter(250g)';
                    }
                   if ($letterlargewt3 == 1){
                        echo ' <br> ln357 it is a  large letter(500g)';
                    }
                }
                $aupost_url_string = AUPOST_URL_PROD;
                // $aupost_url_string = AUPOST_URL_TEST;
                // BMH DEBUG echo ' $aupost_url_string =' . $aupost_url_string ;
                if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMHDEBUG2 == "Yes")) {
                    echo '<br> line 360 ' .'https://' . $aupost_url_string . LETTER_URL_STRING . "length=$letterlength&width=$letterwidth&thickness=$letterheight&weight=$letterweight" ; 
                }
                
                $quL = $this->get_auspost_api( 
                    'https://' . $aupost_url_string . LETTER_URL_STRING . "length=$letterlength&width=$letterwidth&thickness=$letterheight&weight=$letterweight") ; 
                // BMH DEBUG echo '<br> ln 366 $quL= ' . $quL;
                // If we have any results, parse them into an array   
                $xmlquote_letter = ($quL == '') ? array() : new SimpleXMLElement($quL)  ;
        
                if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMHDEBUG2 == "Yes")) {
                    echo "<div ><strong>>> Server Returned - LETTERS BMHDEBUG1+2 line 369 << </strong><textarea rows=50 cols=100 style=\"margin:0;\"> ";
                    print_r($xmlquote_letter) ; // exit ; // ORIG DEBUG to output api xml // BMH DEBUG
                    echo "</textarea><div>";
                }
                if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMHDEBUG2 == "Yes")) { 
                    echo "<table><tr><td><b>auPost - Server Returned BMHDEBUG2 line377 LETTERS:</b><br>" . $quL . "</td></tr></table>" ; 
                }
        
            // ======================================
            //  loop through the LETTER quotes retrieved //

                $i = 0 ;  // counter
                foreach($xmlquote_letter as $foo => $bar) {    
                    //BMH keep API code for label
                    $code = ($xmlquote_letter->service[$i]->code); $code = str_replace("_", " ", $code); $code = substr($code,11);
                 //echo ' <br> ln384 $code=' . $code;
                    $id = str_replace("_", "", $xmlquote_letter->service[$i]->code);
                    // remove underscores from AusPost methods. Zen Cart uses underscore as delimiter between module and method.
                    // underscores must also be removed from case statements below.

                    $cost = (float)($xmlquote_letter->service[$i]->price);
                    $description =  ($code) . " ". ($xmlquote_letter->service[$i]->name); // BMH append name to code
                    //$descx = $description; $description = "LETTER " . $descx;
                    $description =  "LETTER " . $code; // BMH Prepend LETTER to CODE to differentiate from Parcels code
                    $description =  $description . $MSGLETTERTRACKING; // BMH append no tracking to letters
                    $i++ ;

                if (( MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes"))  { 
                    echo "<table><tr><td>" ; echo " LETTER ID= $id COST= $cost DESC= $description" ; echo "</td></tr></table>" ; 
                    } // BMH 2nd level debug each line of quote parsed

                    $add = 0 ; $f = 0 ; $info=0 ;
                  
                switch ($id) {

                    case  "AUSLETTEREXPRESSSMALL" ;
                    case  "AUSLETTEREXPRESSMEDIUM" ;
                    case  "AUSLETTEREXPRESSLLARGE" ;
                        if ((in_array("Aust Express", $this->allowed_methods_l)))
                        { 
                            $add = MODULE_SHIPPING_AUPOST_LETTER_EXPRESS_HANDLING ; $f = 1 ;
                        }
                        break;
                     
                    case  "AUSLETTERPRIORITYSMALL" ;
                    case  "AUSLETTERPRIORITYLARGE125" ;
                    case  "AUSLETTERPRIORITYLARGE250" ;
                    case  "AUSLETTERPRIORITYLARGE500" ;
                        if ((in_array("Aust Priority", $this->allowed_methods_l)))
                        {
                            $add =  MODULE_SHIPPING_AUPOST_LETTER_PRIORITY_HANDLING ; $f = 1 ;
                        }
                        break;
                        
                    case  "AUSLETTERREGULARSMALL";  // normal mail - own packaging
                    case  "AUSLETTERREGULARLARGE125";  // normal mail - own packaging
                    case  "AUSLETTERREGULARLARGE250";
                    case  "AUSLETTERREGULARLARGE500";               
                        if (in_array("Aust Standard", $this->allowed_methods_l))
                        {
                            $add = MODULE_SHIPPING_AUPOST_LETTER_HANDLING ; $f = 1 ;
                        }
                        break;
                         
                    case  "AUSLETTERSIZEDL"; // This requires purchase of Aus Post packaging
                    case  "AUSLETTERSIZEC6"; // This requires purchase of Aus Post packaging
                    case  "AUSLETTERSIZEC5"; // This requires purchase of Aus Post packaging
                    case  "AUSLETTERSIZEC5"; // This requires purchase of Aus Post packaging
                    case  "AUSLETTERSIZEC4"; // This requires purchase of Aus Post packaging
                    case  "AUSLETTERSIZEB4"; // This requires purchase of Aus Post packaging
                    case  "AUSLETTERSIZEOTH"; // This requires purchase of Aus Post packaging
                        $cost = 0;$f=0; 
                        // echo "shouldn't be here"; //BMH debug
                        //do nothing - ignore the code
                        break;

                    }  // end switch
                    
                    //// list options and all values debug mode 
                    // BMH DEBUG echo '<br> ln448 $cost='. $cost . '$f=' .$f . ' MODULE_SHIPPING_AUPOST_DEBUG=' . MODULE_SHIPPING_AUPOST_DEBUG;
                    if ((($cost > 0) && ($f == 1)) && ( MODULE_SHIPPING_AUPOST_DEBUG == "Yes" )) { //BMH DEBUG
                        $cost = $cost + $add ;
                        
                        //if ( MODULE_SHIPPING_AUPOST_CORE_WEIGHT == "Yes") { $cost = $cost * $shipping_num_boxes ; }

                    /// GST (tax) included in all prices in Aust
                        if (($dest_country == "AU") && (($this->tax_class) > 0)) {
                            $t = $cost - ($cost / (zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])+1)) ;
                            
                            if ($t > 0) $cost = $t ;
                        }
                        
                        if  (MODULE_SHIPPING_AUPOST_HIDE_HANDLING !='Yes') {
                            $details = ' (Includes ' . $currencies->format($add / $aus_rate ). ' Packaging &amp; Handling ';

                            if ($info > 0)  {
                                $details = $details." +$".$info." fee)." ;

                            }  else {$details = $details.")" ;}
                        }
                        // BMH DEBUG echo 'ln466' . $aus_rate;
                        $cost = $cost / $aus_rate;

                        $methods[] = array('id' => "$id",  'title' => " ".$description . " " . $details, 'cost' => ($cost ));
                        // BMH DEBUG echo '<br> ln469 $methods[]=' ; var_dump($methods);
                    } // eof debug listing

                    //////// only list valid options without debug info // BMH
                    if ((($cost > 0) && ($f == 1)) && ( MODULE_SHIPPING_AUPOST_DEBUG == "No" )) { //BMH DEBUG = ONLY if not debug mode
                        $cost = $cost + $add ;
                        // if ( MODULE_SHIPPING_AUPOST_CORE_WEIGHT == "Yes") { $cost = $cost * $shipping_num_boxes ; }

                        // GST (tax) included in all prices in Aust
                        if (($dest_country == "AU") && (($this->tax_class) > 0)) {
                            $t = $cost - ($cost / (zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])+1)) ;
                            
                            if ($t > 0) $cost = $t ;
                        }
                        
                        if  (MODULE_SHIPPING_AUPOST_HIDE_HANDLING !='Yes') {
                            $details = ' (Includes ' . $currencies->format($add / $aus_rate ). ' Packaging &amp; Handling ';

                            if ($info > 0)  {
                                $details = $details." +$".$info." fee)." ;
                            }  else {$details = $details.")" ;}
                        }

                        $cost = $cost / $aus_rate;
                        $methods[] = array('id' => "$id",  'title' => " ".$description . " " . $details, 'cost' => ($cost ));
                    }  // end display output

                }  // end foreach loop
                
                //  check to ensure we have at least one valid LETTER quote - produce error message if not.
                if  (sizeof($methods) == 0) {
                    $cost = $this->_get_error_cost($dest_country) ; // retrieve default rate
                
                   if ($cost == 0)  return  ;
                   $methods[] = array( 'id' => "Error",  'title' =>MODULE_SHIPPING_AUPOST_TEXT_ERROR ,'cost' => $cost ) ;
                }
                
                //  Sort by cost //    
                $sarray[] = array() ;
                $resultarr = array() ;

                foreach($methods as $key => $value) {
                    $sarray[ $key ] = $value['cost'] ;
                }
                asort( $sarray ) ;
                // BMH DEBUG echo '<br>  ln 512 var_dump($sarray)' ; var_dump($sarray);

                //foreach($sarray as $key => $value)
                //{
                 //   $resultarr[ $key ] = $methods[ $key ] ;
                //}
                // BMH remove zero values from postage options
                foreach ($sarray as $key => $value) { 
                
                    if ($value == 0 ) { 
                    }
                    else 
                    {
                    $resultarr[ $key ] = $methods [ $key ] ;
                    }
                } // BMH eof remove zero values
            //
             // BMH DEBUG echo '<br> ln528 sort array '; var_dump($resultarr); // BMH DEBUG
            
                $this->quotes['methods'] = array_values($resultarr) ;   // set it
                
                // return $this->quotes;   //  all done // BMH DEBUG only if letters only
            }
        
            //// EOF LETTERS /////////
            
            
            // Useful debugging information // in formatted table display
            if ( MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) {
                $dim_query = "select products_name from " . TABLE_PRODUCTS_DESCRIPTION . " where products_id='$producttitle' limit 1 ";
                $name = $db->Execute($dim_query);

                echo "<center><table border=1 width=95% ><th colspan=8>Debugging information</hr>
                <tr><th>Item " . ($x + 1) . "</th><td colspan=7>" . $name->fields['products_name'] . "</td>
                <tr><th width=1%>Attribute</th><th colspan=3>Item</th><th colspan=4>Parcel</th></tr>
                <tr><th>Qty</th><td>&nbsp; " . $q . "<th>Weight</th><td>&nbsp; " . $w . "</td>
                <th>Qty</th><td>&nbsp;$packageitems</td><th>Weight</th><td>&nbsp;" ; echo $parcelweight + (($parcelweight* $tare)/100) ; echo "</td></tr>
                <tr><th>Dimensions</th><td colspan=3>&nbsp; " . $dims->fields['products_length'] . " x " . $dims->fields['products_width'] . " x "  . $dims->fields['products_height'] . "</td>
                <td colspan=4>&nbsp;$parcellength  x  $parcelwidth  x $parcelheight </td></tr>
                <tr><th>Cube</th><td colspan=3>&nbsp; " . $itemcube . "</td><td colspan=4>&nbsp;" . ($parcelheight * $parcellength * $parcelwidth) . " </td></tr>
                <tr><th>CubicWeight</th><td colspan=3>&nbsp;" . (($dims->fields['products_length'] * $dims->fields['products_height'] * $dims->fields['products_width']) * 0.00001 * 250) . "Kgs  </td><td colspan=4>&nbsp;" . (($parcelheight * $parcellength * $parcelwidth) * 0.00001 * 250) . "Kgs </td></tr>
                </table></center> " ;
            }
        }

       

        // package created, now re-orientate and check dimensions
        $var = array($parcelheight, $parcellength, $parcelwidth) ; sort($var) ;
        $parcelheight = $var[0] ; $parcelwidth = $var[1] ; $parcellength = $var[2] ;
        $girth = ($parcelheight * 2) + ($parcelwidth * 2)  ;

        $parcelweight = $parcelweight + (($parcelweight*$tare)/100) ;

        if (MODULE_SHIPPING_AUPOST_WEIGHT_FORMAT == "gms") {$parcelweight = $parcelweight/1000 ; }

        //  save dimensions for display purposes on quote form (this way I don't need to hack another system file)
        $_SESSION['swidth'] = $parcelwidth ; $_SESSION['sheight'] = $parcelheight ;
        $_SESSION['slength'] = $parcellength ;  // $_SESSION['boxes'] = $shipping_num_boxes ;

        // Check for maximum length allowed
        if($parcellength > $MAXLENGTH_P) {
             $cost = $this->_get_error_cost($dest_country) ;

           if ($cost == 0) return  ;
       
            $methods[] = array('title' => ' (AusPost excess length)', 'cost' => $cost ) ;
            $this->quotes['methods'] = $methods;   // set it
            return $this->quotes;
        }  // exceeds AustPost maximum length. No point in continuing.

       // Check girth
        if($girth > $MAXGIRTH_P ) {
             $cost = $this->_get_error_cost($dest_country) ;
           if ($cost == 0)  return  ;
          
            $methods[] = array('title' => ' (AusPost excess girth)', 'cost' => $cost ) ;
            $this->quotes['methods'] = $methods;   // set it
            return $this->quotes;
        }  // exceeds AustPost maximum girth. No point in continuing.

        if ($parcelweight > $MAXWEIGHT_P) {
             $cost = $this->_get_error_cost($dest_country) ;
           if ($cost == 0)  return  ;
          
            $methods[] = array('title' => ' (AusPost excess weight)', 'cost' => $cost ) ;
            $this->quotes['methods'] = $methods;   // set it
            return $this->quotes;
        }  // exceeds AustPost maximum weight. No point in continuing.

        // Check to see if cache is useful
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
                if ( MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) {
                    echo "<center><table border=1 width=95% ><td align=center><font color=\"#FF0000\">Using Cached quotes </font></td></table></center>" ;
                }
                
            $this->quotes =  $_SESSION['aupostQuotes'] ;
        return $this->quotes ;

    ///////////////////////////////////  Cache Exit Point //////////////////////////////////

            } // No cache match -  get new quote from server //

        }  // No cache session -  get new quote from server //
    ///////////////////////////////////////////////////////////////////////////////////////////////

        // always save new session  CSV //
        $_SESSION['aupostParcel'] = implode(",", array($dest_country, $topcode, $parcelwidth, $parcelheight, $parcellength, $parcelweight, $ordervalue)) ;
        $shipping_weight = $parcelweight ;  // global value for zencart

        // convert cm to mm 'cos thats what the server uses // No uses mm only for letters Cm for parcels
        $parcelwidth = $parcelwidth ;
        $parcelheight = $parcelheight ;
        $parcellength = $parcellength ;

        // Set destination code ( postcode if AU, else 2 char iso country code )
        $dcode = ($dest_country == "AU") ? $topcode:$dest_country ;

        if (!$dcode) $dcode =  SHIPPING_ORIGIN_ZIP ; // if no destination postcode - (aka first run - zencart only), set to local (cheapest rates)

        $flags = ((MODULE_SHIPPING_AUPOST_HIDE_PARCEL == "No") || ( MODULE_SHIPPING_AUPOST_DEBUG == "Yes" )) ? 0:1 ;

        // Server query string //
        
        $aupost_url_string = AUPOST_URL_PROD ;
        // if test mode replace with test variables - url + api key
        if (AUPOST_MODE == 'Test') { 
            //$aupost_url_string = AUPOST_URL_TEST ; Aus Post say to use production servers
            $aupost_url_apiKey = AUPOST_TESTMODE_AUTHKEY;
        }
        if (BMHDEBUG2 == "Yes") { 
            // BMH DEBUG echo '<br>line 500 $aupost_url_string= ' . $aupost_url_string;
            echo '<br> line 501 ' .'https://' . $aupost_url_string . PARCEL_URL_STRING . MODULE_SHIPPING_AUPOST_SPCODE . "&to_postcode=$dcode&length=$parcellength&width=$parcelwidth&height=$parcelheight&weight=$parcelweight" ; 
       }

        // BMH DEBUG echo '<br> ln 540 get parcel api';
        $qu = $this->get_auspost_api( 
       'https://' . $aupost_url_string . PARCEL_URL_STRING . MODULE_SHIPPING_AUPOST_SPCODE . "&to_postcode=$dcode&length=$parcellength&width=$parcelwidth&height=$parcelheight&weight=$parcelweight") ; 
       
       // BMH prev code"https://digitalapi.auspost.com.au/postage/parcel/domestic/service.xml?from_postcode=" . MODULE_SHIPPING_AUPOST_SPCODE . "&to_postcode=$dcode&length=$parcellength&width=$parcelwidth&height=$parcelheight&weight=$parcelweight") ; 
       //if (BMHDEBUG2 == "Yes") { 
        //    echo '<br>line 391 $qu= <br>' .$qu;
        //    echo '<br> string= ' ."https://" . $aupost_url_string . PARCEL_URL_STRING . MODULE_SHIPPING_AUPOST_SPCODE . "&to_postcode=$dcode&length=$parcellength&width=$parcelwidth&height=$parcelheight&weight=$parcelweight" ; 
       //}
        if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG2 == "Yes")) { echo "<table><tr><td><b>auPost - Server Returned BMHDEBUG2 line515:</b><br>" . $qu . "</td></tr></table>" ; }

        // If we have any results, parse them into an array   
        $xml = ($qu == '') ? array() : new SimpleXMLElement($qu)  ;
        
        if ((MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes") && (BMHDEBUG2 == "Yes")) {
            echo "<div ><strong>>> Server Returned BMHDEBUG1+2 line 521 << </strong><textarea rows=50 cols=100 style=\"margin:0;\"> ";
            print_r($xml) ; // exit ; // ORIG DEBUG to output api xml // BMH DEBUG
        	echo "</textarea><div>";
		}
        /////  Initialise our quote array(s) 
        // BMH DEBUG arrays methods with combined methods
       /*
        $this->quotes = array('id' => $this->code, 'module' => $this->title);
        $methods = array() ;
        */

    ///////////////////////////////////////
    //
    //  loop through the Parcel quotes retrieved //

        $i = 0 ;  // counter
        foreach($xml as $foo => $bar) {    
            //BMH keep API code for label
            $code = ($xml->service[$i]->code); $code = str_replace("_", " ", $code); $code = substr($code,11);
         
            $id = str_replace("_", "", $xml->service[$i]->code);
        // remove underscores from AusPost methods. Zen Cart uses underscore as delimiter between module and method.
        // underscores must also be removed from case statements below.

            $cost = (float)($xml->service[$i]->price);
            $description =  ($code) . " ". ($xml->service[$i]->name); // BMH append name to code
            $description =  ($code) ; // BMH append name to code
            
            $i++ ;

        if (( MODULE_SHIPPING_AUPOST_DEBUG == "Yes" ) && (BMHDEBUG1 == "Yes"))  { echo "<table><tr><td>" ;  echo "ID= $id COST= $cost DESC= $description" ; echo "</td></tr></table>" ; } // BMH 2nd level debug each line of quote parsed

            $add = 0 ; $f = 0 ; $info=0 ;
          
        switch ($id) {

            case  "AUSPARCELREGULARSATCHELEXTRALARGE" ;
            case  "AUSPARCELREGULARSATCHELLARGE" ;
            case  "AUSPARCELREGULARSATCHELMEDIUM" ;
            case  "AUSPARCELREGULARSATCHELSMALL" ;
                if ((in_array("Prepaid Satchel", $this->allowed_methods)))
                { 
                    $add = MODULE_SHIPPING_AUPOST_PPS_HANDLING ; $f = 1 ;
                }
                break;
                
            case  "AUSPARCELEXPRESSSATCHEL5KG" ;
            case  "AUSPARCELEXPRESSSATCHELLARGE" ;
            case  "AUSPARCELEXPRESSSATCHELMEDIUM" ;
            case  "AUSPARCELEXPRESSSATCHELSMALL" ;
                if ((in_array("Prepaid Express Satchel", $this->allowed_methods)))
                {
                    $add =  MODULE_SHIPPING_AUPOST_PPSE_HANDLING ; $f = 1 ;
                }
                break;
                
           
            case  "AUSPARCELREGULAR"; // normal mail - own packaging
                if (in_array("Regular Parcel", $this->allowed_methods))
                {
                    $add = MODULE_SHIPPING_AUPOST_RPP_HANDLING ; $f = 1 ;
                }
                break;
                
                case  "REG" ;
                if (in_array("Registered Parcel", $this->allowed_methods))
                {
                    $add =  MODULE_SHIPPING_AUPOST_RPP_HANDLING + MODULE_SHIPPING_AUPOST_RI_HANDLING ; $f = 1 ; $info = $xml->information[0]->registration ;
                }
                break;
                
            case  "AUSPARCELEXPRESS" ;
            case  "AUSPARCELEXPRESSPACKAGEMEDIUM" ;
                if (in_array("Express Parcel", $this->allowed_methods)) 
                {
                    $add = MODULE_SHIPPING_AUPOST_EXP_HANDLING ; $f = 1 ;
                }
                break;
            
            //case  "AUSPARCELPLATINUM" ; v
            //     if (in_array("Express Post Platinum Parcel", $this->allowed_methods))
            //    {
            //        $add = MODULE_SHIPPING_AUPOST_PLAT_HANDLING ; $f = 1 ;
            //    }
            //    break;
            //    
            //case  "AUSPARCELPLATINUMSATCHEL5KG" ; NOT AVAILABLE 2022-05-06
            //case  "AUSPARCELPLATINUMSATCHEL3KG" ;
            //case  "AUSPARCELPLATINUMSATCHEL500G" ;
            //    if ((in_array("Express Post Platinum Satchel", $this->allowed_methods)))
            //    {
            //        $add = MODULE_SHIPPING_AUPOST_PLATSATCH_HANDLING ; $f = 1 ;
            //    }
            //    break;
                
            case  "AUSPARCELEXPRESSSATCHEL1KG" ;
            case  "AUSPARCELEXPRESSSATCHEL500G";
            case  "AUSPARCELREGULARSATCHEL5KG" ;
            case  "AUSPARCELREGULARSATCHEL3KG" ; // superceded by AUSPARCELREGULARSATCHELLARGE
            case  "AUSPARCELREGULARSATCHEL1KG" ;
            case  "AUSPARCELREGULARSATCHEL500G";
            case  "AUSPARCELEXPRESSPACKAGESMALL"; // This is cheaper but requires purchase of Aus Post packaging
            case  "AUSPARCELREGULARPACKAGESMALL"; // This is cheaper but requires purchase of Aus Post packaging
                $cost = 0;$f=0; 
                // echo "shouldn't be here"; //BMH debug
                //do nothing - ignore the code
                break;
            }
                    
            ////////////////////////////// list options and all values
            if ((($cost > 0) && ($f == 1)) && ( MODULE_SHIPPING_AUPOST_DEBUG == "Yes" )) { //BMH DEBUG
                $cost = $cost + $add ;
            if ( MODULE_SHIPPING_AUPOST_CORE_WEIGHT == "Yes") { $cost = $cost * $shipping_num_boxes ; }

                if (($dest_country == "AU") && (($this->tax_class) > 0)) {
                    // BMH DEBUG echo '<br> ln797 tax_class=' . $this->tax_class;
                    $t = $cost - ($cost / (zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])+1)) ;

                    if ($t > 0) $cost = $t ;
                }
                if  (MODULE_SHIPPING_AUPOST_HIDE_HANDLING !='Yes') {
                    $details = ' (Includes ' . $currencies->format($add / $aus_rate ). ' Packaging &amp; Handling ';

                    if ($info > 0)  {
                        $details = $details." +$".$info." fee)." ;

                    }  else {$details = $details.")" ;}

                }

                $cost = $cost / $aus_rate;

                $methods[] = array('id' => "$id",  'title' => " ".$description . " " . $details, 'cost' => ($cost ));
            }

    //////////////////////////////////////////// only list valid options without debug info // BMH
            if ((($cost > 0) && ($f == 1)) && ( MODULE_SHIPPING_AUPOST_DEBUG == "No" )) { //BMH DEBUG = ONLY if not debug mode
                        $cost = $cost + $add ;
                    if ( MODULE_SHIPPING_AUPOST_CORE_WEIGHT == "Yes") { $cost = $cost * $shipping_num_boxes ; }

                        if (($dest_country == "AU") && (($this->tax_class) > 0)) {
                            $t = $cost - ($cost / (zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])+1)) ;

                            if ($t > 0) $cost = $t ;
                        }
                        if  (MODULE_SHIPPING_AUPOST_HIDE_HANDLING !='Yes') {
                            $details = ' (Includes ' . $currencies->format($add / $aus_rate ). ' Packaging &amp; Handling ';

                            if ($info > 0)  {
                                $details = $details." +$".$info." fee)." ;

                            }  else {$details = $details.")" ;}

                        }

                        $cost = $cost / $aus_rate;

                        $methods[] = array('id' => "$id",  'title' => " ".$description . " " . $details, 'cost' => ($cost ));
                    }

        }  // end foreach loop

    ///////////////////////////////////////////////////////////////////////
    //
    //  check to ensure we have at least one valid quote - produce error message if not.
        if  (sizeof($methods) == 0) {                       // no valid methods
            $cost = $this->_get_error_cost($dest_country) ; // give default cost
            if ($cost == 0)  return  ;                      // 

           $methods[] = array( 'id' => "Error",  'title' =>MODULE_SHIPPING_AUPOST_TEXT_ERROR ,'cost' => $cost ) ; // display reason
        }


        //  Sort by cost //    
        $sarray[] = array() ;
        $resultarr = array() ;

        foreach($methods as $key => $value) {
            $sarray[ $key ] = $value['cost'] ;
        }
        asort( $sarray ) ;

        foreach($sarray as $key => $value)
        //{
         //   $resultarr[ $key ] = $methods[ $key ] ;
        //}
        // BMH remove zero values from postage options
        foreach ($sarray as $key => $value) { 
            if ($value == 0 ) { 
            }
            else 
            {
            $resultarr[ $key ] = $methods [ $key ] ;
            }
        } // BMH eof remove zero values

      $this->quotes['methods'] = array_values($resultarr) ;   // set it

        if ($this->tax_class >  0) {
            $this->quotes['tax'] = zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
        }
        
    $_SESSION['aupostQuotes'] = $this->quotes  ; // save as session to avoid reprocessing when single method required
         
    return $this->quotes;   //  all done //

    ///////////////////////////////////  Final Exit Point //////////////////////////////////
    }

    function _get_error_cost($dest_country) 
    {
        $x = explode(',', MODULE_SHIPPING_AUPOST_COST_ON_ERROR) ;

            unset($_SESSION['aupostParcel']) ;  // don't cache errors.
            $cost = $dest_country == "AU" ?  $x[0]:$x[1] ;

                if ($cost == 0) {
                $this->enabled = FALSE ;
                unset($_SESSION['aupostQuotes']) ;
                }
            else 
            {  
            $this->quotes = array('id' => $this->code, 'module' => 'Flat Rate'); 
            }

        return $cost;
    }
    
    ////////////////////////////////////////////////////////////////
    // BMH - parts for admin module 
    function check()
    {
        global $db;
        if (!isset($this->_check)) {
            $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_AUPOST_STATUS'");
            $this->_check = $check_query->RecordCount();
        }
        return $this->_check;
    }
    ////////////////////////////////////////////////////////////////////////////
    function install()
    {
        global $db;
        // check for XML // BMH
        if (!class_exists('SimpleXMLElement')) {
			$messageStack->add_session(
			'Installation FAILED. Ozpost requires SimpleXMLElement to be installed on the system '
		);
		echo "This module requires SimpleXMLElement to work. Most Webhosts will support this.<br>Installation will NOT continue.<br>Press your back-page to continue ";
        exit;
		}
        
          $result = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'SHIPPING_ORIGIN_ZIP'"  ) ;
          $pcode = $result->fields['configuration_value'] ;
          
        if (!$pcode) $pcode = "4121" ;  

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) 
            VALUES ('Enable this module?', 'MODULE_SHIPPING_AUPOST_STATUS', 'True', 'Enable this Module', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");          
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
            VALUES ('Auspost API Key:', 'MODULE_SHIPPING_AUPOST_AUTHKEY', '', 'To use this module, you must obtain a 36 digit API Key from the <a href=\"https:\\developers.auspost.com.au\" target=\"_blank\">Auspost Development Centre</a>', '6', '2', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
            VALUES ('Dispatch Postcode', 'MODULE_SHIPPING_AUPOST_SPCODE', $pcode, 'Dispatch Postcode?', '6', '2', now())");
    // BMH LETTERS

        $db->Execute(
				"insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function,  date_added)
                  VALUES ('</div><hr> <div>AustPost Letters (and small parcels@letter rates)', 'MODULE_SHIPPING_AUPOST_TYPE_LETTERS',
                            'Aust Standard, Aust Priority, Aust Express',
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
             'MODULE_SHIPPING_AUPOST_LETTER_PRIORITY_HANDLING', '2.50', 'Handling Fee for Priority letters.', '6', '13', now())"
        );    
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
             VALUES ('Handling Fee - Express Letters',
             'MODULE_SHIPPING_AUPOST_LETTER_EXPRESS_HANDLING', '2.50', 'Handling Fee for Express letters.', '6', '13', now())"
        );    
    // BMH END LETTERS
    // PARCELS
        $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
        VALUES ('Shipping Methods for Australia', 'MODULE_SHIPPING_AUPOST_TYPES1', 'Regular Parcel, Registered Parcel, Express Parcel, , Prepaid Satchel, Prepaid Express Satchel',
                            'Select the methods you wish to allow', '6', '3',
                            'zen_cfg_select_multioption(array(\'Regular Parcel\',\'Express Parcel\',\'Prepaid Satchel\',\'Prepaid Express Satchel\'), ',
                            now())"
                ) ;

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
            VALUES ('Handling Fee - Regular parcels', 'MODULE_SHIPPING_AUPOST_RPP_HANDLING', '0.00', 'Handling Fee Regular parcels', '6', '6', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) 
            VALUES ('Handling Fee - Prepaid Satchels', 'MODULE_SHIPPING_AUPOST_PPS_HANDLING', '0.00', 'Handling Fee for Prepaid Satchels.', '6', '7', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Handling Fee - Prepaid Satchels - Express', 'MODULE_SHIPPING_AUPOST_PPSE_HANDLING', '0.00', 'Handling Fee for Prepaid Express Satchels.', '6', '8', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Handling Fee - Express parcels', 'MODULE_SHIPPING_AUPOST_EXP_HANDLING', '0.00', 'Handling Fee for Express parcels.', '6', '9', now())");
        //$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Handling Fee - Platinum parcels', 'MODULE_SHIPPING_AUPOST_PLAT_HANDLING', '0.00', 'Handling Fee for Platinum parcels.', '6', '10', now())");
        //$db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Handling Fee - Platinum Satchels', 'MODULE_SHIPPING_AUPOST_PLATSATCH_HANDLING', '0.00', 'Handling Fee for Platinum Satchels.', '6', '11', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Hide Handling Fees?', 'MODULE_SHIPPING_AUPOST_HIDE_HANDLING', 'Yes', 'The handling fees are still in the total shipping cost but the Handling Fee is not itemised on the invoice.', '6', '16', 'zen_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Default Parcel Dimensions', 'MODULE_SHIPPING_AUPOST_DIMS', '10,10,2', 'Default Parcel dimensions (in cm). Three comma separated values (eg 10,10,2 = 10cm x 10cm x 2cm). These are used if the dimensions of individual products are not set', '6', '40', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Cost on Error', 'MODULE_SHIPPING_AUPOST_COST_ON_ERROR', '25', 'If an error occurs this Flat Rate fee will be used.</br> A value of zero will disable this module on error.', '6', '20', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Parcel Weight format', 'MODULE_SHIPPING_AUPOST_WEIGHT_FORMAT', 'gms', 'Are your store items weighted by grams or Kilos? (required so that we can pass the correct weight to the server).', '6', '25', 'zen_cfg_select_option(array(\'gms\', \'kgs\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Show AusPost logo?', 'MODULE_SHIPPING_AUPOST_ICONS', 'Yes', 'Show Auspost logo in place of text?', '6', '19', 'zen_cfg_select_option(array(\'No\', \'Yes\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable Debug?', 'MODULE_SHIPPING_AUPOST_DEBUG', 'No', 'See how parcels are created from individual items.</br>Shows all methods returned by the server, including possible errors. <strong>Do not enable in a production environment</strong>', '6', '40', 'zen_cfg_select_option(array(\'No\', \'Yes\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Tare percent.', 'MODULE_SHIPPING_AUPOST_TARE', '10', 'Add this percentage of the items total weight as the tare weight. (This module ignores the global settings that seems to confuse many users. 10% seems to work pretty well.).', '6', '50', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Sort order of display.', 'MODULE_SHIPPING_AUPOST_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '55', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) VALUES ('Tax Class', 'MODULE_SHIPPING_AUPOST_TAX_CLASS', '0', 'Set Tax class or -none- if not registered for GST.', '6', '60', 'zen_get_tax_class_title', 'zen_cfg_pull_down_tax_classes(', now())");

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

    }
    // BMH removal of module in admin
    function remove()
    {
        global $db;
        $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE_SHIPPING_AUPOST_%' ");
    }
        // BMH order of options loaded into admin-shipping
    function keys()
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

    //// auspost API
    function get_auspost_api($url)
    {
         // BMH DEBUG echo '<br> ln1052 AUPOST_MODE=' . AUPOST_MODE;
        If (AUPOST_MODE == 'Test') {
            $aupost_url_apiKey = AUPOST_TESTMODE_AUTHKEY;
            }
            else {
            $aupost_url_apiKey = MODULE_SHIPPING_AUPOST_AUTHKEY;
            }
        if (BMHDEBUG2 == "Yes") {
            // BMH DEBUG echo '<br> ln1065 get_auspost_api $url= ' . $url;
            echo '<br> ln1066 $aupost_url_apiKey= ' . $aupost_url_apiKey;
        }
    $crl = curl_init();
    $timeout = 5;
    // BMH old curl_setopt ($crl, CURLOPT_HTTPHEADER, array('AUTH-KEY:' . MODULE_SHIPPING_AUPOST_AUTHKEY)); // BMH changed to allow test key
    curl_setopt ($crl, CURLOPT_HTTPHEADER, array('AUTH-KEY:' . $aupost_url_apiKey)); // BMH new
    curl_setopt ($crl, CURLOPT_URL, $url);
    curl_setopt ($crl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt ($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
    $ret = curl_exec($crl);
    // Check the response: if the body is empty then an error occurred
    /*if (BMHDEBUG2 == "Yes") {
            echo '<br> ln1078 $ret= ' . $ret;
    }
    */
    if(!$ret){
        die('Error: "' . curl_error($crl) . '" - Code: ' . curl_errno($crl));
    }
    
    curl_close($crl);
    return $ret;
    }
    // end auspost API

}  // end class
