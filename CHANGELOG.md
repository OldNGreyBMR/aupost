CHANGELOG
=========
# AusPost Shipping Module 3.2.3 10 November 2026 (weight-format fix)
--------------------------------------------
 - The admin weight format (MODULE_SHIPPING_AUPOST_WEIGHT_FORMAT) is now read once at the start of quote(), before any weight calculation.
 - In kgs mode, the parcel weight is normalised to grams before the letter checks, so letter limits and the letter API receive the correct weight. A 0.2 kg item is now 235 g including tare and envelope, not about 16 g.
 - The parcel weight is converted back to kilograms once, just before the parcel API call.
 - Missing or zero item weights now use a unit-aware minimum (1 g in gms mode, 0.001 kg in kgs mode). Previously max(weight, 1) turned a 0.2 kg item into 1 kg in kgs mode.
 - gms mode behaviour is unchanged.
 - Live-tested in kgs mode with product 2044 (0.2 kg, 2 x 2 x 0.5 cm): parcel API weight 0.22 kg, letter API weight 235 g, Large 250 letter offered at $7.55.
 - Debug switches were turned off before the commit (MODULE_SHIPPING_AUPOST_DEBUG and BMHDEBUG1..BMH_P_DEBUG4).

# AusPost Shipping Module 3.2.3 08 November 2026
--------------------------------------------
 - v3.2.2 is left untouched as the known-good version.
 - Letter quoting is now optional. The letter size limits are always defined, but letter services are only requested when at least one letter type is selected in MODULE_SHIPPING_AUPOST_TYPE_LETTERS.
 - Parcel quoting is no longer nested inside the letter branch. Parcels are quoted whether or not any letter types are configured.
 - When letters are enabled, an item that is taller than the letter limit skips the letter quote; the parcel quote still runs.
 - Removed the duplicated domestic country/postcode validation inside quoteSingle(); the check at the top of the function is now the only one.
 - buildSplitShipments() no longer returns early when letters are not configured, so own-box and over-limit split quoting works with letters disabled.
 - Live-tested with letters enabled and disabled, with items above and below the letter height limit, and with a two-parcel split order while letters were disabled.
 - Backup of the changed module: v3.2.3/catalog/includes/modules/shipping/nppBackup/aupost.php.2026-11-08_162651.bak

# AusPost Shipping Module 3.2.1 05-06 November 2026
--------------------------------------------
 - The split-overlimit switch moved from the file constant AUPOST_SPLIT_OVERLIMIT to the admin setting MODULE_SHIPPING_AUPOST_SPLIT_OVERLIMIT. It is a Yes/No selector (radio options, matching the other module switches), default Yes. Set it to No to keep the existing over-limit error quote.
 - Zero-value shipments (free products, gifts or 100% discounts) are now excluded from the minimum insurance rule and from the paid-value pool. They are quoted with a declared value of 0 and never consume cover from paid parcels.
 - Split quotes now keep paid and zero-value parcels apart during the service merge. Paid parcels keep the exact common service set (including insured options); each zero-value parcel is matched to the most specific compatible uninsured service in the same base family.
 - An all-free split order quotes every parcel at 0 and therefore offers only uninsured services (base and +sg).
 - The plugin installer's upgrade step adds the MODULE_SHIPPING_AUPOST_SPLIT_OVERLIMIT setting when upgrading from a version that did not have it, and leaves an existing value untouched.
 - The cart debug table is now printed once per product per page instead of repeating for every packing pass (including over-limit bin trials).
 - Static-analysis null-safety guard in the layer assignment (`$layers[$bestOption['layer_index'] ?? 0]`); the null case only occurs for new layers, which use the other branch, so there is no behaviour change.
 - Version strings, manifest and installer updated to 3.2.1.
 - Live-tested: paid 5020 ($500 own-box) + free normal 9001 keeps insured services; paid 5017 ($8.50) + free own-box 9002 offers base and +sig only; six 5017 (over-limit, two paid parcels) + free own-box 9002 allocates 34.00 / 17.00 / 0.00 without raising shares; all-free 9001 + 9002 offers base and +sig only; setting No restores the over-limit error quote.
 - Full checkout tested with a registered customer: 5020 + free 9001, split method Regular Insured +sig selected at 74.29 for 2 parcels. The order stored one shipping line at 74.29 with the "(2 parcels)" title, and the order total included it. Order 10643.
 - Test debug switches turned off for production: BMHDEBUG1, BMH_P_DEBUG2, BMH_P_DEBUG3, BMH_P_DEBUG4 and MODULE_SHIPPING_AUPOST_DEBUG are now No. Quoting was re-checked live with debug off.
 - Backup of the changed module: aupost_backup-of321_split-admin-zero-value_2026-11-06.php2

# AusPost Shipping Module 3.2 04-05 November 2026
--------------------------------------------
 - calculateOptimalParcel() reworked: items ordered by volume first, 2D shelf packing with quantity splitting via gap candidates, and 3D stacking within each layer.
 - Products flagged product_ships_in_own_box now ship in their own box using raw product dimensions and raw weight. No packing tolerance, no packaging tare and no letter handling are applied to these parcels.
 - Each own-box unit is treated as a separate parcel. Remaining (normal) cart items are still packed together as one shipment.
 - Orders containing own-box items are split into multiple Australia Post quotes. Results are merged by service id, only services available for every parcel are offered, and their costs are summed into one shipping total. Titles show the parcel count.
 - Identical own-box units are quoted once in memory and their cost is counted once per unit.
 - Orders whose normal (non-own-box) items exceed Australia Post's maximum weight or cubic volume can now be split into multiple parcels. Each parcel is quoted separately and the results are merged into one total. This is controlled by AUPOST_SPLIT_OVERLIMIT at the top of aupost.php (default 'Yes'); set it to 'No' to keep the existing over-limit error quote.
 - A single item that is itself over the weight or dimension limit is not split; the existing over-limit error quote is used.
 - Normal split parcels still receive the configured packaging tare; own-box parcels do not.
 - Split quotes bypass the single-parcel cache and use aupostSplitParcel / aupostSplitQuotes instead. A later single-method re-quote returns the combined split total.
 - Split quotes allocate the order total across parcels in proportion to item value, with a minimum insurable value per parcel when the order total allows. This prevents extra cover being charged on the full order value for every parcel and keeps insured services available on each parcel.
 - Non-AU destinations and invalid postcodes keep the previous no-quote behaviour.
 - The CALL_FOR_BOXES API request is skipped for split parcels.
 - Known limitation: handling fees apply per parcel.
 - Backup of the changed module: aupost_backup-of32_own-box-and-overlimit-split_2026-11-05.php2

# AusPost Shipping Module + AusPost Overseas Shipping Module 3.0.2 01 September 2026
-----------------------------------------------------------------------------------
Include Flat Rate satchels

# AusPost Shipping Module + AusPost Overseas Shipping Module 3.0.0 12 June 2026
------------------------------------------
 - Total rewrite to use json API instead of XML API
 - Fully encapsulated plugin
 - array to string for logfile;  limit letter code to only when switch is set; set domestic check earlier;
 - check PHP 8.5 compatibility
 - parcel calc moved to function; parcel size optimised, letter size optimised
 - processing time is no slower even with extra sizing calculations
 - improved postcode validation; error messages if blank
 - debugging css loaded from plugins
 - admin invoice.php renamed to invoice.php.orig on install and reverted on uninstall so the provided plugins updated invoice is used.
 - caching is set to ON by default, so if nothing has changed on the shopping cart page a new request will NOT be made to the Australia Post servers
 - invoice css from plugins; invoice has dashed fold line controlled by a switch in the file.
