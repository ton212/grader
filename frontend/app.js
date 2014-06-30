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
			url: '',
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

app.controller('Problems', ['Restangular', '$stateParams', '$scope', function(Restangular, params, $scope){
	var object = Restangular.one('test', params.test)
	object.get().then(function(data){
		$scope.test = data;
	});
	object.getList('problems').then(function(data){
		$scope.problems = data;
	});
}]);
app.controller('ShowProblem', ['Restangular', '$stateParams', '$scope', '$http', '$interpolate', function(Restangular, params, $scope, $http, $interpolate){
	$scope.source = 'public class Solution {\n\tpublic static void main(String[] args){\n\t\t\n\t}\n}';

	var object = Restangular.one('test', params.test).one('problems', params.problem);
	object.get().then(function(data){
		$scope.problem = data;	
	});
	object.all('submissions').getList().then(function(data){
		$scope.submissions = data.map(function(item){
			item.line = $interpolate("#{{sub.id}} at {{sub.created_at*1000|date:'medium'}} [{{sub.result}}]")({sub: item});
			if(item.correct){
				item.line = "✔ " + item.line
			}
			return item;
		});
	});

	$scope.$watch('loadOlder', function(val){
		$scope.prevSub = val;
		if(val){
			$http.get('http://grader.whs.in.th/server/codeload/sub/' + val.id).then(function(src){
				$scope.source = src.data;
			});
		}
	});

	$scope.submit = function(){
		object.all('submit').post({
			code: $scope.source,
			lang: 'java'
		});
	};
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