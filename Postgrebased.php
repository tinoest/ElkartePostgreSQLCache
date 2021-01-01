<?php

/**
 * This file contains functions that deal with getting and setting cache values using PostgreSQL.
 *
 * @author    tinoest http://tinoest.co.uk
 * @copyright tinoest
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 * @mod       PostgreSQL Cache - PostgreSQL based caching mechanism
 *
 * @version 1.0.0
 *
 */

namespace ElkArte\sources\subs\CacheMethod;

/**
 * PostgreSQLbased caching stores the cache data in a PostgreSQL database
 * The performance gain may or may not exist depending on many factors.
 *
 * It requires the a PostgreSQL database greater than 9.4 to work
 */
class Postgrebased extends Cache_Method_Abstract
{
	/**
	 * {@inheritdoc}
	 */
	protected $title = 'Postgre-based caching';

	/**
	 * {@inheritdoc}
	 */
	public function __construct($options)
	{

		global $modSettings;

		$oldModSetting					    = $modSettings['disableQueryCheck'];
		$modSettings['disableQueryCheck']   = true;

		parent::__construct($options);

		$db		= database();
		if($db->fetch_row($db->query('', 'SELECT to_regclass(\'{db_prefix}cache\');')[0]) == null)
		{
			$db_table = db_table();
			$db_table->db_create_table('{db_prefix}cache',
				array(
					array('name' => 'key',    'type' => 'text'),
					array('name' => 'value',  'type' => 'text'),
					array('name' => 'ttl',    'type' => 'bigint'),
				),
				array('name' => 'key', 'columns' => array('key')),
				array(),
				'ignore'
			);
		}

		$modSettings['disableQueryCheck'] = $oldModSetting;

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function exists($key) {

	}

	/**
	 * {@inheritdoc}
	 */
	public function put($key, $value, $ttl = 120)
	{
		global $modSettings;

		$oldModSetting					    = $modSettings['disableQueryCheck'];
		$modSettings['disableQueryCheck']   = true;

		$db			= database();
		$key		= $db->escape_string($key);
		$value	= $db->escape_string($value);
		$ttl		= time();
		$ttl		= $db->escape_string($ttl);

		$query	= 'UPDATE {db_prefix}cache SET value =\''.$value.'\', ttl = \''.$ttl.'\' WHERE key = \''.$key.'\';
						INSERT INTO {db_prefix}cache (key, value, ttl)
						SELECT \''.$key.'\', \''.$value.'\', \''.$ttl.'\'
							WHERE NOT EXISTS (SELECT 1 FROM {db_prefix}cache WHERE key = \''.$key.'\');';

		$result = $db->query('', $query);

		$modSettings['disableQueryCheck'] = $oldModSetting;

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get($key, $ttl = 120)
	{
		global $modSettings;

		$oldModSetting						= $modSettings['disableQueryCheck'];
		$modSettings['disableQueryCheck']   = true;

		$db			= database();
		$ttl		= time() - $ttl;
		$query	= 'SELECT value FROM {db_prefix}cache WHERE key = \'' . $db->escape_string($key) . '\' AND ttl >= ' . $ttl . ' LIMIT 1';
		$result = $db->query('', $query);
		$value	= $db->fetch_assoc($result)['value'];

		$modSettings['disableQueryCheck']   = $oldModSetting;

		return !empty($value) ? $value : null;

	}

	/**
	 * {@inheritdoc}
	 */
	public function clean($type = '')
	{
		global $modSettings;

		$oldModSetting						= $modSettings['disableQueryCheck'];
		$modSettings['disableQueryCheck']   = true;

		$db			= database();
		$query	= 'DELETE FROM {db_prefix}cache;';
		$result = $db->query('', $query);

		$modSettings['disableQueryCheck']   = $oldModSetting;

		return $result;

	}

	/**
	 * {@inheritdoc}
	 */
	public function isAvailable()
	{
		$db = database();
		if( ($db->db_title() == 'PostgreSQL') && (version_compare($db->db_server_version(), '9.4.0', '>=')) )
			return true;

		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function details()
	{
		return array('title' => $this->title, 'version' => '1.0');
	}

	/**
	 * Adds the settings to the settings page.
	 *
	 * Used by integrate_modify_cache_settings added in the title method
	 *
	 * @param array $config_vars
	 */
	public function settings(&$config_vars)
	{

	}
}
