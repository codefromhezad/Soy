SOY is the contraction of Simple deplOY.

This tool is meant to replace huge deployment toolsets for simpler projects (especially PHP projects)

## Features
* Deploy files over SFTP.
* Deploy database schema
* Execute SSH commands
* Run SQL queries
* Multiconnections

## Usage
```
php soy/soy_starter.php <task-name>
```


## Example soy.php file

```php
<?php

Task('deploy', function() {
	Run('files');
	Run('schema');
});

Task('files', function() {
	global $remote_server;
	
	transfer('local_files', 'remote_server');
	select('remote_server');
	bash("mv {$remote_server['path']}/test_folder/test.txt {$remote_server['path']}/test_folder/test_production.txt");
});

Task('schema', function() {
	transfer('local_database', 'remote_database');
	select('remote_database');
	sql_query("UPDATE page SET page_name = REPLACE(page_name, 'localhost', 'production')");
});

```


## Example soy.conf.php file

```php
<?php

Connection('local_database', 'mysql://username:pass@localhost/dbname');
Connection('remote_database', 'mysql://username:pass@remotehost/dbname');

Connection('local_folder', 'file:///local_folder');
Connection('remote_folder', 'ssh://username:pass@remotehost/remote_folder');

```

## File exclusions
Files starting with, or contained in a folder named as the next strings won't be uploaded, whatever the scheme you're using :
```
'.git', '.svn'
```
