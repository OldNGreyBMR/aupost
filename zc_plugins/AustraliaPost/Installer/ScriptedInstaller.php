<?php
/** Module Australia Post
 * @requires    Zen CArt 2.1.0 to 2.2.2  and PHP 8.3 to 8.5
 * @author      OldNGrey
 * @copyright   2026, Zen Cart
 * @license     GNU General Public License (GPL) - https://www.gnu.org/licenses/gpl-3.0.html
 * @version     3.0.2
 * @github
 */
//Scripted installer for the Aus Post Shipping plugin

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    private string $configGroupTitle = 'Aus Post';
    public const AUPOST_CURRENT_VERSION = '3.0.1';
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
        // Upgrade logic here
        return true;
    }
}
