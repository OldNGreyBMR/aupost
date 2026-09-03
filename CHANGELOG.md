CHANGELOG
=========
AusPost Shipping Module + AusPost Overseas Shipping Module 3.1 03 September 2026
------------------------------------------
#aupost
 - Total rewrite to use json API instead of XML API
 - Fully encapsulated plugin
 - array to string for logfile;  limit letter code to only when switch is set; set domestic check earlier;
 - check PHP 8.5 compatibility
 - parcel calc moved to function; parcel size optimised, letter size optimised 
 - processing time is no slower even with extra sizing calculations
 - improved postcode validation; error messages if blank
 - debugging css loaded from plugins
 - admin invoice.php renamed to invoice.php.orig on install and reverted on uninstall so the provided plugins updated invoice is used.
 - caching is set to ON by default, so if nothing has changed on the shopping cart page a new request will NOT be made to the Australia Post servers
 - invoice css from plugins; invoice has dashed fold line controlled by a switch in the file.
 
 - includes Flat rate packaging
 - consolidated code to reduce redundancy;  duplicate code moved to functions
 - normalise: single service returned as object rather than array for when only one option is returned
 
 #aupostoverseas
 - consolidated code to reduce redundancy;  duplicate code moved to functions
 - check and recover for invalid destination country
 - normalise: single service returned as object rather than array for when only one option is returned