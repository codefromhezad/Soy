<?php

Run('soy:setup');

Task('deploy', function() {
	global $release_path, $shared_path;
	
	select('local_files');
	upload_to('remote_server');

	select('remote_server');
	bash("mv $release_path/test.txt $release_path/test_modified.txt");
	
	share("test_modified.txt");
	
	select('local_database');
	dump_to('remote_database');
});
