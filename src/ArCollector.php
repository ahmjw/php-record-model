<?php

/**
 * Jakarta, January 17th 2016
 * @link http://www.introvesia.com
 * @author Ahmad <ahmadjawahirabd@gmail.com>
 */

namespace Introvesia\Chupoo\Models;

use PDO;

class ArCollector
{
	public $rowCount = 0;
	private $resource;
	public $model;
	private $pager = array();
	private $controller;
	private $class_name;
	private $primary_key;
	private $query;

	public function __construct($model, $sql, $values, $pager)
	{
		$this->model = $model;
		$this->pager = $pager;
		$this->class_name = get_class($model);
		$this->query = $model->prepareOf($sql, $values);
		$this->resource = Db::execute($this->query);
		$this->rowCount = $this->resource->num_rows;
		$this->controller = $model->controller();
		$this->primary_key = $model->table('primary_key');
	}

	public function controller()
	{
		return $this->controller;
	}

	public function isSuccess()
	{
		return $this->controller->session->contain('model_flash_message');
	}

	public function success()
	{
		switch (func_num_args()) {
			case 0:
				$message = $this->controller->session('model_flash_message');
				$this->controller->session->clear('model_flash_message');
				return $message;
			case 1:
				$this->controller->session('model_flash_message', func_get_arg(0));
				break;
			default:
				throw new Exception("Bad request", 500);
		}
	}

	public function unsuccess()
	{
		$this->controller->session->clear('model_flash_message');
	}

	public function fetch($is_render = false)
	{
		$row = $this->resource->fetch_object();
		if ($row) {
			if ($is_render) {
				$model = new $this->class_name;
				$model->dataset('is_new', false);
				$model->dataset('is_init_new', false);
				$model->dataset('controller', $this->controller);
				if ($this->primary_key != null && isset($row->{$this->primary_key})) {
					$model->dataset('condition', array($this->primary_key . ' = ?', array($row->{$this->primary_key})));
				}
				foreach ($row as $key => $value) {
					$model->{$key} = stripslashes($value);
				}
			} else {
				$model = $row;
			}
			return $model;
		}
		return false;
	}

	public function getQuery()
	{
		return $this->query;
	}

	public function getPager()
	{
		if (isset($this->pager['sql'])) {
			$resource = Db::execute($this->model->prepareOf($this->pager['sql'], $this->pager['values']));
			if ($resource !== null) {
				$row = $resource->fetch_object();
				$count = $resource->num_rows > 1 ? $resource->num_rows : $row->count;
				if ($this->pager['limit'] > 0) {
					$pager['num_page'] = ceil($count / $this->pager['limit']);
				}
				$this->pager = array_merge($this->pager, $pager);
			}
		}
		$this->pager['limit'] = isset($this->pager['limit']) ? $this->pager['limit'] : 0;
		$this->pager['num_page'] = isset($this->pager['num_page']) ? $this->pager['num_page'] : 1;
		$this->pager['current'] = isset($this->pager['current']) ? $this->pager['current'] : 1;
		$this->pager['current'] = $this->pager['num_page'] > 0 ? $this->pager['current'] : 0;
		$this->pager['num_record'] = isset($this->pager['num_record']) ? $this->pager['num_record'] : 0;
		return $this->pager;
	}

	public function getFirst()
	{
		if (!empty($this->pager)) {
			return $this->pager['limit'] * ($this->pager['current'] - 1) + 1;
		}
		return 1;
	}
}