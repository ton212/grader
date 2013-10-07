Exec {
	path => "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin",
}

import 'base.pp'
import 'server.pp'
import 'client.pp'