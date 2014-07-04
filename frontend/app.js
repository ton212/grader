(function(){

var app = angular.module('grader', ['ui.router', 'ui.ace', 'ngAnimate', 'ngSanitize', 'restangular']);
app.config(['$stateProvider', '$urlRouterProvider', function($stateProvider, $urlRouterProvider){
	$stateProvider
		.state('login', {
			url: '/login',
			templateUrl: 'templates/login.html',
			controller: 'Login'
		})
		.state('tests', {
			url: '/',
			templateUrl: 'templates/tests.html',
			controller: 'Tests'
		})
		.state('problem', {
			url: '/:test',
			templateUrl: 'templates/problem.html',
			controller: 'Problems'
		})
		.state('problem.problem', {
			url: '/:problem',
			templateUrl: 'templates/show.html',
			controller: 'ShowProblem'
		});
	$urlRouterProvider.when('', '/login');
}]);

app.config(['RestangularProvider', function(provider){
	provider.setBaseUrl('/server/');
}]);

app.service('User', ['Restangular', '$rootScope', function(Restangular, $rootScope){
	var out = {
		'loaded': false,
		'user': null
	};
	$rootScope.user = out;

	out.load = function(){
		return Restangular.all('user').get('').then(function(data){
			out.user = data;
			out.loaded = true;
		});
	};

	return out;
}]);

app.run(['$state', 'User', function($state, User){
	User.load().then(function(){
		if(!User.user.id){
			$state.go('login');
		}
	});
}]);

app.controller('Login', ['User', '$state', '$scope', 'Restangular', function(User, $state, $scope, Restangular){
	var checkLogin = function(){
		if(!User.loaded){
			return User.load().then(checkLogin);
		}
		if(User.user.id){
			$state.go('tests');
		}
	};

	checkLogin();

	$scope.login = {'username': '', 'password': ''};
	$scope.registerState = 0;

	var registerPassword = '';

	$scope.register = function(){
		if($scope.registerState === 0){
			if(!$scope.login.username || !$scope.login.password){
				return;
			}
			registerPassword = $scope.login.password;
			$scope.login.password = '';
			$scope.registerState = 1;
		}
	};

	$scope.submit = function(){
		if($scope.registerState == 1){
			if($scope.login.password != registerPassword){
				$scope.login.password = '';
				$scope.error = 'Password do not match';
				return;
			}
			$scope.error = '';
			$scope.registerState = 2;
			Restangular.all('auth_password/register').post($scope.login).then(function(data){
				$scope.registerState = 0;
				$scope.error = null;
			}, function(data){
				$scope.error = data.data.error;
				$scope.registerState = 1;
			});
		}else{
			Restangular.all('auth_password/').post($scope.login).then(function(data){
				User.load().then(checkLogin);
			}, function(data){
				$scope.error = data.data.error;
			});
		}
	};
}]);

app.controller('Tests', ['Restangular', '$scope', function(Restangular, $scope){
	Restangular.all('test').getList().then(function(data){
		$scope.tests = data.map(function(item){
			if(item.end){
				item.end = new Date(item.end * 1000);
			}
			return item;
		});
	});
}]);

app.controller('Problems', ['Restangular', '$stateParams', '$scope', '$interval', function(Restangular, params, $scope, $interval){
	var object = Restangular.one('test', params.test)
	object.get().then(function(data){
		$scope.test = data;
	});

	var loadProblem = function(){
		object.getList('problems').then(function(data){
			$scope.problems = data;
		});
	};

	var loadStats = function(){
		object.getList('stats').then(function(data){
			$scope.stats = {};
			data.forEach(function(item){
				$scope.stats[item.id] = item;
				item.percent = (item.passed/item.attempt)*100 || 0;
			});
		});
	};

	loadProblem();
	loadStats();

	$scope.loadProblem = loadProblem;

	var autorefresh = $interval(function(){
		loadStats();
		loadProblem();
	}, 10000);
	$scope.$on('$destroy', function(){
		$interval.cancel(autorefresh);
	});
}]);
app.controller('ShowProblem', ['Restangular', '$stateParams', '$scope', '$http', '$interpolate', '$interval', function(Restangular, params, $scope, $http, $interpolate, $interval){
	$scope.source = 'public class Input {\n\tpublic static void main(String[] args){\n\t\t\n\t}\n}';
	$scope.noSubmit = false;

	var object = Restangular.one('test', params.test).one('problems', params.problem);
	object.get().then(function(data){
		$scope.problem = data;
	});

	var loadSubmission = function(){
		return object.all('submissions').getList().then(function(data){
			$scope.submissions = data.map(function(item){
				if(allowSubmitOn && item.id === allowSubmitOn && item.state == 2){
					allowSubmitOn = null;
					$scope.noSubmit = false;

					// this used to be event emitter
					// but ui.router does not nest scope properly
					$scope.loadProblem();
				}

				item.line = $interpolate("#{{sub.id}} at {{sub.created_at*1000|date:'medium'}} [{{sub.result}}]")({sub: item});
				if(item.correct){
					item.line = "✔ " + item.line
				}
				return item;
			});
		});
	};
	loadSubmission();

	var autorefresh = $interval(loadSubmission, 4000);
	var allowSubmitOn = null;
	var loadedCode = null;

	var updateOlder = function(){
		$scope.prevSub = null;
		if($scope.loadOlder){
			var obj = _.where($scope.submissions, {id: $scope.loadOlder})[0];
			$scope.prevSub = obj;

			if($scope.loadOlder != loadedCode){
				$http.get('/server/codeload/sub/' + $scope.loadOlder).then(function(src){
					$scope.source = src.data;
				});
				loadedCode = $scope.loadOlder;
			}
		}
	};

	$scope.$watch('loadOlder', updateOlder);
	$scope.$watch('submissions', updateOlder);

	$scope.submit = function(){
		$scope.noSubmit = true;
		object.all('submit').post({
			code: $scope.source,
			lang: 'java'
		}).then(function(data){
			if(data.id){
				allowSubmitOn = data.id;
				loadedCode = data.id;
				$scope.loadOlder = data.id;
				loadSubmission();
			}else{
				$scope.noSubmit = false;
			}
		});
	};

	$scope.$on('$destroy', function(){
		$interval.cancel(autorefresh);
	});
}]);

app.filter('markdown', ['$sce', function($sce){
	var showdown = new Showdown.converter();
	return function(text){
		if(!text){
			return '';
		}
		return $sce.trustAsHtml(showdown.makeHtml(text));
	};
}]);

app.filter('state', function(){
	var states = ['In queue', 'Grading', 'Graded'];
	return function(text){
		return states[parseInt(text)];
	};
});

})();