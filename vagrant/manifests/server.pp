apt::source { 'mariadb':
	location          => 'http://download.nus.edu.sg/mirror/mariadb/repo/5.5/ubuntu',
	repos             => 'main',
	include_src       => false,
	key               => '1BB943DB',
	key_server        => 'pgp.mit.edu',
}

class {
	'php::extension::mysql':
		provider => 'apt';
}

include php::fpm
include php::extension::mysql

# setup mysql

class mysql::server {
	## seems that mysql::server doesn't seem to work out well
	## so we have to install it manually

	package { 'mariadb-server-5.5':
		ensure => latest,
		require => [Anchor['apt::source::mariadb']],
	}

	service { 'mysql':
		ensure => running,
		enable => true,
		require => Package['mariadb-server-5.5']
	}
}
include mysql::server

## now let the package config it

class { 'mysql::config':
	root_password => $mysql_root,
	require => Class['mysql::server'],
	subscribe => Package['mariadb-server-5.5'],
	old_root_password => ""
}

include mysql::config

class { 'mysql::server::account_security':
	require => Class['mysql::config'],
	subscribe => Package['mariadb-server-5.5'],
}

include mysql::server::account_security

mysql::db { 'grader':
	user     => 'grader',
	password => $mysql_user,
	host     => 'localhost',
	grant    => ['all'],
	require  => Class['mysql::config'],
	subscribe => Package['mariadb-server-5.5'],
}

# setup nginx

class {
	'nginx':
		user => 'www-data';
}

include nginx

nginx::file { 'default-vhost.conf':
	source => '/vagrant/vagrant/nginx.conf',
}

# copy grader
file { '/var/www':
	ensure => directory,
	recurse => true,
	source => "/vagrant",
	ignore => ["grader-bb", "grader-ember", "mock", "vagrant", "vm", "problems", "server"]
}
file { '/var/www/server':
	ensure => directory,
	recurse => true,
	source => "/vagrant/server",
	ignore => ["vendor", "database.php"],
	require => File["/var/www"]
}

file { '/var/www/server/composer.json':
	ensure => file,
	source => "/vagrant/server/composer.json"
}

exec { 'composer frontend':
	command	=> "composer install",
	cwd => "/var/www/server",
	require => [File["/var/www/server"], Exec['download composer'], Package['git']],
	creates => "/var/www/server/vendor/autoload.php",
	timeout => 0
}

# write configuration

file { '/var/www/server/database.php':
	require => File["/var/www/server"],
	content => template("/vagrant/vagrant/database.php.erb")
}

# install schema
exec { '/var/www/server/schema.php':
	require => Exec['composer frontend'],
	subscribe => Database["grader"],
	refreshonly => true,
	command => "php /var/www/server/schema.php",
	cwd => "/var/www/server",
}
# setup initial problem (in schema?)
# write oauth keys

# setup firewall
firewall { '100 allow ssh':
	dport   => 22,
	proto  => tcp,
	action => accept,
}
firewall { '200 allow http':
	dport   => 80,
	proto  => tcp,
	action => accept,
}