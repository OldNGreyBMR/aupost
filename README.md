# aupost
 aupost zen cart Australia postage plug in
----------------------------------
Australia Post Shipping Module 2.4
----------------------------------
Updated: 20 February 2022 by browe BMH
Updated: 08 June 2018 by millsii
Updated: 19 April 2018 by http://www.avantmarketing.com.au
Updated: 02 November 2016 by foobic
Updated: 14 March 2013 by http://www.avantmarketing.com.au
Original Copyright (c) 2007-2009 Rod Gasson / VCSWEB

This version tested on Zen Cart version 1.5.6a, 1.5.7c, 1.5.7d and PHP 8.0; 
----------------------------------

This module uses the new Australia Post API to get valid quotes for parcels directly from the Australia Post server.

To use this module, you must obtain a 36 digit API Key from the Auspost Development Centre:
 https://developers.auspost.com.au/
 
The aupost.php module is required for postage rates within Australia only.
The aupostoverseas.php module is required for postage rates overseas only.

Australian Delivery Options:
============================
Regular Parcels
Express Post Parcels
Regular Prepaid Satchels
Express Post Prepaid Satchels
Express Post Platinum Parcels 
Express Post Prepaid Platinum Satchels

International Delivery Options:
===============================
Sea Mail
Economy Air Mail
Standard Post International
Express Post International
Courier International

-------------
Installation:
-------------
1 Data
======
To use this Zen Cart plugin for calculating postage with Australia Post you need to have made the following customisation to Zen Cart.
The products table must include the following fields:
 products_width (included by default in Zen Cart)
 products_length
 products_height
 products_width.
 
 The latter three fields can be added by installing the "Numinix Product Fields" add on and adding the predefined custom group "products_dimensions".
 These fields must have valid values to calculate the postage charges correctly. Dimensions should be in cm, weight should be in grams (gms).
 If you have used the OzPpost postage calculator previously you will have these fields. The default values are 10cm x 10cm x 2cm which will be a small parcel.
 
2 Australia Post Account
=======================
To use this module, you must obtain a 36 digit API Key from the Auspost Development Centre:
 https://developers.auspost.com.au/
For use in a test environment see readme_aupost_test_environment_v2-4.txt for the settings.
 
3 Configuration - Australia Post
===============
3.1 Make sure you have entered your own postcode in your Zen Cart admin by going to: Configuration > shipping/packaging > postal code
3.2 Upload the 'includes' folder to the root folder of your Zen Cart store.
3.3 In Admin go to: modules > shipping > Australia Post > click install
3.4 Under 'Auspost API Key', enter your 36 digit API key.
3.5 Add the Tax Class defined in Zen Cart. Australian Postage includes GST. Overseas postage is GST exempt (tax free).
3.6 Scroll down and click 'update'.

Congratulations! You have now successfully installed the Australia Post Shipping Module.

4 Additional Configurations
=========================
4.1 Add handling fees if you factor in additional costs for material and packaging.
4.2 Cost on error is the default if a valid postage rate is not returned or the Australia Post servers cannot be reached. I recommend an amount large enough to cover most postage and that will be obvious eg 99.99.
4.3 The Tare percent allows for weight of packaging etc when requesting postage rates. The default is 10. 

5   Configuration - Australia Post International
================================================
5.1 Repeat step 3.1 above
5.2 Repeat step 3.2 above
5.3 In Admin go to: modules > shipping > Australia Post International > click install
5.4 Repeat step 3.4 above
5.5 Add the Tax Class defined in Zen Cart. Australian Postage includes GST. Overseas postage is GST exempt (tax free).
5.6 Scroll down and click 'update'.


-------------------------------------------------
Upgrading from Australia Post Shipping Module previous versions
-------------------------------------------------
A complete removal and reinstall is recommended.
1. Note Australia Post API key and other settings.
2. Remove old module.
3. Overwrite the files with the new fileset.
4. Install new version.
5. Re-enter Australia Post API key and other settings.

====================================================
Changelog Version 2.4
-------------------------------------------------
See separate file changelog.md


