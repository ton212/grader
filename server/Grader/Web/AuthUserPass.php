<?php

namespace Grader\Web;

use Symfony\Component\HttpFoundation\Request;
use \Grader\Model\User;

class AuthUserPass extends Base{
	public $routeName = '';

	private $password_algo = PASSWORD_DEFAULT;
	private $password_param = array(
		'cost' => 12
	);


	public function connect(\Silex\Application $app){
		$this->app = $app;
		$controllers = $app['controllers_factory'];
		$controllers->post('/'.$this->routeName, array($this, 'login'));
		$controllers->post('/'.$this->routeName.'/register', array($this, 'register'));
		return $controllers;
	}

	public function login(\Silex\Application $app, Request $req){
		$username = $req->get('username');
		if(empty($username)){
			$this->error('Specify username', 400);
		}

		$user = User::where('username', '=', $username)->first();
		$hash = $user ? $user->auth->password : '';

		// prevent timing attack
		if(!password_verify($req->get('password'), $hash) || !$user){
			$this->error('Invalid username or password', 401);
		}

		if(password_needs_rehash($hash, $this->password_algo, $this->password_param)){
			$user->auth->password = password_hash($req->get('password'), $this->password_algo, $this->password_param);
			$user->save();
		}

		$app['session']->set('user', $user->id);
		return $this->json(array(
			'id' => $user->id,
			'user' => $user->username
		));
	}

	public function register(Request $req){
		$username = $req->get('username');
		$password = $req->get('password');

		if(!preg_match('~^[a-z0-9._\\-]{3,20}$~', $username)){
			$this->error('Username must be a-z0-9._- at least 3-20 characters', 400);
		}
		if(empty($password)){
			$this->error('Enter password', 400);
		}

		// XXX: This is prone to race condition
		$otherUser = User::where('username', '=', $username)->count() > 0;
		if($otherUser){
			$this->error('Username is duplicate', 400);
		}

		$user = User::create(array(
			'username' => $username
		));
		$user->auth = array(
			'password' => password_hash($password, $this->password_algo, $this->password_param)
		);
		$user->save();

		return $this->json(array(
			'id' => $user['id'],
			'user' => $user['username']
		));
	}
}