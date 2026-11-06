2026-11-05 15:09
Resumed and completed the live checkout testing. The main finding was the known insurance limitation, so I fixed it.

## Live test results
Tested through the real cart shipping estimator to QLD 4000 from 4121:

- 1 × product 5020 (own-box, 18 kg): quoted as 53 × 32 × 32 cm, 18 kg, no tare, 8 parcel services.
- 2 × 5020: 2 parcels, all 8 services; Regular $59.40 = 2 × $29.70, Express $79.00 = 2 × $39.50.
- 3 × 5020: 3 parcels; all base and insured totals match 3 × the single-parcel rates within 1 cent.
- Mixed 5020 + 5017: 2 parcels, normal parcel kept its 10% tare (4.4 kg), all 8 services.
- 6 × 5017 (24 kg): correctly split into 2 parcels (17.6 kg + 8.8 kg).
- Mixed 5020 + 6 × 5017: 3 parcels; own-box 18 kg plus two normal split parcels.
- AUPOST_SPLIT_OVERLIMIT = 'No': the old Exceeds maximum cubic vol /girth error quote still appears at $99.99. Constant restored to 'Yes'.
- Single normal cart: unchanged single-parcel behaviour.
## Insurance fix
- Previously each parcel was insured for the full order value. That overcharged multi-parcel orders and dropped insured services when the order exceeded the maximum cover.
- 
- Split quotes now allocate the order total across parcels by item value, with a minimum insurable value per parcel when the order total allows. For example, 2 × 5020 now insures each parcel for $500 instead of $1000 each, and all 8 services remain available.

## Files saved
- Module: public/zc_plugins/AustraliaPost/v3.2/catalog/includes/modules/shipping/aupost.php
- SHA-256: 634C20D022F735D1456E0F0824A6F5D653AB9E14B16B5A6BFC4DA1FC56A0921F
- Backup: .../aupost_backup-of32_own-box-and-overlimit-split_2026-11-05.php2
- Byte-identical, same hash.
- Changelog: public/zc_plugins/AustraliaPost/CHANGELOG.md
- SHA-256: 377266427CE1AECE1EBB0FE71DA7E5C36678F299E23C00D0256FC22B5E48CAC9
- Resume note: public/zc_plugins/AustraliaPost/WORK_IN_PROGRESS.md
- SHA-256: 39FCAF935D568E94CBB11846CCB7FBB87277CB5946E5B682313010C0651E95A6
- Snapshot: public/zc_plugins/AustraliaPost/AustralPost_v3.2_work_snapshot_2026-11-05.zip
- SHA-256: 2C10DCDF250778DA564AE4DCB1C4857504973F5595419616F3E46035DA7EF878
- PHP 8.3 lint passes. The test cart has been emptied.

Remaining optional work: a full logged-in checkout test, deciding whether handling fees should stay per parcel, and reviewing the zero-value item edge case in the insurance allocation.
++++++++++++++++++++++++++++++++++++++++++
# handling fees should stay per parcel, as this includes additional paperwork required, packaging materials, labels etc. 
Please explain " reviewing the zero-value item edge case in the insurance allocation".
Handling fees: understood — they stay per parcel. I updated the resume note to record that they cover the extra paperwork, packaging materials and labels.

## Zero-value item edge case
The split allocation does two things:

1 Gives each parcel a share of the order total based on the value of the items in it.
2 Raises any parcel below $102 to $102 when the order total can afford it, because the module only offers insured services when the insured value is above $101.
A zero-value item breaks the value match. That could be a free product, sample, gift, or an item reduced to $0 by a discount.

Example: a $500 paid item and a $0 free item, one parcel each, order total $500.

- Proportional shares would be $500 and $0.
- The minimum rule changes them to $398 and $102.
- Total cover is still $500, but it no longer matches the actual value at risk: the paid parcel is covered for only $398, while the free parcel carries $102 of nominal cover.
If the order total is too low to give every parcel $102, the minimum rule is skipped. The free parcel then gets $0, has no insured services, and because only services available for every parcel are offered, insured options can disappear from the whole order.

The minimum exists for legitimate low-value parcels — for example, the $8.50 normal item in the mixed-cart test. Without it, that parcel would have no insurance and the insured options would vanish for the entire order.

This is harmless for the current catalogue, which has no free items. If free or zero-priced products become common, the fix would be to exclude zero-value shipments from the minimum rule and handle their service set separately.

## Updated save files:

- WORK_IN_PROGRESS.md SHA-256: 17A1F2D464DDEE744D99BEBDDBA4994CD1CB1D9EBC7FAEB5E4E33F177EA28842
- AustraliaPost_v3.2_work_snapshot_2026-11-05.zip SHA-256: F9807B4C5D092BF39B800CAC7AE6650B84CC8E5E25E0781C669D67A44322CCFD
The module and changelog are unchanged.

===================================
# Understood. exclude zero-value shipments from the minimum rule and handle their service set separately.

## Objective
- Improve Australia Post Zen Cart module v3.2 packing and add product_ships_in_own_box support with multi-parcel quotes.
- Latest goal: exclude zero-value shipments from the insurance minimum rule and handle their service set separately.
## Requirements
- Own-box products ship in their own box using raw product dimensions and raw weight; no packing tolerance, packaging tare, or letter handling.
- Each own-box unit is a separate parcel; normal cart items remain packed together as one shipment.
- Split orders quote each parcel and merge by Australia Post service id; only services available for every parcel are offered; costs are summed.
- Over-limit splitting is controlled by AUPOST_SPLIT_OVERLIMIT in aupost.php.
- Handling fees stay per parcel; they cover extra paperwork, packaging materials, labels, etc.
- Exclude zero-value shipments from the minimum rule and handle their service set separately.
- Preserve existing module behaviour, error handling, and debug output where possible.
- No git repo; maintain changelog and resume notes.

## Decisions
- Volume-first ranking, 2D shelf packing, gap-candidate quantity splitting, and 3D stacking within layers.
- Own-box units use raw dimensions/weight; normal items use calculateOptimalParcel().
- Split mode skips letters for all shipments, bypasses the single-parcel cache, and uses $_SESSION['aupostSplitParcel'] / $_SESSION['aupostSplitQuotes'].
- Identical own-box units are quoted once in memory and counted once per unit.
- Over-limit parcels use a first-fit-decreasing bin packer verified with calculateOptimalParcel().
- Split quotes allocate the order total across parcels by item value, with a minimum MIN_INSURED_VALUE of 102 AUD when the order total allows.
- Handling fees stay per parcel, confirmed by the user.
- Zero-value shipments must be excluded from the minimum rule and merged with a separate service set; implementation is pending.
- Known limitation resolved: each parcel is no longer quoted with the full order value for insurance/extra cover.
## Work State

## Completed
- Own-box support, public quote() wrapper plus private quoteSingle(), over-limit splitting, merge by service id, and split cache/session handling.
- Insurance allocation implemented: allocateSplitOrderValues(), unit_value on records, value on parcels, $orderValueOverride in quoteSingle(), and value-aware cache signatures.
- Live tests passed for 1/2/3 units of product 5020, mixed 5020 + 5017, 6 × 5017 over-limit split, mixed 5020 + 6 × 5017, AUPOST_SPLIT_OVERLIMIT = 'No' fallback, and a single normal cart.
- Handling fee decision recorded in WORK_IN_PROGRESS.md.
- New 2026-11-05 backup and snapshot created; PHP 8.3 lint passes.
## ACtive
- Implement zero-value shipment exclusion from the minimum rule and separate service-set merging.
## Blocked
- (none)

## Next Move
- Change allocateSplitOrderValues() so shipments with value_raw <= 0 are excluded from the MIN_INSURED_VALUE floor and do not consume cover from paid parcels.
- Change split merge logic so zero-value parcels use a compatible base service while positive-value parcels keep insured services; likely use service-ID prefix/family matching instead of exact intersection.
- Add tests for paid + free item, all-free cart, order total below 102 × parcel count, and rerun existing own-box/over-limit scenarios.
- Update changelog/resume note, create a new backup/snapshot, and report hashes.

## Relevant Files
- E:\Web\zc222_exp\public\zc_plugins\AustraliaPost\v3.2\catalog\includes\modules\shipping\aupost.php: main module with all packing, own-box, split, and insurance changes.
- E:\Web\zc222_exp\public\zc_plugins\AustraliaPost\v3.2\catalog\includes\modules\shipping\aupost_backup-of32_own-box-and-overlimit-split_2026-11-05.php2: byte-identical backup.
- E:\Web\zc222_exp\public\zc_plugins\AustraliaPost\CHANGELOG.md: v3.2 changelog entry.
- E:\Web\zc222_exp\public\zc_plugins\AustraliaPost\WORK_IN_PROGRESS.md: resume note including the zero-value edge-case section.
- E:\Web\zc222_exp\public\zc_plugins\AustraliaPost\AustraliaPost_v3.2_work_snapshot_2026-11-05.zip: saved snapshot.
- E:\Web\zc222_exp\public\includes\classes\shopping_cart.php: exposes ships_in_own_box, length, width, height, weight, final_price.
- E:\Web\zc222_exp\public\includes\classes\order.php: carries ships_in_own_box into order products.
- C:\Masters\PHP\php-8.3.36-Win32-vs16-x64\php.exe: PHP binary for lint/testing.

## Important Context
- Current module/backup SHA-256: 634C20D022F735D1456E0F0824A6F5D653AB9E14B16B5A6BFC4DA1FC56A0921F.
- CHANGELOG.md SHA-256: 377266427CE1AECE1EBB0FE71DA7E5C36678F299E23C00D0256FC22B5E48CAC9.
- WORK_IN_PROGRESS.md SHA-256: 17A1F2D464DDEE744D99BEBDDBA4994CD1CB1D9EBC7FAEB5E4E33F177EA28842.
- Snapshot SHA-256: F9807B4C5D092BF39B800CAC7AE6650B84CC8E5E25E0781C669D67A44322CCFD.
- MIN_INSURED_VALUE = 102; _process_parcel_option() skips extra cover when $ordervalue <= 101.
- mergeSplitMethods() currently intersects exact service IDs and sums costs; only common services survive.
- Service IDs are prefix-structured: base AUSPARCELREGULAR; +sig appends AUSSERVICEOPTIONSIGNATUREONDELIVERY; insured adds AUSSERVICEOPTIONEXTRACOVER.
- Current allocation: proportional shares by value_raw; floor raises shares below 102 when orderTotal >= 102 × count, then removes the excess from higher shares.
- All-free or zero cart value currently falls back to null, causing quoteSingle() to use the full order total per parcel.
- Zero-value example: $500 paid + $0 free, 2 parcels, $500 total currently allocates $398 + $102; the user wants the zero-value parcel excluded from the floor and handled separately.
- Live site: https://192.168.1.63/zc222_exp/public/; test destination QLD 4000 from 4121; cart is currently empty.
- Product 5020: own-box, 53 × 32 × 32 cm, 18000 g, $500. Product 5017: normal, 485 × 385 × 280 mm, 4 kg, $8.50.
- AUPOST_SPLIT_OVERLIMIT = 'Yes'; USE_CACHE = 'No'; CALL_FOR_BOXES = 'No'.
- Debug switches BMHDEBUG1, BMH_P_DEBUG2, BMH_P_DEBUG3, BMH_P_DEBUG4 are 'Yes'; BMH_P_DEBUG4_BOXES is 'No'.
- Older 2026-11-04 backups and snapshot remain in the plugin folder.

1 Thought
I'll start by reading the current allocation and merge logic.

Now I'll update the allocation to exclude zero-value shipments and rework the merge to match zero-value parcels by service family.

Now I'll set up a live test with a temporary zero-priced product. First, let me inspect the product tables.

Test products created (9001 free normal, 9002 free own-box). Now let me run the live cart tests.

Execute: Paid + free works exactly as intended. Now the all-free cart.

Execute: Now a paid low-value item + free own-box parcel.

Used free messages. They reseta t Nov 6, 2026 10:04AM