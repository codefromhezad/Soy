<?php

Task('soy:setup', function() {
	global $remote_server;
	select('remote_server');

	info('Creating required folders ...');

	if( ! test("-e {$remote_server['path']}") ) {
		bash("mkdir {$remote_server['path']}");
	} else {
		info('>> Base folder already exists.');
	}

	if( ! test("-e {$remote_server['path']}/shared") ) {
		bash("mkdir {$remote_server['path']}/shared");
	} else {
		info('>> Shared folder already exists.');
	}
});