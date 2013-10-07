<?php

namespace Grader\Model;

class Acl extends Model{
	public $fillable = array('user_id', 'object', 'object_id', 'acl');
}