<?php
/** Module Australia Post
 * @requires    Zen CArt 2.1.0 to 2.2.2  and PHP 8.3 to 8.5
 * @author      OldNGrey
 * @copyright   2026, Zen Cart
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     3.2.3
 * @github
 */
//Scripted installer for the Aus Post Shipping plugin

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    private string $configGroupTitle = 'Aus Post';
    public const AUPOST_CURRENT_VERSION = '3.2.3';
    protected int $configurationGroupId;
protected function executeInstall(): bool
    {
        // rename the old structure invoice.php and invoice.css files
        // new files are loaded from the plugin structure
if (file_exists(DIR_FS_ADMIN . 'invoice.php')) {
    /*
            $pathinstold = DIR_FS_ADMIN . '/invoice.php';
            $pathinstnew = DIR_FS_ADMIN . '/invoice.php.orig';
            rename( $pathinstold,  $pathinstnew);
            */
    if (!rename(DIR_FS_ADMIN . 'invoice.php', DIR_FS_ADMIN . 'invoice.php.orig')) {
        echo 'Failed to rename invoice.php';
        $this->errorContainer->addError(0, 'Failed to rename invoice.php');
        return false;
    }
            //rename(DIR_FS_ADMIN . 'invoice.php', DIR_FS_ADMIN . 'invoice.php.orig');
        }
        if (file_exists(DIR_FS_ADMIN . '/includes/css/invoice.css')) {
            $pathinstold = DIR_FS_ADMIN . '/includes/css/invoice.css';
            $pathinstnew = DIR_FS_ADMIN . '/includes/css/invoice.css.orig';
            rename( $pathinstold,  $pathinstnew);
        }

        return true;
    }

    protected function executeUninstall()
    {
        if (file_exists(DIR_FS_ADMIN . 'invoice.php.orig')) {
            $pathinstold = DIR_FS_ADMIN . '/invoice.php.orig';
            $pathinstnew = DIR_FS_ADMIN . '/invoice.php';
            rename( $pathinstold,  $pathinstnew);
            //rename(DIR_FS_ADMIN . 'invoice.php.orig', DIR_FS_ADMIN . 'invoice.php');
        }
        if (file_exists(DIR_FS_ADMIN . '/includes/css/invoice.css.orig')) {
            $pathold = DIR_FS_ADMIN . '/includes/css/invoice.css.orig';
            $pathnew = DIR_FS_ADMIN . '/includes/css/invoice.css';
            rename($pathold, $pathnew);
        }
        return true;
    }
    protected function executeUpgrade($oldVersion)
    {
        // v3.2.1 moved the split-overlimit switch from a file constant to an
        // admin setting. Add the configuration row when upgrading from a
        // version that did not have it yet.
        $this->ensureSplitOverlimitSetting();
        // v3.2.2 also adds the parcel-number suffix switch, for upgrades from
        // v3.2.1 or earlier.
        $this->ensureParcelNameSuffixSetting();
        return true;
    }

    /**
     * Create the split-overlimit admin setting if it does not exist.
     *
     * Safe to call more than once: an existing row (including a value the
     * store owner has already changed) is left untouched.
     */
    private function ensureSplitOverlimitSetting(): void
    {
        $check = $this->dbConn->Execute(
            "SELECT configuration_key FROM " . TABLE_CONFIGURATION
            . " WHERE configuration_key = 'MODULE_SHIPPING_AUPOST_SPLIT_OVERLIMIT' LIMIT 1"
        );
        if ($check->RecordCount() > 0) {
            return;
        }

        $this->executeInstallerSql(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
                VALUES ('Split large orders', 'MODULE_SHIPPING_AUPOST_SPLIT_OVERLIMIT', 'Yes', 'Set to Yes to split oversize orders into multiple parcels.', '6', '65', 'zen_cfg_select_option(array(\'Yes\', \'No\'), ', now())"
        );
    }

    /**
     * Create the parcel-number-in-product-name admin setting if it does not
     * exist. Safe to call more than once: an existing row is left untouched.
     */
    private function ensureParcelNameSuffixSetting(): void
    {
        $check = $this->dbConn->Execute(
            "SELECT configuration_key FROM " . TABLE_CONFIGURATION
            . " WHERE configuration_key = 'MODULE_SHIPPING_AUPOST_PARCEL_NAME_SUFFIX' LIMIT 1"
        );
        if ($check->RecordCount() > 0) {
            return;
        }

        $this->executeInstallerSql(
            "INSERT INTO " . TABLE_CONFIGURATION . "
                (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
                VALUES ('Parcel number in product name', 'MODULE_SHIPPING_AUPOST_PARCEL_NAME_SUFFIX', 'Yes', 'Set to Yes to append the parcel number to each product name on split Australia Post orders, e.g. <em>Widget [Parcel 2/3]</em>. Helps packing and picking slips identify which parcel an item belongs to.', '6', '66', 'zen_cfg_select_option(array(\'Yes\', \'No\'), ', now())"
        );
    }
}
