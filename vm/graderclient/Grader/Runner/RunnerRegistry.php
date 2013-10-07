<?php

namespace Grader\Runner;

class RunnerRegistry{
	public static $registry = array();
	private static $tested = array();
	public static function register($cls){
		static::$registry[] = $cls;
	}
	public static function by_extension($ext){
		foreach(static::$registry as $cls){
			$obj = new $cls();
			if($obj->support_ext($ext)){
				if(in_array($cls, static::$tested) || $obj->version() !== false){
					static::$tested[] = $cls;
					return $obj;
				}
			}
		}
		return false;
	}
}

RunnerRegistry::register('\Grader\Runner\LocalPhpRunner');
RunnerRegistry::register('\Grader\Runner\PhpRunner');
RunnerRegistry::register('\Grader\Runner\PyRunner');
RunnerRegistry::register('\Grader\Runner\Py3Runner');
RunnerRegistry::register('\Grader\Runner\RbRunner');
RunnerRegistry::register('\Grader\Runner\JsRunner');

RunnerRegistry::register('\Grader\Runner\CRunner');
RunnerRegistry::register('\Grader\Runner\CppRunner');
RunnerRegistry::register('\Grader\Runner\CsRunner');
RunnerRegistry::register('\Grader\Runner\JavaRunner');