<?php

namespace Grader\Web;

use Symfony\Component\HttpFoundation\Request;
use Grader\Runner\RunnerRegistry;
use Grader\Model\Problem;
use Grader\Model\Result;

class ProblemAPI extends API{
	public $className = 'Grader\Model\Problem';
	public $routeName = 'test/{testId}/problems';
	public $useAcl = false;
	public function __construct(){	
	}
	public function connect(\Silex\Application $app){
		$controllers = parent::connect($app);
		$controllers->post('/'.$this->routeName.'/{id}/iocode', array($this, 'save_code'));
		$controllers->post('/'.$this->routeName.'/{id}/submit', array($this, 'submit'));
		return $controllers;
	}

	public function save_code(Request $req){
		$model = $this->get_query()->where('id', '=', $req->get('id'))->first();
		if(!$model){
			$this->app->abort('Item requested not found', 404);
		}
		$this->save_acl($model['id'], 'edit', $model);
		foreach(array('input', 'output') as $type){
			if($file = $req->files->get($type)){
				if($type === 'input' && !in_array($file->getClientOriginalExtension(), array('php', 'py', 'java'))){
					return $this->app->redirect('/#/'.$model['test_id'].'/'.$model['id'].'?notify=invalid_ext&reason='.$file->getClientOriginalExtension().' '.$type);
				}
				$model->{$type.'_lang'} = $file->getClientOriginalExtension();
				$model->$type = file_get_contents($file->getPathname());
			}
		}
		$model->save();
		return $this->app->redirect('/oldui/#/'.$model['test_id'].'/'.$model['id'].'?notify=code_saved');
	}

	public function submit(Request $req, \Silex\Application $app){
		$outputFormat = $req->files->has('source'); // true = old format

		// find model
		$model = $this->get_query()->where('id', '=', $req->get('id'))->first();
		if(!$model){
			$this->app->abort(403, 'Item requested not found');
		}
		// get acl
		if(!$this->acl('tests', $model->test_id, 'view') || !$this->user()){
			if($outputFormat){
				return $this->app->redirect('/oldui/#/'.$model['test_id'].'/'.$model['id'].'?notify=sub_perm');
			}else{
				$this->app->abort(403, 'Permission denied');
			}
		}
		if(!$model->test->allow_submission() && !$this->acl('tests', $model->test_id, 'edit')){
			if($outputFormat){
				return $this->app->redirect('/oldui/#/'.$model['test_id'].'/'.$model['id'].'?notify=sub_closed');
			}else{
				$this->app->abort(403, 'Submission closed');
			}
		}

		// get config
		$config = $model['graders']->grader;
		if(empty($config) || empty($model['input']) || (empty($model['output']) && $model['comparator'] == 'hash')){
			if($outputFormat){
				return $this->app->redirect('/oldui/#/'.$model['test_id'].'/'.$model['id'].'?notify=sub_notready');
			}else{
				$this->app->abort(403, 'Problem not ready for submission');
			}
		}

		// validate extension
		if($outputFormat){
			if($file = $req->files->get('source')){
				$lang = $file->getClientOriginalExtension();
				if(!in_array($lang, $config->allowed)){
					return $this->app->redirect('/oldui/#/'.$model['test_id'].'/'.$model['id'].'?notify=invalid_ext&reason='.$file->getClientOriginalExtension());
				}
				$code = file_get_contents($file->getPathname());
			}
		}else{
			$lang = $req->request->get('lang');
			if(!in_array($lang, $config->allowed)){
				$this->app->abort(403, 'Language not allowed for submission');
			}
			$code = $req->request->get('code');
		}

		// get user
		$user = $this->user();
		$userId = $user ? $user['id'] : null;
		// count stat?
		$countStats = !(\Grader\Model\Result::where('user_id', '=', $userId)
				->where('correct', '=', 1)
				->where('problem_id', '=', $model['id'])
				->exists());

		// save to db
		$result = \Grader\Model\Result::create(array(
			'problem_id' => $model['id'],
			'user_id' => $userId,
			'state' => 0,
			'correct' => null,
			'code' => $code,
			'grader' => 'grader',
			'lang' => $lang,
			'count_stats' => $countStats?1:0
		));

		if($model['output_lang'] === 'jar'){
			$model['output'] = base64_encode($model['output']);
		}

		// publish task
		$app['beanstalk']->useTube('grader');
		$taskId = $app['beanstalk']->put(
			json_encode(array(
				'type' => 'grade',
				'result_id' => $result['id'],
				'comparator' => $model['comparator'],
				'input' => array(
					'lang' => $model['input_lang'],
					'code' => $model['input'],
				),
				'output' => array(
					'lang' => $model['output_lang'],
					'code' => $model['output'],
				),
				'submission' => array(
					'lang' => $lang,
					'code' => $code,
				),
				'limits' => array(
					'mem' => $config->memory_limit ? $config->memory_limit : null,
					'time' => $config->time_limit ? $config->time_limit : null,
				)
			)), 50, 0, 60
		);

		if($outputFormat){
			return $this->app->redirect('/oldui/#/'.$model['test_id'].'/'.$model['id'].'?notify=grading&result_id='.$result['id']);
		}else{
			return $this->json(array(
				'success' => true,
				'id' => $result->id
			));
		}
	}

	public function get_query(){
		return $this->call_model('where', 'test_id', '=', $this->app['request']->get('testId'));
	}

	protected $saveGrant = false;

	protected function save_acl($id, $perm, $obj){
		$testId = $this->app['request']->get('testId');
		if($id > 0 && $obj->test_id != $testId){
			$this->error('Problem does not belong to current test.', 404);
		}
		if(!$this->acl('tests', $testId, 'edit')){
			$this->error('You don\'t have permission to '.$perm.' this object.', 403);
		}
	}

	protected function save_mangle($obj){
		$obj->test_id = $this->app['request']->get('testId');
	}

	protected function _mangle_item($name, &$item){
		if(!$item instanceof Problem){
			return parent::_mangle_item($name, $item);
		}

		// check acl
		if(!$this->acl('tests', $item['test_id'], 'view')){
			return self::$remove;
		}

		// check input/output is set
		$can_grade = true;
		if($item->input == '' || ($item->output == '' && $item['comparator'] == 'hash')){
			$can_grade = false;
		}

		$outitem = parent::_mangle_item($name, $item);

		$allow_edit = $this->acl('tests', $item['test_id'], 'edit');
		if(isset($outitem['acl_edit'])){
			$outitem['acl_edit'] = $allow_edit;
		}else if(is_array($outitem)){
			foreach($outitem as &$iitem){
				if(isset($iitem['acl_edit'])){
					$iitem['acl_edit'] = $allow_edit;
				}
			}
		}

		if($allow_edit){
			foreach(array('input_lang', 'output_lang', 'comparator') as $copy){
				$outitem[$copy] = $item[$copy];
			}
		}

		if(!$can_grade && is_object($outitem['graders'])){
			if(!$allow_edit){
				unset($outitem['graders']->codejam);
				unset($outitem['graders']->grader);
			}
			$outitem['graders']->invalid = true;
		}

		if($outitem == parent::$remove){
			return $outitem;
		}else{
			if($user = $this->user()){
				$passed = Result::where('user_id', '=', $user['id'])
					->where('state', '=', 2)
					->where('correct', '=', 1)
					->where('problem_id', '=', $item['id']);
				$outitem['passed'] = $passed->exists();
			}
		}
		return $outitem;
	}
}