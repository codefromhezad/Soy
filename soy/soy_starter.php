<?php

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__).'/SSH');

include('SSH/Net/SSH2.php');
include('SSH/Net/SFTP.php');
include('MysqlDump.php');

include('class.Soy.php');

/* Start */
if( ! isset( $argv[1] ) || empty( $argv[1] ) ) {
	die("Usage: php soy <task-name>");
}

include('soy.conf.php');
include('soy.php');

Run($argv[1]);
