<?php
/**
 * @package AustraliaPost
 * @copyright Copyright 2003-2026 Zen Cart Development Team
 * @license http://zen-cart.com GNU Public License V2.0
 */

use Zencart\Traits\InteractsWithPlugins;

// Name must match zcObserver + CamelCased filename (excluding 'auto.')
class zcObserverAupostCssLoader extends base
{
    use InteractsWithPlugins;

    public function __construct()
    {
        // Determine this plugin's installed file-system location so the CSS path is
        // derived from the actual installed version rather than a hardcoded one.
        $this->detectZcPluginDetails(__DIR__);

        // Listen for the HTML head output event
        $this->attach($this, ['NOTIFY_HTML_HEAD_END']);
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
        $target_pages = ['shopping_cart', 'checkout_shipping', 'checkout_payment', 'checkout_confirmation'];

        if (in_array($current_page_base, $target_pages)) {
            // Derive the web path from the plugin's installed version directory so the
            // stylesheet keeps loading correctly regardless of the installed version.
            if (!empty($this->zcPluginCatalogPath)) {
                $css_url = DIR_WS_CATALOG . $this->zcPluginCatalogPath . 'includes/templates/css/stylesheet_aupost.css';
            } else {
                // Fallback: derive purely from this file's location, no DB install record needed.
                $rel = str_replace(rtrim(DIR_FS_CATALOG, '\\/') . '/', '', str_replace('\\', '/', dirname(__DIR__, 3)));
                $css_url = DIR_WS_CATALOG . $rel . '/templates/css/stylesheet_aupost.css';
            }

            // Output the raw link markup directly into the template's head
            echo '<!-- Australia Post Module Styles -->' . PHP_EOL;
            echo '<link rel="stylesheet" type="text/css" href="' . $css_url . '" />' . PHP_EOL;
        }
    }
}
