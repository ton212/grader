<?php

namespace Grader\Web;

use Grader\Model\Test;
use Grader\Model\Result;

class TestAPI extends API{
	public $className = 'Grader\Model\Test';
	public $sort = 'name';
	
	protected function _mangle_item($name, &$item){
		if(!$item instanceof Test){
			return parent::_mangle_item($name, $item);
		}
		$problemCount = $item->problems()->count();
		$outitem = parent::_mangle_item($name, $item);
		if($outitem == parent::$remove){
			return $outitem;
		}else{
			$outitem['problem'] = $problemCount;
			if($user = $this->user()){ // note: assignment
				$passed = Result::where('user_id', '=', $user['id'])
					->join('problems', 'results.problem_id', '=', 'problems.id')
					->where('state', '=', 2)
					->where('correct', '=', 1)
					->where('problems.test_id', '=', $item['id'])
					->groupBy('problems.id');
				$outitem['score'] = 0;
				$outitem['finished'] = 0;
				foreach($passed->get() as $resultItem){
					$outitem['score'] += $resultItem['point'];
					$outitem['finished'] += 1;
				}
			}else{
				$outitem['score'] = 0;
				$outitem['finished'] = 0;
			}
			if($item->end && $item->end->isPast() && !$this->acl('tests', $outitem['id'], 'edit')){
				$outitem['readonly'] = true;
			}
			if($item->start && $item->start->isFuture() && !$this->acl('tests', $outitem['id'], 'edit')){
				return parent::$remove;
			}
		}
		return $outitem;
	}
}