file { '/var/grader':
	ensure => directory,
	recurse => true,
	source => "/vagrant/vm/graderclient",
	ignore => ["vendor"]
}
package{ 'beanstalkd':
	ensure => latest,
	require => Apt::Source['ubuntu']
}

# setup composer
file { '/var/grader/composer.json':
	ensure => file,
	source => "/vagrant/vm/graderclient/composer.json"
}

exec { 'composer backend':
	command	=> "composer install",
	cwd => "/var/grader",
	require => [File["/var/grader"], Exec['download composer'], Package['git']],
	creates => "/var/grader/vendor/autoload.php",
	timeout => 0
}
# setup docker
# the setup instruction in docker module seems to be deprecated

apt::source { 'docker':
	location          => 'http://get.docker.io/ubuntu',
	release           => 'docker',
	repos             => 'main',
	include_src       => false,
	key               => 'A88D21E9',
	key_source        => 'https://get.docker.io/gpg'
}

package { 'lxc-docker': 
	ensure => latest,
	require => Anchor['apt::source::docker']
}

package { 'kernel-ext':
	name => "linux-image-extra-${kernelrelease}",
	ensure => latest,
	require => Anchor['apt::source::ubuntu']
}

#firewall { '400 docker nat':
#	chain => 'POSTROUTING',
#	jump => 'MASQUERADE',
#	proto => 'all',
#	src_range => '10.0.3.0/24',
#	table => 'nat',
#	require => Package['lxc-docker'] 
#}

exec { "/vagrant/vm/build-raring.sh":
	cwd => "/tmp/",
	unless  => "docker images | grep ^raring",
	timeout => 0,
	require => Package['lxc-docker']
}

# build grader template
exec { "docker build -t=grader .":
	cwd => "/vagrant/vm/",
	unless  => "docker images | grep ^grader",
	timeout => 0,
	require => [Exec['/vagrant/vm/build-raring.sh'], Package['kernel-ext']]
}

# setup supervisor
package{ 'supervisor':
	ensure => latest,
	require => Anchor['apt::source::ubuntu'],
}