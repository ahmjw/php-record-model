<?php

/**
 * Jakarta, January 17th 2016
 * @link http://www.introvesia.com
 * @author Ahmad <ahmadjawahirabd@gmail.com>
 */

namespace Introvesia\Chupoo\Models;

use Exception;
use Introvesia\Chupoo\Starter;
use Introvesia\Chupoo\Helpers\Config;

abstract class Model
{
	protected $_dataset = array(
		'put' => false
	);

	public abstract function rules();

	public function __get($field)
	{
		$method_name = 'get' . ucfirst($field);
		if (method_exists($this, $method_name)) {
			$this->{$field} = call_user_func(array($this, $method_name));
		} else {
			$this->{$field} = null;
		}
		return $this->{$field};
	}

	public function controller()
	{
		return $this->_dataset['controller'];
	}

	public function dataset()
	{
		switch (func_num_args()) {
			case 0:
				return $this->_dataset;
			case 1:
				$name = func_get_arg(0);
				if (isset($this->_dataset[$name])) {
					return $this->_dataset[$name];
				}
				break;
			case 2:
				$this->_dataset[func_get_arg(0)] = func_get_arg(1);
				break;
			default:
				throw new Exception("Invalid argument", 500);
		}
	}

	public function isOrigin($field)
	{
		return isset($this->_dataset['origin']) && isset($this->_dataset['origin'][$field]) &&
			$this->_dataset['origin'][$field] == $this->{$field};
	}

	public function origin()
	{
		switch (func_num_args()) {
			case 0:
				return isset($this->_dataset['origin']) ? $this->_dataset['origin'] : array();
			case 1:
				$field = func_get_arg(0);
				return isset($this->_dataset['origin']) && isset($this->_dataset['origin'][$field]) ?
					$this->_dataset['origin'][$field] : null;
			case 2:
				$this->_dataset['origin'][func_get_arg(0)] = func_get_arg(1);
				break;
			default:
				throw new Exception("Invalid argument", 500);
		}
	}

	public function input(array $data)
	{
		foreach ($data as $key => $value) {
			if ($key == '_dataset') continue;
			$this->{$key} = $value;
		}
		$this->_dataset['put'] = true;
	}

	public function clear()
	{
		foreach ($this as $key => $value) {
			if ($key == '_dataset') continue;
			$this->{$key} = null;
		}
		if (isset($this->_dataset['origin'])) {
			unset($this->_dataset['origin']);
		}
	}

	public function unsetField()
	{
		foreach ($this as $key => $value) {
			if ($key == '_dataset') continue;
			unset($this->{$key});
		}
	}

	public function validate()
	{
		return Validation::validate($this);
	}

	public function hasError($field = null)
	{
		return Validation::hasError($field);
	}

	public function unrule($field = null)
	{
		if ($field !== null) {
			return Validation::unrule($field);
		} else {
			return Validation::unruleAll();
		}
	}

	public function setRule()
	{
		Validation::setRule(func_get_args());
	}

	public function directValidate()
	{
		return Validation::directValidate($this, func_get_args());
	}

	public function combineError($model)
	{
		$errors = array_merge(Validation::getError(), $model->error());
		Validation::setError($errors);
	}

	public function showError($read_all = true)
	{
		echo '<ul>';
		foreach (Validation::getError() as $key => $message) {
			if ($read_all) {
				echo '<li>' . $message . '</li>';
			} else if (is_numeric($key)) {
				echo '<li>' . $message . '</li>';
			}
		}
		echo '</ul>';
	}

	public function error()
	{
		switch (func_num_args()) {
			case 0:
				return Validation::getError();
			case 1:
				return Validation::getError(func_get_arg(0));
			case 2:
				Validation::save(func_get_arg(0), func_get_arg(1));
				break;
		}
	}

	public function isSuccess()
	{
		return $this->controller()->session->contain('model_flash_message');
	}

	public function success()
	{
		switch (func_num_args()) {
			case 0:
				$message = $this->controller()->session('model_flash_message');
				$this->controller()->session->clear('model_flash_message');
				return $message;
			case 1:
				$this->controller()->session('model_flash_message', func_get_arg(0));
				break;
			default:
				throw new Exception("Bad request", 500);
		}
	}

	public function unsuccess()
	{
		$this->controller()->session->clear('model_flash_message');
	}

	public function model($name, $is_new = false)
	{
		if (preg_match('/^\/(.*?)$/', $name, $match)) {
			$name = $match[1];
			$path = Config::find('app_path') . DIRECTORY_SEPARATOR . 'modules' .
				DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR . $name . '.php';
		} else {
			$name = str_replace('/', '\\', $name);
			$path = Config::find('app_path') . DIRECTORY_SEPARATOR . 'models' .
				DIRECTORY_SEPARATOR . $name . '.php';
		}
		$path = $this->normalizePath($path);
		if (!file_exists($path)) {
			throw new Exception("Model is not found: $path", 500);
		}
		include_once($path);
		$name = '\\Models\\' . $name;
		if (!class_exists($name)) {
			throw new Exception("Model class is not found: $name", 500);
		}
		$obj = new $name;
		$obj->dataset('is_new', (bool)$is_new);
		$obj->dataset('controller', Starter::getController());
		return $obj;
	}

	public function normalizePath($path)
	{
		return str_replace('\\', DIRECTORY_SEPARATOR, str_replace('/', DIRECTORY_SEPARATOR, $path));
	}
}