<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_search
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Search\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Methods supporting a list of search terms.
 *
 * @since  1.6
 */
class SearchesController extends BaseController
{
	/**
	 * Method to reset the search log table.
	 *
	 * @return  boolean
	 */
	public function reset()
	{
		// Check for request forgeries.
		$this->checkToken();

		$model = $this->getModel('Searches');

		if (!$model->reset())
		{
			$this->app->enqueueMessage($model->getError(), 'error');
		}

		$this->setRedirect('index.php?option=com_search&view=searches');
	}

	/**
	 * Method to toggle the view of results.
	 *
	 * @return  boolean
	 */
	public function toggleResults()
	{
		// Check for request forgeries.
		$this->checkToken();

		if ((int) $this->getModel('Searches')->getState('show_results', 1) === 0)
		{
			$this->setRedirect('index.php?option=com_search&view=searches&show_results=1');
		}
		else
		{
			$this->setRedirect('index.php?option=com_search&view=searches&show_results=0');
		}
	}
}
