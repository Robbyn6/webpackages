<?php
/**
 *  Copyright (C) 2010 - 2020  <Robbyn Gerhardt>
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package       truetravel_bootstrap
 * @author        Robbyn Gerhardt
 * @copyright     Copyright (c) 2010 - 2020, pressmind GmbH (https://www.pressmind.de/)
 * @license       http://opensource.org/licenses/MIT	MIT License
 * @link          https://www.pressmind.de
 * @since         Version 2.0.0
 * @filesource
 */

namespace system\core\DB;

use system\core\DB\Adapter\AdapterInterface;
use system\core\DB\Adapter\mysql;

class database
{
	/**
	 * Get database class by engine
	 *
	 * @param DBConnectionConfig $config
	 *
	 * @return AdapterInterface
	 */
	public static function getDatabase(DBConnectionConfig $config)
	{
		if($config->engine == 'mysql')
		{
			$database	=	new mysql();
		}
		else
		{
			echo 'Failed, database "'.$config->engine.'" not exist';
			exit;
		}

		$database->connection($config);

		return $database;
	}
}
