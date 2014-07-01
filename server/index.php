<?php
require_once __DIR__.'/vendor/autoload.php';

date_default_timezone_set('Asia/Bangkok');

$app = new Silex\Application();
$app['debug'] = true;

require_once "opauth.php";

require_once "pheanstalk.php";

use SilexOpauth\OpauthExtension;

require_once "database.php";

$app->register(new OpauthExtension());
$app['dispatcher']->addListener(OpauthExtension::EVENT_SUCCESS, function($e) use ($app){
	$data = $e->getSubject();
	$data = $data['auth'];
	$name = isset($data['info']['nickname']) ? $data['info']['nickname'] : $data['info']['name'];
	$user = Grader\Model\User::where('username', '=', $name)->first();
	$loginOk = false;
	if(empty($user)){
		$user = new Grader\Model\User(array('username' => $name));
		$user->auth = array(
			$data['provider'] => $data['uid']
		);
		$user->save();
		$loginOk = true;
	}else{
		if(isset($user->auth->{$data['provider']})){
			if($user->auth->{$data['provider']} == $data['uid']){
				$loginOk = true;
			}
		}
	}
	if($loginOk){
		$app['session']->set('user', $user['id']);
		$request = new Symfony\Component\HttpFoundation\Response('', 303);
		$request->headers->set('Location', '/frontend/');
		$e->setArgument('result', $request);
	}else{
		$request = new Symfony\Component\HttpFoundation\Response('', 303);
		$request->headers->set('Location', '/frontend/');
		$e->setArgument('result', $request);
	}
	return $e;
});
$app['dispatcher']->addListener(OpauthExtension::EVENT_ERROR, function($e){
	$data = $e->getSubject();
	$request = new Symfony\Component\HttpFoundation\Response('', 303);
	if(isset($data['error'])){
		$request->headers->set('Location', '/frontend/#?notify=login_fail_reason&reason='.$data['error']['message']);
	}else{
		$request->headers->set('Location', '/frontend/#?notify=login_fail');
	}
	$e->setArgument('result', $request);
	return $e;
});

$app->before(function (Symfony\Component\HttpFoundation\Request $request) {
	if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
		$data = json_decode($request->getContent(), true);
		$request->request->replace(is_array($data) ? $data : array());
	}
});

use Symfony\Component\HttpFoundation\Response;

$app->error(function(Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e){
    $res = new Response($e->getMessage());
    $res->headers->replace($e->getHeaders());
    $res->setStatusCode($e->getStatusCode());
    return $res;
});

use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\HttpKernel\Debug\ExceptionHandler;

ErrorHandler::register();
ExceptionHandler::register();

$app->register(new Silex\Provider\SessionServiceProvider());

$app->mount('/', new Grader\Web\TestAPI('\Grader\Model\Test'));
$app->mount('/', new Grader\Web\ProblemAPI('\Grader\Model\Problem'));
$app->mount('/', new Grader\Web\StatAPI());
$app->mount('/', new Grader\Web\UserAPI());
$app->mount('/', new Grader\Web\TaskAPI());
$app->mount('/', new Grader\Web\SubmissionAPI());
$app->mount('/auth_password', new Grader\Web\AuthUserPass());
$app->mount('/codeload/', new Grader\Web\Codeload());

$app->run();