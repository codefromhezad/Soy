<?php

define('ANNOUNCE_MESSAGE_PADDING', 65);

class SOY {
	public $connections = array(),
		   $tasks = array();
	
	public $selected_connection = null;
	
	public function announce($cat, $message, $padded=false) {
		echo ($padded ? "    ":"").str_pad($message, ($padded ? -4 : 0 ) + ANNOUNCE_MESSAGE_PADDING, ".", STR_PAD_RIGHT);
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
				echo " OK\n";
				break;
			default:
				echo "\n";
		}
	}
	
	public function normalize_path($path) {
		return str_replace('\\', '/', $path);
	}
	
	public function __invoke($params) {
		list($conn_name, $attr) = explode('.', $params);
		
		if( isset( $this->connections[$conn_name] ) ) {
			return $this->connections[$conn_name]['parsed_string'][$attr];
		}
		return false;
	}
	
	public function ssh_login($cname) {		
		
		$conn = $this->connections[$cname]['parsed_string'];
		
		if( isset($conn['user']) && isset($conn['pass']) ) {
			$credentials = array($conn['user'], $conn['pass']);
		} else {
			die('Soy only supports username based connections right now');
		}
		
		// Hack to kill a Net_SSH2 error (from Random.php)
		unset($GLOBALS); 
		
		// Init SSH and SFTP and then log on the server
		$this->announce("SSH", "Open SSH session on {$conn['host']} as '$cname'");
		$this->connections[$cname]['objects']['ssh'] = new Net_SSH2($conn['host']);
		if( $this->connections[$cname]['objects']['ssh'] ) {
			$this->status('OK');
		} else {
			$this->status('FAIL', 'Unable to open SSH connection to '.$conn['host']);
		}
		
		$this->announce("SFTP", "Open SFTP session on {$conn['host']} as '$cname'");
		$this->connections[$cname]['objects']['sftp'] = new Net_SFTP($conn['host']);
		if( $this->connections[$cname]['objects']['sftp'] ) {
			$this->status('OK');
		} else {
			$this->status('FAIL', 'Unable to open SFTP connection to '.$conn['host']);
		}
		
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
		
		$link = new \PDO($connection['scheme'].':host='.$connection['host'].';', 
			$connection['user'], 
			$connection['pass'], 
			array(
				\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, 
				\PDO::ATTR_PERSISTENT => false
			)
		);
		
		if( $link )
			$this->status('ok');
		else
			$this->status('fail', "Can't connect to the database '$cname'");
		
		return $link;
	}
	
	/* Helpers to use in tasks */
	
	public function transfer($conn_from, $conn_to) {
		$c = array(
			$this->connections[$conn_from]['parsed_string'],
			$this->connections[$conn_to]['parsed_string']
		);
		
		$o = array(
			$this->connections[$conn_from]['objects'],
			$this->connections[$conn_to]['objects']
		);
		
		switch( $c[0]['scheme'].' to '.$c[1]['scheme'] ) {
			case 'file to ssh':
				
				// deploy folder over SFTP
				$local_iterator = new RecursiveDirectoryIterator($c[0]['path']);
				
				$local_dir = $c[0]['path'];
				$remote_dir = $c[1]['path'];
				
				$this->announce("SFTP",
"Deploy $local_dir ($conn_from)
     to $remote_dir ($conn_to)"
				);
				$this->status("");
				
				foreach(new RecursiveIteratorIterator($local_iterator) as $filename => $fileinfo) {
					
					$filename = $this->normalize_path($filename);
					
					$relative_filename = str_replace($local_dir, '', $filename);
					$remote_path = $remote_dir.$relative_filename;
					
					$remote_current_dir = dirname($remote_path);
					
					$this->announce(" ", $relative_filename, true);
					
					$this->connections[$conn_to]['objects']['ssh']
						 ->exec('if [ ! -d "'.$remote_current_dir.'" ]; then mkdir -p "'.$remote_current_dir.'" --; fi');
					
					if( $this->connections[$conn_to]['objects']['sftp']
							 ->put($remote_path, $filename, NET_SFTP_LOCAL_FILE) ) {
						$this->status('ok');
					} else {
						$this->status('fail', "An error occured while uploading this file");
					}
				}
				
				break;
			case 'mysql to mysql':
				
				/* Deploy database */
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
				
				$this->announce('DB', 'Deploying database '.$dbname.' on "'.$conn_to.'"');
				
				$remote_connection = $c[1];
				$remote_dbname = ltrim($remote_connection['path'],'/');
				
				$o[1]['mysql']->query(
					"SET foreign_key_checks = 0;\n".
					"DROP DATABASE IF EXISTS ".mysql_real_escape_string($remote_dbname).";\n".
					"CREATE DATABASE ".mysql_real_escape_string($remote_dbname).";\n".
					"USE ".mysql_real_escape_string($remote_dbname).";\n".
					$local_sql_schema.
					"SET foreign_key_checks = 1;\n"
				);
				
				unlink('tmp_dump.sql');
				$this->status('ok');
				break;
		}
	}
	
	public function select($conn_name) {
		$this->announce('SOY', "Select connection '{$conn_name}'");
		$this->selected_connection = $this->connections[$conn_name];
		$this->status('ok');
	}
	
	public function bash($bash_string) {
		$this->announce('BASH', $bash_string);
		
		if( ! $this->selected_connection ) {
			$this->status('fail', "No connection selected for this bash command");
		}
		
		$ret = $this->selected_connection['objects']['ssh']->exec($bash_string);
		
		if( $this->selected_connection['objects']['ssh']->getExitStatus() == 0 ) {
			$this->status('ok');
			
			if( $ret ) {
				$this->announce('BASH', "<< ".$ret, true);
			}
				
		} else {
			echo "\n";
			$this->announce('error', rtrim($ret,"\n"));
			die; 
		}
	}
	
	public function sql_query($query) {
		if( ! $this->selected_connection ) {
			$this->status('fail', "No connection selected for this bash command");
		}
		
		$this->announce('QUERY', "$query");
		if( $res = $this->selected_connection['objects']['mysql']->query($query) ) {
			$this->status('ok');
			return $res;
		} else {
			$this->status('fail', mysql_error());
		}
	}
}

/* SOY Instanciation */
$soy = new SOY();

/* SOY methods helpers */
function select($conn_name) { global $soy; return $soy->select($conn_name); }
function bash($bash_string) { global $soy; return $soy->bash($bash_string); }
function sql_query($query)  { global $soy; return $soy->sql_query($query); }
function transfer($conn_from, $conn_to)  {
							  global $soy; return $soy->transfer($conn_from, $conn_to); }
function __($params)		{ global $soy; return $soy($params); }


/* Main so_file.php functions */
function Task($task_name, $task_callback) {
	global $soy;
	$soy->tasks[$task_name] = $task_callback;
}

function Run($task_name) {
	global $soy;
	echo "\n".'[[[ EXECUTING TASK {{ '.$task_name.' }} ]]]'."\n";
	$soy->tasks[$task_name]();
}

function Connection($conn_name, $conn_string) {
	global $soy;
	
	$conn = parse_url($conn_string);
	$conn['path'] = $soy->normalize_path($conn['path']);
	
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
