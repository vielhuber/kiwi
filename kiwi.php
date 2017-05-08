<?php
require_once(__DIR__.'/vendor/autoload.php');
require_once(__DIR__.'/magicdiff.php');
use vielhuber\dbhelper\dbhelper;
use vielhuber\magicreplace\magicreplace;

class Kiwi
{

	public static $debug = false;

	public static function init() {
		if( Kiwi::checkInit() === true ) { Kiwi::output('already kiwied',true); }
		Kiwi::setupInitFiles();
		Kiwi::output('done. now edit config.json.',true);
	}

	public static function checkInit() {
		if( is_dir(Kiwi::path().'/.kiwi') && file_exists(Kiwi::path().'/.kiwi/config.json') ) { return true; }
		return false;
	}

	public static function setupInitFiles() {
		mkdir( Kiwi::path().'/.kiwi' );
		file_put_contents( Kiwi::path().'/.kiwi/config.json', Kiwi::getBoilerplateConfig() );
		file_put_contents( Kiwi::path().'/.kiwi/local.state', '' );
	}

	public static function status() {
		$local_state = Kiwi::getLocalState();
		$remote_state = Kiwi::getRemoteState();
		Kiwi::output('you are currently on state '.str_pad($local_state, 10, '0', STR_PAD_LEFT));
		Kiwi::output('the remote is currently on state '.str_pad($remote_state, 10, '0', STR_PAD_LEFT));
		if( $local_state < $remote_state ) { Kiwi::output('you need to pull first before you push'); }
		else { Kiwi::output('nothing to pull'); }
		$diff = magicdiff::diff();
		if(empty($diff)) {
			Kiwi::output('nothing to push',true);
		}
		else {
			$diff_output = '';
			foreach($diff as $diff__value) {
				$diff_output .= $diff__value['diff']['all'].PHP_EOL;
			}
			Kiwi::output('you are ahead of '.Kiwi::getLocalState(true).'. difference: '.$diff_output,true);
		}
	}

	public static function getLocalState($long_form = false) {
		if( !file_exists(Kiwi::path().'/.kiwi/local.state') ) { return (($long_form===false)?(0):('0000000000')); }
		$state = file_get_contents(Kiwi::path().'/.kiwi/local.state');
		$state = str_replace(["\r\n", "\r", "\n"],'',$state);				
		if( !Kiwi::isValidState($state) ) { return (($long_form===false)?(0):('0000000000')); }
		if($long_form === true) { $state = str_pad($state, 10, '0', STR_PAD_LEFT); }
		else { $state = intval($state); }
		return $state;
	}

	public static function getRemoteState($long_form = false) {
		Kiwi::copyFromRemote('remote.state', 'remote.state');
		if( !file_exists(Kiwi::path().'/.kiwi/remote.state') ) { return (($long_form===false)?(0):('0000000000')); }
		$state = file_get_contents(Kiwi::path().'/.kiwi/remote.state');
		$state = str_replace(["\r\n", "\r", "\n"],'',$state);
		if( !Kiwi::isValidState($state) ) { return (($long_form===false)?(0):('0000000000')); }
		unlink(Kiwi::path().'/.kiwi/remote.state');
		if($long_form === true) { $state = str_pad($state, 10, '0', STR_PAD_LEFT); }
		else { $state = intval($state); }
		return $state;
	}

	public static function isValidState($state) {
		if($state === null) { return false; }
		if($state == "") { return false; }
		$state = intval($state);
		if($state < 0 || $state > 9999999999) { return false; }
		return true;
	}

	public static function pull() {

		$local_state = Kiwi::getLocalState();
		$remote_state = Kiwi::getRemoteState();
		
		if( $local_state >= $remote_state ) { Kiwi::output('nothing to pull',true); }

		// setup sql connection
		$db = Kiwi::initSql();

		// fetch not applied
		for($i = 1; $i <= ($remote_state-$local_state); $i++) {
			$next_state = str_pad($local_state+$i, 10, '0', STR_PAD_LEFT);

				// fetch from remote
				Kiwi::copyFromRemote($next_state.'_*.*', '');

				// do magicreplace on fetched files
				if( Kiwi::conf('replace') !== null && !empty(Kiwi::conf('replace')) ) {
					foreach(glob(Kiwi::path().'/.kiwi/'.$next_state.'_*.*') as $file) {
						magicreplace::run($file,$file,array_flip(Kiwi::conf('replace')));
					}
				}

				// get all table names based on fetched files
				$tables = [];
				foreach(glob(Kiwi::path().'/.kiwi/'.$next_state.'_*.*') as $file) {
					$tables[] = substr($file,strpos($file,'_')+1,strrpos($file,'_')-strpos($file,'_')-1);
				}
				$tables = array_unique($tables);
				
				foreach($tables as $tables__value) {

					// apply query by query in interactive manner (to overcome transactions not possible with ddl schema changes)
					$diff = '';
					if( file_exists(Kiwi::path().'/.kiwi/'.$next_state.'_'.$tables__value.'_schema.diff') ) {
						$diff .= file_get_contents(Kiwi::path().'/.kiwi/'.$next_state.'_'.$tables__value.'_schema.diff');
					}
					if( file_exists(Kiwi::path().'/.kiwi/'.$next_state.'_'.$tables__value.'_data.diff') ) {
						$diff .= file_get_contents(Kiwi::path().'/.kiwi/'.$next_state.'_'.$tables__value.'_data.diff');
					}
					foreach( Kiwi::splitQuery($diff) as $diff_single ) {
						Kiwi::output($diff_single);
						$answer = Kiwi::ask('do you want to commit this? (y/n)',['y','n']);
						if($answer == 'n') { continue; }
						try { $db->exec($diff_single); }
						catch (PDOException $e) { echo $e->getMessage(); }
					}

					// apply patch to local reference file (with all queries, even if not applied all [this is important!])
					if( !file_exists(Kiwi::path().'/.kiwi/_reference_'.$tables__value.'_schema.sql') ) {
						touch(Kiwi::path().'/.kiwi/_reference_'.$tables__value.'_schema.sql');
					}
					if( !file_exists(Kiwi::path().'/.kiwi/_reference_'.$tables__value.'_data.sql') ) {
						touch(Kiwi::path().'/.kiwi/_reference_'.$tables__value.'_data.sql');
					}				
					Kiwi::command('patch '.Kiwi::path().'/.kiwi/_reference_'.$tables__value.'_schema.sql '.Kiwi::path().'/.kiwi/'.$next_state.'_'.$tables__value.'_schema.patch');
					Kiwi::command('patch '.Kiwi::path().'/.kiwi/_reference_'.$tables__value.'_data.sql '.Kiwi::path().'/.kiwi/'.$next_state.'_'.$tables__value.'_data.patch');
				
				}

				Kiwi::command('rm '.Kiwi::path().'/.kiwi/'.$next_state.'_*.*');
		}

		// update local state
		Kiwi::updateLocalState($remote_state);

	}

	public static function initSql() {
		return new PDO('mysql:host='.Kiwi::conf('database.host').';port='.Kiwi::conf('database.port').';dbname='.Kiwi::conf('database.database'), Kiwi::conf('database.username'), Kiwi::conf('database.password'), [
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
			PDO::ATTR_EMULATE_PREPARES => false,
			PDO::ATTR_PERSISTENT => true,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
		]);
	}

	public static function push() {
	
		// if not, show an error (pull first)
		if( Kiwi::getLocalState() < Kiwi::getRemoteState() ) { Kiwi::output('you need to pull first before you push',true); }
		
		// call kiwi status to get diff query
		$diff = magicdiff::diff();
		if(empty($diff)) {
			Kiwi::output('nothing to push',true);
		}

		// generate new state
		$state = Kiwi::generateNextState();

		// update local reference dump
		magicdiff::init();

		// update local state
		Kiwi::updateLocalState($state);

		// update remote dumps (also applying magicreplace)
		Kiwi::updateRemoteDump($diff, $state);

		// update remote state
		Kiwi::updateRemoteState($state);

	}

	public static function updateRemoteDump($diff, $state) {
		$state = str_pad($state, 10, '0', STR_PAD_LEFT);
		foreach($diff as $diff__value) {
			foreach([['patch','schema'],['patch','data'],['diff','schema'],['diff','data']] as $type) {
				file_put_contents(Kiwi::path().'/.kiwi/tmp', $diff__value[$type[0]][$type[1]]);
				if( Kiwi::conf('replace') !== null && !empty(Kiwi::conf('replace')) ) {
					magicreplace::run(Kiwi::path().'/.kiwi/tmp',Kiwi::path().'/.kiwi/tmp',Kiwi::conf('replace'));
				}
				Kiwi::copyToRemote('tmp',$state.'_'.$diff__value['table'].'_'.$type[1].'.'.$type[0].'');
			}
		}
		@unlink(Kiwi::path().'/.kiwi/tmp');
	}

	public static function importSql($file) {
		if(!file_exists($file)) { Kiwi::output('file cannot be imported',true); }
		Kiwi::command(Kiwi::conf('database.import').' -h '.Kiwi::conf('database.host').' --port '.Kiwi::conf('database.port').' -u '.Kiwi::conf('database.username').' -p"'.Kiwi::conf('database.password').'" --default-character-set=utf8 '.Kiwi::conf('database.database').' < '.$file);
	}

	public static function deleteDb() {
		$db = Kiwi::initSql();
		$db->query('SET foreign_key_checks = 0');
		$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
		if(!empty($tables)) {
			foreach($tables as $tables__value) {
				$db->query('DROP TABLE IF EXISTS '.$tables__value);
			}
		}
		$db->query('SET foreign_key_checks = 1');
	}

	public static function ask($question, $answers = null) {
		echo $question.' ';
		$handle = fopen("php://stdin","r");
		$answer = fgets($handle);
		fclose($handle);
		$answer = trim($answer);
		if($answers != null) {
			if( !in_array($answer,$answers) ) {
				return Kiwi::ask($question, $answers);
			}
		}
		return $answer;
	}

	public static function exportDatabase() {
		Kiwi::command(Kiwi::conf('database.export').' --no-data --skip-add-drop-table --skip-add-locks --skip-comments --extended-insert=false --disable-keys --quick -h '.Kiwi::conf('database.host').' --port '.Kiwi::conf('database.port').' -u '.Kiwi::conf('database.username').' -p"'.Kiwi::conf('database.password').'" '.Kiwi::conf('database.database').' > '.Kiwi::path().'/.kiwi/current_schema.sql');
		Kiwi::command(Kiwi::conf('database.export').' --no-create-info --skip-add-locks --skip-comments --extended-insert=false --disable-keys --quick -h '.Kiwi::conf('database.host').' --port '.Kiwi::conf('database.port').' -u '.Kiwi::conf('database.username').' -p"'.Kiwi::conf('database.password').'" '.Kiwi::conf('database.database').' > '.Kiwi::path().'/.kiwi/current_data.sql');
    }

	public static function clearDiffFiles() {
		foreach(glob(Kiwi::path().'/.kiwi/*.diff') as $file) {
			unlink($file);
		}
	}

	public static function splitQuery($query) {
		$unique_delimiter = md5(uniqid(rand(), true));
		$query = str_replace(["\r\n", "\r", "\n"], $unique_delimiter, $query);
		$query = str_replace(';'.$unique_delimiter, ';'.PHP_EOL, $query);
		$query = str_replace($unique_delimiter, '', $query);
		$query = explode(PHP_EOL,$query);
		$query = array_filter($query);
		return $query;
	}

	public static function updateLocalState($state = null) {
		if( $state === null ) {
			$state = Kiwi::getRemoteState();
			$state++;
		}
		file_put_contents(Kiwi::path().'/.kiwi/local.state',str_pad($state, 10, '0', STR_PAD_LEFT));
	}

	public static function updateRemoteState($state = null) {
		if( $state === null ) {
			$state = Kiwi::getRemoteState();
			$state++;
		}
		file_put_contents(Kiwi::path().'/.kiwi/remote.state',str_pad($state, 10, '0', STR_PAD_LEFT));
		Kiwi::copyToRemote('remote.state','remote.state');
		unlink(Kiwi::path().'/.kiwi/remote.state');
	}

	public static function generateNextState() {
		$state = Kiwi::getRemoteState();
		$state++;
		return $state;
	}

	public static function copyFromRemote($remote, $local) {
		//Kiwi::output('copying from remote '.$remote.' '.$local);
		Kiwi::command('scp -r -i "'.Kiwi::conf('remote.key').'" '.Kiwi::conf('remote.username').'@'.Kiwi::conf('remote.host').':'.Kiwi::conf('remote.path').'/'.$remote.' '.Kiwi::path(true).'/.kiwi/'.$local);
	}

	public static function copyToRemote($local, $remote) {
		Kiwi::command('scp -r -i "'.Kiwi::conf('remote.key').'" '.Kiwi::path(true).'/.kiwi/'.$local.' '.Kiwi::conf('remote.username').'@'.Kiwi::conf('remote.host').':'.Kiwi::conf('remote.path').'/'.$remote);
	}

	public static function commandOnRemote($command) {
		Kiwi::command('ssh -i "'.Kiwi::conf('remote.key').'" '.Kiwi::conf('remote.username').'@'.Kiwi::conf('remote.host').' "'.$command.'"');
	}

	public static function command($command, $verbose = false) {
		if(Kiwi::$debug === true) {
			echo $command;die();
		}
		if( $verbose === false ) {
			if( strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ) {
				$command .= ' 2> nul';
			}
			else {
				$command .= ' 2>/dev/null';
			}
		}
		$return = shell_exec($command);
		return $return;
	}

	public static function output($line, $die = false) {
		echo $line;
		echo "\n";
		if($die) { die(); }
	}

	public static function path($scp = false) {
		$path = getcwd();
        if( $scp === true && strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ) {
        	$path = str_replace('C:\\','/cygdrive/c/',$path);
        }
        return $path;
	}

	public static function conf($path) {
		$config = file_get_contents(Kiwi::path().'/.kiwi/config.json');
		$config = json_decode($config);
		// check if this is valid json
		if( json_last_error() != JSON_ERROR_NONE ) { die('corrupt config file.'); }
		$config = json_decode(json_encode($config),true);
        $keys = explode('.', $path);
        foreach($keys as $key) {
	        if(isset($config[$key])) {
	            $config = $config[$key];
	        }
	        else {
	            return null;
	        }
        }
        return $config;       
	}

	public static function rollback() {

		// completely delete all tables in database
		Kiwi::deleteDb();
		
		// simply restore reference
		Kiwi::importSql(Kiwi::path().'/.kiwi/reference_schema.sql');
		Kiwi::importSql(Kiwi::path().'/.kiwi/reference_data.sql');

	}

	public static function clear() {
		file_put_contents(Kiwi::path().'/.kiwi/local.state','');
		Kiwi::command('rm '.Kiwi::path().'/.kiwi/*.sql');
		Kiwi::commandOnRemote('rm '.Kiwi::conf('remote.path').'/*');
		Kiwi::deleteDb();
	}

	public static function test() {
		Kiwi::deleteDb();
		Kiwi::createRandomData();
	}

	public static function createRandomData() {
		$db = Kiwi::initSql();
		$db->query('CREATE TABLE MyGuests ( id INT(6) UNSIGNED AUTO_INCREMENT, firstname VARCHAR(30) NOT NULL, lastname VARCHAR(30) NOT NULL, email VARCHAR(50), PRIMARY KEY (id))');
		$db->query('INSERT INTO MyGuests(firstname, lastname, email) VALUES (\'foo\',\'bar\',\'baz\')');
	}

	public static function getBoilerplateConfig() {
		return '{
	"engine": "mysql",
	"database": {
		"host": "localhost",
		"port": "3306",
		"database": "_test1",
		"username": "root",
		"password": "root",
		"export": "C:\\MAMP\\bin\\mysql\\bin\\mysqldump.exe",				
		"import": "C:\\MAMP\\bin\\mysql\\bin\\mysql.exe"
	},
	"remote": {
		"host": "kiwi.close2dev.de",
		"port": "22",
		"username": "ssh-w015acc1",
		"key": "/home/david/.ssh/id_rsa",
		"path": "/www/htdocs/w015acc1/remote.kiwi"
	},
	"ignore": [
		"s_table1",
		"s_table2",
		"s_table3"
	],
	"replace": {
		"first-example": "first-memample"
	}'.PHP_EOL.'}';
	}

}