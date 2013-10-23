<?php

Task('soy:setup', function() {
	global $base_path;
	select('remote_server');

	announce('soy', 'Checking presence of required folders');
	status();

	if( ! test("-e $base_path") ) {
		bash("mkdir $base_path");
	} else {
		announce('bash', '>> Base folder already exists', true);
		status('ok');
	}

	if( ! test("-e $base_path/release") ) {
		bash("mkdir $base_path/release");
	} else {
		announce('bash', '>> Release folder already exists', true);
		status('ok');
	}

	if( ! test("-e $base_path/shared") ) {
		bash("mkdir $base_path/shared");
	} else {
		announce('bash', '>> Shared folder already exists', true);
		status('ok');
	}
});
