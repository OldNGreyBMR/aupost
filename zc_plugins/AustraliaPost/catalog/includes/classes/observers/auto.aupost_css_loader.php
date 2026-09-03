<?php
/**
 * @package AustraliaPost
 * @copyright Copyright 2003-2026 Zen Cart Development Team
 * @license http://zen-cart.com GNU Public License V2.0
 */

// Name must match zcObserver + CamelCased filename (excluding 'auto.')
class zcObserverAupostCssLoader extends base 
{
    public function __construct() 
    {
        // Listen for the HTML head output event
        $this->attach($this, array('NOTIFY_HTML_HEAD_END'));
    }

    /**
     * Listens for the HTML head event and injects the CSS file link dynamically.
     * 
     * @param object $calling_class Reference to the instantiated html_header logic
     * @param string $notifier Name of the intercepted notifier event
     * @param string $current_page_base The current page string identifier (e.g. 'shopping_cart')
     */
    public function updateNotifyHtmlHeadEnd(&$calling_class, $notifier, $current_page_base) 
    {
        // Only load the CSS on relevant shipping/cart pages to maximize performance
        $target_pages = array('shopping_cart', 'checkout_shipping', 'checkout_payment', 'checkout_confirmation');
        
        if (in_array($current_page_base, $target_pages)) {
            // Build path dynamically to support standard zc_plugins directory routing
            $css_url = DIR_WS_CATALOG . 'zc_plugins/AustraliaPost/v3.0.0/catalog/includes/templates/css/stylesheet_aupost.css';
            
            // Output the raw link markup directly into the template's head
            echo '<!-- Australia Post Module Styles -->' . PHP_EOL;
            echo '<link rel="stylesheet" type="text/css" href="' . $css_url . '" />' . PHP_EOL;
        }
        // Inside your observer updateNotifyHtmlHeadEnd method:
//$css_url = DIR_WS_CATALOG . 'zc_plugins/AustraliaPost/v3.0.0/catalog/includes/templates/css/stylesheet_aupost.css';
//echo '<link rel="stylesheet" type="text/css" href="' . $css_url . '" />';
    }
}
