SOY is the contraction of Simple deplOY.

This tool is meant to replace huge deployment toolsets for simpler projects (especially PHP projects)


# This is a very early version of the code. Use it at your own risks.

## A few things you want to know :

* Implemented transfers :
  * MySQL to MySQL
  * Local files to SFTP Connection
* The bash($string) function executes $string on the server via SSH. Be careful. It's common sense but don't use a root account for your SSH Connection.  
* The sql_query($query) function queries $query on the mysql Connection. Once again. Be careful with the queries you execute. You don't want to drop your production database.
* The transfer() functions overwrite its destination, whatever the scheme is (MySQL, SSH, ...). As a result, IT WILL DROP AND RECREATE THE ENTIRE TARGET DATABASE. Use it as an initial deployment function only.
* SOY assumes you are using an utf-8 character set for your database.

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

Run('soy:setup');

Task('deploy', function() {
	global $release_path, $shared_path;
	
	select('local_files');
	upload_to('remote_server');

	select('remote_server');
	bash("mv $release_path/test.txt  $release_path/test_modified.txt");
	
	select('local_database');
	dump_to('remote_database');
});

```


## Example soy.conf.php file

```php
<?php

Lands( array(
	'local_files'     => 'file:///path/to/files',
	'remote_server'   => 'ssh://user:password@remote_host.com/path/to/files',
	'local_database'  => 'mysql://user:password@localhost/dbname',
	'remote_database' => 'mysql://user:password@remote_host.com/dbname'
) );

```

## File exclusions
Files starting with, or contained in a folder named as the next strings won't be uploaded, whatever the scheme you're using :
```
'.git', '.svn'
```

## Todo
* Sudoable commands
* Shared folder
* Release folder (with rollbacks possibility)
