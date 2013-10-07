<?php

namespace Grader\Model;

class TaskResult extends Model{
	protected $fillable = array('id', 'result');
	public function getResultAttribute($value){
		return json_decode($value);
	}
	public function setResultAttribute($value){
		$this->attributes['result'] = json_encode($value);
	}
}