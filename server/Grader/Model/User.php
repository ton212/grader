<?php

namespace Grader\Model;

class User extends Model{
	public $fillable = array('username');
	public function getAuthAttribute($value){
		return json_decode($value);
	}
	public function setAuthAttribute($value){
		$this->attributes['auth'] = json_encode($value);
	}
}