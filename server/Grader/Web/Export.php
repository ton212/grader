<?php

namespace Grader\Web;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use \Grader\Model\User;
use \Grader\Model\Result;

class Export extends Base{
	public $routeName = '';

	public function connect(\Silex\Application $app){
		$this->app = $app;
		$controllers = $app['controllers_factory'];
		$controllers->get('/'.$this->routeName, array($this, 'export'));
		return $controllers;
	}

	public function export(Request $req){
		$user = $req->get('user');

		if(empty($user)){
			$user = $this->user() ? $this->user()->username : null;
		}
		if(empty($user)){
			$this->error('Authentication required', 401);
		}

		if($user != $this->user()->username && !$this->acl('tests', 0, 'edit')){
			$this->error('You may not download submissions of this user', 401);
		}

		$user = User::where('username', '=', $user)->first();
		if(!$user){
			$this->error('User not found', 500);
		}

		$q = Result::where('user_id', '=', $user->id);

		$zipPath = tempnam(sys_get_temp_dir(), 'gd-export-');
		$zip = new \ZipArchive();
		$zip->open($zipPath, \ZipArchive::OVERWRITE);

		$dirCreated = array();
		foreach($q->get() as $item){
			if(!in_array($item->problem_id, $dirCreated)){
				$zip->addEmptyDir($item->problem_id);
				$dirCreated[] = $item->problem_id;
			}
			$fn = $item->problem_id.'/'.$item->id.'.'.$item->lang;
			$zip->addFromString($fn, $item->code);
			$zip->setCommentName($fn, 'Result: '.$item->result."\r\nCorrect: ".$item->correct."\r\nError:\r\n".$item->error."\r\nSubmitted: ".$item->created_at->toCOOKIEString());
		}

		$zip->close();

		$res = new Response(
			file_get_contents($zipPath),
			200,
			array(
				'Content-Type' => 'application/zip',
				'Content-Length' => stat($zipPath)[7],
				'Content-Disposition' => 'attachment; filename='.$user->username.'.zip'
			)
		);
		unlink($zipPath);
		return $res;
	}
}