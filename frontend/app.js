(function(){

var app = angular.module('grader', ['ui.router', 'ui.ace', 'ngAnimate', 'ngSanitize', 'restangular']);
app.config(['$stateProvider', function($stateProvider){
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
}]);

app.config(['RestangularProvider', function(provider){
	provider.setBaseUrl('http://grader.whs.in.th/server/');
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

app.controller('Login', ['User', '$state', function(User, $state){
	var checkLogin = function(){
		if(!User.loaded){
			return User.load().then(checkLogin);
		}
		if(User.user.id){
			$state.go('tests');
		}
	};

	checkLogin();
}]);

app.controller('Tests', ['Restangular', '$scope', function(Restangular, $scope){
	Restangular.all('test').getList().then(function(data){
		$scope.tests = data;
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

	var autorefresh = $interval(loadStats, 10000);
	$scope.$on('$destroy', function(){
		$interval.cancel(autorefresh);
	});
}]);
app.controller('ShowProblem', ['Restangular', '$stateParams', '$scope', '$http', '$interpolate', '$interval', function(Restangular, params, $scope, $http, $interpolate, $interval){
	$scope.source = 'public class Solution {\n\tpublic static void main(String[] args){\n\t\t\n\t}\n}';
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
				$http.get('http://grader.whs.in.th/server/codeload/sub/' + $scope.loadOlder).then(function(src){
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

app.filter('markdown', function(){
	var showdown = new Showdown.converter();
	return function(text){
		if(!text){
			return '';
		}
		return showdown.makeHtml(text);
	};
});

app.filter('state', function(){
	var states = ['In queue', 'Grading', 'Graded'];
	return function(text){
		return states[parseInt(text)];
	};
});

})();