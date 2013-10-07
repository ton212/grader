<?php
// used in grader communication. set to random string
$app['key'] = '';
$app['opauth'] = array(
	'login' => '/auth',
	'callback' => '/callback',
	'config' => array(
		'path' => '/server/auth/',
		'callback_url' => '/server/callback',
		'security_salt' => 'random string here',
		'Strategy' => array(
			'Facebook' => array(
				'app_id' => '',
				'app_secret' => '',
			),
			'Twitter' => array(
				'key' => '',
				'secret' => ''
			),
			'Google' => array(
				'client_id' => '',
				'client_secret' => ''
			)
		)
	)
);