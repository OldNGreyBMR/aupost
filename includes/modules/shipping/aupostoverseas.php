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

 $Id: overseasaupost.php,v2.4  April 2022

*/ 
/* BMH 2022-01-30 
            Line 26    define  MODULE_SHIPPING_OVERSEASAUPOST_HIDE_PARCEL
            line 144   process only international destinations
            line 488   correct URL for AusPost API
    BMH 2022-04-01  line 196    Undefined array key "products_weight"
                    line 405    changed logic for debug so invalid options are not included in final list
                    separated out 2nd level debug WITH Constant BMHDEBUGI
*/
if (!defined('MODULE_SHIPPING_OVERSEASAUPOST_HIDE_PARCEL')) { define('MODULE_SHIPPING_OVERSEASAUPOST_HIDE_PARCEL',''); } // BMH line 294
if (!defined('MODULE_SHIPPING_OVERSEASAUPOST_TAX_CLASS')) { define('MODULE_SHIPPING_OVERSEASAUPOST_TAX_CLASS',''); }
if (!defined('MODULE_SHIPPING_OVERSEASAUPOST_TYPES1')) { define('MODULE_SHIPPING_OVERSEASAUPOST_TYPES1',''); }

if (!defined('MODULE_SHIPPING_OVERSEASAUPOST_STATUS')) { define('MODULE_SHIPPING_OVERSEASAUPOST_STATUS',''); }
if (!defined('MODULE_SHIPPING_OVERSEASAUPOST_SORT_ORDER')) { define('MODULE_SHIPPING_OVERSEASAUPOST_SORT_ORDER',''); }
if (!defined('MODULE_SHIPPING_OVERSEASAUPOST_ICONS')) { define('MODULE_SHIPPING_OVERSEASAUPOST_ICONS',''); }
if (!defined('MODULE_SHIPPING_OVERSEASAUPOST_TAX_CLASS')) { define('MODULE_SHIPPING_OVERSEASAUPOST_TAX_CLASS',''); }

/*
define('MODULE_SHIPPING_OVERSEASAUPOST_STATUS','');
define('MODULE_SHIPPING_OVERSEASAUPOST_SORT_ORDER','');
define('MODULE_SHIPPING_OVERSEASAUPOST_ICONS','');
define('MODULE_SHIPPING_OVERSEASAUPOST_TAX_CLASS','');
define('MODULE_SHIPPING_OVERSEASAUPOST_TAX_CLASS','');
*/
define('BMHDEBUGI',"No"); // BMH 2nd level debug to display all returned data from Aus Post

// class constructor

class aupostoverseas extends base
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
    if (zen_get_shipping_enabled($this->code))  $this->enabled = ((MODULE_SHIPPING_OVERSEASAUPOST_STATUS == 'True') ? true : false);


    $this->code = 'aupostoverseas';
    $this->title = MODULE_SHIPPING_OVERSEASAUPOST_TEXT_TITLE ;
    $this->description = MODULE_SHIPPING_OVERSEASAUPOST_TEXT_DESCRIPTION;
    $this->sort_order = MODULE_SHIPPING_OVERSEASAUPOST_SORT_ORDER;
    $this->icon = $template->get_template_dir('aupost.jpg', '' ,'','images/icons'). '/aupost.jpg';
    if (zen_not_null($this->icon)) $this->quotes['icon'] = zen_image($this->icon, $this->title);
    $this->logo = $template->get_template_dir('aupost_logo.jpg', '','' ,'images/icons'). '/aupost_logo.jpg';
    $this->tax_class = MODULE_SHIPPING_OVERSEASAUPOST_TAX_CLASS;
    $this->tax_basis = 'Shipping' ;    // It'll always work this way, regardless of any global settings

     if (MODULE_SHIPPING_OVERSEASAUPOST_ICONS != "No" )
	 {
        if (zen_not_null($this->logo)) $this->title = zen_image($this->logo, $this->title) ;
    }

    $this->allowed_methods = explode(", ", MODULE_SHIPPING_OVERSEASAUPOST_TYPES1) ;
}

// class methods
function quote($method = '')
{
    global $db, $order, $cart, $currencies, $template, $parcelweight, $packageitems;
	
    if (zen_not_null($method) && (isset($_SESSION['overseasaupostQuotes'])))
    {
        $testmethod = $_SESSION['overseasaupostQuotes']['methods'] ;

       foreach($testmethod as $temp) {
            $search = array_search("$method", $temp) ;

            if (strlen($search) > 0 && $search >= 0) break ;
            }

        $usemod = $this->title ; $usetitle = $temp['title'] ;

        if (MODULE_SHIPPING_OVERSEASAUPOST_ICONS != "No" )
        {  // strip the icons //
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

        if ($this->tax_class >  0)
        {
            $this->quotes['tax'] = zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
        }

        return $this->quotes;   // return a single quote

    }  ////////////////////////////  Single Quote Exit Point //////////////////////////////////

  // Maximums
    $MAXWEIGHT_P = 22 ; // BMH was 20kgs - now 22kgs
    $MAXLENGTH_P = 105 ;  // 105cm max parcel length
    $MAXGIRTH_P =  140 ;  // 140cm max parcel girth  ( (width + height) * 2)

    // default dimensions //
    $x = explode(',', MODULE_SHIPPING_OVERSEASAUPOST_DIMS) ;
    $defaultdims = array($x[0],$x[1],$x[2]) ;
    sort($defaultdims) ;  // length[2]. width[1], height=[0]

    // initialise variables
    $parcelwidth = 0 ;
    $parcellength = 0 ;
    $parcelheight = 0 ;
    $parcelweight = 0 ;
    $cube = 0 ;

    $frompcode = MODULE_SHIPPING_OVERSEASAUPOST_SPCODE;
    $dest_country=$order->delivery['country']['iso_code_2'];
	
	if ($dest_country == "AU") {
	 return $this->quotes ;} // BMH exit if AU
	
    $topcode = str_replace(" ","",($order->delivery['postcode']));
    $aus_rate = (float)$currencies->get_value('AUD') ;
    $ordervalue=$order->info['total'] / $aus_rate ;
    $tare = MODULE_SHIPPING_OVERSEASAUPOST_TARE ;

      $FlatText = " Using AusPost Flat Rate." ;

    // loop through cart extracting productIDs and qtys //

    $myorder = $_SESSION['cart']->get_products();
 
    for($x = 0 ; $x < count($myorder) ; $x++ )
    {
        $t = $myorder[$x]['id'] ;
        $q = $myorder[$x]['quantity'];
		$w = $myorder[$x]['weight'];
 
        $dim_query = "select products_length, products_height, products_width from " . TABLE_PRODUCTS . " where products_id='$t' limit 1 ";
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

        // Useful debugging information //
        if ( MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "Yes" )
        {
            $dim_query = "select products_name from " . TABLE_PRODUCTS_DESCRIPTION . " where products_id='$t' limit 1 ";
            $name = $db->Execute($dim_query); // BMH Undefined array key "products_weight"

            echo "<center><table border=1 width=95% ><th colspan=8>Debugging information</hr>
            <tr><th>Item " . ($x + 1) . "</th><td colspan=7>" . $name->fields['products_name'] . "</td>
            <tr><th width=1%>Attribute</th><th colspan=3>Item</th><th colspan=4>Parcel</th></tr>
            <tr><th>Qty</th><td>&nbsp; " . $q . "<th>Weight</th><td>&nbsp; " . ($dims->fields['products_weight'] ?? '') . "</td>
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

    if (MODULE_SHIPPING_OVERSEASAUPOST_WEIGHT_FORMAT == "gms") {$parcelweight = $parcelweight/1000 ; }

//  save dimensions for display purposes on quote form (this way I don't need to hack another system file)
$_SESSION['swidth'] = $parcelwidth ; $_SESSION['sheight'] = $parcelheight ;
$_SESSION['slength'] = $parcellength ;  // $_SESSION['boxes'] = $shipping_num_boxes ;

    // Check for maximum length allowed
    if($parcellength > $MAXLENGTH_P)
    {
         $cost = $this->_get_error_cost($dest_country) ;

       if ($cost == 0) return  ;
   
        $methods[] = array('title' => ' (AusPost excess length)', 'cost' => $cost ) ;
        $this->quotes['methods'] = $methods;   // set it
        return $this->quotes;
    }  // exceeds AustPost maximum length. No point in continuing.



   // Check girth
    if($girth > $MAXGIRTH_P )
    {
         $cost = $this->_get_error_cost($dest_country) ;

       if ($cost == 0)  return  ;
      
        $methods[] = array('title' => ' (AusPost excess girth)', 'cost' => $cost ) ;
        $this->quotes['methods'] = $methods;   // set it
        return $this->quotes;
    }  // exceeds AustPost maximum girth. No point in continuing.



    if ($parcelweight > $MAXWEIGHT_P)
    {
         $cost = $this->_get_error_cost($dest_country) ;

       if ($cost == 0)  return  ;
      
        $methods[] = array('title' => ' (AusPost excess weight)', 'cost' => $cost ) ;
        $this->quotes['methods'] = $methods;   // set it
        return $this->quotes;
    }  // exceeds AustPost maximum weight. No point in continuing.

    // Check to see if cache is useful
    if(isset($_SESSION['overseasaupostParcel']))
    {
        $test = explode(",", $_SESSION['overseasaupostParcel']) ;

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
            if ( MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "Yes" )
            {
                echo "<center><table border=1 width=95% ><td align=center><font color=\"#FF0000\">Using Cached quotes </font></td></table></center>" ;
            }


$this->quotes =  $_SESSION['overseasaupostQuotes'] ;
return $this->quotes ;

///////////////////////////////////  Cache Exit Point //////////////////////////////////

        } // No cache match -  get new quote from server //

    }  // No cache session -  get new quote from server //
///////////////////////////////////////////////////////////////////////////////////////////////

    // always save new session  CSV //
    $_SESSION['overseasaupostParcel'] = implode(",", array($dest_country, $topcode, $parcelwidth, $parcelheight, $parcellength, $parcelweight, $ordervalue)) ;
    $shipping_weight = $parcelweight ;  // global value for zencart

    // convert cm to mm 'cos thats what the server uses //
    $parcelwidth = $parcelwidth ;
    $parcelheight = $parcelheight ;
    $parcellength = $parcellength ;

    // Set destination code ( postcode if AU, else 2 char iso country code )
    $dcode = ($dest_country == "AU") ? $topcode:$dest_country ;

    $flags = ((MODULE_SHIPPING_OVERSEASAUPOST_HIDE_PARCEL == "No") || ( MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "Yes" )) ? 0:1 ;

    // Server query string //
	$qu = $this->get_auspost_api("https://digitalapi.auspost.com.au/postage/parcel/international/service.xml?country_code=$dcode&weight=$parcelweight");

    if ((MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "Yes" ) && (BMHDEBUGI == "Yes")) { echo "<table><tr><td>Server Returned:<br>" . $qu . "</td></tr></table>" ; }

    // If we have any results, parse them into an array   
    $xml = ($qu == '') ? array() : new SimpleXMLElement($qu)  ;

 // print_r($xml) ; exit ;
    /////  Initialise our quote array(s)
    $this->quotes = array('id' => $this->code, 'module' => $this->title);
    $methods = array() ;
	

///////////////////////////////////////
//
//  loop through the quotes retrieved //

    $i = 0 ;  // counter
    foreach($xml as $foo => $bar)
    {
        $id = str_replace("_", "", $xml->service[$i]->code);
	// remove underscores from AusPost methods. Zen Cart uses underscore as delimiter between module and method.
	// underscores must also be removed from case statements below.
        $cost = (float)($xml->service[$i]->price);
        $description = ($xml->service[$i]->name);
        $i++ ;

if ((MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "Yes" ) && (BMHDEBUGI == "Yes")) { echo "<table><tr><td>" ; echo "ID $id COST $cost DESC $description" ; echo "</td></tr></table>" ; }

        $add = 0 ; $f = 0 ; $info=0 ;
		
	switch ($id) {
		
		case  "INTPARCELAIROWNPACKAGING" ;
        if (in_array("Economy Air Mail", $this->allowed_methods))
        {
            $add = MODULE_SHIPPING_OVERSEASAUPOST_AIRMAIL_HANDLING ; $f = 1 ;
        }
		break;
		
		case  "INTPARCELSEAOWNPACKAGING" ;
        if (in_array("Sea Mail", $this->allowed_methods))
        {
            $add =  MODULE_SHIPPING_OVERSEASAUPOST_SEAMAIL_HANDLING ; $f = 1 ; 
        }
		break;
        
		case  "INTPARCELSTDOWNPACKAGING" ;
        if (in_array("Standard Post International", $this->allowed_methods))     
        {
            $add = MODULE_SHIPPING_OVERSEASAUPOST_STANDARD_HANDLING ; $f = 1 ;
        }
		break;

		case  "INTPARCELEXPOWNPACKAGING" ;
        if (in_array("Express Post International", $this->allowed_methods))     
        {
            $add = MODULE_SHIPPING_OVERSEASAUPOST_EXPRESS_HANDLING ; $f = 1 ;
        }
		break;
		
		case  "INTPARCELCOROWNPACKAGING" ;
		if (in_array("Courier International", $this->allowed_methods))     
        {
            $add = MODULE_SHIPPING_OVERSEASAUPOST_COURIER_HANDLING ; $f = 1 ;
        } 
        
		break;
	}

        ////////////////////////////// list options and all values
        if ((($cost > 0) && ($f == 1)) && ( MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "Yes" )) //BMH DEBUG
        {
            $cost = $cost + $add ;

            if (($dest_country == "AU") && (($this->tax_class) > 0))
            {
                $t = $cost - ($cost / (zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])+1)) ;

                if ($t > 0) $cost = $t ;
            }
            if  (MODULE_SHIPPING_OVERSEASAUPOST_HIDE_HANDLING !='Yes')
            {
                $details = ' (Includes ' . $currencies->format($add / $aus_rate ). ' Packaging &amp; Handling ';

                if ($info > 0)  {
                    $details = $details." +$".$info." fee)." ;

                }  else {$details = $details.")" ;}


            }

            $cost = $cost / $aus_rate;

            $methods[] = array('id' => "$id",  'title' => " ".$description . " " . $details, 'cost' => ($cost ));
        }
        //////////////////////// only list valid options without debug info BMH
        if ((($cost > 0) && ($f == 1)) && ( MODULE_SHIPPING_OVERSEASAUPOST_DEBUG == "No" )) // BMH DEBUG = ONLY if not debug mode
        {
            $cost = $cost + $add ;

            if (($dest_country == "AU") && (($this->tax_class) > 0))
            {
                $t = $cost - ($cost / (zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id'])+1)) ;

                if ($t > 0) $cost = $t ;
            }
            if  (MODULE_SHIPPING_OVERSEASAUPOST_HIDE_HANDLING !='Yes')
            {
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

    if  (sizeof($methods) == 0) {

        $cost = $this->_get_error_cost($dest_country) ;

       if ($cost == 0)  return  ;

       $methods[] = array( 'id' => "Error",  'title' => MODULE_SHIPPING_OVERSEASAUPOST_TEXT_ERROR ,'cost' => $cost ) ;
    }


    //  Sort by cost //
    $sarray[] = array() ;
    $resultarr = array() ;

    foreach($methods as $key => $value)
    {
		$sarray[ $key ] = $value['cost'] ;
    }
    asort( $sarray ) ;

    foreach($sarray as $key => $value)
    {
        $resultarr[ $key ] = $methods[ $key ] ;
	}

	$this->quotes['methods'] = array_values($resultarr) ;   // set it

    if ($this->tax_class >  0)
    {
        $this->quotes['tax'] = zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
   
    }

 
$_SESSION['overseasaupostQuotes'] = $this->quotes  ; // save as session to avoid reprocessing when single method required

return $this->quotes;   //  all done //

   ///////////////////////////////////  Final Exit Point //////////////////////////////////
}

function _get_error_cost($dest_country) {
	
 $x = explode(',', MODULE_SHIPPING_OVERSEASAUPOST_COST_ON_ERROR) ;

        unset($_SESSION['overseasaupostParcel']) ;  // don't cache errors.
        $cost = $dest_country != "AU" ?  $x[0]:$x[1] ;

            if ($cost == 0) {
            $this->enabled = FALSE ;
            unset($_SESSION['overseasaupostQuotes']) ;
            }
	    else 
	    {  
		$this->quotes = array('id' => $this->code, 'module' => 'Flat Rate'); 
	    }

    return $cost;
    }
////////////////////////////////////////////////////////////////
function check()
{
    global $db;
    if (!isset($this->_check))
    {
        $check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_SHIPPING_OVERSEASAUPOST_STATUS'");
        $this->_check = $check_query->RecordCount();
    }
    return $this->_check;
}
////////////////////////////////////////////////////////////////////////////
function install()
{
    global $db;

      $result = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'SHIPPING_ORIGIN_ZIP'"  ) ;
      $pcode = $result->fields['configuration_value'] ;
      
	if (!$pcode) $pcode = "2000" ;  

    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable this module?', 'MODULE_SHIPPING_OVERSEASAUPOST_STATUS', 'True', 'Enable this Module', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");																																																																																															  $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Auspost API Key:', 'MODULE_SHIPPING_OVERSEASAUPOST_AUTHKEY', '', 'To use this module, you must obtain a 36 digit API Key from the <a href=\"https:\\developers.auspost.com.au\" target=\"_blank\">Auspost Development Centre</a>', '6', '2', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Dispatch Postcode', 'MODULE_SHIPPING_OVERSEASAUPOST_SPCODE', $pcode, 'Dispatch Postcode?', '6', '3', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title,
                          configuration_key,
                          configuration_value,
                          configuration_description,
                          configuration_group_id,
                          sort_order,
                          set_function,
                          date_added)

                    values ('Shipping Methods for Overseas',
                            'MODULE_SHIPPING_OVERSEASAUPOST_TYPES1',
                            'Economy Air Mail, Sea Mail, Standard Post International, Express Post International, Courier International',
                            'Select the methods you wish to allow',
                            '6',
                            '3',
                            'zen_cfg_select_multioption(array(\'Economy Air Mail\',\'Sea Mail\',\'Standard Post International\',\'Express Post International\',\'Courier International\'), ',
                            now())"
                ) ;


    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Handling Fee - Economy Air Mail', 'MODULE_SHIPPING_OVERSEASAUPOST_AIRMAIL_HANDLING', '0.00', 'Handling Fee for Economy Air Mail.', '6', '6', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Handling Fee - Sea Mail', 'MODULE_SHIPPING_OVERSEASAUPOST_SEAMAIL_HANDLING', '0.00', 'Handling Fee for Sea Mail.', '6', '7', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Handling Fee - Standard Post International', 'MODULE_SHIPPING_OVERSEASAUPOST_STANDARD_HANDLING', '0.00', 'Handling Fee for Standard Post International.', '6', '8', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Handling Fee - Express Post International', 'MODULE_SHIPPING_OVERSEASAUPOST_EXPRESS_HANDLING', '0.00', 'Handling Fee for Express Post International.', '6', '9', now())");
	$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Handling Fee - Courier International', 'MODULE_SHIPPING_OVERSEASAUPOST_COURIER_HANDLING', '0.00', 'Handling Fee for Courier International.', '6', '10', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Hide Handling Fees?', 'MODULE_SHIPPING_OVERSEASAUPOST_HIDE_HANDLING', 'Yes', 'The handling fees are still in the total shipping cost but the Handling Fee is not itemised on the invoice.', '6', '16', 'zen_cfg_select_option(array(\'Yes\', \'No\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Default Parcel Dimensions', 'MODULE_SHIPPING_OVERSEASAUPOST_DIMS', '10,10,2', 'Default Parcel dimensions (in cm). Three comma seperated values (eg 10,10,2 = 10cm x 10cm x 2cm). These are used if the dimensions of individual products are not set', '6', '40', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Cost on Error', 'MODULE_SHIPPING_OVERSEASAUPOST_COST_ON_ERROR', '75', 'If an error occurs this Flat Rate fee will be used.</br> A value of zero will disable this module on error.', '6', '20', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Parcel Weight format', 'MODULE_SHIPPING_OVERSEASAUPOST_WEIGHT_FORMAT', 'kgs', 'Are your store items weighted by grams or Kilos? (required so that we can pass the correct weight to the server).', '6', '25', 'zen_cfg_select_option(array(\'gms\', \'kgs\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Show AusPost logo?', 'MODULE_SHIPPING_OVERSEASAUPOST_ICONS', 'Yes', 'Show Auspost logo in place of text?', '6', '19', 'zen_cfg_select_option(array(\'No\', \'Yes\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable Debug?', 'MODULE_SHIPPING_OVERSEASAUPOST_DEBUG', 'No', 'See how parcels are created from individual items.</br>Shows all methods returned by the server, including possible errors. <strong>Do not enable in a production environment</strong>', '6', '40', 'zen_cfg_select_option(array(\'No\', \'Yes\'), ', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Tare percent.', 'MODULE_SHIPPING_OVERSEASAUPOST_TARE', '10', 'Add this percentage of the items total weight as the tare weight. (This module ignores the global settings that seems to confuse many users. 10% seems to work pretty well.).', '6', '50', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_SHIPPING_OVERSEASAUPOST_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '55', now())");
    $db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Tax Class', 'MODULE_SHIPPING_OVERSEASAUPOST_TAX_CLASS', '0', 'Set Tax class or -none- if not registered for GST.', '6', '60', 'zen_get_tax_class_title', 'zen_cfg_pull_down_tax_classes(', now())");

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

    if(isset($inst))
    {
      //  echo "new" ;
        $db->Execute("ALTER TABLE " .TABLE_PRODUCTS. " ADD `products_length` FLOAT(6,2) NULL AFTER `products_weight`, ADD `products_height` FLOAT(6,2) NULL AFTER `products_length`, ADD `products_width` FLOAT(6,2) NULL AFTER `products_height`" ) ;
    }
    else
    {
      //  echo "update" ;
        $db->Execute("ALTER TABLE " .TABLE_PRODUCTS. " CHANGE `products_length` `products_length` FLOAT(6,2), CHANGE `products_height` `products_height` FLOAT(6,2), CHANGE `products_width`  `products_width`  FLOAT(6,2)" ) ;
    }

}

function remove()
{
    global $db;
    $db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE_SHIPPING_OVERSEASAUPOST_%' ");
}

function keys()
{
    return array
    (
        'MODULE_SHIPPING_OVERSEASAUPOST_STATUS',
		'MODULE_SHIPPING_OVERSEASAUPOST_AUTHKEY',
	    'MODULE_SHIPPING_OVERSEASAUPOST_SPCODE',
	    'MODULE_SHIPPING_OVERSEASAUPOST_TYPES1',
        'MODULE_SHIPPING_OVERSEASAUPOST_AIRMAIL_HANDLING',
        'MODULE_SHIPPING_OVERSEASAUPOST_SEAMAIL_HANDLING',
        'MODULE_SHIPPING_OVERSEASAUPOST_STANDARD_HANDLING',
        'MODULE_SHIPPING_OVERSEASAUPOST_EXPRESS_HANDLING',
		'MODULE_SHIPPING_OVERSEASAUPOST_COURIER_HANDLING',
        'MODULE_SHIPPING_OVERSEASAUPOST_COST_ON_ERROR',
		'MODULE_SHIPPING_OVERSEASAUPOST_HIDE_HANDLING',
	    'MODULE_SHIPPING_OVERSEASAUPOST_DIMS',
	    'MODULE_SHIPPING_OVERSEASAUPOST_WEIGHT_FORMAT',
	    'MODULE_SHIPPING_OVERSEASAUPOST_ICONS',
	    'MODULE_SHIPPING_OVERSEASAUPOST_DEBUG',
        'MODULE_SHIPPING_OVERSEASAUPOST_TARE',
	    'MODULE_SHIPPING_OVERSEASAUPOST_SORT_ORDER',
	    'MODULE_SHIPPING_OVERSEASAUPOST_TAX_CLASS'
    );
}

//auspost API
function get_auspost_api($url)
{
$crl = curl_init();
$timeout = 5;
curl_setopt ($crl, CURLOPT_HTTPHEADER, array('AUTH-KEY:' . MODULE_SHIPPING_OVERSEASAUPOST_AUTHKEY));
curl_setopt ($crl, CURLOPT_URL, $url);
curl_setopt ($crl, CURLOPT_RETURNTRANSFER, 1);
curl_setopt ($crl, CURLOPT_CONNECTTIMEOUT, $timeout);
$ret = curl_exec($crl);
curl_close($crl);
return $ret;
}
// end auspost API

}  // end class
