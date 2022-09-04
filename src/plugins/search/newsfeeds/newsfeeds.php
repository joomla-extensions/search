<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Search.newsfeeds
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\ParameterType;

/**
 * Newsfeeds search plugin.
 *
 * @since  1.6
 */
class PlgSearchNewsfeeds extends CMSPlugin
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
			'newsfeeds' => 'PLG_SEARCH_NEWSFEEDS_NEWSFEEDS'
		);

		return $areas;
	}

	/**
	 * Search content (newsfeeds).
	 *
	 * The SQL must return the following fields that are used in a common display
	 * routine: href, title, section, created, text, browsernav.
	 *
	 * @param   string  $text      Target search string.
	 * @param   string  $phrase    Matching option (possible values: exact|any|all).  Default is "any".
	 * @param   string  $ordering  Ordering option (possible values: newest|oldest|popular|alpha|category).  Default is "newest".
	 * @param   mixed   $areas     An array if the search it to be restricted to areas or null to search all areas.
	 *
	 * @return  array  Search results.
	 *
	 * @since   1.6
	 */
	public function onContentSearch($text, $phrase = '', $ordering = '', $areas = null)
	{
		$db 	= $this->db;
		$app 	= $this->app;
		$groups = $app->getIdentity()->getAuthorisedViewLevels();

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

		switch ($phrase)
		{
			case 'exact':
				$text = $db->quote('%' . $db->escape($text, true) . '%', false);
				$wheres2 = array();
				$wheres2[] = 'a.name LIKE ' . $text;
				$wheres2[] = 'a.link LIKE ' . $text;
				$where = '(' . implode(') OR (', $wheres2) . ')';
				break;

			case 'all':
			case 'any':
			default:
				$words = explode(' ', $text);
				$wheres = array();

				foreach ($words as $word)
				{
					$word = $db->quote('%' . $db->escape($word, true) . '%', false);
					$wheres2 = array();
					$wheres2[] = 'a.name LIKE ' . $word;
					$wheres2[] = 'a.link LIKE ' . $word;
					$wheres[] = implode(' OR ', $wheres2);
				}

				$where = '(' . implode(($phrase === 'all' ? ') AND (' : ') OR ('), $wheres) . ')';
				break;
		}

		switch ($ordering)
		{
			case 'alpha':
				$order = 'a.name ASC';
				break;

			case 'category':
				$order = 'c.title ASC, a.name ASC';
				break;

			case 'oldest':
			case 'popular':
			case 'newest':
			default:
				$order = 'a.name ASC';
		}

		$searchNewsfeeds = Text::_('PLG_SEARCH_NEWSFEEDS_NEWSFEEDS');

		$query = $db->getQuery(true);

		$caseWhen = ' CASE WHEN ' . $query->charLength('a.alias', '!=', '0')
			. ' THEN ' . $query->concatenate(array($query->castAsChar('a.id'), 'a.alias'), ':')
			. ' ELSE a.id END AS slug';

		$caseWhen1 = ' CASE WHEN ' . $query->charLength('c.alias', '!=', '0')
			. ' THEN ' . $query->concatenate(array($query->castAsChar('c.id'), 'c.alias'), ':')
			. ' ELSE c.id END AS catslug';

		$query->select('a.name AS title')
			->select($db->quote('') . ' AS created, a.link AS text')
			->select($caseWhen)
			->select($caseWhen1)
			->select($query->concatenate(array($db->quote($searchNewsfeeds), 'c.title'), ' / ') . ' AS section')
			->select($db->quote('1') . ' AS browsernav')
			->from($db->quoteName('#__newsfeeds', 'a'))
			->innerJoin($db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
			->where('(' . $where . ')')
			->whereIn($db->quoteName('a.published'), $state)
			->where($db->quoteName('c.published') .' = 1')
			->whereIn($db->quoteName('c.access'), $groups)
			->order($order);

		// Filter by language.
		if ($app->isClient('site') && Multilanguage::isEnabled())
		{
			$languages = [$app->getLanguage()->getTag(), '*'];
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
				$rows[$key]->href = 'index.php?option=com_newsfeeds&view=newsfeed&catid=' . $row->catslug . '&id=' . $row->slug;
			}
		}

		return $rows;
	}
}
