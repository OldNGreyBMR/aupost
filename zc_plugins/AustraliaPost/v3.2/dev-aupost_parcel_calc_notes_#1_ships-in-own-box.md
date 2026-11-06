aupost_parcel_cal_notes_ships-in-own-box

n the products fields there is a field named "product_ships_in_own_box". If this value is true that  product is shipped without calculating parcel size or adding extra packaging . if more than one of this type of product is in the shopping cart each should ship as is . This means that orders must be able to be split into multiple quotes from australia post and then a summary presented to calculate total shipping cost. An example is product_id = 5020 that has  at total weight of 18000 grams and is marked as product_ships_in_own_box. If this is in the shopping cart the postal quote should be for the parcel without added packaging. If more than oneof these items is in the shopping cart multiple quotes will be required and they will be shipped as separate  parcels.
++++++++++++++++++++++++++++++++++++++++++++++++

** Objective
 - Improve calculateOptimalParcel() packing in the Australia Post Zen Cart module v3.2, then add support for products flagged product_ships_in_own_box, including splitting an order into multiple Australia Post quotes and presenting a combined shipping total.
** Requirements
If product_ships_in_own_box is true, that product ships in its own box with no parcel-size calculation and no extra packaging/tare.
If more than one own-box unit is in the cart, each unit ships as a separate parcel and requires its own quote.
Orders must be splittable into multiple Australia Post quotes, with a summary that calculates total shipping cost.
Example: product_id = 5020, total weight 18000 grams, marked own-box; quote should be for the parcel without added packaging; multiple units mean multiple separate parcels.
Preserve existing module behaviour, error handling, and debug output where possible.
Decisions
Earlier packing work kept: volume-first ranking because Australia Post bills cubic weight; 2D shelf packing; quantity splitting via gap candidates; 3D stacking within layers.
Own-box units use raw product dimensions and raw weight; skip PACKING_TOLERANCE, tare, and letter handling.
Normal (non-own-box) cart items remain packed together as one shipment using calculateOptimalParcel().
Split quotes merge by Australia Post service id and sum costs; only services available for every parcel are offered.
Implemented as quote() wrapper + private quoteSingle(); split detection via buildOwnBoxShipments().
Split mode skips letters for all shipments so every parcel uses a common parcel-service set.
Identical own-box units are quoted once in-memory per request, then their cost is counted once per unit.
Split mode bypasses the single-parcel cache and uses $_SESSION['aupostSplitParcel'] / $_SESSION['aupostSplitQuotes']; stale $_SESSION['aupostParcel'] is cleared.
Per-parcel handling fees are summed; insurance/order-value allocation across parcels remains a known nuance.
Work State
Completed
3D stacking within layers implemented and validated (previous work).
quote() split into public wrapper and private quoteSingle($method = '', ?array $forcedParcel = null, bool $skipLetters = false, bool $rawMethods = false, bool $isSplit = false).
Added helpers: defaultParcelDims(), isOwnBoxItem(), fetchProductDims(), ownBoxParcel(), buildOwnBoxShipments(), quoteSplitShipments(), mergeSplitMethods(), splitCacheSignature(), splitErrorQuote().
calculateOptimalParcel() now excludes own-box items and reuses fetchProductDims().
quoteSingle() uses forced parcels, skips tare/letters for own-box, skips cache/session writes in split mode, and preserves all services when $rawMethods is true.
Non-AU and invalid-postcode carts preserve the original no-quote behaviour.
CALL_FOR_BOXES box-size API call skipped for split parcels; misleading debug message avoided.
PHP 8.3 lint passes.
Temporary harness C:\Users\Bailey\AppData\Local\Temp\opencode\aupost_ownbox_test.php passes 61 checks with no warnings/deprecations, covering own-box detection, shipments, dimensions, weight/tare, normal-cart regression, merge intersection/summing, error paths, cache guards, non-AU, and invalid postcode.
Active
Final code review/verification of the split-quote implementation; no functional gaps known.
Blocked
(none)
Next Move
Re-run PHP 8.3 lint and the temporary harness after any further edits.
Review the final quoteSingle() / helper sections for consistency and remove or update stale commented-out own-box code if desired.
Remove the temporary test harness when done.
Report the implemented behaviour and known insurance/order-value allocation nuance.
Relevant Files
E:\Web\zc222_exp\public\zc_plugins\AustraliaPost\v3.2\catalog\includes\modules\shipping\aupost.php: main module; all packing and split-quote changes.
E:\Web\zc222_exp\public\includes\classes\shopping_cart.php: exposes ships_in_own_box on cart items at line 1409.
E:\Web\zc222_exp\public\includes\classes\order.php: carries ships_in_own_box into order products at line 629.
C:\Masters\PHP\php-8.3.36-Win32-vs16-x64\php.exe: PHP binary for lint/testing (not on PATH).
C:\Users\Bailey\AppData\Local\Temp\opencode\aupost_ownbox_test.php: temporary 61-check harness for the new logic.
Important Context
No git repo; changes are applied directly and remain uncommitted.
quote() has no internal callers; Zen Cart calls it externally.
quoteSingle() is private and only called internally by the wrapper and split flow.
The entire parcel section is nested inside if (MODULE_SHIPPING_AUPOST_TYPE_LETTERS <> null), so that constant must be non-empty for parcels to quote.
Cart items expose length, width, height, weight, and ships_in_own_box; ships_in_own_box may be [0,1], boolean, or null.
$item['id'] may include attributes; existing code uses (int) $item['id'] for DB lookups.
USE_CACHE is hardcoded to 'No' at aupost.php line 37, so cache code is effectively inactive in production.
quote() early single-method/session path uses $_SESSION['aupostQuotes'] and runs before split detection.
The harness requires stubs for base, FakeCart, FakeDb, FakeMessageStack, zen_not_null, zen_image, zen_get_tax_rate, and constants including DIR_FS_SQL_CACHE, TABLE_PRODUCTS, TABLE_PRODUCTS_DESCRIPTION, MODULE_SHIPPING_AUPOST_DEBUG, MODULE_SHIPPING_AUPOST_TARE, MODULE_SHIPPING_AUPOST_WEIGHT_FORMAT, MODULE_SHIPPING_AUPOST_DIMS, MODULE_SHIPPING_AUPOST_SPCODE, MODULE_SHIPPING_AUPOST_TYPE_LETTERS, MODULE_SHIPPING_AUPOST_COST_ON_ERROR, MODULE_SHIPPING_AUPOST_TEXT_ERROR, ERROR_NO_VALID_PARCEL_QUOTE_MSG, ERROR_MAX_LENGTH_MSG, ERROR_MAX_CUBIC_MSG, ERROR_MAX_WEIGHT_MSG, and MSGLETTERTRACKING.
Implemented and verified. Here's the final state.

What changed
File: public/zc_plugins/AustraliaPost/v3.2/catalog/includes/modules/shipping/aupost.php

Packing (calculateOptimalParcel)
Volume-first item ordering (Australia Post bills cubic weight).
2D shelf packing with quantity splitting via gap candidates.
3D stacking inside each layer.
Own-box items are excluded from the normal pack.
Product dimensions are fetched through the shared fetchProductDims() helper.
Own-box products
isOwnBoxItem() recognises ships_in_own_box from the cart item (1, '1', 'yes', 'true', 'on', true; falsey values ignored).
ownBoxParcel() builds a parcel from raw product dimensions and raw weight — no PACKING_TOLERANCE, no tare, no letter handling.
buildOwnBoxShipments() creates one shipment per own-box unit. All normal items are packed together as one additional shipment.
If the product has no dimensions, the configured default dimensions are used.
Split quotes
quote() is now a wrapper; the original logic lives in quoteSingle().
quoteSplitShipments() quotes each parcel, then mergeSplitMethods() keeps only services available for every parcel and sums their costs.
Titles get a (N parcels) suffix when more than one parcel is involved.
Identical own-box units are quoted once in memory and their cost is counted once per unit.
Split mode skips letters so all parcels use a common parcel-service set.
Split mode bypasses the single-parcel cache (aupostParcel is cleared) and uses aupostSplitParcel / aupostSplitQuotes when caching is enabled.
Non-AU destinations and invalid postcodes keep the original no-quote behaviour.
CALL_FOR_BOXES is skipped for split parcels to avoid an unnecessary API call per parcel.
Error handling falls back to splitErrorQuote() when no common service exists.
Verification
PHP 8.3 lint: no syntax errors.
Temporary harness: 61 checks, 0 failures, no warnings/deprecations, covering:
own-box flag parsing and shipment splitting
raw dimensions/weight and no tare
normal-cart regression
service intersection and cost summing
error paths, cache guards, non-AU and invalid postcode
single-method re-quote returning the combined split total
Harness and temporary diagnostic scripts were removed.
** Known nuances
- Each parcel is quoted with the full order value for insurance/extra cover, so a split order can over-insure compared with allocating value across parcels.
- Handling fees are applied per parcel, so the combined total includes handling for each parcel.
- Only services available for all parcels are offered; the module does not mix different services across parcels.
- No git repository is present, so the changes are applied directly and remain uncommitted.

-----------
Added the changelog entry at the top of the existing file:

public/zc_plugins/AustraliaPost/CHANGELOG.md

It documents the v3.2 changes under 04 November 2026:

calculateOptimalParcel() packing rework (volume-first, 2D shelf packing, quantity splitting, 3D stacking).
product_ships_in_own_box handling: raw dimensions/weight, no tolerance, no tare, no letter handling.
One parcel per own-box unit; normal items packed together.
Split quoting, service-id merging, summed costs, parcel-count titles.
Identical own-box units quoted once and counted per unit.
Split cache behaviour (aupostSplitParcel / aupostSplitQuotes).
Non-AU/invalid-postcode behaviour preserved.
CALL_FOR_BOXES skipped for split parcels.
Known insurance/handling nuance.
Backup file reference.
No git repository was created.

