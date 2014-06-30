<?php

namespace Grader\Web;

use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\HttpFoundation\Request;

class StatAPI extends Base implements \Silex\ControllerProviderInterface{
	public function connect(\Silex\Application $app){
		$this->app = $app;
		$controllers = $app['controllers_factory'];
		$controllers->get('/test/{testId}/stats', array($this, 'get'));
		$controllers->get('/test/{testId}/scoreboard', array($this, 'scoreboard'));
		return $controllers;
	}
	public function get(Request $req){
		if(!$this->acl('tests', $req->get('testId'), 'view') || !$this->can_view($req)){
			$this->app->abort(403, 'You don\'t have permission to view this');
		}
		$q = Capsule::connection()->select('
			SELECT `problems`.`id` AS `id`,
			(
				SELECT COUNT(*)
				FROM `results`
				WHERE `problems`.`id` = `results`.`problem_id`
				AND `results`.`state` = 2
				AND `results`.`correct` IS NOT NULL
				AND `results`.`count_stats` = 1
			) AS `attempt`,
			(
				SELECT COUNT(*)
				FROM `results`
				WHERE `problems`.`id` = `results`.`problem_id`
				AND `results`.`state` = 2
				AND `results`.`correct` = 1
				AND `results`.`count_stats` = 1
			) AS `passed`
			FROM `problems`
			WHERE `problems`.`test_id` = ?
		', array($req->get('testId')));
		return $this->json($q);
	}

	public function scoreboard(Request $req){
		if(!$this->acl('tests', $req->get('testId'), 'view') || !$this->can_view($req)){
			$this->app->abort(403, 'You don\'t have permission to view this');
		}
		$q = Capsule::connection()->select('
			SELECT `id`, `username`, CAST(SUM(CASE WHEN `correct` = 1 THEN `point` ELSE 0 END) AS int) AS score FROM (
				SELECT DISTINCT `problem_id`+" "+`user_id`, `users`.`id`, `username`, `problems`.`point`, `correct`
				FROM `results`
				INNER JOIN `problems` ON `problems`.`id` = `results`.`problem_id`
				INNER JOIN `users` ON `results`.`user_id` = `users`.`id`
				WHERE `state` = 2
				AND `problems`.`test_id` = ?
			) result
			GROUP BY `username`
			ORDER BY `score` DESC
		', array($req->get('testId')));
		$out = array();
		$can_download = $this->acl('tests', $req->get('testId'), 'edit');
		foreach($q as $item){
			// TODO: Can we group to only one query not per-user?
			$q = Capsule::connection()->select('
				SELECT DISTINCT `results`.`problem_id`, `results`.`id`, `problems`.`name`, `results`.`lang`, `results`.`created_at`, `results`.`correct`,
					LENGTH(`results`.`code`) AS `size`, `results`.`closest`, `closest`.`id` AS `closest_id`, `closest_user`.`username` AS `closest_user`,
				(
					SELECT COUNT(*) `wrong`
					FROM `results` `r`
					WHERE `r`.`problem_id` = `results`.`problem_id`
					AND `r`.`state` = 2 AND `r`.`correct` != 1
					AND `r`.`user_id` = `results`.`user_id`
					AND `results`.`count_stats` = 1
				) AS `wrong` FROM `results`
				INNER JOIN `problems` ON `problems`.`id` = `results`.`problem_id`
				INNER JOIN `users` ON `results`.`user_id` = `users`.`id`
				LEFT OUTER JOIN `results` `closest` ON `results`.`closest_id` = `closest`.`id`
				LEFT OUTER JOIN `users` `closest_user` ON `closest`.`user_id` = `closest_user`.`id`
				WHERE `results`.`state` = 2
				AND `results`.`user_id` = ?
				AND `problems`.`test_id` = ?
				ORDER BY (CASE WHEN `results`.`correct` = 1 THEN 1 ELSE 0 END) ASC, `size` DESC, `id` ASC
			', array($item['id'], $req->get('testId')));
			$item['problems'] = array();
			foreach($q as $problem){
				if(!$can_download){
					unset($problem['id']);
					unset($problem['closest']);
					unset($problem['closest_id']);
					unset($problem['closest_user']);
				}
				if($problem['correct'] != 1){
					unset($problem['id']);
					unset($problem['lang']);
					unset($problem['size']);
				}
				$item['problems'][$problem['problem_id']] = $problem;
			}
			$out[] = $item;
		}
		return $this->json($out);
	}

	private function can_view(Request $req){
		$test = \Grader\Model\Test::find($req->get('testId'));
		return $test && (
			(
				$test->start &&
				$test->start->isFuture() &&
				!$this->acl('tests', $test->id, 'edit')
			) ||
			!$test->start ||
			$this->acl('tests', $test->id, 'edit')
		);
	}
}