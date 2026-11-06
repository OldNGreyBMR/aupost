in my zen cart plugin shipping module  "Australia Post" v 3.2 the parcel size is calculated in the "calculateOptimalParcel" function. If an items is 12 cm x 12cm x 1cm and 100 are selected the function stacks them to make one long parcel instead of laying them in a grid to minimise the long side. What can be done to improve the function?

++++++++++++++++++++++++++++++++++++
So the real fix is not just “lay them in a grid” — it is to let the function consider 3D grids, then rank by the longest side rather than footprint area.

Why it produces a 100 cm long parcel
For a 12 × 12 × 1 item, the function sorts the sides to h=1, w=12, l=12.

The candidate generator only creates two shapes:

Single-layer grids — w × cols, l × rows, height h
Single-column stacks — w × cols, l, height h × rows
It never creates a block that is a grid and multiple layers high, e.g. 2 × 2 × 25.

It then sorts candidates by footprint area only:

usort($candidates, fn($a, $b) => ($a['w'] * $a['l']) <=> ($b['w'] * $b['l']));
The single-column stack has the smallest footprint (12 × 12 = 144), so it always wins regardless of how tall it gets:

Stack: 12 × 12 × 100 → after tolerance/re-sort, longest side ≈ 102 cm
Flat 10×10 grid: 120 × 120 × 1 → longest side ≈ 122 cm — actually worse
Proper 3D block: 2 × 2 × 25 → 24 × 24 × 25 → longest side ≈ 25 cm
So the real fix is not just “lay them in a grid” — it is to let the function consider 3D grids, then rank by the longest side rather than footprint area.

Recommended changes
Generate all a × b × c ≥ q block arrangements.
Pair the largest count with the item’s smallest dimension so the longest side stays short.
Score candidates by longest side → girth → bounding volume → wasted cells, not footprint area.
Remove the min($q, 50) cap.
Fix the caller so it uses the configured default dimensions instead of hard-coded [5, 10, 15].
Fix the partial-dimension fallback (currently a zero dimension only falls back on that one axis).
For mixed carts, the “shared layer” branch still needs work because it does not track used floor area — more on that below.
Drop-in code
File: public\zc_plugins\AustraliaPost\v3.2\catalog\includes\modules\shipping\aupost.php

1. Use the configured default dimensions
Around line 465, change:

$result = $this->calculateOptimalParcel($_SESSION['cart'], $db, [5, 10, 15]);
to:

$result = $this->calculateOptimalParcel($_SESSION['cart'], $db, $defaultdims);
2. Replace the candidate generation
Around lines 2257–2272, replace the whole candidate block with:

// ---- Generate all candidate 3D block arrangements ----------- //
// a x b x c >= q, where a is the number of layers along the
// item's thinnest axis and b x c is the footprint grid.
// This includes blocks such as 2 x 2 x 25 for 100 x 12x12x1.
$candidates = $this->buildBlockCandidates($q, [$h, $w, $l]);

// Best = shortest longest side, then smallest girth, then
// smallest bounding volume, then least wasted cells.
usort($candidates, fn($a, $b) =>
    $this->blockScore($a) <=> $this->blockScore($b)
);
3. Stop re-sorting by footprint for new layers
Around lines 2307–2313, replace:

if (!$placed) {
    usort($candidates, fn($a, $b) =>
        ($a['w'] * $a['l']) <=> ($b['w'] * $b['l'])
        ?: max($a['w'], $a['l']) <=> max($b['w'], $b['l'])
    );
    $best = $candidates[0];
with:

if (!$placed) {
    $best = $candidates[0];   // already sorted best-first
4. Add the helper methods
Add these after calculateOptimalParcel() (before _log()):

/**
 * Generate candidate 3D blocks for $q identical items.
 *
 * Counts are returned in descending order a >= b >= c with
 * a * b * c >= q. The largest count is paired with the item's
 * smallest dimension, so the longest side is kept as short as
 * possible. The block is:
 *   height = a * d0   (layers along the thinnest axis)
 *   width  = b * d1
 *   length = c * d2
 *
 * For 100 items of 12 x 12 x 1 this includes 2 x 2 x 25,
 * i.e. 24 x 24 x 25, instead of only 12 x 12 x 100 or
 * 120 x 120 x 1.
 *
 * @param int   $q     Quantity (>= 1)
 * @param array $dims  [smallest, middle, largest] item dims in cm
 * @return array
 */
private function buildBlockCandidates(int $q, array $dims): array
{
    $q = max(1, $q);
    sort($dims);
    $d0 = max((float) ($dims[0] ?? 1), 0.01);
    $d1 = max((float) ($dims[1] ?? 1), 0.01);
    $d2 = max((float) ($dims[2] ?? 1), 0.01);

    $candidates = [];
    $seen = [];

    // The smallest count never needs to exceed the cube root of q.
    // Beyond that, every count is larger than a balanced block, so
    // the block is bigger on every axis.
    $cMax = max(1, (int) ceil(pow($q, 1 / 3)) + 1);

    for ($c = 1; $c <= $cMax; $c++) {
        for ($b = $c; $b <= $q; $b++) {
            $a = (int) ceil($q / ($b * $c));
            if ($a < $b) {
                break; // increasing b only makes a smaller
            }

            $height = $a * $d0;          // stack along the thinnest axis
            $f1 = $b * $d1;
            $f2 = $c * $d2;
            $width = min($f1, $f2);
            $length = max($f1, $f2);

            $key = round($width, 3) . 'x' . round($length, 3) . 'x' . round($height, 3);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $candidates[] = [
                'w' => $width,
                'l' => $length,
                'h' => $height,
                'cols' => $c,
                'rows' => $b,
                'layers' => $a,
                'waste' => ($a * $b * $c) - $q,
            ];
        }
    }

    return $candidates;
}

/**
 * Score a block; lower is better.
 *
 * Order: longest side, girth, bounding volume, wasted cells.
 * Change the order if your service penalises girth or cubic
 * weight more than length.
 *
 * @param array $block
 * @return array
 */
private function blockScore(array $block): array
{
    $sides = [$block['h'], $block['w'], $block['l']];
    sort($sides);

    return [
        $sides[2],                          // longest side
        2 * ($sides[0] + $sides[1]),        // girth of the two shorter sides
        $sides[0] * $sides[1] * $sides[2],  // bounding volume
        $block['waste'] ?? 0,               // prefer exact blocks on ties
    ];
}
5. Optional robustness fix for missing dimensions
Around lines 2210–2221, this:

$row = $dims->fields ?: [];
$sides = [
    (float) ($row['products_width'] ?? $defaultdims[1]),
    (float) ($row['products_height'] ?? $defaultdims[0]),
    (float) ($row['products_length'] ?? $defaultdims[2]),
];
sort($sides);

$h = $sides[0] ?: $defaultdims[0];
$w_dim = $sides[1] ?: $defaultdims[1];
$l = $sides[2] ?: $defaultdims[2];
would be better as:

$row = $dims->fields ?: [];
$raw = [
    (float) ($row['products_width'] ?? 0),
    (float) ($row['products_height'] ?? 0),
    (float) ($row['products_length'] ?? 0),
];

if (min($raw) <= 0) {
    // If any side is missing/zero, use the complete default set
    $raw = [
        (float) $defaultdims[1],
        (float) $defaultdims[0],
        (float) $defaultdims[2],
    ];
}

sort($raw);
[$h, $w_dim, $l] = $raw;
Expected result
For 100 × 12 × 12 × 1:

Arrangement	Parcel after tolerance	Longest side
Current single stack	12.3 × 12.3 × 102	102 cm
Single-layer 10×10 grid	122.4 × 122.4 × 1.02	122 cm
New 2 × 2 × 25 block	24.5 × 24.5 × 25.5	25.5 cm
The cubic volume is the same in all exact packings (14,400 cm³), so the new block is also no worse for cubic weight.

Remaining limitation: mixed carts
The change above fixes the single-product case. For carts with multiple different products, the existing “shared layer” logic is still optimistic:

if ($c['w'] <= $layer['w'] && $c['l'] <= $layer['l']) {
    $layer['h'] = max($layer['h'], $c['h']);
It checks whether a block fits inside a layer’s footprint but does not track how much of that footprint is already used. So two separate blocks can be treated as occupying the same floor space. For mixed carts, replace that branch with a proper 2D shelf/rectangle packer, or conservatively start a new layer for each product block. That is a larger change than the single-product fix, but worth doing if mixed carts are common.

I can apply the patch above to v3.2 if you want, and I’d also suggest testing with these cases: 100 × 12×12×1, 50 × 12×12×1, 10 × 5×5×100, and a mixed cart.


++++++++++++++++++++++++++++++

Changes made
Full 3D candidate search — replaced the old flat-grid / single-stack generator with buildBlockCandidates(), which enumerates a × b × c ≥ q blocks. For 100 × 12×12×1 it now finds 2 × 2 × 25 → 24 × 24 × 25.
Better ranking — added blockScore(). Candidates are now ranked by:
longest side
girth
bounding volume
wasted cells
instead of footprint area, which previously always favoured the tallest stack.

New-layer selection — removed the old footprint-area re-sort; it now uses the already-ranked candidate list.
Default dimensions fixed — calculateOptimalParcel() now receives the configured $defaultdims instead of the hard-coded [5, 10, 15]. Also added a safe fallback if the admin setting is blank.
Dimension fallback fixed — if any product dimension is missing/zero, the complete default set is used instead of mixing zeros with defaults.
Docblock updated to describe the new strategy.
Verification
PHP 8.3 lint: No syntax errors detected.
Tested the actual class methods via reflection:

XXXXXXXXXXXXXXXXXX
Mixed carts are common. Please implement a 2D shelf/rectangle packer.

bjective
Improve the calculateOptimalParcel() function in the Australia Post Zen Cart shipping module v3.2 so quantities like 100 × 12×12×1 pack into a compact 3D block instead of one long stack, and mixed carts use a real 2D shelf/rectangle packer.
Requirements
User asked what could improve the function, then explicitly requested the proposed patch be applied to v3.2.
User then requested: "Mixed carts are common. Please implement a 2D shelf/rectangle packer."
Preserve existing module behavior, error handling, and debug output where possible.
Decisions
Replaced footprint-area ranking with 3D block candidate generation and ranking by longest side → girth → bounding volume → wasted cells.
For 100 × 12×12×1, chosen block is 2×2×25 → 24×24×25; final parcel after tolerance/existing ceil() is 26 × 24.5 × 24.5 (longest side 26 vs old 102).
Implemented a shelf packer per layer that tracks shelves (y, depth, used_w) and placements (x, y, w, l, h) to prevent overlapping blocks.
Placement choice evaluates every candidate block, both footprint orientations, every existing layer, and a new layer; picks best overall parcel score.
parcelScore/scorePlacement uses longest side, girth, volume, then layer count.
Work State
Completed
Applied 3D block candidate generation, blockScore(), default-dimension fix, and partial-dimension fallback.
Added buildBlockCandidates() and blockScore() helpers.
Added shelf-packer helpers: newLayer(), tryPlaceInLayer(), scorePlacement().
Replaced old shared-layer logic in the main loop with best-placement evaluation.
Updated the calculateOptimalParcel() docblock to describe the shelf packer.
PHP 8.3 lint passed after all edits: No syntax errors detected.
Earlier reflection test confirmed actual methods produce 24×24×25 for 100 × 12×12×1.
Active
Validating the new shelf packer with a mixed-cart test script just created at C:\Users\Bailey\AppData\Local\Temp\opencode\aupost_shelf_test.php.
The test script mirrors the main loop and checks for overlapping placements/out-of-bounds rectangles.
Blocked
(none)
Next Move
Run C:\Users\Bailey\AppData\Local\Temp\opencode\aupost_shelf_test.php with C:\Masters\PHP\php-8.3.36-Win32-vs16-x64\php.exe.
Inspect output for overlaps, out-of-bounds placements, and sensible parcel dimensions across the mixed-cart scenarios.
Re-lint the modified module after any further fixes.
Remove temporary test scripts when finished.
Report the shelf-packer implementation, test results, and any remaining limitations (whole-quantity blocks only; no quantity splitting across layers).
Relevant Files
E:\Web\zc222_exp\public\zc_plugins\AustraliaPost\v3.2\catalog\includes\modules\shipping\aupost.php: main modified module containing all changes.
C:\Users\Bailey\AppData\Local\Temp\opencode\aupost_shelf_test.php: mixed-cart shelf-packer validation script, not yet run.
C:\Masters\PHP\php-8.3.36-Win32-vs16-x64\php.exe: PHP binary used for lint/testing.
Important Context
The module file uses declare(strict_types=1) and class aupost extends base.
PHP is not on PATH; use the full binary path above.
$defaultdims is now passed from quote() and is a sorted float array [height, width, length]; blank admin setting falls back to [2,10,10].
calculateOptimalParcel() starts around line 2187; buildBlockCandidates() around line 2495; blockScore() around line 2529; new shelf helpers follow around lines 2542+.
$layers entries now require w, l, h, shelves, and placements; all layers are created through newLayer().
tryPlaceInLayer() places in the best-fit existing shelf or starts a new shelf at y = layer.l; it returns a new layer array and never mutates the original.
scorePlacement() signature: (array $layers, ?int $replaceIndex, ?array $replacement, ?array $extra).
The mixed-cart test script uses reflection to call the private methods and defines a stub base class plus DIR_FS_SQL_CACHE.
No git repo; changes are applied directly and uncommitted.
I'll run the shelf-packer validation script now.

pack() collides with a PHP builtin. Renaming it.

No overlaps or out-of-bounds placements in any scenario. Let me review the final code state and check a few edge cases in the packer.

The packer is correct (no overlaps), but the placement scoring is producing unnecessarily bulky parcels in mixed carts. Example: two identical 100 × 12×12×1 products give 36×36×34 (44,064 cm³) when 24×48×25 (28,800 cm³) is available. In one test the bounding box reached 0.252 m³ — just over the module's 0.25 m³ limit — even though the items total only 0.085 m³.

All tests pass. Cleaning up the temporary test scripts.

Implemented in public\zc_plugins\AustraliaPost\v3.2\catalog\includes\modules\shipping\aupost.php.

What changed
2D shelf packer

Layers are now real slabs rather than a bare w × l × h triple. Each layer tracks:

shelves[] — {y, depth, used_w} rows within the slab
placements[] — {x, y, w, l, h} for every block placed
Three new helpers:

newLayer() — creates a layer with one shelf and one placement
tryPlaceInLayer() — best-fit into an existing shelf, otherwise starts a new shelf at y = layer.l. Returns a copy, never mutates the original.
scorePlacement() — evaluates the whole parcel that would result from a placement.
The main loop now evaluates every candidate block × both footprint orientations × every existing layer, plus a new layer, and keeps the best result. Two products can no longer be treated as occupying the same floor space.

Ranking changed to volume-first

While validating I found the previous "shortest longest side" objective was producing needlessly bulky parcels — and in one valid mixed cart the bounding box hit 0.252 m³, just over the module's 0.25 m³ limit, even though the items totalled only 0.085 m³. That would have rejected the quote.

Ranking is now:

within the 105 cm parcel length limit (accounting for the existing 2% packing tolerance)
smallest cube / bounding volume (Australia Post bills cubic weight)
shortest longest side
smallest girth
fewest layers
The tolerance is now a shared PACKING_TOLERANCE constant so the packer and the final dimension rounding can't drift apart.

Test results
End-to-end through the real calculateOptimalParcel() (fake cart/DB), plus overlap and bounds validation of every placement:

Cart	Before	After	Item volume
2 × 100 items 12×12×1	36×36×34 (44,064)	24×48×25 (28,800)	28,800
100 × 12×12×1 + 20 × 10×10×5	34×30×35 (35,700)	24×44×25 (26,400)	24,400
100 × 12×12×1 + 10 × 10×10×30	44×30×50 (66,000)	30×24×75 (54,000)	44,400
5 products × 10 × 10×10×2	30×20×30 (18,000)	50×10×20 (10,000)	10,000
100 × 12×12×1 (regression)	24×24×25	24×24×25	14,400
No overlapping or out-of-bounds placements in any scenario.
Long-item carts stay under 105 cm: 20 × 5×5×100 → raw 20×100×25, final 20.4×25.5×102.
1000 × 10×10×2 → 51×51×81.6, 94% efficient, 1.1 ms.
A 12-line mixed cart with a valid total volume (0.032 m³) → 28.6×16.4×100, 68% efficient, 1.1 ms.
The only over-105 cm result was a deliberately invalid cart whose items total 0.455 m³ — already over the 0.25 m³ limit, so the module rejects it regardless.
PHP 8.3 lint: No syntax errors detected.
Debug output and the existing error handling were left intact; the packing log now also records layer, x, and y.

** Limitations

- Greedy, no backtracking. Products are packed largest-volume-first and decisions aren't revisited. A pathological ordering can still leave a parcel larger than the theoretical minimum.
- Whole-quantity blocks only. A product's quantity is never split across shelves or layers (e.g. 60 items won't become 40 on one layer and 20 on another). This keeps the packing log readable but can waste space for awkward quantities.
- Shelf packing, not free-form rectangle packing. Blocks go into a shelf row or start a new shelf at the end of the layer; they aren't placed into arbitrary leftover gaps.
- Length guard is a preference, not a guarantee. If every arrangement exceeds 105 cm, the quote still errors as before — correctly, since the parcel genuinely can't be sent.

If you'd like, the next step could be splitting a quantity across layers (bounded by, say, two sub-blocks per product), which would usually recover the remaining 5–15% of wasted cube.

++++++++++++++++++++
Please try the to recover the remaining wasted space.

Thought Splitting helps a little, but most cases are unchanged. Let me inspect where the waste actually is in the worst valid case (the 12-line cart).

Objective
Improve calculateOptimalParcel() in the Australia Post Zen Cart shipping module v3.2 so mixed carts pack efficiently, then recover the remaining wasted space.
Earlier work replaced long stacks with 3D block candidates; user then requested a 2D shelf/rectangle packer, and now asks: "Please try the to recover the remaining wasted space."
Requirements
Preserve existing module behaviour, error handling, and debug output where possible.
Mixed carts are common and must pack correctly (no overlapping placements).
Recover as much of the remaining wasted parcel volume as practical.
Decisions
Volume-first ranking (blockScore/scorePlacement) replaced longest-side-first because Australia Post bills cubic weight; the old ordering produced a 0.252 m³ parcel (over the 0.25 m³ limit) for items totalling only 0.085 m³.
Placement score order: legal length → least over-length → smallest cube → shortest longest side → smallest girth → fewest layers.
Length guard compares against MAXLENGTH_P / PACKING_TOLERANCE so the final 2% padded dimensions still pass the 105 cm check.
PACKING_TOLERANCE = 1.02 is now a shared private class constant used by both the packer and the final rounding.
Shelf packer uses best-fit shelf, else a new shelf at y = layer.l; tryPlaceInLayer() never mutates the input layer.
Quantity splitting implemented via gap-filling candidates limited to free shelf width; 60-attempt safety valve; ties prefer consuming more items.
Work State
Completed
3D block candidate generation and volume-first ranking.
2D shelf packer: newLayer(), tryPlaceInLayer(), scorePlacement(); layers now hold w, l, h, shelves[], placements[].
Full placement search: every candidate block × both orientations × every layer, plus new layer; no overlaps or out-of-bounds in any test.
Length-limit guard, shared PACKING_TOLERANCE, float epsilons in fit and over-length checks.
Quantity splitting: 'qty' added to block candidates; new buildGapCandidates(); main loop converted to while ($remaining > 0) with per-placement packing log entries.
Validation passed: quantity totals, reconstructed layer dimensions, overlap checks, performance ~0.02–3.6 ms.
PHP 8.3 lint passed after the main loop rewrite (No syntax errors detected); split test ran successfully after later helper edits.
Active
Investigating why splitting recovers little: diagnostic script created at C:\Users\Bailey\AppData\Local\Temp\opencode\aupost_diag.php but not yet run.
Analysis so far: waste is mainly layer-footprint mismatch and shelf depth waste, not shelf-width gaps, so gap splitting only changed one case (100×12×12×1 + 10×10×10×30: 54,000 → 53,604 cm³ with tolerance).
Blocked
(none)
Next Move
Run aupost_diag.php to inspect per-layer footprints and fill for the 12-line cart.
Decide whether to upgrade shelves to a true 2D rectangle/guillotine or height-map packer (to recover depth and footprint waste) or report splitting gains as marginal.
Re-run PHP 8.3 lint on the module after the final buildGapCandidates edits.
Remove temporary test/diagnostic scripts when finished.
Report results, including the qty-0 behaviour change and remaining limitations.
Relevant Files
E:\Web\zc222_exp\public\zc_plugins\AustraliaPost\v3.2\catalog\includes\modules\shipping\aupost.php: main module with all changes.
C:\Users\Bailey\AppData\Local\Temp\opencode\aupost_split_test.php: end-to-end test using reflection, FakeCart/FakeDb, overlap/qty/dimension validation.
C:\Users\Bailey\AppData\Local\Temp\opencode\aupost_diag.php: layer-footprint diagnostic for the 12-line cart, not yet run.
C:\Masters\PHP\php-8.3.36-Win32-vs16-x64\php.exe: PHP binary for lint/testing.
Important Context
PHP is not on PATH; use the full binary path above.
Module uses declare(strict_types=1), class aupost extends base; no git repo, changes are uncommitted and applied directly.
calculateOptimalParcel() starts ~line 2192; buildBlockCandidates() ~2510; buildGapCandidates() ~2591; blockScore() ~2684; shelf helpers follow after that.
Layer shape: ['w'=>float,'l'=>float,'h'=>float,'shelves'=>[['y'=>float,'depth'=>float,'used_w'=>float]],'placements'=>[['x'=>float,'y'=>float,'w'=>float,'l'=>float,'h'=>float]]].
scorePlacement(array $layers, ?int $replaceIndex, ?array $replacement, ?array $extra) returns a 6-element score array.
buildGapCandidates(array $layers, array $dims, int $qty) returns blocks with 'qty' => capacity; whole-quantity candidates carry 'qty' => $q.
Test harness defines DIR_FS_SQL_CACHE, TABLE_PRODUCTS, TABLE_PRODUCTS_DESCRIPTION, MODULE_SHIPPING_AUPOST_DEBUG, MODULE_SHIPPING_AUPOST_TARE, MODULE_SHIPPING_AUPOST_WEIGHT_FORMAT, VERSION_AU, plus a stub base class.
Test cart items must include 'weight' or the module emits Undefined array key "weight" warnings.
qty 0 now yields no layers and final dims 0.00 / 0.00 / 0.00 (previously clamped to one item via buildBlockCandidates); Zen Cart carts should never contain qty 0.
The 6-medium-line test cart (0.275 m³) and the 12-line high-qty cart (0.455 m³) exceed MAXCUBIC_P = 0.25, so their over-limit results are expected and invalid as success criteria.
Before/after (valid carts): two identical 100×12×12×1 36×36×34 → 24×48×25; flat+small flat 34×30×35 → 24×44×25; five small products 30×20×30 → 50×10×20; single 100×12×12×1 unchanged at 24×24×25 (raw).
I'll run the diagnostic to see where the waste sits in the 12-line cart.

PHP failed to start due to a system memory/paging issue, not a code error. Let me retry.

The diagnostic shows the waste is mostly inside layers: short blocks share a layer with tall ones, so the layer height is set by the tallest block. Let me read the full packer to plan a height-aware fix.

The waste is confirmed: layers are as tall as their tallest block, leaving voids above shorter blocks. I'll make each layer 3D-aware by allowing blocks to stack on top of shorter placements (fully supported), then let the existing scorer decide if it beats starting a new layer.

First, add a z coordinate to placements:

Now add the 3D stacking helpers after tryPlaceInLayer:

Now wire the variants into the placement search:

Add the vertical position to the packing log so stacked placements are recorded correctly:

Now update the test harness to validate stacking in 3D (overlap and layer height including z):

Good progress: the 12-line cart dropped from 7 layers to 3, height 100 → 90.8, and cube ~9% smaller. Let me inspect the new layout and confirm no waste remains from stacked height.

The stacking pass recovers a lot (7 layers → 3, ~9% cube reduction on the mixed cart). Before finalizing, I need to check performance and worst-case behaviour with many distinct products, since stacking variants multiply the search.

Let me verify stacking never makes a cart worse by comparing against a no-stack copy of the same module.

Stacking is confirmed never worse and gives 4.7–9.2% on the mixed carts. Let me check whether a different product ordering could recover more, which would justify a multi-start pass.

Multi-start orderings only gain ~1.6% on one cart and hurt others, so it isn't worth the extra complexity. I'll keep the stacking pass and add a guard so very large layers don't blow up the search.

The server restarted mid-run. Let me verify the edits are intact and re-run lint and the full test suite.

++++++++++++++++++
The packing is now solid. Let me clean up the temporary test/diagnostic scripts as planned.

Done. I recovered a meaningful chunk of the remaining waste by making each layer 3D-aware.

What changed
public/zc_plugins/AustraliaPost/v3.2/catalog/includes/modules/shipping/aupost.php

The packer previously treated every layer as a flat slab: a layer was always as tall as its tallest block, so the space above shorter blocks was billed but empty.

Now a block can be placed on top of an existing placement when that placement fully supports its footprint. The existing scorer evaluates every variant (floor/shelf placement, plus each valid stack) and still picks the arrangement with the smallest overall parcel.

Supporting changes:

Placements now carry a z coordinate; newLayer() and tryPlaceInLayer() initialise it to 0.
New placeVariantsInLayer() returns the floor placement plus each valid stack.
New boxOverlapsPlacements() rejects any 3D intersection (touching faces are allowed).
Layer height is now max(z + h), so stacked blocks correctly contribute to parcel height.
The packing log records z.
Large layers (>40 placements) only try supports with free height above them, to bound search cost.
Measured results
Cart	Before	After	Change
12-line mixed	28.6 × 16.4 × 100.0, 0.04690 m³	28.6 × 16.4 × 90.8, 0.04259 m³	−9.2% cube, 7 layers → 3
6 medium lines × 30	72.5 × 36.8 × 103.1, 0.27507 m³	73.5 × 36.8 × 96.9, 0.26210 m³	−4.7% cube
All other test carts	—	unchanged	0% regression
The 12-line cart now uses the vertical voids directly, e.g. one layer holds four products at different heights instead of four separate layers.

Validation: PHP 8.3 lint clean; the end-to-end harness passed quantity totals, 3D overlap/bounds checks, reconstructed layer dimensions, and length limits for every case. Performance: ~1.2 ms for 1000 identical items, ~40 ms for 100 distinct products, ~0.3 s for 200 distinct products.

Remaining limitation
The bulk of what's left is footprint mismatch between layers (each layer has its own width/length, but the parcel is billed at the largest) and gaps between awkwardly sized items. Recovering that would require a full 3D bin packer with a shared floor, which is a much larger rewrite for a modest further gain, so I stopped here.

Note: the earlier quantity-splitting work remains in place, including the behaviour change that a cart line with quantity 0 now produces no layers and 0.00 × 0.00 × 0.00 dimensions (Zen Cart carts should never contain qty 0). Temporary test/diagnostic scripts were removed.


All checks pass. Let me review the final helper code once to make sure it reads cleanly and matches the module's style.
