<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Search
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerScript;

/**
 * Installation class to perform additional changes during install/uninstall/update
 *
 * @since  __DEPLOY_VERSION__
 */
class Pkg_SearchInstallerScript extends InstallerScript
{
	/**
	 * Extension script constructor.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct()
	{
		$this->minimumJoomla = '4.2.0';
		$this->minimumPhp    = JOOMLA_MINIMUM_PHP;
	}
}
