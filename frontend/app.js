(function(){

var app = angular.module('grader', ['ui.router', 'ngAnimate', 'ngSanitize', 'restangular']);
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
app.controller('ShowProblem', ['Restangular', '$stateParams', '$scope', function(Restangular, params, $scope){
	var object = Restangular.one('test', params.test).one('problems', params.problem);
	object.get().then(function(data){
		$scope.problem = data;
	});
}]);

app.filter('markdown', function(){
	var showdown = new Showdown.converter();
	return function(text){
		return showdown.makeHtml(text);
	};
});

})();