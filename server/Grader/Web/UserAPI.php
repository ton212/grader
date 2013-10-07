<?php

namespace Grader\Web;

class UserAPI extends Base implements \Silex\ControllerProviderInterface{
	public function connect(\Silex\Application $app){
		$this->app = $app;
		$controllers = $app['controllers_factory'];
		$controllers->get('/user', array($this, 'get'));
		$controllers->post('/logout', array($this, 'logout'));
		return $controllers;
	}
	public function get(\Silex\Application $app){
		$user = $this->user();
		if($user == null){
			return $this->json(null);
		}
		return $this->json(array(
			'id' => $user['id'],
			'user' => $user['username'],
			'admin' => $this->acl('tests', 0, 'create')
		));
	}
	public function logout(\Silex\Application $app){
		$app['session']->set('user', null);
		return $app->redirect('/#?notify=logged_out');
	}
}