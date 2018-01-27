<?php

/**
 * Jakarta, January 17th 2016
 * @link http://www.introvesia.com
 * @author Ahmad <ahmadjawahirabd@gmail.com>
 */

namespace Introvesia\Chupoo\Models;

use Exception;

class Validation
{
	private static $errors = array();
	private static $unrules = array();
	private static $added_rules = array();

	public static function getError()
	{
		if (func_num_args() == 1) {
			$name = func_get_arg(0);
			if (isset(self::$errors[$name])) {
				return self::$errors[$name];
			}
		} else {
			return self::$errors;
		}
	}

	public static function hasError($field)
	{
		if ($field !== null) {
			return isset(self::$errors[$field]);
		}
		return count(self::$errors) > 0;
	}

	public static function save($field, $message)
	{
		if ($field === false) {
			self::$errors[] = $message;
		} else {
			self::$errors[$field] = $message;
		}
	}

	public static function setError(array $errors)
	{
		return self::$errors = $errors;
	}

	public static function setRule($args)
	{
		switch (count($args)) {
			case 1:
				self::$added_rules = $args[0];
				break;
			case 2:
				self::$added_rules[$args[0]] = $args[1];
				break;
			default:
				throw new Exception("Invalid argument", 500);
		}
	}

	public static function unrule($field)
	{
		if (!in_array($field, self::$unrules)) {
			self::$unrules[] = $field;
		}
	}

	public static function unruleAll()
	{
		return self::$unrules = false;
	}

	public static function validate($model)
	{
		if (self::$unrules === false) return true;
		$rule_items = $model->rules();
		if (empty($rule_items)) return true;
		$rule_items = array_merge($rule_items, self::$added_rules);

		foreach ($rule_items as $field => $rules) {
			if (isset(self::$errors[$field]) || in_array($field, self::$unrules)) {
				continue;
			}
			foreach ($rules as $rule_name => $options) {
				$func_name = 'rule' . ucfirst($rule_name);
				if (method_exists(__CLASS__, $func_name)) {
					$args = array_merge(array($model, $field, $options));
					call_user_func_array(array(__CLASS__, $func_name), $args);
				} else {
					throw new Exception("No rule with name '$rule_name'", 500);
				}
			}
		}
		return count(self::$errors) == 0;
	}

	public static function directValidate($model, $args)
	{
		switch (count($args)) {
			case 1:
				self::$added_rules = $args[0];
				break;
			case 2:
				self::$added_rules[$args[0]] = $args[1];
				break;
			default:
				throw new Exception("Invalid argument", 500);
		}

		foreach (self::$added_rules as $field => $rules) {
			if (isset(self::$errors[$field]) || in_array($field, self::$unrules)) {
				continue;
			}
			foreach ($rules as $rule_name => $options) {
				$func_name = 'rule' . ucfirst($rule_name);
				if (method_exists(__CLASS__, $func_name)) {
					$args = array_merge(array($model, $field, $options));
					call_user_func_array(array(__CLASS__, $func_name), $args);
				} else {
					throw new Exception("No rule with name '$rule_name'", 500);
				}
			}
		}
		return count(self::$errors) == 0;
	}

	private static function ruleRequired($model, $field, $args = array())
	{
		if (empty($model->{$field})) {
			if (!isset($args['message'])) {
				self::$errors[$field] = ucfirst($field) . ' is required';
			} else {
				self::$errors[$field] = $args['message'];
			}
		}
	}

	private static function ruleNumber($model, $field, $args = array())
	{
		if (!is_numeric($model->{$field})) {
			if (!isset($args['message'])) {
				self::$errors[$field] = ucfirst($field) . ' is must be number';
			} else {
				self::$errors[$field] = $args['message'];
			}
		}
	}

	private static function ruleLength($model, $field, $args = array())
	{
		if (isset($args[0]) && strlen($model->{$field}) != 4) {
			if (!isset($args['message'])) {
				self::$errors[$field] = ucfirst($field) . ' is must be has length ' . $args[0];
			} else {
				self::$errors[$field] = $args['message'];
			}
		}
	}

	private static function ruleUnique($model, $field, $args = array())
	{
		$count = $model->count($field . ' = \'' . $model->{$field} . '\'');
		if (($model->isNew() && $count > 0) || (!$model->isNew() && !$model->isOrigin($field) && $count > 0)) {
			if (!isset($args['message'])) {
				self::$errors[$field] = ucfirst($field) . ' is unique';
			} else {
				self::$errors[$field] = $args['message'];
			}
		}
	}

	private static function ruleEmail($model, $field)
	{
		if (!filter_var($model->{$field}, FILTER_VALIDATE_EMAIL)) {
			self::$errors[$field] = ucfirst($field) . ' is not a valid email format';
		}
	}

	private static function ruleCompare($model, $field, $args)
	{
		if ($model->{$field} != $model->{$args[0]}) {
			self::$errors[$field] = ucfirst($field) . ' is different with ' . ucfirst($args[0]);
		}
	}
}