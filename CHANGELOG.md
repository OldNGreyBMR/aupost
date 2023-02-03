CHANGELOG

Australia Post Shipping Module 2.4
----------------------------------
Version 2.4 April 2022:
__________________________________
Files changed in 2.4
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
