<?php

namespace Grader\Web;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Grader\Model\Result;
use Grader\Model\Problem;

class Codeload extends Base{
	// using fileinfo
	// https://github.com/glensc/file/tree/master/magic/Magdir
	public static $extToMime = array(
		'php' => 'text/x-php',
		'py' => 'text/x-python',
		'py3' => 'text/x-python-3',
		'rb' => 'text/x-ruby',
		// IANA approved
		'js' => 'application/javascript',
		'java' => 'text/x-java',
		'c' => 'text/x-c',
		'cs' => 'text/x-csharp',
		'cpp' => 'text/x-c++',
		'java' => 'text/x-java'
	);

	public function connect(\Silex\Application $app){
		$this->app = $app;
		$controllers = $app['controllers_factory'];
		$controllers->get('/sub/{id}', array($this, 'sub_load'));
		$controllers->get('/input/{id}', array($this, 'inp_load'));
		$controllers->get('/output/{id}', array($this, 'out_load'));
		return $controllers;
	}

	public function sub_load(Request $req){
		$this->request = $req;
		$res = Result::find($req->get('id'));
		if(!$res){
			$this->app->abort(404, 'Submission not found');
		}
		$user = $this->user();
		// check acl
		if(!$user || $res->user_id != $user->id && !$this->acl('tests', $res->problem->test_id, 'edit')){
			$this->app->abort(403, 'You do not have permission to perform this action.');
		}
		return $this->response($res['id'], $res['code'], $res['lang'], $res['created_at']);
	}

	private function get_problem(Request $req){
		$res = Problem::find($req->get('id'));
		if(!$res){
			$this->app->abort(404, 'Problem not found');
		}
		if(!$this->acl('tests', $res->test->id, 'edit')){
			$this->app->abort(403, 'You do not have permission to perform this action.');
		}
		return $res;
	}

	public function inp_load(Request $req){
		$this->request = $req;
		$problem = $this->get_problem($req);
		return $this->response('input_'.$problem['id'], $problem['input'], $problem['input_lang'], $problem['updated_at']);
	}

	public function out_load(Request $req){
		$this->request = $req;
		$problem = $this->get_problem($req);
		return $this->response('output_'.$problem['id'], $problem['output'], $problem['output_lang'], $problem['updated_at']);
	}

	protected function response($id, $code, $ext, $mod=null){
		if(isset(self::$extToMime[$ext])){
			$contentType = self::$extToMime[$ext];
		}else{
			$contentType = 'text/plain';
		}
		$res = new Response(
			$code,
			200,
			array(
				'Content-Type' => $contentType,
				'Content-Length' => mb_strlen($code, 'latin1')
			)
		);
		if(!$this->request->query->has('view')){
			$res->headers->set('Content-Disposition', 'attachment; filename="'.$id.'.'.$ext.'"');
		}
		$res->setCache(array(
			'last_modified' => $mod,
			'max_age'       => 60*60*24*365,
			's_maxage'      => 60*60*24*365,
			'private'       => true,
			'public'        => false,
		));
		$res->setCharset('UTF-8');
		return $res;
	}
}