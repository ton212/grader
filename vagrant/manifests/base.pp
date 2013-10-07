class { 'apt':
	always_apt_update => false,
	purge_sources_list   => true,
	purge_sources_list_d => true,
}

include apt

apt::source { 'ubuntu':
	location          => 'http://mirror1.ku.ac.th/ubuntu/',
	repos             => 'main universe',
	required_packages => 'ubuntu-keyring',
	include_src       => false
}
apt::source { 'ubuntu-update':
	location          => 'http://mirror1.ku.ac.th/ubuntu/',
	release	          => "$lsbdistcodename-updates",
	repos             => 'main universe',
	required_packages => 'ubuntu-keyring',
	include_src       => false
}
apt::source { 'ubuntu-security':
	location          => 'http://security.ubuntu.com/ubuntu/',
	release	          => "$lsbdistcodename-security",
	repos             => 'main universe',
	required_packages => 'ubuntu-keyring',
	include_src       => false
}
package{ 'landscape-client':
	ensure => purged
}
package{ 'landscape-common':
	ensure => purged
}
include php
include php::apt

class {
	'php::cli':
		ensure => installed,
		provider => 'apt';
	'php::extension::curl':
		provider => 'apt';
	'php::composer':
		require => Class['php::cli']
}

include php::cli
include php::composer
include php::extension::curl

package{ 'git':
	ensure => latest,
	require => Apt::Source['ubuntu'],
}

resources { "firewall":
	purge => true
}

firewall { "000 accept all icmp requests":
	proto  => "icmp",
	action => "accept",
}

firewall { "999 drop all other requests":
	action => "drop",
}