<?php

namespace Grader\Web;

use Symfony\Component\HttpFoundation\Request;
use Grader\Model\Result;

class SubmissionAPI extends API{
	public $allowed=array('get', 'query');
	public $className = 'Grader\Model\Result';
	public $routeName = 'test/{testId}/problems/{problemId}/submissions';
	public $useAcl = false;
	public function __construct(){
	}

	public function get_query(){
		if(!$this->user()){
			$this->error('Please login', 403);
		}
		return $this->call_model('where', 'problem_id', '=', $this->app['request']->get('problemId'))
			->where('user_id', '=', $this->user()['id'])
			->orderBy('id', 'desc');
	}

	protected function _mangle_item($name, &$item){
		if(!$item instanceof Result){
			return parent::_mangle_item($name, $item);
		}
		$out = parent::_mangle_item($name, $item);
		$out['has_code'] = $item->code != "";
		return $out;
	}
}