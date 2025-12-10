aupost Zen Cart Australia postage plug in
==========================================
AusPost Shipping Module 2.5.9
-------------------------------
Updated 10 December 2025 by OldNGrey BMH

AusPost Shipping Module 2.5.8f
-------------------------------
Updated 08 November 2025 by OldNGrey BMH

AusPost Shipping Module 2.5.8e
-------------------------------
Updated 19 October 2025 by OldNGrey BMH

AusPost Shipping Module 2.5.8d
-------------------------------
Updated 26 August 2025 by OldNGrey BMH

AusPost Shipping Module 2.5.8c 
-------------------------------
Updated 29 July 2025 by OldNGrey BMH

AusPost Shipping Module 2.5.8b 
-------------------------------
Updated 18 July 2025 by OldNGrey BMH

Australia Post Shipping Module 2.5.8a
--------------------------------------
Updated 09 July 2025 by OldNGrey BMH

For code changes see the changelog.

### This version tested on Zen Cart version 1.5.7, 1.5.8, 1.5.8a , 2.0.0, 2.1.0 and PHP 8.0, 8.1, 8.2, 8.3; 8.4;

This module uses the new Australia Post API to get valid quotes for letters and parcels directly from the Australia Post server.

To use this module, you must obtain a 36 digit API Key from the Auspost Development Centre:
 https://developers.auspost.com.au/
 
The aupost.php module is required for postage rates within Australia only.
The aupostoverseas.php module is required for postage rates for overseas only.

Australian Delivery Options:
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
- Express Parcel 
- Express Parcel +sig 
- Express Parcel Insured +sig 
- Express Parcel Insured (no sig) 
- Prepaid Express Satchel 
- Prepaid Express Satchel +sig 
- Prepaid Express Satchel Insured +sig 
- Prepaid Express Satchel Insured (no sig) 

Parcels do not include Australia Post prices that require additional AP packaging.

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
    
    The latter three fields can be added by installing the "Numinix Product Fields" add on and adding the predefined custom group "products_dimensions". These fields must have valid values to calculate the postage charges correctly. 
    Dimensions should be in cm, weight should be in grams (gms).
    If you have used the OzPpost postage calculator previously you will have these 
    fields. If you do not add the extra fields and populate their values the module will use default values.
    The default values are 10cm x 10cm x 2cm which will be a small parcel.
 
2 Australia Post Account
------------------------
To use this module, you must obtain a 36 digit API Key from the Auspost Development Centre:
 https://developers.auspost.com.au/

3.0 Upgrading on ZenCart v2.0.0+
--------------------------------
    3.0.1 In Admin go to: Modules > Shipping Modules, select "Australia Post" take note of your settings and AP key.
    3.0.2 click "Remove Module" twice.
    3.0.3 repeat for Australia Post International
    3.0.4 In Admin go to: Modules > Plugin Manager and select "Australia Post" and click "Un-Install" twice
    3.0.5 Go to step 3.1
3.1 Installing on zencart v2.0.0+
-------------------------------
    3.1.1 Configuration - Australia Post
    3.1.2 Make sure you have entered your own postcode in your Zen Cart admin by going to: Configuration > shipping/packaging > postal code 
    3.1.3 Upload the 'zc_plugins/AustraliaPost' folder to the root folder of your Zen Cart store.
    3.1.4 A CSS file is in \catalog\includes\templates\template_default\css. A new icon file is in \catalog\includes\templates\template_default\images\icons. 
        Upload the icons folder and the css folder to the template used on your site.
    3.1.5 In Admin go to: Modules > Plugin Manager and select "Australia Post" and install.
       If you have a previous version installed, uninstall it first, but take note of your settings and AP key.
    3.1.6 In Admin go to modules > shipping select aupost and edit
    3.1.7 Under 'Auspost API Key', enter your 36 digit API key.
    3.1.8 Add the Tax Class defined in Zen Cart. Australian Postage includes GST. Overseas postage is GST exempt (tax free).
    3.1.9 Scroll down and click 'update'.

3.2 Installing on zencart v158 and 157
----------------------------
    3.2 Configuration - Australia Post
    3.2.1 Make sure you have entered your own postcode in your Zen Cart admin by going to: Configuration > shipping/packaging > postal code 
    3.2.2 Upload the 'includes' folder to the root folder of your Zen Cart store.
    3.2.3 A CSS file is uploaded to \includes\templates\template_default\css\. A new icon file is uploaded \includes\templates\template_default\images\icons. 
        Upload the icons folder and the css folder to the template used on your site.
    3.2.4 In Admin go to: modules > shipping > Australia Post > select it and click install. If you have a previous version of the module installed, uninstall the existing version then reinstall.
    3.2.5 Under 'Auspost API Key', enter your 36 digit API key.
    3.2.6 Add the Tax Class defined in Zen Cart. Australian Postage includes GST. Overseas postage is GST exempt (tax free).
    3.2.7 Scroll down and click 'update'.

Congratulations! You have now successfully installed the Australia Post Shipping Module.

4 Additional Configurations
=========================
4.1 Select the postage options you wish to offer to customers.
4.2 Add handling fees if you factor in costs for material and packaging.
4.3 Cost on error is the default if a valid postage rate is not returned or the Australia Post servers cannot be reached. I recommend an amount large enough to cover most postage and that will be obvious eg 99.99.
4.4 The Tare percent allows for weight of packaging etc when requesting postage rates. The default is 10.

5   Configuration - Australia Post International
================================================
5.1 Repeat steps 3.1 above
5.2 In Admin go to: modules > shipping > Australia Post International > click install
5.3 Repeat step 3.1.4 to 3.1.5 above
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

If you have used the defunct OzPost Shipping Module ensure that all OzPost files are removed.

Tax (GST) Calculations
======================
Australia Post postage rates to Australian destinations includes GST. This is taken into account in the module by providing the GST exempt price to Zen Cart and letting Zen Cart process the tax according to the rules you have defined. The tax-basis returned by aupost is "Shipping" 
    so ensure that the setting 
        Admin | Configuration | My Store | Basis of Shipping Tax is set to "Shipping" 
    and that the Tax Class set in 
        Admin | Modules |shipping |aupost is set to your tax rate that covers GST.
Australia Post postage rates to overseas destinations do not include GST. Set your Tax Class in 
    Admin | Modules |shipping |aupostoverseas     to your tax rate that does not include GST.

Parcel sizing calculations
==========================
NOTE: The sizing calculations are primitive eg dimensions are totalled so many small items are made into one long parcel. Read the "cubing.txt" file included in this build.

