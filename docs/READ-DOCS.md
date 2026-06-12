2026-06-10  Australia Post postage plugin for Zen Cart version 2.1.0+ and PHP 8.3 to 8.5

AusPost Shipping Module 3.0.0 encapsulated plugin [json]
========================================================
Updated 10 June 2026 by OldNGrey

### This version tested on Zen Cart version 2.1.0, 2.2.0, 2.2.1, 2.2.2 and PHP 8.3, 8.4, 8.5;

This module uses the new Australia Post API [json interface] to get valid quotes for letters and parcels directly from the Australia Post server.
The module:
- Calculates optimal parcel size
- Displays Live/Real-Time Shipping rates on your Zen Cart Shopping Cart page
- Supports both domestic and international shipping options, including standard, express, and economy services.
- Is easy to install and configure, with no coding required.
- fully encapsulated plugin

This encapsulated version will not install on versions prior to 2.1.0.  
If you must run it on versions prior to 2.1.0 see the notes beloe.

To use this module, you must obtain a 36 digit API Key from the Auspost Development Centre:
 https://developers.auspost.com.au/
 
The aupost.php module is required for postage rates within Australia only.
The aupostoverseas.php module is required for postage rates for overseas only.

Installation instructions ar ein teh main README.md file

The encapsulated plugin includes modified invoice.php file that includes "Tax Invoice" as required by the ATO.
It also displays the customers phone number and email address in smaller type below the main address label. 
From 2026-05-31 Australia Post requires the contact phone number or email address of teh recipient when a parcel is lodged.
The display of this information is controlled by a switch in the invoice.php file. This is on by default.

Also a switch is provided to place a dotted fold line across the invoice as a guide to folding to fit into a clear address sleeve. This is off by default.


