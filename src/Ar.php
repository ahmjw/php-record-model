<?php

/**
 * Jakarta, January 17th 2016
 * @link http://www.introvesia.com
 * @author Ahmad <ahmadjawahirabd@gmail.com>
 */

namespace Introvesia\Chupoo\Models;

use PDO;
use Exception;

abstract class Ar extends Model
{
	public abstract function tableName();

	public abstract function relations();

	public function access()
	{
		switch (func_num_args()) {
			case 0:
				return isset($this->_dataset['access']) ? $this->_dataset['access'] : array();
			case 1:
				$access_name = func_get_arg(0);
				return isset($this->_dataset['access']) && isset($this->_dataset['access'][$access_name]) ?
					$this->_dataset['access'][$access_name] : true;
			case 2:
				$this->_dataset['access'][func_get_arg(0)] = (bool)func_get_arg(1);
				break;
			default:
				throw new Exception("Bad request", 500);
		}
	}

	public function save()
	{
		if (!$this->validate()) {
			return false;
		}
		if ($this->_dataset['is_new'] === true) {
			return $this->insert();
		}
		return $this->update();
	}

	public function directSave()
	{
		if ($this->_dataset['is_new'] === true) {
			return $this->insert();
		}
		return $this->update();
	}

	public function isNew($is_new = null)
	{
		if ($is_new === null)
			return $this->_dataset['is_new'];
		else
			$this->_dataset['is_new'] = $is_new;
	}

	public function isInitNew()
	{
		return isset($this->_dataset['is_init_new']) && (bool)$this->_dataset['is_init_new'];
	}

	public function table($key = null)
	{
		if ($key === null) {
			return Table::findAll($this->tableName());
		}
		return Table::find($this->tableName(), $key);
	}

	public function setCondition($condition, array $criteria = array())
	{
		$this->_dataset['condition'] = array($condition, $criteria);
	}

	public function hasRelatedValue()
	{
		$relations = $this->relations();
		if (!is_array($relations)) return false;
		$pkey = $this->table('primary_key');
		foreach ($relations as $relation) {
			if ($this->model($relation[0])->isExists(array($relation[1].' = ?', $this->{$pkey}))) {
				return true;
			}
		}
		return false;
	}

	private function setDeletedFromParent()
	{
		if (!method_exists($this, 'parent')) return;
		list($model_name, $in_field, $field_name) = $this->parent();
		// Update source
		$source = $this->model($model_name)->find($this->{$field_name}, array(
			'select' => $in_field,
			'limit' => 1
		));
		if ($source != null) {
			$source->{$in_field} = $this->isExists($field_name.' = "'.$this->{$field_name}.'"');
			$source->directSave();
		}
	}

	private function updateStatusInParent()
	{
		if (!method_exists($this, 'parent')) return;
		list($model_name, $in_field, $field_name) = $this->parent();
		if ($this->isInitNew()) {
			$model = $this->model($model_name)->find($this->{$field_name}, array(
				'select' => $in_field,
				'limit' => 1
			));
			if ($model != null) {
				$model->{$in_field} = true;
				$model->directSave();
			}
		} else {
			$origin_value = $this->origin($field_name);
			if ($origin_value != $this->{$field_name}) {
				if ($model != $this->model($model_name)->find($this->{$field_name}, array(
					'select' => $in_field,
					'limit' => 1
					))) {
					$model->{$in_field} = false;
					$model->directSave();
				}
				if ($model = $this->model($model_name)->find($origin_value, array(
					'select' => $in_field,
					'limit' => 1
					))) {
					$model->{$in_field} = true;
					$model->directSave();
				}
			}
		}
	}

	public function insert()
	{
		if (!$this->access('create')) {
			throw new Exception('Access denied', 403);
		}
		$fields = $this->table('fields');
		$into = null;
		$availables = array();
		foreach ($this as $field => $value) {
			if (in_array($field, $fields)) {
				$availables[] = $field;
			}
		}
		$count = count($availables) - 1;
		$values = null;
		$data_values = array();
		foreach($availables as $i => $field) {
			$into .= '`' . $field . '`';
			$data_values[] = $this->{$field};
			$values .= '?';
			if ($i < $count) {
				$into .= ', ';
				$values .= ', ';
			}
		}
		$pkey = $this->table('primary_key');
		$schema = $this->table('schema');
		$sql = 'INSERT INTO `' . $this->tableName() . '` (' . $into . ') VALUES(' . $values . ');';
		$stmt = Db::execute($this->prepareOf($sql, $data_values));
		if ($stmt) {
			$this->_dataset['is_new'] = false;
			if (isset($schema[$pkey])) {
				if ($schema[$pkey]['Extra'] == 'auto_increment') {
					$this->_dataset['condition'][0] = $pkey . ' = ?';
					$this->{$pkey} = Db::getConnection()->insert_id;
					$this->_dataset['condition'][1] = array($this->{$pkey});
				} else {
					$this->_dataset['condition'][0] = $pkey . ' = ?';
					$this->_dataset['condition'][1] = array($this->{$pkey});
				}
			}
			$this->updateStatusInParent();
			return (bool)$stmt;
		}
		return false;
	}

	public function update()
	{
		if (!$this->access('update')) {
			throw new Exception('Access denied', 403);
		}
		if (func_num_args() == 0) {
			$fields = $this->table('fields');
			$pkey = $this->table('primary_key');
			$availables = array();
			if (isset($this->_dataset['use_origin_pk']) && $this->_dataset['use_origin_pk'] === true) {
				foreach ($this as $field => $value) {
					if (in_array($field, $fields)) {
						$availables[] = $field;
					}
				}
			} else {
				foreach ($this as $field => $value) {
					if ($pkey != $field && in_array($field, $fields)) {
						$availables[] = $field;
					}
				}
			}
			$count = count($availables) - 1;
			$set = null;
			$values = array();
			foreach($availables as $i => $field) {
				$values[] = $this->{$field};
				$set .= '`' . $field . '` = ?';
				if ($i < $count) {
					$set .= ', ';
				}
			}
			$sql = 'UPDATE `' . $this->tableName() . '` SET ' . $set;
			if (!empty($this->_dataset['condition'])) {
				$sql .= ' WHERE ' . $this->_dataset['condition'][0];
				$values = array_merge($values, $this->_dataset['condition'][1]);
			}
		} else if (func_num_args() == 2) {
			$sets = func_get_arg(0);
			$values = array();
			if (is_array($sets)) {
				$set = null;
				$count = count($sets);
				$i = 1;
				$values = array();
				foreach ($sets as $key => $value) {
					$set .= $key . ' = ?';
					$values[] = $value;
					if ($i < $count) {
						$set .= ', ';
					}
					$i++;
				}
				$sets = $set;
			}
			$condition = func_get_arg(1);
			$sql = 'UPDATE `' . $this->tableName() . '` SET ' . $sets;
			if (is_array($condition)) {
				$sql .= ' WHERE ' . $condition[0];
				$condition = array_slice($condition, 1);
				$values = array_merge($values, $condition);
			}
		} else {
			throw new Exception("Invalid argument", 500);
		}
		
		if (Db::execute($this->prepareOf($sql, $values))) {
			$this->updateStatusInParent();
			return true;
		}
		return false;
	}

	public function query($sql, array $args = array())
	{
		return Db::execute($this->prepareOf($sql, $args));
	}

	private function buildJoinQuery($join_info, $level = 0)
	{
		if ($level == 0) {
			$text = $this->tableName() . ' AS ' . $join_info[0];
			if (isset($join_info['type'])) {
				$text .= ' ' . $join_info['type'];
				unset($join_info['type']);
			}
			$members = array_slice($join_info, 1);
		} else {
			$text = '';
			$members = array_slice($join_info, 3);
		}
		foreach ($members as $data) {
			$type = isset($data['type']) ? ' ' . strtoupper($data['type']) : '';
			$text .= $type . ' JOIN ';
			if (isset($data[3])) {
				$text .= '(';
			}
			$text .= $this->model($data[0])->tableName() . ' AS ' . $data[1];
			if (isset($data[3])) {
				$join_info = array_slice($data, 3);
				$text .= $this->buildJoinQuery($data, $level + 1);
				$text .= ')';
			}
			$text .= ' ON ' . $data[2];
		}
		return $text;
	}

	private function buildSelectQuery(array $args)
	{
		$sql = $pager_sql = 'SELECT ';
		$pager_sql .= 'COUNT(*) AS count';
		$values = array();
		$pager = array();
		$condition = null;
		$num_arg = count($args);
		if ($num_arg == 1) {
			if (is_array($args[0])) {
				if (isset($args[0]['select'])) {
					$sql .= $args[0]['select'];
				} else {
					$sql .= '*';
				}
				$text = ' FROM ';
				$sql .= $text;
				$pager_sql .= $text;
				if (isset($args[0]['join'])) {
					$text = $this->buildJoinQuery($args[0]['join']);
					$sql .= $text;
					$pager_sql .= $text;
				} else {
					$text = $this->tableName();
					$sql .= $text;
					$pager_sql .= $text;
				}
				if (isset($args[0]['where'])) {
					if (is_array($args[0]['where'])) {
						$condition = $args[0]['where'][0];
						$text = ' WHERE ' . $condition;
						$values = array_slice($args[0]['where'], 1);
					} else {
						$condition = $args[0]['where'];
						$text = ' WHERE ' . $condition;
					}
					$sql .= $text;
					$pager_sql .= $text;
				}
				if (isset($args[0]['having'])) {
					$sql .= ' HAVING ' . $args[0]['having'];
				}
				if (isset($args[0]['groupBy'])) {
					$sql .= ' GROUP BY ' . $args[0]['groupBy'];
					$pager_sql .= ' GROUP BY ' . $args[0]['groupBy'];
				}
				if (isset($args[0]['orderBy'])) {
					$sql .= ' ORDER BY ' . $args[0]['orderBy'];
				}
				if (isset($args[0]['limit']) && $args[0]['limit'] !== false) {
					if (!is_array($args[0]['limit'])) {
						$current = 1;
						if (isset($_GET['page'])) {
							$current = (int)$_GET['page'];
							$current =  $current <= 0 ? 1 : $current;
						}
						$limit = (int)$args[0]['limit'];
					} else {
						$limit = (int)$args[0]['limit'][0];
						$current = (int)$args[0]['limit'][1];
					}
					$offset = ($current - 1) * $limit;
					$sql .= ' LIMIT ' . $offset . ', ' . $limit;
					$pager = array(
						'sql' => $pager_sql,
						'values' => $values,
						'limit' => $limit,
						'current' => $current,
					);
				}
			} else {
				$sql .= '*';
				$text = ' FROM ';
				$sql .= $text;
				$pager_sql .= $text;
				$sql .= $this->tableName();
				$pkey = $this->table('primary_key');
				$condition = $pkey . ' = ?';
				$sql .= ' WHERE ' . $condition;
				$values = array($args[0]);
				if (isset($this->_dataset['origin'])) {
					echo $this->_dataset['origin'][$pkey];
				}
			}
		} else if ($num_arg == 2 && !is_array($args[0]) && is_array($args[1])) {
			if (isset($args[1]['select'])) {
				$sql .= $args[1]['select'];
			} else {
				$sql .= '*';
			}
			$text = ' FROM ';
			$sql .= $text;
			$sql .= $this->tableName();
			$pkey = $this->table('primary_key');
			$condition = $pkey . ' = ?';
			$sql .= ' WHERE ' . $condition;
			$values = array($args[0]);
		} else {
			$text = ' * FROM ';
			$sql .= $text;
			$pager_sql .= $text;
			$sql .= $this->tableName();
		}
		if (!isset($this->_dataset['used'])) {
			$this->_dataset['used'] = true;
			$this->_dataset['args'] = $args;
			$this->_dataset['condition'] = array($condition, $values);
		}
		return array(
			'sql' => $sql,
			'values' => $values,
			'pager' => $pager,
		);
	}

	public function getScalar()
	{
		if (!$this->access('read')) {
			throw new Exception('Access denied', 403);
		}
		$query = $this->buildSelectQuery(func_get_args());
		$resource = Db::execute($this->prepareOf($query['sql'], $query['values']));
		$record = $resource->fetch_array();
		return stripslashes($record[0]);
	}

	public function find()
	{
		if (!$this->access('read')) {
			throw new Exception('Access denied', 403);
		}
		$query = $this->buildSelectQuery(func_get_args());
		$this->_dataset['query'] = $this->prepareOf($query['sql'], $query['values']);
		$resource = Db::execute($this->_dataset['query']);
		if ($resource->num_rows > 0) {
			$this->_dataset['origin'] = array();
			foreach ($resource->fetch_object() as $key => $value) {
				$this->{$key} = stripslashes($value);
				$this->_dataset['origin'][$key] = $this->{$key};
			}
			return $this;
		}
	}

	public function findAll()
	{
		if (!$this->access('read')) {
			throw new Exception('Access denied', 403);
		}
		$query = $this->buildSelectQuery(func_get_args());
		return new ArCollector($this, $query['sql'], $query['values'], $query['pager']);
	}

	public function get()
	{
		if (!$this->access('read')) {
			throw new Exception('Access denied', 403);
		}
		$query = $this->buildSelectQuery(func_get_args());
		$model = new ArCollector($this, $query['sql'], $query['values'], $query['pager']);
		$records = array();
		while ($row = $model->fetch()) {
			$records[] = $row;
		}
		return $records;
	}

	public function lists()
	{
		if (!$this->access('read')) {
			throw new Exception('Access denied', 403);
		}
		$args = array();
		switch (func_num_args()) {
			case 1:
				$args = func_get_args();
				$items = array();
				break;
			case 2:
				$arg0 = func_get_arg(0);
				$arg1 = func_get_arg(1);
				if (is_string($arg0) && is_string($arg1)) {
					$items = array();
					$args = array(array('select' => $arg0 . ', ' . $arg1));
				} else {
					$args = array($arg0);
					$items = func_get_arg(1);
				}
				break;
		}
		$query = $this->buildSelectQuery($args);
		$resource = Db::execute($this->prepareOf($query['sql'], $query['values']));
		if ($resource->num_rows > 0) {
			while ($row = $resource->fetch_array()) {
				if (isset($row[1])) {
					$items[$row[0]] = $row[1];
				} else {
					$items[$row[0]] = $row[0];
				}
			}
		}
		return $items;
	}

	public function directDelete($condition = null)
	{
		$sql = 'DELETE FROM ' . $this->tableName();
		if ($condition !== null) {
			if (!is_array($condition)) {
				$sql .= ' WHERE ' . $condition;
			} else {
				$sql .= ' WHERE ' . $this->prepare($condition);
			}
		} else if (!empty($this->_dataset['condition'])) {
			$sql .= ' WHERE ' . $this->_dataset['condition'][0];
		}
		return (bool)Db::execute($sql);
	}

	public function delete()
	{
		$sql = 'DELETE FROM ' . $this->tableName();
		if (!empty($this->_dataset['condition'])) {
			$sql .= ' WHERE ' . $this->_dataset['condition'][0];
			if (Db::execute($this->prepareOf($sql, $this->_dataset['condition'][1]))) {
				$this->setDeletedFromParent();
				return true;
			}
			return false;
		}
		return (bool)Db::execute($sql);
	}

	public function isExists($condition = null)
	{
		$sql = 'SELECT 1 FROM ' . $this->tableName();
		if ($condition !== null) {
			if (!is_array($condition)) {
				$sql .= ' WHERE ' . $condition;
			} else {
				$sql .= ' WHERE ' . $this->prepare($condition);
			}
		}
		$sql .= ' LIMIT 1';
		$resource = Db::execute($sql);
		$record = $resource->fetch_array();
		return $record[0] == 1;
	}

	public function count($condition = null)
	{
		$sql = 'SELECT COUNT(*) FROM ' . $this->tableName();
		if ($condition !== null) {
			if (!is_array($condition)) {
				$sql .= ' WHERE ' . $condition;
			} else {
				$sql .= ' WHERE ' . $this->prepare($condition);
			}
		}
		$resource = Db::execute($sql);
		$record = $resource->fetch_array();
		return $record[0];
	}

	public function prepareOf($criteria, $values)
	{
		$i = 0;
		return preg_replace_callback('/\?/', function($matches) use(&$i, $values) {
			if (!is_array($values[$i]) && !is_object($values[$i])) {
				$value = "'" . addslashes($values[$i]) . "'";
			} else {
				$value = "'" . json_encode($values[$i]) . "'";
			}
			$i++;
			return $value;
		}, $criteria);
	}

	public function prepare(array $data)
	{
		$i = 0;
		$criteria = $data[0];
		$values = array_slice($data, 1);
		return preg_replace_callback('/\?/', function($matches) use(&$i, $values) {
			if (!is_array($values[$i]) && !is_object($values[$i])) {
				$value = "'" . addslashes($values[$i]) . "'";
			} else {
				$value = "'" . json_encode($values[$i]) . "'";
			}
			$i++;
			return $value;
		}, $criteria);
	}
}