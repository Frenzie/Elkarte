<?php

/**
 * Dummy
 *
 * @name      ElkArte Forum
 * @copyright ElkArte Forum contributors
 * @license   BSD http://opensource.org/licenses/BSD-3-Clause
 *
 * @version 1.1
 *
 */

namespace ElkArte\UrlGenerator;

class Standard extends Abstract_Url_Generator
{
	protected $_types = array('standard');

	public function generate($params)
	{
		$url = '';
		$args = array();
		foreach ($params as $k => $v)
		{
			if (!is_int($k))
				$args[] = $k . '=' . $v;
			else
				$args[] = $v;
		}

		return implode($this->_separator, $args);
	}
}
