<?php

namespace Grader\Web;

use Symfony\Component\HttpFoundation\Request;

class API extends Base{
	public $className;
	public $routeName;
	public $allowed=array('get', 'query', 'edit', 'add');
	/**
	 * Array of query params that is allowed.
	 * `true` to allow all.
	 */
	public $query_allowed = true;

	protected $model;

	public function __construct($class, $allowed=array('get', 'query', 'edit', 'add')){
		$this->className = $class;
		$this->allowed = $allowed;
		preg_match('~[\\\\]{0,1}([^\\\\]+)$~', $class, $clsName);
		$this->routeName = strtolower($clsName[1]);
	}

	public function connect(\Silex\Application $app){
		$this->app = $app;
		$controllers = $app['controllers_factory'];
		$controllers->get('/'.$this->routeName, array($this, 'query'));
		$controllers->get('/'.$this->routeName.'/{id}', array($this, 'get'));
		$controllers->post('/'.$this->routeName, array($this, 'save'));
		$controllers->post('/'.$this->routeName.'/{id}', array($this, 'save'));
		return $controllers;
	}

	public function get(\Silex\Application $app, Request $req){
		$model = $this->get_query()->where('id', '=', $req->get('id'))->first();
		if(!$model){
			$this->error('Item requested not found', 404);
		}
		return $this->json($model);
	}

	public function query(\Silex\Application $app, Request $req){
		if(!$this->allowed('query')){
			$app->error('This controller does not allow querying.', 405);
		}
		$this->request = $req;
		$q = $this->get_query();
		foreach($req->query->all() as $query=>$value){
			if($this->query_allowed === true || in_array($query, $this->query_allowed)){
				$q->where($query, '=', $value);
			}
		}
		return $this->json($q->get()->all());
	}

	/**
	 * Whether to grant ACL permissions on save
	 */
	protected $saveGrant = true;

	public function save(\Silex\Application $app, Request $req){
		$id = $req->get('id');
		if(empty($id)){
			$perm = 'add';
		}else{
			$perm = 'edit';
		}
		if(!$this->allowed($perm)){
			$app->error('This controller does not allow '.$perm.'ing.', 405);
		}
		$this->request = $req;
		$create = false;
		if(!$id){
			$id = 0;
			$obj = $this->create_model();
			$create = true;
		}else{
			$obj = $this->call_model('find', $id);
		}
		$this->save_acl($id, $perm, $obj);
		try{
			$obj->fill($req->request->all());
		}catch(Illuminate\Database\Eloquent\MassAssignmentException $e){
			$this->error('Object of type '.$obj->getTable().' does not allow mass filling.', 500);
		}
		$this->save_mangle($obj);
		$obj->save();
		if($create && $this->saveGrant){
			// grant acl if created new object
			$uid = $this->user();
			$uid = $uid['id'];
			foreach(array('edit', 'view', 'delete') as $perm){
				$acl = new \Grader\Model\Acl(array(
					'user_id' => $uid,
					'object' => $obj->getTable(),
					'object_id' => $obj['id'],
					'acl' => $perm,
				));
				$acl->save();
			}
		}
		return $this->json($obj);
	}

	/**
	 * Overridable.
	 * @param int Object ID or 0 for add
	 * @param string "add" or "edit"
	 * @param Model Blank model or an existing model
	 */
	protected function save_acl($id, $perm, $obj){
		if(!$this->acl($this->create_model()->getTable(), $id, $perm)){
			$this->error('You don\'t have permission to '.$perm.' this object', 403);
		}
	}

	protected function save_mangle($obj){
	}

	protected function call_model($func){
		$cls = $this->className;
		$args = func_get_args();
		$args = array_slice($args, 1);
		return forward_static_call_array(array($this->className, $func), $args);
	}

	protected function create_model(){
		if(!isset($this->reflection)){
			$this->reflection = new \ReflectionClass($this->className);
		}
		return $this->reflection->newInstanceArgs(func_get_args());
	}

	protected function get_query(){
		return $this->create_model()->newQuery();
	}

	protected function allowed($name){
		return in_array($name, $this->allowed);
	}
}