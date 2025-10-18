CHANGELOG
=========
AusPost Shipping Module + AusPost Overseas Shipping Module2.5.8e 19 Oct 2025
------------------------------------------
Check for value over max cover without signature; check for error returned from AP notin XML format
improve debugging 

AusPost Shipping Module 2.5.8d 26 Aug 2025
------------------------------------------
Check for from postcode and API key as some users ignore the warnings in the Admin config page. Write to error log and return without processing to avoid crash.
Remove check for empty letter quote.


AusPost Shipping Module 2.5.8c 29 July 2025
------------------------------------------
Corrects the invalid configuration_group_id values

AusPost Shipping Module 2.5.8b 18 July 2025
------------------------------------------
2025-07-17 v2.5.8b 2025-07-17 check for Constants on initial install
        
AusPost Shipping Module 2.5.8a 09 July 2025
------------------------------------------
Improved error msgs; output errors to log file; display dims as int as AP only shows as int now; improve handling of  MODULE_SHIPPING_AUPOST_COST_ON_ERROR

AusPost Shipping Module 2.5.8 01 July 2025
------------------------------------------
Changes for new rates and parcel sizes effective 01 July 2025

AusPost Shipping Module 2.5.7a 30 Nov 2024
--------------------------------------------------------------------------
- issue#19 Missing method 'id' for excess length, volume and weight quotes

AusPost Shipping Module 2.5.7 17 Nov 2024
--------------------------------------------------------------------------
- correct pricing with sub options eg extracover + sig; 
- created _debug_output function

AusPost Shipping Module 2.5.6e 11 Nov 2024
--------------------------------------------------------------------------
- correct issue #16 initialised $methods
- use count($methods); 

AusPost Shipping Module 2.5.6d 16 July 2024
--------------------------------------------------------------------------
- correct issue #12 valid Australian postcodes

AusPost Shipping Module 2.5.6.a + AusPost Overseas Shipping Module 2.5.6.a 15 Feb 2024
--------------------------------------------------------------------------
- correct issue #5 blank handling fee causes error

AusPost Shipping Module 2.5.6 + AusPost Overseas Shipping Module 2.5.6 13 Feb 2024
--------------------------------------------------------------------------
- update CODES to Exclude AP options that require purchasing additional AP packaging
- correct issue #10 related to incorrect price for some parcels >500 and <1000g

Shipping Module 2.5.5j + AusPost Overseas Shipping Module 2.5.5j 20 Jan 2024
----------------------------------------------------------------------------
- use strict_types=1; 
- add declared vars identified; 
- strval used for str_replace
- version numbering v n.n.na


Version 2.5.5-07 16 Oct 2023:
---------------------------
- Added AUSPARCELREGULAR - Aust Post have reverted to default code for large parcels

Version 2.5.5-05 17 Sep 2023:
---------------------------
- Corrected error that let smallest satchel through when satchels are not included in Admin config settings
- double checked tax inclusions
- checked for and excluded some redundant returned codes returned by AustPost


Version 2.5.5-03 11 Apr 2023:
---------------------------
- Local postage girth not used anymore - Aus Post based on max vol 0.25 cubic meters
- define MODULE_SHIPPING_AUPOST_TAX_BASIS
- improved output formatting in debug lines

Version 2.5.5 22 Mar 2023:
---------------------------
- removed hard coded values for returned methods
- better allows for potential malformed entries when postage options changed multiple times. This is inherent intermittent Zen Cart issue.
- icons to be in template folder. Corrected format in returned array.
____________________________________

Australia Post Shipping Module 2.5.4
Version 2.5.4 21 Feb 2023:
___________________________________
Files changed in 2.5.4
- included version number
- handling fee not included on all base options for overseas

------------------------------------
Version 2.5.3 14 Feb 2023:
___________________________________
Files changed in 2.5.3
- Updated for ZC version 1.5.8 and backwards compatible with PHP 7.4


Version 2.5-2 04 Feb 2023:
___________________________________
Files changed in 2.5.2
- Updated for ZC version 1.5.8 and PHP 8.2
- Error msg and error handling when Australia Post servers are down
- define more class public vars
- move the logo display to avoid icon loading on file opening and no quote selected
- change defines
- ensure unique var names to avoid clash between aupost and aupostoverseas modules
- data load select all options and make handling fee 2.00 on all, show handling fees
- restructure logic to not fail with Australia Post invalid codes returned
- disable cache for zc158
- remove redundant options no longer available via API (may be available over the counter)


Australia Post Shipping Module 2.5.0
----------------------------------
Version 2.5-0 16 Nov 2022:
__________________________________
Files changed in 2.5.0
- Updated for ZC version 1.5.8 and PHP 8.1
- added options for regular parcels, express parcels
- round up extra cover value
- do not check insurance if below min cover amt
- Version 2.5 runs on Zen Cart 158
- rearrange logic for PHP8.1
- quotes on AUS; hide ins for parcels where value less than min ins value
- standard formatting - removes some divs
- ensure each option has unique identifier

Australia Post Shipping Module 2.4.2
----------------------------------
Version 2.4-2 31 July 2022:
__________________________________
Files changed in 2.4.2
- Updated for ZC version 1.5.7d and PHP 8.0
- defined constants in aupost.php and aupostoverseas.php
- aupost.php will only return postage charges for Australian destinations
- aupostoverseas.php will only return postage charges for overseas destinations
- postage rates with zero charges and duplicate charges are filtered out
- postage rates are sorted lowest to highest
- Australia Post information URL updated
- postage rates return all rates within a category
- maximum parcel weight set to 22kg
- defaulted weight measurement to gms
- Updated Aus Post codes
- Debug mode only shows valid options
- included letters: regular, priority and express
- included parcels: extra cover and signature for regular and satchels
- updated Australia Post icon
- included css file for debugging options
