<?php

Task('soy:setup', function() {
	global $remote_server;
	select('remote_server');

	announce('soy', 'Checking presence of required folders');
	status();

	if( ! test("-e {$remote_server['path']}") ) {
		bash("mkdir {$remote_server['path']}");
	} else {
		announce('bash', '>> Base folder already exists', true);
		status('ok');
	}

	if( ! test("-e {$remote_server['path']}/release") ) {
		bash("mkdir {$remote_server['path']}/release");
	} else {
		announce('bash', '>> Release folder already exists', true);
		status('ok');
	}

	if( ! test("-e {$remote_server['path']}/shared") ) {
		bash("mkdir {$remote_server['path']}/shared");
	} else {
		announce('bash', '>> Shared folder already exists', true);
		status('ok');
	}
});
