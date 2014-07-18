<?php
namespace Grader\Web;

use Grader\Model\User;
use Grader\Model\Acl;

abstract class Base implements \Silex\ControllerProviderInterface{
	/**
	 * Check for view acl
	 */
	public $useAcl = true;
	/**
	 * Check for edit acl
	 */
	public $useAclEdit = true;
	/**
	 * Get current user
	 */
	public function user(){
		static $user;
		if($user){
			return $user;
		}
		$userId = $this->app['session']->get('user');
		if(!$userId){
			return null;
		}
		$user = User::find($userId);
		return $user;
	}
	/**
	 * Check for acl for object
	 * acl("tests") -> list all acl for tests
	 * acl("tests", 1, "view") -> check for view access to tests id 1 from current user
	 * Use object ID = 0 for all object of type, user = NULL for all user incl. guest
	 * user = -1 for guest only
	 * This is a whitelist-based system.
	 */
	public function acl($object, $objectId=null, $acl=null, $user=null){
		if(!$user){
			$user = $this->user();
			if($user == null){
				$user = -1;
			}else{
				$user = $user['id'];
			}
		}
		if($objectId !== null){
			$q = Acl::where('object', '=', $object)
				->whereIn('object_id', array(0, $objectId))
				->where(function($q) use ($user){
					$q->where('user_id', '=', $user)
						->orWhere('user_id', '=', null);
				});
			if($acl !== null){
				$q->where('acl', '=', $acl);
			}
			return $q->exists();
		}else{
			$q = Acl::where('object', '=', $object)
				->whereIn('user_id', array(null, $user));
			return $q->get();
		}
	}
	protected function error($message, $status=403){
		// TODO: Really throw a JSON exception. This one throw a Silex error
		return $this->app->abort($status, json_encode(array("error" => $message)), array(
			'Content-Type' => 'application/json'
		));
	}

	protected function json($inp){
		if(is_array($inp)){
			foreach($inp as $key=>&$item){
				/*if($item instanceof ArrayAccess){
					// collection
					foreach($item as $ikey=>&$inner){
						$item[$ikey] = $this->_mangle_item($ikey, $inner);
						if($item[$ikey] == self::$remove){
							unset($item[$ikey]);
						}
					}
				}*/
				$inp[$key] = $this->_mangle_item($key, $item);
				if($inp[$key] === self::$remove){
					unset($inp[$key]);
				}
			}
			$inp = array_values($inp);
		}else{
			$inp = $this->_mangle_item('', $inp);
			if($inp == self::$remove){
				$inp = null;
			}
		}
		return $this->app->json($inp);
	}

	protected static $remove = '!!!!REMOVE';

	protected function _mangle_item($name, &$item){
		$out = $item;
		if($item instanceof \Illuminate\Database\Eloquent\Model){
			$out = $item->toArray();
			if($this->useAcl && !$this->acl($item->getTable(), $item['id'], 'view')){
				return self::$remove;
			}
			$out['acl_edit'] = $this->acl($item->getTable(), $item['id'], 'edit');
		}
		if(is_array($out)){
			foreach($out as $prop_name => $prop_val){
				if($prop_val instanceof \Carbon\Carbon){
					$out[$prop_name] = $prop_val->timestamp;
				}
			}
			if(isset($item['readonly']) && is_numeric($item['readonly'])){
				$out['readonly'] = $item['readonly'] === 1;
			}
		}
		return $out;
	}
}
