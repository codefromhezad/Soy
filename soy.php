<?php

Task('deploy', function() {
	Run('deploy_files');
	Run('empty_remote_cache');
	Run('deploy_schema');
});

Task('empty_remote_cache', function() {
	global $remote_server;
	select('remote_server');
	bash("rm -r {$remote_server['path']}/cache/*");
});

Task('deploy_files', function() {
	transfer('local_files', 'remote_server');
});

Task('deploy_schema', function() {
	transfer('local_database', 'remote_database');
	select('remote_database');
	sql_query("UPDATE page SET page_name = REPLACE(page_name, 'localhost', 'production')");
});
