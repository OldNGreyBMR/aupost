# save this work. I will continue tomorrow.

## Objective
- Improve Australia Post Zen Cart module v3.2 packing and add product_ships_in_own_box support with multi-parcel quotes.
- Add optional splitting of orders that exceed Australia Post weight/dimension limits, controlled by a program constant.
## Requirements
- Own-box product ships in its own box: raw dimensions/weight, no parcel-size calculation, no extra packaging/tare.
- Multiple own-box units → separate parcel and quote each.
- Orders splittable into multiple Australia Post quotes; summary computes total shipping cost.
- Example: product_id = 5020, 18000 g, own-box; quote parcel without added packaging; multiple units → multiple parcels.
- Preserve existing module behaviour, error handling, and debug output where possible.
- Over-limit splitting must be controlled by a constant defined in the program.
- Do not create a git repo.
- Write a changelog note.
- Latest request: save the work for continuation tomorrow.
## Decisions
- Volume-first ranking, 2D shelf packing, quantity splitting via gap candidates, and 3D stacking within layers.
- Own-box units use raw product dimensions and raw weight; skip PACKING_TOLERANCE, tare, and letter handling.
- Normal (non-own-box) cart items remain packed together as one shipment using calculateOptimalParcel().
- Split quotes merge by Australia Post service id and sum costs; only services available for every parcel are offered.
- Implemented as quote() wrapper + private quoteSingle(); split detection via buildSplitShipments().
- Split mode skips letters for all shipments so every parcel uses a common parcel-service set.
- Identical own-box units are quoted once in-memory per request, then counted once per unit.
- Split mode bypasses the single-parcel cache and uses $_SESSION['aupostSplitParcel'] / $_SESSION['aupostSplitQuotes'].
- Over-limit splitting controlled by AUPOST_SPLIT_OVERLIMIT (default 'Yes'; 'No' preserves the existing over-limit error quote); accepted enable values are yes, true, 1, on.
- Over-limit parcels use a first-fit-decreasing bin packer verified with the real calculateOptimalParcel().
- Forced normal parcels still get packaging tare; own-box forced parcels do not, via $applyTare.
- calculateOptimalParcel() prefers cart-item dimensions via itemDims() and falls back to fetchProductDims().
- packRecords() merges identical records before packing.
- Normal cart packing is reused through precomputedNormalParcel to avoid a second packing pass.
- Own-box raw weight is preserved below 1 kg; only missing/zero weight falls back to 1 kg.
## Work State
## Completed
- 3D stacking within layers implemented and validated.
- quote() split into public wrapper and private quoteSingle().
- Own-box helpers implemented: defaultParcelDims(), isOwnBoxItem(), fetchProductDims(), itemDims(), ownBoxParcel(), buildSplitShipments(), quoteSplitShipments(), mergeSplitMethods(), splitCacheSignature(), splitErrorQuote().
- Over-limit helpers implemented: recordsFromItems(), packRecords(), parcelSignature(), parcelWeightKg(), parcelFitsLimits(), buildOverLimitParcels(), maxQtyThatFits(), recordsFit().
- AUPOST_SPLIT_OVERLIMIT added near the top of aupost.php.
- buildSplitShipments() guards against MODULE_SHIPPING_AUPOST_TYPE_LETTERS == null.
- parcelFitsLimits() mirrors quoteSingle() by ceiling height, reorienting, and applying weight/length/cube limits.
- mergeSplitMethods() keeps the cheapest duplicate service per parcel and sums common services across parcels.
- PHP 8.3 lint passed.
- Temporary harness passed: 65 checks with splitting enabled, 60 with AUPOST_SPLIT_OVERLIMIT = 'No', and 8 gram-weight/10% tare checks.
- CHANGELOG.md updated with own-box and over-limit split behaviour.
- Module header updated with the 2026-11-04 own-box/over-limit note.
- Final backup created and verified byte-identical to the module: aupost_backup-of32_own-box-and-overlimit-split_2026-11-04.php2, SHA-256 CD8115DC7082BACF77E6A859A20111ACA67C5252E6A5AFF65060B5CA0D0A5988.
- Temporary test harnesses removed.
## Active
- Saving the work for tomorrow: resume note and snapshot archive have not yet been created.
- Last check confirmed public/zc_plugins/AustraliaPost/WORK_IN_PROGRESS.md does not exist.
## Blocked
- (none)
## Next Move
- Create public/zc_plugins/AustraliaPost/WORK_IN_PROGRESS.md with status, tests, backup hash, constant usage, known limitation, and next steps.
- Create a snapshot zip (for example AustraliaPost_v3.2_work_snapshot_2026-11-04.zip) containing aupost.php, CHANGELOG.md, and the resume note; verify contents and hash.
- Report the saved paths and hashes to the user.
- Tomorrow: live checkout testing with product_id 5020 and multiple own-box units; verify merged split services and totals; review insurance/order-value handling per parcel.
## Relevant Files
- E:\Web\zc222_exp\public\zc_plugins\AustraliaPost\v3.2\catalog\includes\modules\shipping\aupost.php: main module; all packing, own-box, and over-limit split changes. 
- E:\Web\zc222_exp\public\zc_plugins\AustraliaPost\CHANGELOG.md: v3.2 changelog entry covering own-box and over-limit behaviour.
- E:\Web\zc222_exp\public\zc_plugins\AustraliaPost\v3.2\catalog\includes\modules\shipping\aupost_backup-of32_own-box-and-overlimit-split_2026-11-04.php2: final verified backup, SHA-256 CD8115DC7082BACF77E6A859A20111ACA67C5252E6A5AFF65060B5CA0D0A5988.

- E:\Web\zc222_exp\public\zc_plugins\AustraliaPost\WORK_IN_PROGRESS.md: planned resume note; not yet created.
- E:\Web\zc222_exp\public\includes\classes\shopping_cart.php: exposes ships_in_own_box, length, width, height on cart items.
- E:\Web\zc222_exp\public\includes\classes\order.php: carries ships_in_own_box into order products.
- C:\Masters\PHP\php-8.3.36-Win32-vs16-x64\php.exe: PHP binary for lint/testing, not on PATH.
- C:\Users\Bailey\AppData\Local\Temp\opencode\: approved temp directory for snapshot staging and temporary harnesses.

## Important Context
- No git repo; changes are saved directly on disk and remain uncommitted.
- quote() has no internal callers; Zen Cart calls it externally. quoteSingle() is private.
- quoteSingle() signature: (?array $forcedParcel = null, bool $skipLetters = false, bool $rawMethods = false, bool $isSplit = false, bool $applyTare = true).
- The parcel section is nested inside if (MODULE_SHIPPING_AUPOST_TYPE_LETTERS <> null), so that constant must be non-empty for parcels to quote; split detection now checks the same condition.
- USE_CACHE is hardcoded to 'No' at aupost.php line ~39, so cache code is effectively inactive.
- AUPOST_SPLIT_OVERLIMIT is defined at aupost.php lines ~45-47; set to 'No' to disable over-limit splitting.
- Cart items expose length, width, height, weight, and ships_in_own_box; ships_in_own_box may be [0,1], boolean, or null.
- $item['id'] may include attributes; DB lookups use (int) $item['id'].
- Over-limit packing verifies every parcel with calculateOptimalParcel() and rejects the split if any parcel does not fit.
- A single item that is itself overweight or oversized is not split; the existing over-limit error quote is used.
- Known limitation: each parcel is quoted with the full order value for insurance/extra cover, and handling fees apply per parcel.
- Final module and backup both hash to CD8115DC7082BACF77E6A859A20111ACA67C5252E6A5AFF65060B5CA0D0A5988.
- Prior backups remain in the shipping directory, including aupost_backup-of32_own-box-split-quotes_2026-11-04.php2.
+++++
## Saved for tomorrow.

## Resume note
- public/zc_plugins/AustraliaPost/WORK_IN_PROGRESS.md
- SHA-256: 7087A0FBF89EF33BBEC51CB3EF07462AD88971508FA3E2CFEB5D560C217A3763

## Snapshot archive
- public/zc_plugins/AustraliaPost/AustraliaPost_v3.2_work_snapshot_2026-11-04.zip
- SHA-256: CD0259B0CACDEE6D8272D94C5A012EE79DB7FF4121B5B9098EA898255EEA327E
- Contains aupost.php, CHANGELOG.md, and WORK_IN_PROGRESS.md; verified by extracting and re-hashing.
## Live module
- aupost.php SHA-256: 809B560F8B24CDD0EE7728304B5B64CB8F8C83B63BDC4319BE6B22D66D86820A
- PHP 8.3 lint passes.

One difference from the earlier checkpoint: the live module now has BMH_P_DEBUG4_BOXES and CALL_FOR_BOXES set to 'No', which is the production-safe state. The existing .php2 backup has both set to 'Yes'; the resume note records this.

Tomorrow's first task is the live checkout test with product_id 5020, including multiple own-box units, merged service totals, and the AUPOST_SPLIT_OVERLIMIT = 'No' fallback.


