<?php

/**
 * Jakarta, January 17th 2016
 * @link http://www.introvesia.com
 * @author Ahmad <ahmadjawahirabd@gmail.com>
 */

namespace Introvesia\Chupoo\Models;

use PDO;

class Table
{
	private static $collections = array();

	public static function find($name, $key)
	{
		if (!isset(self::$collections[$name])) {
			$sql = 'DESC ' . $name;
			$rs = Db::execute($sql);
			$fields = array();
			self::$collections[$name]['primary_key'] = null;
			while ($row = $rs->fetch_assoc()) {
				self::$collections[$name]['schema'][$row['Field']] = $row;
				if ($row['Key'] == 'PRI') {
					self::$collections[$name]['primary_key'] = $row['Field'];
				}
				$fields[] = $row['Field'];
			}
			self::$collections[$name]['fields'] = $fields;
		}
		return self::$collections[$name][$key];
	}

	public static function findAll($name)
	{
		return self::$collections[$name];
	}
}