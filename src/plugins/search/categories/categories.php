<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Search.categories
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Component\Search\Administrator\Helper\SearchHelper;
use Joomla\Database\ParameterType;

/**
 * Categories search plugin.
 *
 * @since  1.6
 */
class PlgSearchCategories extends CMSPlugin
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
			'categories' => 'PLG_SEARCH_CATEGORIES_CATEGORIES'
		);

		return $areas;
	}

	/**
	 * Search content (categories).
	 *
	 * The SQL must return the following fields that are used in a common display
	 * routine: href, title, section, created, text, browsernav.
	 *
	 * @param   string  $text      Target search string.
	 * @param   string  $phrase    Matching option (possible values: exact|any|all).  Default is "any".
	 * @param   string  $ordering  Ordering option (possible values: newest|oldest|popular|alpha|category).  Default is "newest".
	 * @param   mixed   $areas     An array if the search is to be restricted to areas or null to search all areas.
	 *
	 * @return  array  Search results.
	 *
	 * @since   1.6
	 */
	public function onContentSearch($text, $phrase = '', $ordering = '', $areas = null)
	{
		$db         = $this->db;
		$app        = $this->app;
		$searchText = $text;

		if (is_array($areas) && !array_intersect($areas, array_keys($this->onContentSearchAreas())))
		{
			return array();
		}

		$sContent = $this->params->get('search_content', 1);
		$sArchived = $this->params->get('search_archived', 1);
		$limit = $this->params->def('search_limit', 50);
		$state = array();

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

		/* TODO: The $where variable does not seem to be used at all
		switch ($phrase)
		{
			case 'exact':
				$text = $db->quote('%' . $db->escape($text, true) . '%', false);
				$wheres2 = array();
				$wheres2[] = 'a.title LIKE ' . $text;
				$wheres2[] = 'a.description LIKE ' . $text;
				$where = '(' . implode(') OR (', $wheres2) . ')';
				break;

			case 'any':
			case 'all';
			default:
				$words = explode(' ', $text);
				$wheres = array();
				foreach ($words as $word)
				{
					$word = $db->quote('%' . $db->escape($word, true) . '%', false);
					$wheres2 = array();
					$wheres2[] = 'a.title LIKE ' . $word;
					$wheres2[] = 'a.description LIKE ' . $word;
					$wheres[] = implode(' OR ', $wheres2);
				}
				$where = '(' . implode(($phrase === 'all' ? ') AND (' : ') OR ('), $wheres) . ')';
				break;
		}
		*/

		switch ($ordering)
		{
			case 'alpha':
				$order = 'a.title ASC';
				break;

			case 'category':
			case 'popular':
			case 'newest':
			case 'oldest':
			default:
				$order = 'a.title DESC';
		}

		$text      = '%' . $text . '%';
		$extension = 'com_content';
		$query     = $db->getQuery(true);

		$caseWhen = ' CASE WHEN ' . $query->charLength('a.alias', '!=', '0')
			. ' THEN ' . $query->concatenate(array($query->castAsChar('a.id'), 'a.alias'), ':')
			. ' ELSE a.id END AS slug';

		$query->select('a.title, a.description AS text, a.created_time AS created')
			->select($db->quote('2') . ' AS browsernav')
			->select('a.id AS catid, a.language AS category_language')
			->select($caseWhen)
			->from($db->quoteName('#__categories', 'a'))
			->where('(' . $db->quoteName('a.title') . ' LIKE :title OR ' . $db->quoteName('a.description') . ' LIKE :description)')
			->where($db->quoteName('a.extension') .' = :extension')
			->whereIn($db->quoteName('a.published'), $state)
			->whereIn($db->quoteName('a.access'), $app->getIdentity()->getAuthorisedViewLevels())
			->bind(':title', $text)
			->bind(':description', $text)
			->bind(':extension', $extension)
			->group('a.id, a.title, a.description, a.alias, a.created_time, a.language')
			->order($order);

		if ($app->isClient('site') && Multilanguage::isEnabled())
		{
			$query->whereIn($db->quoteName('a.language'), [$app->getLanguage()->getTag(), '*'], ParameterType::STRING);
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

		$return = array();

		if ($rows)
		{
			foreach ($rows as $i => $row)
			{
				if (SearchHelper::checkNoHtml($row, $searchText, array('name', 'title', 'text')))
				{
					$row->href = RouteHelper::getCategoryRoute($row->slug, $row->category_language);
					$row->section = Text::_('JCATEGORY');

					$return[] = $row;
				}
			}
		}

		return $return;
	}
}
