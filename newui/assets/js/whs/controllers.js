!(function(){
"use strict";
var grader = angular.module("grader", [
	"ngResource", "ngSanitize", "angularMoment", "ui.router", "btford.markdown", "ui.select2"
]).run(['$rootScope', '$state', '$stateParams', '$http', function ($rootScope, $state, $stateParams, $http){
	$rootScope.$state = $state;
	$rootScope.$stateParams = $stateParams;
	$http.get('/server/user').success(function(data){
		$rootScope.user = data;
	});
}]);

var notify;

grader.config(["$locationProvider", function(provider){
	//provider.html5Mode(true);
}]);

grader.config(["$stateProvider", function(provider){
	provider.state("testlist", {
		url: "?notify&reason",
		views: {
			'score@': {
				templateUrl: "templates/score.html",
				controller: "ScoreController",
			},
			'@': {
				templateUrl: "templates/test_list.html",
				controller: "TestListController",
			}
		},
		resolve: {
			'tests': ['$q', 'Test', function($q, Test){
				var promise = $q.defer();
				Test.query(function(data){
					promise.resolve(data);
				}, function(error){
					promise.reject(error)
				});
				return promise.promise;
			}],
		}
	});
	provider.state("login", {
		url: "/login",
		parent: "testlist",
		views: {
			"@": {
				templateUrl: "templates/login.html"
			},
		},
	});
	provider.state("inputhelp", {
		url: "/writing-input",
		views: {
			"@": {
				templateUrl: "templates/inputhelp.html"
			},
		},
	});
	provider.state("test", {
		url: "/:test",
		parent: "testlist",
		views: {
			"@": {
				templateUrl: "templates/test.html",
				controller: "TestController",
			},
			"stats@test": {
				templateUrl: "templates/stats.html",
				controller: "StatsController",
			}
		},
		resolve: {
			'test': ['tests', '$stateParams', function(tests, params){
				if(params.test == "add"){
					return;
				}
				var out;
				tests.forEach(function(v){
					if(v.id == params.test){
						out = v;
						return false;
					}
				});
				return out;
			}],
			'problems': ['$q', 'Problem', '$stateParams', function($q, Problem, $stateParams){
				if($stateParams.test == "add"){
					return;
				}
				var promise = $q.defer();
				Problem.query({'test_id': $stateParams.test}, function(data){
					promise.resolve(data);
				}, function(error){
					promise.reject(error)
				});
				return promise.promise;
			}]
		}
	});
	provider.state("test.scoreboard", {
		url: "/scoreboard",
		views: {
			"@": {
				templateUrl: "templates/scoreboard.html",
				controller: "ScoreboardController",
			}
		},
	});
	provider.state("test.problem", {
		url: "/:problem",
		templateUrl: "templates/problem.html",
		controller: "ProblemController",
		resolve: {
			'problem': ['problems', '$stateParams', function(problems, params){
				if(params.problem == "add"){
					return;
				}
				var out;
				problems.forEach(function(v){
					if(v.id == params.problem){
						out = v;
						return false;
					}
				});
				return out;
			}]
		}
	});
}]);

grader.value("notify_code", {
	"login_ok": ["You have been logged in successfully", "success"],
	"login_fail": ["Login provider returned error", "danger"],
	"login_fail_reason": ["Login provider returned error:", "danger"],
	"logged_out": ["You have been logged out", "success"],
	"invalid_ext": ["Unknown file type of uploaded file", "danger"],
	"code_saved": ["Your code has been saved", "success"],
	"no_upload": ["Upload data is empty", "danger"],
	"grading": ["Please wait while your submission is being judged", "success"],
	"sub_closed": ["Submission is closed", "danger"],
	"sub_perm": ["You are not allowed to submit to this problem", "danger"],
	"sub_notready": ["This problem is not ready for submission", "warning"],
});
grader.value("languages", {
	'py': 'Python 2',
	'py3': 'Python 3',
	'php': 'PHP',
	'rb': 'Ruby',
	'js': 'JavaScript',
	'java': 'Java',
	'c': 'C',
	'cpp': 'C++',
	'cs': 'C#',
});

var base_url = "/server/";

grader.factory("Test", ["$resource", function($resource){
	return $resource(base_url + "test/:id");
}]);
grader.factory("Problem", ["$resource", function($resource){
	return $resource(base_url + "test/:test_id/problems/:id");
}]);
grader.factory("Submission", ["$resource", function($resource){
	return $resource(base_url + "test/:test_id/problems/:problem_id/submissions/:id");
}]);
grader.factory("Stats", ["$resource", function($resource){
	return $resource(base_url + "test/:test/stats");
}]);
grader.factory("Scoreboard", ["$resource", function($resource){
	return $resource(base_url + "test/:test/scoreboard");
}]);

grader.directive('graderDate', function() {
	return {
		restrict: 'A',
		require: 'ngModel',
		link: function(scope, element, attr, ngModel){
			ngModel.$parsers.push(function(text){
				var mm = moment(text);
				if(!mm || !mm.isValid()){
					return text || null;
				}
				return mm.unix();
			});
			ngModel.$formatters.push(function(text){
				if(!text){
					return text;
				}
				return moment(text.toString(), "X").format('YYYY-MM-DD HH:mm:ss');
			});
		}
	};
});

grader.controller("TestListController", ['$scope', '$rootScope', 'tests', function($scope, $rootScope, tests){
	$rootScope.title = "";
	$rootScope.test = null;
	$scope.tests = tests;
}]);

grader.controller("TestController", ['$scope', '$state', '$stateParams', 'tests', 'test', 'Test', 'problems', '$rootScope', function($scope, $state, params, tests, test, Test, problems, $rootScope){
	if(test === undefined){
		if(!$rootScope.user){
			$state.go('login');
		}else{
			$state.go('test');
		}
	}
	if(test != null){
		$rootScope.title = test.name;
		$rootScope.test = test;
		$scope.problems = problems;
		$scope.editTest = false;
	}else if(params.test == "add"){
		$rootScope.title = "Add test";
		$scope.editTest = true;
		$rootScope.test = new Test();
	}
	$scope.toggleEdit = function(){
		if(!test.acl_edit){
			return;
		}
		$scope.editTest = !$scope.editTest;
	}
	$scope.saveTest = function(){
		$rootScope.test.$save(function(test){
			notify(test.name+' saved.', 'success');
			$scope.editTest = false;
			$rootScope.test = test;
			if(params.test == "add"){
				$state.go('test', {test: test.id});
				tests.push(test);
			}
		}, function(error){
			if(error['data'] && error.data['error']){
				notify($rootScope.test.name+' is unable to be saved: '+error.data.error, 'danger');
			}else{
				notify($rootScope.test.name+' is unable to be saved.', 'danger');
			}
		});
	};
}]);

grader.controller("StatsController", ['$scope', '$stateParams', '$timeout', 'Stats', 'tests', function($scope, params, $timeout, Stats, tests){
	// note: don't remove tests from dependencies
	// it is not used, but it is ensuring correct loading order
	// i'm not sure why, but without it it AJAX won't fire.
	var timeout;
	var fetch_stats = function(){
		Stats.query({test: params.test}, function(result){
			result.forEach(function(v, k){
				$scope.$parent.problems.forEach(function(problem){
					if(problem.id == v.id){
						result[k].problem = problem;
						return false;
					}
				});
			});
			$scope.stats = result;
			timeout = $timeout(fetch_stats, 10000);
		});
	}
	fetch_stats();
	$scope.$on('$destroy', function(){
		$timeout.cancel(timeout);
	})
}]);

grader.controller("ProblemController", ['$scope', '$state', '$stateParams', 'problem', 'problems', 'test', 'Problem', '$rootScope', 'Submission', '$timeout', '$interpolate', 'languages', function($scope, $state, params, problem, problems, test, Problem, $rootScope, Submission, $timeout, $interpolate, languages){
	if(!problem && params.problem != 'new'){
		$state.go('^');
		return;
	}
	if(params.problem == 'new'){
		$scope.editProblem = true;
		problem = new Problem();
		problem.name = "Untitled";
		problem.graders = {};
		problem.description = "";
		problem.test_id = params.test;
		problem.point = 1;
		problem.comparator = 'hash';
	}
	$rootScope.title = problem.name;
	$scope.problem = problem;
	$scope.viewsub = null;
	$scope.admin_submission_url = "/server/test/"+test.id+"/problems/"+problem.id+"/iocode";
	$scope.submission_url = "/server/test/"+test.id+"/problems/"+problem.id+"/submit";
	if(problem.acl_edit || params.problem == 'new'){
		$scope.$watch('problem.graders.codejam', function(val){
			if(!val){
				return;
			}
			if(!val || !val.time_limit && !val.credit && !val.multiple){
				$scope.problem.graders.codejam = null;
			}
		}, true);
		$scope.$watch('problem.graders.grader', function(val){
			if(!val){
				return;
			}
			if(!val || !val.time_limit && !val.memory_limit && (!val.allowed || val.allowed.length == 0)){
				$scope.problem.graders.grader = null;
			}
		}, true);
	}

	$scope.languages = languages;
	var states = ['In queue', 'Grading', 'Graded'];

	// submission
	if(problem){
		var timeout;
		var fetch_submission = function(){
			Submission.query({test_id: params.test, problem_id: params.problem}, function(result){
				var next = 10000, templ = $interpolate("#{{sub.id}} {{sub.language}} at {{sub.created_at*1000|date:'medium'}} [{{sub.summary}}]");

				result.forEach(function(item){
					// if there is a job in queue, run it more frequently
					if(item.state < 2){
						next = 3000;
					}
					// count result
					var changedPassed = false;
					if(item.result){
						var cnt = {};
						var out = [];
						item.result.split("").forEach(function(x){
							if(cnt[x] === undefined){
								cnt[x] = 0;
							}
							cnt[x]++;
						});
						for(var key in cnt){
							out.push(key+"-"+cnt[key]);
						}
						item.summary = out.join(" ");
						if(!changedPassed && !problem.passed && item.correct === 1){
							problem.passed = true;
							changedPassed = true;
						}
					}else{
						item.summary = states[item.state];
					}
					if(item.correct === 1){
						item.summary = "✔ " + item.summary;
					}
					// recount passed test
					if(changedPassed){
						notify(problem.name+' passed', 'success');
						test.finished = 0;
						test.score = 0;
						problems.forEach(function(prob){
							if(prob.passed){
								test.finished++;
								test.score += prob.point;
							}
						});
					}
					item.language = languages[item.lang];
					item.line = templ({
						sub: item,
					});
					if($scope.viewsub && $scope.viewsub.id == item.id){
						$scope.viewsub = item;
					}
				});
				if($scope.viewsub === null){
					$scope.viewsub = result[0];
				}
				$scope.submissions = result;
				timeout = $timeout(fetch_submission, next);
			});
		}
		fetch_submission();
		$scope.$on('$destroy', function(){
			$timeout.cancel(timeout);
		});
		$scope.$watch('submissions', function(){
			$scope.problems = Problem.query({'test_id': params.test});
		}, true);
	}
	$scope.toggleEdit = function(){
		if(!problem.acl_edit){
			return;
		}
		$scope.editProblem = !$scope.editProblem;
	}
	$scope.saveProblem = function(){
		$scope.problem.$save({'test_id': $scope.problem.test_id, 'id': $scope.problem.id}, function(problem){
			$scope.problem = problem;
			notify(problem.name+' saved.', 'success');
			$scope.editProblem = false;
			if(params.problem == "new"){
				problems.push(problem);
				$state.go('test.problem', {test: problem.test_id, problem: problem.id});
			}
		}, function(error){
			if(error['data'] && error.data['error']){
				notify($scope.problem.name+' is unable to be saved: '+error.data.error, 'danger');
			}else{
				notify($scope.problem.name+' is unable to be saved.', 'danger');
			}
		});
	};
}]);

// i think this is a really, really bad design full of kludges
// i wonder how to do this correctly in angular?
var ScoreController = function($scope, $state, $stateParams, $timeout, notify_code, $injector){
	this.$scope = $scope;
	this.$injector = $injector;
	notify = this.notify.bind(this);
	if($stateParams['notify']){
		var notify_msg = [$stateParams['notify'], "info"];
		if(notify_code[notify_msg[0]]){
			notify_msg = notify_code[notify_msg[0]];
		}
		if($stateParams['reason']){
			notify_msg[0] += ' ' + $stateParams['reason'];
		}
		//console.log($stateParams['notify'], 'invoke');
		//$state.go('.', {notify: undefined, reason: undefined});
		this.queue.push(notify_msg);
		$injector.invoke(this.runQueue, this, {'$scope': $scope});
	}
};
ScoreController.$inject = ['$scope', '$state', '$stateParams', '$timeout', 'notify_code', '$injector'];
ScoreController.prototype.notify = function(msg, type){
	this.queue.push([msg, type]);
	this.$injector.invoke(this.runQueue, this, {'$scope': this.$scope});
};
ScoreController.prototype.queue = [];
ScoreController.prototype.queueActive = false;
ScoreController.prototype.runQueue = ['$injector', '$scope', function($injector, $scope){
	if(this.queueActive){
		return;
	}
	this.queueActive = true;
	$injector.invoke(this._realRunQueue, this, {'$scope': $scope});
}];
ScoreController.prototype._realRunQueue = ['$timeout', '$injector', '$scope', function($timeout, $injector, $scope){
	var self = this;
	var notify = this.queue.shift();
	if(notify === undefined){
		$scope.notify = null;
		this.queueActive = false;
		return;
	}
	$scope.notify = notify;
	$timeout(function(){
		$injector.invoke(self._realRunQueue, self, {'$scope': $scope});
	}, 6000);
}];

grader.controller("ScoreController", ScoreController);

grader.controller("ScoreboardController", ['$scope', 'test', 'problems', '$timeout', 'Scoreboard', '$stateParams', 'languages', function($scope, test, problems, $timeout, Scoreboard, params, languages){
	$scope.test = test;
	$scope.problems = problems;
	$scope.languages = languages;
	var timeout;
	var fetch_stats = function(){
		Scoreboard.query({test: params.test}, function(result){
			$scope.scoreboard = result;
			timeout = $timeout(fetch_stats, 10000);
		});
	}
	fetch_stats();
	$scope.$on('$destroy', function(){
		$timeout.cancel(timeout);
	})
}]);

grader.filter('bytes', function() {
	return function(bytes, precision) {
		if (bytes==0 || isNaN(parseFloat(bytes)) || !isFinite(bytes)) return '-';
		if (typeof precision === 'undefined') precision = 1;
		var units = ['B', 'kB', 'MB', 'GB', 'TB', 'PB'],
			number = Math.floor(Math.log(bytes) / Math.log(1024));
		return (bytes / Math.pow(1024, Math.floor(number))).toFixed(precision) + units[number];
	}
});


})();