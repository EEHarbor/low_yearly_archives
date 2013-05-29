<?php

/**
 * Low Yearly Archives config file
 *
 * @package        low_yearly_archives
 * @author         Lodewijk Schutte <hi@gotolow.com>
 * @link           http://gotolow.com/addons/low-replace
 * @license        http://creativecommons.org/licenses/by-sa/3.0/
 */

if ( ! defined('LOW_YA_NAME'))
{
	define('LOW_YA_NAME',    'Low Yearly Archives');
	define('LOW_YA_PACKAGE', 'low_yearly_archives');
	define('LOW_YA_VERSION', '2.3.0');
	define('LOW_YA_DOCS',    'http://gotolow.com/addons/low-yearly-archives');
}

/**
 * < EE 2.6.0 backward compat
 */
if ( ! function_exists('ee'))
{
	function ee()
	{
		static $EE;
		if ( ! $EE) $EE = get_instance();
		return $EE;
	}
}

/**
 * NSM Addon Updater
 */
$config['name']    = LOW_YA_NAME;
$config['version'] = LOW_YA_VERSION;
$config['nsm_addon_updater']['versions_xml'] = LOW_YA_DOCS.'/feed';
