<?php
/**
 * Australia Post - parcel names on order lines.
 *
 * The shipping module splits an order into parcels while quoting and stores
 * each item's parcel number(s) in the session. This observer appends that
 * allocation to the product name when the order is written, so packing slips,
 * invoices and any other program reading orders_products.products_name can
 * see which parcel each line belongs to.
 *
 * Resulting suffix examples:
 *   Widget [Parcel 2/3]
 *   Widget [Parcels 1(4),2(2)/3]
 *
 * Only applied when the order used an Australia Post shipping method, the
 * cart still matches the cart that was quoted, and the order has more than
 * one parcel. The admin setting MODULE_SHIPPING_AUPOST_PARCEL_NAME_SUFFIX
 * (Yes/No, default Yes) controls it.
 *
 * @copyright Copyright 2003-2026 Zen Cart Development Team
 * @license http://zen-cart.com GNU Public License V2.0
 */

class zcObserverAupostParcelNames extends base
{
    public function __construct()
    {
        $this->attach($this, ['NOTIFY_ORDER_DURING_CREATE_ADDED_PRODUCT_LINE_ITEM']);
    }

    /**
     * @param object $callingClass
     * @param string $eventID
     * @param array  $params          Order-product insert data (includes products_name)
     * @param int    $orderProductsId The just-inserted orders_products_id
     */
    public function updateNotifyOrderDuringCreateAddedProductLineItem(&$callingClass, $eventID, $params, &$orderProductsId): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        if (empty($orderProductsId) || !is_array($params)) {
            return;
        }

        $assignments = $_SESSION['aupostParcelAssignments'] ?? null;
        if (!is_array($assignments) || empty($assignments['items'])) {
            return;
        }

        if (!$this->isAupostShippingSelected()) {
            return;
        }
        if (!$this->cartMatches($assignments['cart_hash'] ?? '')) {
            return;
        }

        // products_prid keeps any attribute suffix; products_id is the base id.
        $itemId = (string) ($params['products_prid'] ?? '');
        $parcels = $assignments['items'][$itemId] ?? null;
        if ($parcels === null) {
            $itemId = (string) ($params['products_id'] ?? '');
            $parcels = $assignments['items'][$itemId] ?? null;
        }
        if (!is_array($parcels) || empty($parcels)) {
            return;
        }

        $suffix = $this->formatSuffix($parcels, (int) ($assignments['parcel_count'] ?? 0));
        if ($suffix === '') {
            return;
        }

        global $db;

        $newName = $this->fitName((string) ($params['products_name'] ?? ''), $suffix);
        $db->Execute(
            "UPDATE " . TABLE_ORDERS_PRODUCTS
            . " SET products_name = '" . zen_db_input($newName) . "'"
            . " WHERE orders_products_id = " . (int) $orderProductsId . " LIMIT 1"
        );
    }

    /**
     * orders_products.products_name is varchar(191) and MySQL runs in strict
     * mode, so keep the suffix intact and shorten the product name if needed.
     * The byte limit is deliberately conservative for UTF-8 names.
     */
    private function fitName(string $name, string $suffix): string
    {
        $maxBytes = 191;
        $suffixLength = strlen($suffix);
        if ($suffixLength >= $maxBytes) {
            return substr($suffix, 0, $maxBytes);
        }

        if (strlen($name) + $suffixLength > $maxBytes) {
            $name = substr($name, 0, $maxBytes - $suffixLength);
            // Do not leave a partial multibyte character at the cut point.
            while ($name !== '' && @preg_match('//u', $name) !== 1) {
                $name = substr($name, 0, -1);
            }
        }

        return $name . $suffix;
    }

    /**
     * The admin switch is Yes/No; default to Yes when the row is missing so
     * an upgrade that has not run yet still behaves sensibly.
     */
    private function isEnabled(): bool
    {
        if (!defined('MODULE_SHIPPING_AUPOST_PARCEL_NAME_SUFFIX')) {
            return true;
        }

        return !in_array(
            strtolower(trim((string) MODULE_SHIPPING_AUPOST_PARCEL_NAME_SUFFIX)),
            ['no', 'false', '0', 'off', 'n'],
            true
        );
    }

    /**
     * Only split Australia Post quotes create the session allocation.
     * Overseas module ids start with "aupostoverseas_", not "aupost_".
     */
    private function isAupostShippingSelected(): bool
    {
        $shippingId = (string) ($_SESSION['shipping']['id'] ?? '');
        return strpos($shippingId, 'aupost_') === 0;
    }

    /**
     * Guard against a stale allocation after the cart was edited.
     */
    private function cartMatches(string $hash): bool
    {
        if ($hash === '' || !isset($_SESSION['cart']) || !is_object($_SESSION['cart'])) {
            return false;
        }

        $items = $_SESSION['cart']->get_products();
        if (!is_array($items)) {
            return false;
        }

        $parts = [];
        foreach ($items as $item) {
            $parts[] = (string) ($item['id'] ?? '') . ':' . (int) ($item['quantity'] ?? 0);
        }
        sort($parts);

        return hash_equals($hash, md5(implode('|', $parts)));
    }

    /**
     * @param array $parcels      parcel number => quantity in that parcel
     * @param int   $parcelCount  total parcels in the order
     */
    private function formatSuffix(array $parcels, int $parcelCount): string
    {
        if ($parcelCount <= 1 || empty($parcels)) {
            return '';
        }

        ksort($parcels);

        // Show per-parcel quantities only when a line is spread over parcels.
        $showQty = false;
        if (count($parcels) > 1) {
            foreach ($parcels as $qty) {
                if ((int) $qty > 1) {
                    $showQty = true;
                    break;
                }
            }
        }

        $parts = [];
        foreach ($parcels as $parcel => $qty) {
            $parts[] = $showQty
                ? $parcel . '(' . (int) $qty . ')'
                : (string) $parcel;
        }

        if (count($parts) === 1) {
            return ' [Parcel ' . $parts[0] . '/' . $parcelCount . ']';
        }

        return ' [Parcels ' . implode(',', $parts) . '/' . $parcelCount . ']';
    }
}
