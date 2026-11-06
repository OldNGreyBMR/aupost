splitting an ORDER
If an order exceeds allowable weight and dimensions can it be split into multiple parcels?  Whether or not this occurs should be controlled by a constant defined in the program.

I'll implement optional over-limit parcel splitting, controlled by a new constant. Let me start with the constant and the packing changes.

** Objective
Improve calculateOptimalParcel() packing in the Australia Post Zen Cart module v3.2 and add product_ships_in_own_box support with multi-parcel quotes.
Add optional splitting of orders that exceed Australia Post weight/dimension limits, controlled by a program constant.
** Requirements
- Own-box product ships in its own box: raw dimensions/weight, no parcel-size calculation, no extra packaging/tare.
- Multiple own-box units → separate parcel and quote each.
- Orders splittable into multiple Australia Post quotes; summary computes total shipping cost.
- Example: product_id = 5020, 18000 g, own-box; quote parcel without added packaging; multiple units → multiple parcels.
- Preserve existing module behaviour, error handling, and debug output where possible.
- Over-limit splitting must be controlled by a constant defined in the program.
- Do not create a git repo.
- Write a changelog note.
** Decisions
Volume-first ranking, 2D shelf packing, quantity splitting via gap candidates, and 3D stacking within layers.
Own-box units use raw product dimensions and raw weight; skip PACKING_TOLERANCE, tare, and letter handling.
Normal (non-own-box) cart items remain packed together as one shipment using calculateOptimalParcel().
Split quotes merge by Australia Post service id and sum costs; only services available for every parcel are offered.
Implemented as quote() wrapper + private quoteSingle(); split detection via buildSplitShipments().
Split mode skips letters for all shipments so every parcel uses a common parcel-service set.
Identical own-box units are quoted once in-memory per request, then counted once per unit.
Split mode bypasses the single-parcel cache and uses $_SESSION['aupostSplitParcel'] / $_SESSION['aupostSplitQuotes'].
Over-limit splitting controlled by AUPOST_SPLIT_OVERLIMIT (default 'Yes'; 'No' preserves the existing over-limit error quote).
Over-limit parcels use a first-fit-decreasing bin packer verified with the real calculateOptimalParcel().
Forced normal parcels still get packaging tare; own-box forced parcels do not, via new $applyTare parameter.
calculateOptimalParcel() now prefers cart-item dimensions and falls back to fetchProductDims().
Work State
** Completed
- 3D stacking within layers implemented and validated.
- quote() split into public wrapper and private quoteSingle().
- Own-box helpers implemented: defaultParcelDims(), isOwnBoxItem(), fetchProductDims(), ownBoxParcel(), buildSplitShipments() (replaced buildOwnBoxShipments()), quoteSplitShipments(), mergeSplitMethods(), splitCacheSignature(), splitErrorQuote().
- calculateOptimalParcel() excludes own-box items and reuses cart dimensions / fetchProductDims().
- Non-AU and invalid-postcode carts preserve original no-quote behaviour.
- CALL_FOR_BOXES box-size API call skipped for split parcels.
- PHP 8.3 lint passed and a 61-check temporary harness passed for the own-box work before the latest over-limit edits.
- Byte-identical backup created: aupost_backup-of32_own-box-split-quotes_2026-11-04.php2 (SHA-256 E6173E39D6354B9B759514491B7D622D125C712B7C1EA8E7D8444083D0063A71).
- CHANGELOG.md updated with a v3.2 entry dated 04 November 2026 covering own-box and split-quote behaviour.
- Active
- Over-limit splitting just implemented; needs lint, regression tests, and review after the latest edits.
- Blocked
- (none)
- Next Move
- Run PHP 8.3 lint on aupost.php.
- Recreate/run a test harness covering over-limit splitting, single-unit-too-large cases, own-box + over-limit normal carts, AUPOST_SPLIT_OVERLIMIT = 'No', and existing regressions.
- Fix any issues found and re-run lint/tests.
- Update CHANGELOG.md with the over-limit splitting feature.
- Create a fresh backup after final edits and report the implemented behaviour and known insurance/order-value nuance.
** Relevant Files
- E:\Web\zc222_exp\public\zc_plugins\AustraliaPost\v3.2\catalog\includes\modules\shipping\aupost.php: main module; all packing, own-box, and over-limit split changes.
- E:\Web\zc222_exp\public\zc_plugins\AustraliaPost\CHANGELOG.md: v3.2 changelog entry; needs over-limit note.
- E:\Web\zc222_exp\public\zc_plugins\AustraliaPost\v3.2\catalog\includes\modules\shipping\aupost_backup-of32_own-box-split-quotes_2026-11-04.php2: backup of own-box/split-quote state before over-limit edits.
- E:\Web\zc222_exp\public\includes\classes\shopping_cart.php: exposes ships_in_own_box, length, width, height on cart items.
- E:\Web\zc222_exp\public\includes\classes\order.php: carries ships_in_own_box into order products.
- C:\Masters\PHP\php-8.3.36-Win32-vs16-x64\php.exe: PHP binary for lint/testing (not on PATH).
- C:\Users\Bailey\AppData\Local\Temp\opencode\aupost_ownbox_test.php: temporary harness removed; recreate for new tests.
** Important Context
- No git repo; changes are applied directly and remain uncommitted.
- quote() has no internal callers; Zen Cart calls it externally.
- quoteSingle() is private and only called internally by the wrapper and split flow.
- quoteSingle() signature is now (?array $forcedParcel = null, bool $skipLetters = false, bool $rawMethods = false, bool $isSplit = false, bool $applyTare = true).
- The entire parcel section is nested inside if (MODULE_SHIPPING_AUPOST_TYPE_LETTERS <> null), so that constant must be non-empty for parcels to quote.
- Cart items expose length, width, height, weight, and ships_in_own_box; ships_in_own_box may be [0,1], boolean, or null.
- $item['id'] may include attributes; existing code uses (int) $item['id'] for DB lookups.
- USE_CACHE is hardcoded to 'No' at aupost.php line 37, so cache code is effectively inactive in production.
- Over-limit helpers added: recordsFromItems(), packRecords(), parcelSignature(), parcelWeightKg(), parcelFitsLimits(), buildOverLimitParcels(), maxQtyThatFits(), recordsFit().
- parcelFitsLimits() accounts for tare and MODULE_SHIPPING_AUPOST_WEIGHT_FORMAT == 'gms' when checking MAXWEIGHT_P.
- The harness requires stubs for base, FakeCart, FakeDb, FakeMessageStack, zen_not_null, zen_image, zen_get_tax_rate, and constants including DIR_FS_SQL_CACHE, TABLE_PRODUCTS, TABLE_PRODUCTS_DESCRIPTION, MODULE_SHIPPING_AUPOST_DEBUG, MODULE_SHIPPING_AUPOST_TARE, MODULE_SHIPPING_AUPOST_WEIGHT_FORMAT, MODULE_SHIPPING_AUPOST_DIMS, MODULE_SHIPPING_AUPOST_SPCODE, MODULE_SHIPPING_AUPOST_TYPE_LETTERS, MODULE_SHIPPING_AUPOST_COST_ON_ERROR, MODULE_SHIPPING_AUPOST_TEXT_ERROR, ERROR_NO_VALID_PARCEL_QUOTE_MSG, ERROR_MAX_LENGTH_MSG, ERROR_MAX_CUBIC_MSG, ERROR_MAX_WEIGHT_MSG, and MSGLETTERTRACKING.

+++++++++
Yes — orders that exceed Australia Post’s limits can now be split into multiple parcels, and the behaviour is controlled by a constant.

What was implemented
Constant — at the top of aupost.php:

if (!defined('AUPOST_SPLIT_OVERLIMIT')) {
    define('AUPOST_SPLIT_OVERLIMIT', 'Yes');
}
Set it to 'No' to keep the original over-limit error quote.

Over-limit splitting

Normal cart items are packed as before.
If the packed parcel exceeds the maximum weight, length, or cubic volume, the items are redistributed into multiple parcels using a first-fit-decreasing bin packer.
Every candidate parcel is re-checked with the real calculateOptimalParcel() result, so a parcel is only accepted if it genuinely fits.
Each parcel is quoted separately. Results are merged by Australia Post service id:
only services available for every parcel are offered
costs are summed into one total
titles show the parcel count, e.g. (3 parcels)
A single item that is itself overweight or oversized is not split; the existing error quote is used.
Normal split parcels still receive the configured packaging tare.
Own-box behaviour

product_ships_in_own_box units still ship in their own box.
Raw product dimensions and raw weight are used, with no packing tolerance and no tare.
Each unit becomes a separate parcel and quote.
A sub-1 kg weight is now preserved; only a missing/zero weight falls back to 1 kg.
Other

The normal cart is packed once and reused by quoteSingle(), avoiding a duplicate packing pass.
Split mode skips letter services so every parcel uses a comparable parcel-service set.
Non-AU destinations and invalid postcodes keep the previous no-quote behaviour.
Testing
PHP 8.3 lint: passed.
Temporary harness:
65 checks with over-limit splitting enabled: passed
60 checks with AUPOST_SPLIT_OVERLIMIT = 'No': passed
8 checks for gram weights + 10% tare: passed
Covered own-box parcels, multi-unit own-box, weight splits, cubic splits, mixed own-box/over-limit carts, single oversized/overweight items, service merging, duplicate service costs, and 3D packing regression.
Files changed
public/zc_plugins/AustraliaPost/v3.2/catalog/includes/modules/shipping/aupost.php
public/zc_plugins/AustraliaPost/CHANGELOG.md
Backup created:

aupost_backup-of32_own-box-and-overlimit-split_2026-11-04.php2

SHA-256: CD8115DC7082BACF77E6A859A20111ACA67C5252E6A5AFF65060B5CA0D0A5988

Known limitation
Each split parcel is still quoted using the full order value for insurance/extra cover, and handling fees apply per parcel. This is noted in the changelog.
