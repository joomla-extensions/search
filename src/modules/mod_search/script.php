<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Search
 *
 * @copyright   (C) 2023 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerScript;

/**
 * Installation class to perform additional changes during install/uninstall/update
 *
 * @since  __DEPLOY_VERSION__
 */
class Mod_SearchInstallerScript extends InstallerScript
{
    /**
     * A list of files to be deleted
     *
     * @var    string[]
     * @since  __DEPLOY_VERSION__
     */
    protected $deleteFiles = [
        '/modules/mod_search/helper.php',
    ];

    public function update($adapter): bool
    {
        $this->removeFiles();

        return true;
    }
}
