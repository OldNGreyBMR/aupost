aupost Zen Cart Australia postage plug in
==========================================
# AusPost Shipping Module 3.1 encapsulated plugin [json]
-------------------------------
Updated 03 September 2026 by OldNGrey 

## This version tested on Zen Cart version 2.1.0, 2.2.0, 2.2.1, 2.2.2 and PHP 8.3, 8.4, 8.5;

This module uses the new Australia Post API [json interface] to get valid quotes for letters and parcels directly from the Australia Post server.
The module:
- Calculates optimal parcel size
- Displays Live/Real-Time Shipping rates on your Zen Cart Shopping Cart page
- Supports both domestic and international shipping options, including standard, express, and economy services.
- Is easy to install and configure, with no coding required.
- fully encapsulated plugin
- includes Flat Rate packaging and Express Flat Rate packaging

This encapsulated version will not install on versions prior to 2.1.0.  

To use this module, you must obtain a 36 digit API Key from the Auspost Development Centre:
 https://developers.auspost.com.au/
 
The aupost.php module is required for postage rates within Australia only.
The aupostoverseas.php module is required for postage rates for overseas only.

## Australian Delivery Options:
============================
Letters:
-------
- Aust Standard  
- Aust Priority  
- Aust Express  
- Aust Express +sig  
- Aust Express Insured +sig  
- Aust Express Insured (no sig)  

Parcels:
========
- Regular Parcel  
- Regular Parcel +sig 
- Regular Parcel Insured +sig 
- Regular Parcel Insured (no sig) 
- Prepaid Satchel 
- Prepaid Satchel +sig 
- Prepaid Satchel Insured +sig 
- Prepaid Satchel Insured (no sig) 
- Flat rate packaging 
- Flat rate packaging +sig 
- Flat rate packaging Insured +sig 
- Flat rate packaging Insured (no sig)

- Express Parcel 
- Express Parcel +sig 
- Express Parcel Insured +sig 
- Express Parcel Insured (no sig) 
- Prepaid Express Satchel 
- Prepaid Express Satchel +sig 
- Prepaid Express Satchel Insured +sig 
- Prepaid Express Satchel Insured (no sig) 
- Express Flat rate packaging 
- Express Flat rate packaging +sig 
- Express Flat rate packaging Insured +sig 
- Express Flat rate packaging Insured (no sig)

Parcels NOW include Australia Post prices that require additional AP packaging. The cost os teh packaging should be included in the handling cost.

International Delivery Options:
===============================
International letters are not offered as no items of commercial value can be send by International Letter
- Sea Mail 
- Sea Mail +sig 
- Sea Mail Insured +sig 
- Sea Mail Insured (no sig) 
- Economy Air Mail 
- Economy Air Mail +sig 
- Economy Air Mail Insured +sig 
- Economy Air Mail Insured (no sig) 
- Standard Post International 
- Standard Post International +sig 
- Standard Post International Insured +sig 
- Standard Post International Insured (no sig) 
- Express Post International 
- Express Post International International +sig 
- Express Post International International Insured +sig 
- Express Post International International Insured (no sig) 
- Courier International 
- Courier International Insured  

Installation:
==============
1 Data
------
To obtain really accurate postage quotes directly from Australia Post the following fields are preferred. The module will still return quotations if dimensions are not provided.

To use this Zen Cart plugin for calculating postage with Australia Post IT IS PREFERRED that you 
have made the following customisation to Zen Cart.

    The products table SHOULD include the following fields:
    - products_width (included by default in Zen Cart)
    - products_length (included by default in Zen Cart 2.0)
    - products_height. (included by default in Zen Cart 2.0)
    
    The latter three fields can be added to earlier versions of ZC by installing the "Numinix Product Fields" add on and adding the predefined custom group "products_dimensions". These fields must have valid values to calculate the postage charges correctly. 
    Dimensions should be in cm, weight should be in grams (gms).
    If you have used the OzPpost postage calculator previously you will have these fields. 
    If you do not add the extra fields and populate their values the module will use default values.
    The default values are 10cm x 10cm x 2cm which will be a small parcel.
 
2 Australia Post Account
------------------------
To use this module, you must obtain a 36 digit API Key from the Auspost Development Centre:
 https://developers.auspost.com.au/

3 Upgrading on ZenCart v2.1.0+ from a previous encapsulated version (eg 2.5.9)
--------------------------------
 3.1 Copy the zc_plugins/AustraliaPost folder zc_plugins folder of your website
 3.2 If you have a previous encapsulated version: In Admin go to: Modules > Plugin Manager. Select Australia Post and click "Upgrade Available"; select this version and click "Install. 
    
4 Installing on zencart v2.1.0+
-------------------------------
 4.1  Configuration - Australia Post Make sure you have entered your own postcode in your Zen Cart admin by going to: Configuration > shipping/packaging > postal code 
 4.2  Upload the 'zc_plugins/AustraliaPost' folder to the root folder of your Zen Cart store.
 4.3  A CSS file for debugging messages is in /catalog/includes/templates/template_default/css/stylesheet_zczaupost.php. 
 4.4  A new Austalia Post icon file is in /catalog/includes/templates/template_default/images/icons/aupost_logo.jpg. 
 4.5  Upload the icons folder and the css folder to the template used on your site.
 4.6  In Admin go to: Modules > Plugin Manager and select "Australia Post" and install.
      If you have a previous version installed, uninstall it first, but take note of your settings and AP key.
 4.7  In Admin go to modules > shipping select aupost and edit
 4.8  Under 'Auspost API Key', enter your 36 digit API key.
 4.9  Add the Tax Class defined in Zen Cart. Australian Postage includes GST. Overseas postage is GST exempt (tax free).
 4.10 Scroll down and click 'update'.

Congratulations! You have now successfully installed the Australia Post Shipping Module.

5 Additional Configurations
=========================
5.1 Select the postage options you wish to offer to customers.
5.2 Add handling fees if you factor in costs for material and packaging.
5.3 Cost on error is the default if a valid postage rate is not returned or the Australia Post servers cannot be reached. I recommend an amount large enough to cover most postage and that will be obvious eg 99.99.
5.4 The Tare percent allows for weight of packaging etc when requesting postage rates. The default is 10.

6   Configuration - Australia Post International
================================================
6.1 Repeat steps 4.1 above
6.2 In Admin go to: modules > shipping > Australia Post International > click install
6.3 Repeat step 4.1 to 4.4 above
6.6 Add the Tax Class defined in Zen Cart. Australian Postage includes GST. Overseas postage is GST exempt (tax free).
6.7 Scroll down and click 'update'.


Tax (GST) Calculations
======================
Australia Post postage rates to Australian destinations includes GST. This is taken into account in the module by providing the GST exempt price to Zen Cart 
    and letting Zen Cart process the tax according to the rules you have defined. The tax-basis returned by aupost is "Shipping" 
    so ensure that the setting 
        Admin | Configuration | My Store | Basis of Shipping Tax is set to "Shipping" 
    and that the Tax Class set in 
        Admin | Modules |shipping |aupost is set to your tax rate that covers GST.
Australia Post postage rates to overseas destinations do not include GST. Set your Tax Class in 
    Admin | Modules |shipping |aupostoverseas     to your tax rate that does not include GST.

Parcel sizing calculations
==========================
The NEW sizing calculations follows the following process:
 * Items are sorted largest-first (by volume) for better packing efficiency.
  - Each item quantity is split into a grid: items are placed side-by-side to minimise height, favouring a roughly square footprint.
  - The parcel footprint grows to fit the widest/longest row of items.
  - Height accumulates per product row (stacked on top of previous rows).

HISTORY
=======
Previous versions of AusPost Shipping Module up to and including 2.5.9x used the Austalia Post XML API interface.
This version uses the json API interface
