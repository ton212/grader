<?php

namespace Grader\Model;

class Result extends Model{
	protected $fillable = array('problem_id', 'user_id', 'state', 'correct', 'code', 'grader', 'lang');
	protected $visible = array('id', 'problem_id', 'user_id', 'state', 'correct', 'created_at', 'updated_at', 'result', 'lang', 'error');
	public function problem(){
		return $this->belongsTo('Grader\Model\Problem');
	}
}