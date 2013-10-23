<?php

define('ANNOUNCE_MESSAGE_PADDING', 65);

class SOY {
	public $connections = array(),
		   $tasks = array();
	
	public $loadable_connections = array();
	public $selected_connection = null;
	public $public_variables = array();
	
	public $dsn_types = array(
		'file'     => array('ssh', 'files'),
		'database' => array('mysql')
	);
	
	public $file_exclusions = array('.git', '.svn');
	
	public $last_announce_length = 0;
	
	public function announce($cat, $message, $padded=false) {
		$message = ($padded ? "    ":"")."  ".$message;
		$this->last_announce_length = strlen($message);
		
		echo $message;
	}
	
	public function status($status = null, $message = null) {
			
		switch( strtolower($status) ) {
			case "fail":
				echo " FAIL\n";
				if( $message ) {
					echo print_r($message, true)."\n";
				}
				die;
				break;
			case "ok":
				echo " ".str_pad(" OK", (ANNOUNCE_MESSAGE_PADDING - $this->last_announce_length), ".", STR_PAD_LEFT)."\n";
				break;
			default:
				echo "\n";
				break;
		}
	}
	
	public function normalize_path($path) {
		return str_replace('\\', '/', $path);
	}
	
	public function set($var, $val) {
		$this->public_variables[$var] = $val;
		eval('global $'.$var.'; $'.$var.' = '.var_export($val, true).';');
	}
	
	public function get($var) {
		return $this->public_variables[$var];
	}
	
	public function ssh_login($cname) {		
		
		$conn = $this->connections[$cname]['parsed_string'];
		
		if( isset($conn['user']) && isset($conn['pass']) ) {
			$credentials = array($conn['user'], $conn['pass']);
		} else {
			die('Soy only supports username based connections right now'."\n");
		}
		
		// Hack to kill a Net_SSH2 error (from Random.php)
		unset($GLOBALS); 
		
		// Init SSH and SFTP and then log on the server
		$this->announce("SSH", "Open SSH session on {$conn['host']} as '$cname'");
		try {
			$this->connections[$cname]['objects']['ssh'] = new Net_SSH2($conn['host']);
		} catch(Exception $e) {
			$this->status('FAIL', 'Unable to open SSH connection to '.$conn['host']);
		}
		$this->status('OK');
		

		$this->announce("SFTP", "Open SFTP session on {$conn['host']} as '$cname'");
		try {
			$this->connections[$cname]['objects']['sftp'] = new Net_SFTP($conn['host']);
		} catch(Exception $e) {
			$this->status('FAIL', 'Unable to open SFTP connection to '.$conn['host']);
		}
		$this->status('OK');
		
		/* Password based login */
		$c = $credentials;
		
		$this->announce("SSH", "Login as {$c[0]}");
		if (! $this->connections[$cname]['objects']['ssh']->login($c[0], $c[1]) ) {
			$this->status('FAIL', 'Bad credentials');
		} else {
			$this->status('OK');
		}
		
		$this->announce("SFTP", "Login as {$c[0]}");
		if( ! $this->connections[$cname]['objects']['sftp']->login($c[0], $c[1]) ) {
			$this->status('FAIL', 'Bad credentials');
		} else {
			$this->status('OK');
		}
	}
	
	public function db_connect($cname) {
		$connection = $this->connections[$cname]['parsed_string'];
		$dbname = ltrim($connection['path'],'/');
		
		$this->announce('DB', "Connect database '$cname'");
		
		try {
			$link = @new \PDO($connection['scheme'].':host='.$connection['host'].';', 
				$connection['user'], 
				$connection['pass'], 
				array(
					\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, 
					\PDO::ATTR_PERSISTENT => false
				)
			);
		} catch(Exception $e) {
			$this->status('fail', $e->getMessage());
		}
		
		if( $link ) {
			$this->status('ok');
		} else {
			$this->status('fail', "Can't connect to database");
		}
		
		return $link;
	}
	
	/* Helpers to use in tasks */
	
	public function upload_to($conn_dest) {
		if( ! isset( $this->selected_connection ) ) {
			die('No connection selected'."\n");
		}
		if( ! isset( $this->connections[$conn_dest] ) ) {
			die($conn_dest.' is not a connection'."\n");
		}
		
		$c = array(
			$this->selected_connection['parsed_string'],
			$this->connections[$conn_dest]['parsed_string']
		);
		
		$o = array(
			$this->selected_connection['objects'],
			$this->connections[$conn_dest]['objects']
		);
		
		// deploy folder over SFTP
		$local_iterator = new RecursiveDirectoryIterator($c[0]['path']);
		
		$local_dir = $c[0]['path'];
		$remote_dir = $c[1]['path'].'/release';
		
		$this->announce("SFTP",
"Deploy $local_dir ({$this->selected_connection['name']})
      to $remote_dir ($conn_dest)"
		);
		$this->status("");
		
		foreach(new RecursiveIteratorIterator($local_iterator) as $filename => $fileinfo) {
			
			$filename = $this->normalize_path($filename);
			
			foreach( $this->file_exclusions as $exclusion ) {
				$exclusion = preg_quote($exclusion);
				if( preg_match('`(\/'.$exclusion.'|'.$exclusion.'\/)`', $filename) ) {
					continue 2;
				}
			}
			
			$relative_filename = str_replace($local_dir, '', $filename);
			$remote_path = $remote_dir.$relative_filename;
			
			$remote_current_dir = dirname($remote_path);
			
			$this->announce(" ", $relative_filename, true);
			
			$this->connections[$conn_dest]['objects']['ssh']
				 ->exec('if [ ! -d "'.$remote_current_dir.'" ]; then mkdir -p "'.$remote_current_dir.'" --; fi');
			
			if( $this->connections[$conn_dest]['objects']['sftp']
					 ->put($remote_path, $filename, NET_SFTP_LOCAL_FILE) ) {
				$this->status('ok');
			} else {
				$this->status('fail', $this->connections[$conn_dest]['objects']['sftp']->getLastSFTPError());
			}
		}
	}
	
	public function dump_to($conn_dest) {
		if( ! isset( $this->selected_connection ) ) {
			die('No connection selected'."\n");
		}
		if( ! isset( $this->connections[$conn_dest] ) ) {
			die($conn_dest.' is not a connection'."\n");
		}
		
		$c = array(
			$this->selected_connection['parsed_string'],
			$this->connections[$conn_dest]['parsed_string']
		);
		
		$o = array(
			$this->selected_connection['objects'],
			$this->connections[$conn_dest]['objects']
		);
		
		/* Deploy database */
		
		$conn_from = $this->selected_connection['name'];
		
		$connection = $c[0];
		$dbname = ltrim($connection['path'],'/');
		
		$this->announce('DB', 'Dumping database '.$dbname.' from "'.$conn_from.'"');
		
		$dumpSettings = array(	
			'compress' => 'NONE',
			'no-data' => false,
			'add-drop-table' => false,
			'single-transaction' => true,
			'lock-tables' => false,
			'add-locks' => true,
			'extended-insert' => true
		);

		$dump = new Mysqldump($dbname, $connection['user'], $connection['pass'], $connection['host'], $connection['scheme'], $dumpSettings);
		$dump->start('tmp_dump.sql');
		
		if( file_exists('tmp_dump.sql') ) {
			$this->status('ok');
		} else {
			$this->status('fail', 'Something wrong happened while dumping the local database');
		}
		
		$local_sql_schema = file_get_contents('tmp_dump.sql');
		
		$this->announce('DB', 'Deploying database '.$dbname.' on "'.$conn_dest.'"');
		
		$remote_connection = $c[1];
		$remote_dbname = ltrim($remote_connection['path'],'/');
		
		$o[1]['mysql']->query(
			"SET foreign_key_checks = 0;\n".
			"SET NAMES utf8;\n".
			"DROP DATABASE IF EXISTS ".mysql_real_escape_string($remote_dbname).";\n".
			"CREATE DATABASE ".mysql_real_escape_string($remote_dbname)." DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_general_ci;\n".
			"USE ".mysql_real_escape_string($remote_dbname).";\n".
			$local_sql_schema.
			"SET foreign_key_checks = 1;\n"
		);
		
		unlink('tmp_dump.sql');
		$this->status('ok');
	}
	
	public function select($conn_name) {
		$this->announce('SOY', "Select connection '{$conn_name}'");
		$this->status();
		
		if( isset( $this->loadable_connections[$conn_name] ) ) {
			Connection($conn_name);
		}
		
		$this->selected_connection = $this->connections[$conn_name];
		$this->selected_connection['name'] = $conn_name;
		
		$this->set('release_path', $this->selected_connection['parsed_string']['path'].'/release');
		$this->set('shared_path', $this->selected_connection['parsed_string']['path'].'/shared');
		$this->set('base_path', $this->selected_connection['parsed_string']['path']);
	}
	
	public function bash($bash_string, $verbose=true) {
		if( $verbose ) {
			$this->announce('BASH', $bash_string);
			$this->status();
		}
		
		if( ! $this->selected_connection ) {
			$this->status('fail', "No connection selected for this bash command");
		}
		
		$ret = $this->selected_connection['objects']['ssh']->exec($bash_string);
		
		if( $this->selected_connection['objects']['ssh']->getExitStatus() == 0 ) {			
			if( $verbose ) {
				$this->announce('BASH', ">> ".($ret ? $ret : ''), true);
				$this->status('ok');
			}
			return $ret;
		} else {
			echo "\n";
			$this->announce('error', rtrim($ret,"\n"));
			die; 
		}
	}
	
	public function sql_query($query) {
		if( ! $this->selected_connection ) {
			$this->status('fail', "No connection selected for this SQL query");
		}
		
		$this->announce('QUERY', "$query");
		try {
			$res = $this->selected_connection['objects']['mysql']->query($query);
			$this->status('ok');
			if( $res && $res->columnCount() > 0 ) {
				$result = $res->fetch(PDO::FETCH_ASSOC);
				print_r( reset($result) );
			}
			return $res;
		} catch( PDOException $e ) {
			$this->status('fail', $e->getMessage());
		}
	}
}


/* SOY Instanciation */
$soy = new SOY();

/* SOY methods helpers */
function select($conn_name) { global $soy; return $soy->select($conn_name); }
function bash($bash_string, $verbose = true) {
							  global $soy; return $soy->bash($bash_string, $verbose); }
function sql_query($query)  { global $soy; return $soy->sql_query($query); }
function transfer($conn_from, $conn_to)  {
							  global $soy; return $soy->transfer($conn_from, $conn_to); }
function test($test_string) { $ret = bash('if [ '.$test_string.' ] ; then echo 1 ; else echo 0 ; fi', false); return intval($ret); }
function announce($cat, $message, $padded=false) { global $soy; $soy->announce($cat, $message, $padded); }
function status($status = null, $message = null) { global $soy; $soy->status($status, $message); }
function upload_to($dest_conn) { global $soy; $soy->upload_to($dest_conn); }
function dump_to($dest_conn) { global $soy; $soy->dump_to($dest_conn); }
function share($original_file_path) {
	global $shared_path, $release_path;
	
	if( ! test("-e $shared_path/$original_file_path") ) {
		bash("mkdir -p \"".dirname("$shared_path/$original_file_path")."\"");
		bash("cp -r \"$release_path/$original_file_path\" \"$shared_path/$original_file_path\"");
	}
	
	if( test("-e $release_path/$original_file_path") ) {
		bash("rm -r \"$release_path/$original_file_path\"");
	}
	
	bash("ln -s \"$shared_path/$original_file_path\" \"$release_path/$original_file_path\"");
}

/* Main soy.php functions */
function Task($task_name, $task_callback) {
	global $soy;
	$soy->tasks[$task_name] = $task_callback;
}

function Run($task_name) {
	global $soy;
	echo "\n".'[[[ EXECUTING TASK {{ '.$task_name.' }} ]]]'."\n";
	if( ! isset($soy->tasks[$task_name]) ) {
		die($task_name.' is not a task'."\n");
	}
	$soy->tasks[$task_name]();
}

function Set($varname, $varval) {
	global $soy;
	$soy->set($varname, $varval);
}

function Lands($connections_array) {
	global $soy;
	$soy->loadable_connections = $connections_array;
}

function Connection($conn_name) {
	global $soy;
	
	if( isset( $soy->connections[$conn_name] ) && ! empty( $soy->connections[$conn_name] ) ) {
		return;
	}
	
	$conn_string = $soy->loadable_connections[$conn_name];
	
	$conn = parse_url($conn_string);
	$conn['path'] = $soy->normalize_path($conn['path']);
	
	Set($conn_name, $conn);
	
	$soy->connections[$conn_name] = array(
		'string' => $conn_string,
		'objects' => array(),
		'parsed_string' => $conn
	);
	
	switch( $conn['scheme'] ) {
		case 'ssh':
			$soy->ssh_login($conn_name);
			break;
		case 'file':
			break;
		case 'mysql':
			$soy->connections[$conn_name]['objects']['mysql'] = $soy->db_connect($conn_name);
			break;
			
	}
}
