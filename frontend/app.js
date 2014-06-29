(function(){

var app = angular.module('grader', ['ui.router', 'ngAnimate', 'restangular']);
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
		});
}]);

app.config(['RestangularProvider', function(provider){
	provider.setBaseUrl('http://grader.whs.in.th/server/');
}]);

app.service('User', ['Restangular', function(Restangular){
	var out = {
		'loaded': false,
		'user': null
	};

	out.load = function(){
		return Restangular.all('user').get('').then(function(data){
			out.user = data;
			out.loaded = true;
		});
	};

	return out;
}]);

app.run(['$state', function($state){
	$state.go('login');
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

})();