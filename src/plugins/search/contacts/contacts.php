<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Search.contacts
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Contact\Site\Helper\RouteHelper;
use Joomla\Database\ParameterType;

/**
 * Contacts search plugin.
 *
 * @since  1.6
 */
class PlgSearchContacts extends CMSPlugin
{
	/**
	 * Application object
	 *
	 * @var    \Joomla\CMS\Application\CMSApplicationInterface
	 * @since  4.0.0
	 */
	protected $app;

	/**
	 * Database Driver Instance
	 *
	 * @var    \Joomla\Database\DatabaseDriver
	 * @since  4.0.0
	 */
	protected $db;

	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  3.1
	 */
	protected $autoloadLanguage = true;

	/**
	 * Determine areas searchable by this plugin.
	 *
	 * @return  array  An array of search areas.
	 *
	 * @since   1.6
	 */
	public function onContentSearchAreas()
	{
		static $areas = array(
			'contacts' => 'PLG_SEARCH_CONTACTS_CONTACTS'
		);

		return $areas;
	}

	/**
	 * Search content (contacts).
	 *
	 * The SQL must return the following fields that are used in a common display
	 * routine: href, title, section, created, text, browsernav.
	 *
	 * @param   string  $text      Target search string.
	 * @param   string  $phrase    Matching option (possible values: exact|any|all).  Default is "any".
	 * @param   string  $ordering  Ordering option (possible values: newest|oldest|popular|alpha|category).  Default is "newest".
	 * @param   string  $areas     An array if the search is to be restricted to areas or null to search all areas.
	 *
	 * @return  array  Search results.
	 *
	 * @since   1.6
	 */
	public function onContentSearch($text, $phrase = '', $ordering = '', $areas = null)
	{
		$db     = $this->db;
		$app    = $this->app;
		$groups = $app->getIdentity()->getAuthorisedViewLevels();

		if (is_array($areas) && !array_intersect($areas, array_keys($this->onContentSearchAreas())))
		{
			return array();
		}

		$sContent  = $this->params->get('search_content', 1);
		$sArchived = $this->params->get('search_archived', 1);
		$limit     = $this->params->def('search_limit', 50);
		$state     = array();

		if ($sContent)
		{
			$state[] = 1;
		}

		if ($sArchived)
		{
			$state[] = 2;
		}

		if (empty($state))
		{
			return array();
		}

		$text = trim($text);

		if ($text === '')
		{
			return array();
		}

		$section = Text::_('PLG_SEARCH_CONTACTS_CONTACTS');

		switch ($ordering)
		{
			case 'alpha':
				$order = 'a.name ASC';
				break;

			case 'category':
				$order = 'c.title ASC, a.name ASC';
				break;

			case 'popular':
			case 'newest':
			case 'oldest':
			default:
				$order = 'a.name DESC';
		}

		$text = '%' . $text . '%';

		$query = $db->getQuery(true);

		$caseWhen = ' CASE WHEN ' . $query->charLength('a.alias', '!=', '0')
			. ' THEN ' . $query->concatenate(array($query->castAsChar('a.id'), 'a.alias'), ':')
			. ' ELSE a.id END AS slug';

		$caseWhen1 = ' CASE WHEN ' . $query->charLength('c.alias', '!=', '0')
			. ' THEN ' . $query->concatenate(array($query->castAsChar('c.id'), 'c.alias'), ':')
			. ' ELSE c.id END AS catslug';

		$query->select('a.name AS title')
			->select($db->quote('') . ' AS created, a.con_position, a.misc')
			->select($caseWhen)
			->select($caseWhen1)
			->select($query->concatenate(array('a.name', 'a.con_position', 'a.misc'), ',') . ' AS text')
			->select($query->concatenate(array($db->quote($section), 'c.title'), ' / ') . ' AS section')
			->select($db->quote('2') . ' AS browsernav')
			->from($db->quoteName('#__contact_details', 'a'))
			->innerJoin($db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
			->where(
				'(a.name LIKE :name OR a.misc LIKE :misc OR a.con_position LIKE :con_position'
				. ' OR a.address LIKE :address OR a.suburb LIKE :suburb OR a.state LIKE :state'
				. ' OR a.country LIKE :country OR a.postcode LIKE :postcode OR a.telephone LIKE :telephone '
				. ' OR a.fax LIKE :fax)'
			)
			->whereIn($db->quoteName('a.published'), $state)
			->where($db->quoteName('c.published') . '= 1')
			->whereIn($db->quoteName('a.access'), $groups)
			->whereIn($db->quoteName('c.access'), $groups)
			->bind(':name', $text)
			->bind(':misc', $text)
			->bind(':con_position', $text)
			->bind(':address', $text)
			->bind(':suburb', $text)
			->bind(':state', $text)
			->bind(':country', $text)
			->bind(':postcode', $text)
			->bind(':telephone', $text)
			->bind(':fax', $text)
			->order($order);

		// Filter by language.
		if ($app->isClient('site') && Multilanguage::isEnabled())
		{
			$languages = [Factory::getLanguage()->getTag(), '*'];
			$query->whereIn($db->quoteName('a.language'), $languages, ParameterType::STRING)
				->whereIn($db->quoteName('c.language'), $languages, ParameterType::STRING);
		}

		$db->setQuery($query, 0, $limit);

		try
		{
			$rows = $db->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			$rows = array();
			$app->enqueueMessage(Text::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');
		}

		if ($rows)
		{
			foreach ($rows as $key => $row)
			{
				$rows[$key]->href  = RouteHelper::getContactRoute($row->slug, $row->catslug);
				$rows[$key]->text  = $row->title;
				$rows[$key]->text .= $row->con_position ? ', ' . $row->con_position : '';
				$rows[$key]->text .= $row->misc ? ', ' . $row->misc : '';
			}
		}

		return $rows;
	}
}
