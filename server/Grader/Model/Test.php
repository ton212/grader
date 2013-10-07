<?php

namespace Grader\Model;

class Test extends Model{
	protected $hidden = array('created_at', 'updated_at', 'deleted_at');
	protected $fillable = array('name', 'mode', 'start', 'end', 'readonly');
	public function getDates(){
		return array_merge(parent::getDates(), array('start', 'end'));
	}
	public function problems(){
		return $this->hasMany('Grader\Model\Problem');
	}
}