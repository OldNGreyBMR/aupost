aupost Zen Cart Australia postage plug in
==========================================
AusPost Shipping Module 2.5.8b 
2025-07-22

files located in the additional_files folder provide:
    a sample invoice, [ YOUR_ADMIN/invoice.php]
    a language file for the new invoice, and [YOUR_ADMIN/includes/languages/english/extra_definitions/lang.invoice.php]
    an overide language file for Australia settings [YOUR_ADMIN/includes/languages/english/extra_definitions/lang.zbmh_overrides.php].
    
    
invoice.php
-----------
The new invoice file:
    includes the title "Tax Invoice" as required by the Australian Tax Office,
    removes the SHIP TO address and replaces it with "PICKUP" if the order is to be collected,
    reorganizes the sections an dthe size of the addresses so the invoice can be folded in half then folded 
    again to fit into an address sleeve.

Before you copy this new invoice.php file, locate the original and rename it eg rename invoice.php to invoice_ori.php2

Language override file
----------------------
The language override file name is lang.zYOURNAME_overrides.php. This will list last alphabetically when the lang files are loaded.
It changes; 
    local time to Australia; 
    formats all dates to normal formats (Non US format); 
    changes the logo widths to reduce wasted space;
    changes weight units displayed from lbs to grams and kgs to gms;
    changes header names to MY STORE NAME and changes to logos to MY LOGO names
    ALTER THESE SETTINGS TO SUIT YOUR STORE.

