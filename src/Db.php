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
	private static $config = array();
	private static $connection;

	public static function setConnection(\mysqli $connection)
	{
		self::$connection = $connection;
	}

	public static function setConfig(array $config)
	{
		self::$config = $config;
	}

	private static function connect()
	{
		mysqli_report(MYSQLI_REPORT_STRICT);
		try {
			self::$connection = mysqli_connect(self::$config['host'], self::$config['user'], 
				self::$config['password'], self::$config['database']);
			if (isset(self::$config['charset']))
				self::$connection->set_charset(self::$config['charset']);
		} catch (Exception $ex) {
			throw new Exception($ex->getMessage(), 500);
		}
	}

	public static function getConnection()
	{

		if (self::$connection === null) {
			self::connect();
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