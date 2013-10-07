<?php

namespace Grader\Model;

class Model extends \Illuminate\Database\Eloquent\Model{
	public function toArray(){
		$out = array();
		foreach(array_keys($this->getArrayableAttributes()) as $attr){
			$out[$attr] = $this->getAttribute($attr);
		}
		return array_merge($out, $this->relationsToArray());
	}
}