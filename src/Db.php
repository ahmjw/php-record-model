<?php

/**
 * Jakarta, January 17th 2016
 * @link http://www.introvesia.com
 * @author Ahmad <ahmadjawahirabd@gmail.com>
 */

namespace Introvesia\Chupoo\Models;

use Exception;
use Introvesia\Chupoo\Helpers\Config;

class Db
{
	private static $connection;

	public static function getConnection()
	{
		if (self::$connection === null) {
			mysqli_report(MYSQLI_REPORT_STRICT);
			$path = Config::find('app_path') . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';
			if (!file_exists($path)) {
				throw new Exception('Config file not found: ' . $path, 500);
			}
			$config = include($path);
			try {
				self::$connection = mysqli_connect($config['host'], $config['user'], $config['password'], $config['database']);
				if (isset($config['charset']))
					self::$connection->set_charset($config['charset']);
			} catch (Exception $ex) {
				throw new Exception($ex->getMessage(), 500);
			}
		}
		return self::$connection;
	}

	public static function execute($sql, array $values = array())
	{
		if (empty($sql)) return null;
		$db = self::getConnection();
		if ($db != null) {
			$stmt = $db->query($sql);
		}
		if (!empty($db->error)) {
			throw new Exception($db->error . ' -> ' . $sql, 500);
		}
		return $stmt;
	}

	public static function getTables()
	{
		$items = array();
		$stmt = self::execute('SHOW TABLES');
		while ($row = $stmt->fetch_array()) {
			$items[] = $row[0];
		}
		return $items;
	}

	public static function backup(array $table_names)
	{
		$str = '';
		$table_list = self::getTables();
		foreach ($table_names as $table_name) {
			if (!in_array($table_name, $table_list)) {
				continue;
			}
			$sql = 'SHOW CREATE TABLE ' . $table_name;
			$stmt = self::execute($sql);
			$row = $stmt->fetch_array();
			$str .= '# TABLE STRUCTURE FOR: ' . $table_name . "\n\n";
			$str .= 'DROP TABLE IF EXISTS ' . $table_name . ";\n\n";
			$str .= $row[1] . ";\n\n";

			$sql = 'SELECT * FROM ' . $table_name;
			$stmt = self::execute($sql);
			$fields_str = '';
			$i = 0;
			while ($row = $stmt->fetch_field()) {
				$fields_str .= $row->name;
				if ($i < $stmt->field_count - 1){
					$fields_str .= ', ';
				}
				$i++;
			}
			if ($stmt->num_rows > 0) {
				$str .= 'INSERT INTO ' . $table_name . '(' . $fields_str . ') VALUES' . "\n";
				$i = 0;
				while ($row = $stmt->fetch_assoc()) {
					$str .= '(';
					$j = 0;
					foreach ($row as $key => $value) {
						if ($value !== null) {
							$str .= "'" . $value . "'";
						} else {
							$str .= 'NULL';
						}
						if ($j < $stmt->field_count - 1) {
							$str .= ', ';
						}
						$j++;
					}
					$str .= ')';
					if ($i < $stmt->num_rows - 1) {
						$str .= ",\n";
					}
					$i++;
				}
				$str .= ";\n\n";
			}
		}
		return $str;
	}
}