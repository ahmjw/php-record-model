<?php

/**
 * Jakarta, January 17th 2016
 * @link http://www.introvesia.com
 * @author Ahmad <ahmadjawahirabd@gmail.com>
 */

namespace Introvesia\Chupoo\Models;

abstract class DataNav extends Model
{
	public $criteria = null;
	private $param = '';
	private $order_param = '';
	protected $parameters = array('find_type', 'keyword', 'order_by', 'order_type', 'show');
	protected $items = array();

	protected $modelName;
	protected $limit;
	protected $orderBy;
	protected $groupBy;
	protected $charset;

	abstract function findSingle();
	abstract function findMultiple();

	public function __construct()
	{
		$this->parse();
	}

	public function rules()
	{
	}

	public function getModel()
	{
		// Model parameters
		$args['where'] = $this->criteria;
		$args['limit'] = $this->limit;
		if (!empty($this->orderBy)) {
			$args['orderBy'] = $this->orderBy;
		}
		if (!empty($this->groupBy)) {
			$args['groupBy'] = $this->groupBy;
		}
		return $this->model($this->modelName)->findAll($args);
	}

	public function getCriteria()
	{
		return $this->criteria;
	}

	public function getItem($name)
	{
		if (!isset($this->items[$name])) {
			throw new \Exception("Item '$name' is not found", 500);
		}
		return $this->items[$name];
	}

	public function parse()
	{
		if ($this->items === null) {
			$this->items = array();
		}
		$item_keys = array_keys($this->items);
		$this->input($_GET);
		foreach ($item_keys as $key) {
			if (!in_array($key, $this->parameters)) {
				if (isset($this->{$key})) {
					$this->parameters[] = $key;
				} else {
					$this->{$key} = null;
				}
			}
		}
		if ($this->find_type === true) {
			$idx = array_search('find_type', $this->parameters);
			unset($this->parameters[$idx]);
		}
		foreach ($this->parameters as $parameter_name) {
			if (!isset($this->{$parameter_name})) {
				$this->{$parameter_name} = null;
			}
		}
		$this->order_type = !empty($this->order_type) && ($this->order_type == 'ASC' || $this->order_type == 'DESC') ? $this->order_type : 'ASC';
		$i = 0;
		$num_param = count($this->parameters);
		foreach ($this->parameters as $param) {
			$value = isset($this->{$param}) ? $this->{$param} : '';
			$this->param .= $param . '=' . $value;
			if ($i < $num_param - 1) {
				$this->param .= '&amp;';
			}
		}
		$this->order_param = preg_replace_callback('/order_type=(.*?)&/', function($match) {
			return 'order_type=' . ($match[1] == 'ASC' ? 'DESC' : 'ASC') . '&';
		}, $this->param);
		if (isset($this->page)) {
			$this->order_param .= '&page=' . (int)$this->page;
		}
		if (trim($this->find_type) != '') {
			if ($criteria = $this->findSingle()) {
				$this->criteria = $criteria;
			}
		}
		// Find Multiple
		$items = $this->findMultiple();
		$item_criteria = null;
		$num_val = isset($items['values']) ? count($items['values']) : 0;
		if ($num_val > 0) {
			$condition_t = '';
			for ($i=0; $i < $num_val; $i++) {
				$condition_t .= $items['condition'][$i];
				if ($i < $num_val - 1) {
					$condition_t .= ' AND ';
				}
			}
			$item_criteria = array_merge(array($condition_t), $items['values']);
		}
		// Find Single
		if (is_array($this->criteria) && is_array($item_criteria)) {
			$item_criteria[0] = $item_criteria[0] . ' AND ' . $this->criteria[0];
			array_push($item_criteria, $this->criteria[1]);
			$this->criteria = $item_criteria;
		} else if (is_array($item_criteria)) {
			$this->criteria = $item_criteria;
		}
		if (method_exists($this, 'afterSearch')) {
			call_user_func(array($this, 'afterSearch'));
		}
	}

	public function showTitle($field, $title)
	{
		$link = '?' . preg_replace_callback('/order_by=(.*?)&/', function($match) use($field) {
			return 'order_by='.$field.'&';
		}, $this->order_param);
		return $this->controller()->html->link($title, $link);
	}

	public function getParam()
	{
		return $this->param;
	}

	public function getPager($model)
	{
		$pager = $model->getPager();
		$data = array(
			'current' => $pager['current'],
			'num_page' => $pager['num_page'],
			'num_record' => $pager['num_record'],
		);
		$page_margin = 2;
		if ($pager['current'] > 1) {
			$data['pages'][] = array(
				'position' => 'first',
				'url' => '?'.$this->param.'page=1'
			);
		}
		$div = (int)(($pager['current'] - $page_margin) / 10) - 1;
		$prevs = array();
		$j = 1;
		for ($i = $div * 10; $i >= 1 && $j <= $page_margin; $i -= 10) {
			$prevs[] = $i;
			$j++;
		}
		for ($i= count($prevs) - 1; $i >= 0; $i--) {
			$data['pages'][] = array(
				'position' => 'none',
				'url' => '?'.$this->param.'page='.$prevs[$i],
				'text' => $prevs[$i]
			);
		}
		for ($i = $pager['current'] - $page_margin; ($i <= $pager['current'] + $page_margin) && $i <= $pager['num_page']; $i++) {
			if ($i == $pager['current']) {
				$data['pages'][] = array(
					'position' => 'current',
					'text' => $i
				);
			} else if ($i > 0) {
				$data['pages'][] = array(
					'position' => 'none',
					'url' => '?'.$this->param.'page='.$i,
					'text' => $i
				);
			}
		}
		$div = (int)($i / 10) + 1;
		$j = 1;
		for ($i = $div * 10; $i <= $pager['num_page'] && $j <= $page_margin; $i += 10) {
			$data['pages'][] = array(
				'position' => 'none',
				'url' => '?'.$this->param.'page='.$i,
				'text' => $i
			);
			$j++;
		}
		if ($pager['current'] < $pager['num_page']) {
			$data['pages'][] = array(
				'position' => 'last',
				'url' => '?'.$this->param.'page='.$pager['num_page'],
			);
		}
		return $data;
	}

	public function addOrCriteria($condition, $values = array())
	{
		if (empty($condition)) return;
		if (is_array($condition)) {
			if (!empty($this->criteria)) {
				$this->criteria[0] .= ' OR ' . $condition[0];
			} else {
				$this->criteria = array($condition[0]);
			}
			$this->criteria = array_merge($this->criteria, array_slice($condition, 1));
		} else if (is_string($condition)) {
			if (!empty($this->criteria)) {
				$this->criteria[0] .= ' OR ' . $condition;
			} else {
				$this->criteria = array($condition);
			}
			$this->criteria = array_merge($this->criteria, $values);
		}
	}

	public function addAndCriteria($condition, $values = array())
	{
		if (empty($condition)) return;
		if (is_array($condition)) {
			if (!empty($this->criteria)) {
				$this->criteria[0] .= ' AND ' . $condition[0];
			} else {
				$this->criteria = array($condition[0]);
			}
			$this->criteria = array_merge($this->criteria, array_slice($condition, 1));
		} else if (is_string($condition)) {
			if (!empty($this->criteria)) {
				$this->criteria[0] .= ' AND ' . $condition;
			} else {
				$this->criteria = array($condition);
			}
			$this->criteria = array_merge($this->criteria, $values);
		}
	}
}