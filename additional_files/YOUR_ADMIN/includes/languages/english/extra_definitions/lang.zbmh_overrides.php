<?php
/*
*/
// BMH 2024-12-05   setlocale;
//                  ln353 MENU_CATEGORIES_TO_SORT_BY_NAME;
//                  DATE FORMATS/
//                  HEADER LOGOS ; HEADER ALT TEXT
//                  PHP_DATE_TIME_FORMAT
//                  SITE_TAGLINE
//                  TEXT_PRODUCT_WEIGHT_UNIT
// 2025-01-01       logo to webp blue on transparent

@setlocale(LC_TIME, ['en_AU', 'en_AU.utf8mb4', 'en', 'English_Australia.1252']); //@setlocale(LC_TIME, ['en_US', 'en_US.utf8', 'en', 'English_United States.1252']);

$define = [
    'DATE_FORMAT' => 'd/m/Y',                                           /* BMH 'DATE_FORMAT' => 'm/d/Y', */
    'DATE_FORMAT_LONG' => '%a %d %B, %Y' . ' %H:%M:%S' ,                /* BMH for BISN 'DATE_FORMAT_LONG' => '%A %d %B, %Y', */
    'DATE_FORMAT_SHORT' => '%d/%m/%Y',                                  /* BMH 'DATE_FORMAT_SHORT' => '%m/%d/%Y', */
    'DATE_FORMAT_SPIFFYCAL' => 'dd/MM/yyyy',                            /* BMH 'DATE_FORMAT_SPIFFYCAL' => 'MM/dd/yyyy', */
    'ENTRY_DATE_OF_BIRTH' => 'Date of Birth:', 'ENTRY_DATE_OF_BIRTH_ERROR' => '&nbsp;<span class="errorText">(eg. 21/05/1970)</span>', /* BMH 'ENTRY_DATE_OF_BIRTH_ERROR' => '&nbsp;<span class="errorText">(eg. 05/21/1970)</span>', */
    'HEADER_ALT_TEXT' => 'BMH Trading',                                 /* BMH 'HEADER_ALT_TEXT' => 'Admin Powered by Zen Cart :: The Art of E-Commerce', */
    //'HEADER_LOGO_IMAGE' => 'logo.jpg',                                  /* BMH 'HEADER_LOGO_IMAGE' => 'logo.gif', */
    'HEADER_LOGO_IMAGE' => 'bmh-logo-blue-on-transparent-320.webp',                                  /* BMH 'HEADER_LOGO_IMAGE' => 'logo.gif', */
    //'HEADER_LOGO_WIDTH' => '350',                                       /* BMH 'HEADER_LOGO_WIDTH' => '192', */
    'HEADER_LOGO_WIDTH' => 'auto',                                       /* BMH 'HEADER_LOGO_WIDTH' => '192', */
    'HEADER_LOGO_HEIGHT' => '35',                                       /* BMH'HEADER_LOGO_HEIGHT' => '68', */
    'MENU_CATEGORIES_TO_SORT_BY_NAME' => 'configuration,reports,tools', /* BMH     //'MENU_CATEGORIES_TO_SORT_BY_NAME' => 'reports,tools', */
    'PHP_DATE_TIME_FORMAT' => 'd/m/Y H:i:s',                            /* BMH'PHP_DATE_TIME_FORMAT' => 'm/d/Y H:i:s', */
    'SITE_TAGLINE' => 'Classic Motorcycle Parts',                       /* BMH  2024*/
    'PHP_DATE_TIME_FORMAT' => 'd/m/Y H:i:s',                            /* BMH'PHP_DATE_TIME_FORMAT' => 'm/d/Y H:i:s', */
    'SITE_TAGLINE' => 'Classic Motorcycle Parts',                       /* BMH  2024*/
    'TEXT_PRODUCT_WEIGHT_UNIT' => 'grams',                              /* BMH   'TEXT_PRODUCT_WEIGHT_UNIT' => 'lbs', */
    'TEXT_SHIPPING_KGS' => '(gms)',                                     /* BMH 'TEXT_SHIPPING_KGS' => '(kgs)', */

];
return $define;
