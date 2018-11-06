<?php

/**
 * This file contains the post integration of mentions.
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 2.0 dev
 *
 */

/**
 * Class Mentions_Module_Abstract
 *
 * @package Mentions
 */
abstract class Mentions_Module_Abstract extends ElkArte\sources\modules\Abstract_Module
{
	/**
	 * Based on the $action returns the enabled mention types to register to the
	 * event manager.
	 *
	 * @param string $action
	 * @param \ElkArte\EventManager $eventsManager
	 * @global $modSettings
	 */
	protected static function registerHooks($action, \ElkArte\EventManager $eventsManager)
	{
		global $modSettings;

		if (!empty($modSettings['mentions_enabled']))
		{
			$mentions = explode(',', $modSettings['enabled_mentions']);

			foreach ($mentions as $mention)
			{
				$class = '\\ElkArte\\sources\\subs\\MentionType\\' . ucfirst($mention) . '_Mention';
				$hooks = $class::getEvents($action);

				foreach ($hooks as $method => $dependencies)
				{
					$eventsManager->register($method, array($method, array($class, $action . '_' . $method), $dependencies));
				}
			}
		}
	}
}
