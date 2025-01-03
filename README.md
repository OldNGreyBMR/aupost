# aupost
 aupost zen cart Australia postage plug in
----------------------------------
Australia Post Shipping Module 2.5.7a
--------------------------------------
Updated 30 Nov  2024 by OldNGrey BMH

Australia Post Shipping Module 2.5.7
--------------------------------------
Updated 17 Nov  2024 by OldNGrey BMH

Australia Post Shipping Module 2.5.6.e
--------------------------------------
Updated 11 Nov  2024 by OldNGrey BMH
also in zc_plugins

Australia Post Shipping Module 2.5.6.d
--------------------------------------
Updated 16 July 2024 by OldNGrey BMH

Australia Post Shipping Module 2.5.6.c
--------------------------------------
Updated 07 Apr 2024 by OldNGrey BMH

Australia Post Shipping Module 2.5.6.a
------------------------------------
Updated 15 Feb 2024 by OldNGrey BMH

Australia Post Shipping Module 2.5.6
------------------------------------
Updated 13 Feb 2024 by OldNGrey BMH

Australia Post Shipping Module 2.5.5j
----------------------------------
Updated: 20 Jan 2024 by OldNGrey BMH
Updated: 16 Oct 2023 by OldNGrey BMH
Updated: 17 Sep 2023 by OldNGrey BMH
Updated: 11 Apr 2023 by OldNGrey BMH
Updated: 2 Mar 2023 by OldNGrey BMH
Updated: 21 Feb 2023 by OldNGrey BMH
Updated: 14 Feb 2023 by OldNGrey BMH
Updated: 22 October 2022 by OldNGrey BMH
Updated: 09 August 2022 by OldNGrey BMH
Updated: 31 July 2022 by OldNGrey BMH
Updated: 08 June 2018 by millsii
Updated: 19 April 2018 by http://www.avantmarketing.com.au
Updated: 02 November 2016 by foobic
Updated: 14 March 2013 by http://www.avantmarketing.com.au
Original Copyright (c) 2007-2009 Rod Gasson / VCSWEB

This version tested on Zen Cart version 1.5.8, 1.5.8a , 2.0.0, 2.1.0 and PHP 8.0, 8.1, 8.2, 8.3; 
----------------------------------

This module uses the new Australia Post API to get valid quotes for letters and parcels directly from the Australia Post server.

To use this module, you must obtain a 36 digit API Key from the Auspost Development Centre:
 https://developers.auspost.com.au/
 
The aupost.php module is required for postage rates within Australia only.
The aupostoverseas.php module is required for postage rates overseas only.

Australian Delivery Options:
============================
Letters:
========
Aust Standard
Aust Priority
Aust Express
Aust Express +sig
Aust Express Insured +sig
Aust Express Insured (no sig)

Parcels:
========
Regular Parcel
Regular Parcel +sig
Regular Parcel Insured +sig
Regular Parcel Insured (no sig)
Prepaid Satchel
Prepaid Satchel +sig
Prepaid Satchel Insured +sig
Prepaid Satchel Insured (no sig)
Express Parcel
Express Parcel +sig
Express Parcel Insured +sig
Express Parcel Insured (no sig)
Prepaid Express Satchel
Prepaid Express Satchel +sig
Prepaid Express Satchel Insured +sig
Prepaid Express Satchel Insured (no sig)
It does not include AP prices that require additional AP packaging.

International Delivery Options:
===============================
Letters are not offered as no items of commercial value can be send by International Letter
Sea Mail
Sea Mail +sig
Sea Mail Insured +sig
Sea Mail Insured (no sig)
Economy Air Mail
Economy Air Mail +sig
Economy Air Mail Insured +sig
Economy Air Mail Insured (no sig)
Standard Post International
Standard Post International +sig
Standard Post International Insured +sig
Standard Post International Insured (no sig)
Express Post International
Express Post International International +sig
Express Post International International Insured +sig
Express Post International International Insured (no sig)
Courier International
Courier International Insured

-------------
Installation:
-------------
1 Data
======
To obtain really accurate postage quotes directly from Australia Post the following fields are preferred. The module will still return quotations if dimensions are not provided.

To use this Zen Cart plugin for calculating postage with Australia Post IT IS PREFERRED that you 
have made the following customisation to Zen Cart.
	The products table SHOULD include the following fields:
		products_width (included by default in Zen Cart)
		products_length
		products_height.
	
	The latter three fields can be added by installing the "Numinix Product Fields" add on and adding the predefined custom group "products_dimensions". These fields must have valid values to calculate the postage charges correctly. 
	Dimensions should be in cm, weight should be in grams (gms).
	If you have used the OzPpost postage calculator previously you will have these 
	fields. If you do not add the extra fields and populate their values the module will use default values.
	The default values are 10cm x 10cm x 2cm which will be a small parcel.
 
2 Australia Post Account
=======================
To use this module, you must obtain a 36 digit API Key from the Auspost Development Centre:
 https://developers.auspost.com.au/
 
3 Configuration - Australia Post
===============
3.1 Make sure you have entered your own postcode in your Zen Cart admin by going to: Configuration > shipping/packaging > postal code 
3.2 Upload the 'includes' folder to the root folder of your Zen Cart store.
3.3 A CSS file is uploaded to \includes\templates\template_default\css\. A new icon file is uploaded \includes\templates\template_default\images\icons. 
        Upload the icons folder and the css folder to the template used on your site.
3.4 In Admin go to: modules > shipping > Australia Post > click install
3.5 Under 'Auspost API Key', enter your 36 digit API key.
3.6 Add the Tax Class defined in Zen Cart. Australian Postage includes GST. Overseas postage is GST exempt (tax free).
3.7 Scroll down and click 'update'.

If you have a previous version of the module installed, uninstall the existing version then reinstall.

Congratulations! You have now successfully installed the Australia Post Shipping Module.

4 Additional Configurations
=========================
4.1 Select the postage options you wish to offer to cuctomers.
4.2 Add handling fees if you factor in costs for material and packaging.
4.3 Cost on error is the default if a valid postage rate is not returned or the Australia Post servers cannot be reached. I recommend an amount large enough to cover most postage and that will be obvious eg 99.99.
4.4 The Tare percent allows for weight of packaging etc when requesting postage rates. The default is 10.

5   Configuration - Australia Post International
================================================
5.1 Repeat step 3.1 above
5.2 Repeat step 3.2 above
5.3 Repeat step 3.3 above
5.4 In Admin go to: modules > shipping > Australia Post International > click install
5.5 Repeat step 3.5 above
5.6 Add the Tax Class defined in Zen Cart. Australian Postage includes GST. Overseas postage is GST exempt (tax free).
5.7 Scroll down and click 'update'.

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
Changelog Version 2.5.4
-------------------------------------------------
See separate file CHANGELOG.md

Tax (GST) Calculations
----------------------
Australia Post postage rates to Australian destination includes GST. This is taken into account in the module by providing the GST exempt 
    price to Zen Cart and letting Zen Cart process the tax according to teh rules you have defined. The tax-basis returned by aupost is "Shipping" 
    so ensure that the setting Admin | Configuration | My Store | Basis of Shipping Tax is set to Shipping and that the Tax Class set in Admin | Modules |shipping |aupost 
    is set to your tax rate that covers GST.
Australia Post postage rates to overseas destinations does not include GST. Set your Tax Class in Admin | Modules |shipping |aupostoverseas
    to your tax rate that does not include GST.

Parcel sizing calculations
==========================
NOTE: The sizing calculations are primitive eg dimensions are totalled so many small items are made into one long parcel.
  
