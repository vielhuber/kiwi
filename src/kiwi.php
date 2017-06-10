<?php
namespace vielhuber\kiwi;
require_once(__DIR__.'/../vendor/autoload.php');
use vielhuber\dbhelper\dbhelper;
use vielhuber\magicdiff\magicdiff;
use vielhuber\magicreplace\magicreplace;
use PDO;
class kiwi
{

	/* initTest */

	public static function initTest() {
		if( kiwi::checkInit() ) { kiwi::commandOnRemote('rm '.kiwi::conf('remote.path').'/*'); }
		kiwi::command('rm '.kiwi::path().'/*');
		kiwi::init();
		kiwi::deleteDatabase();
		kiwi::createRandomData();
	}

	public static function createRandomData() {
		$db = kiwi::sql();
		$db->query('CREATE TABLE MyGuests ( id INT(6) UNSIGNED AUTO_INCREMENT, firstname VARCHAR(30) NOT NULL, lastname VARCHAR(30) NOT NULL, email VARCHAR(50), PRIMARY KEY (id))');
		$db->query('INSERT INTO MyGuests(firstname, lastname, email) VALUES (\'foo\',\'bar\',\'baz\')');
	}

	/* init */

	public static function init() {
		if( kiwi::checkInit() ) { kiwi::outputAndStop('already kiwied'); }
		kiwi::setupInitFiles();
		kiwi::output('done. now edit config.json.');
	}

	public static function checkInit() {
		if( is_dir(kiwi::path()) && file_exists(kiwi::path().'/config.json') ) { return true; }
		return false;
	}

	public static function checkInitAndStop() {
		if( kiwi::checkInit() === true ) { return true; }
		kiwi::outputAndStop('init first');
	}

	public static function setupInitFiles() {
		if( !is_dir(kiwi::path()) ) { mkdir( kiwi::path() ); }
		magicdiff::setupDir();
		file_put_contents( kiwi::path().'/config.json', kiwi::getConfigBoilerplate() );
		file_put_contents( kiwi::path().'/state.local', '' );
	}

	public static function getConfigBoilerplate() {
		$config = '{
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
				"key": "C:\\Users\\David\\.ssh\\id_rsa",
				"path": "/www/htdocs/w015acc1/remote.kiwi"
			},
			"ignore": [
				"s_table1",
				"s_table2",
				"s_table3"
			],
			"replace": {
				"first-example": "first-memample"
			}
        }';
        $config = str_replace('\\','\\\\',(str_replace('            ','    ',str_replace('        }','}',$config))));
        return $config;
	}

	/* status */

	public static function status() {
		kiwi::checkInitAndStop();
		$local_state = kiwi::getLocalState();
		$remote_state = kiwi::getRemoteState();
		kiwi::output('you are currently on state '.kiwi::formatState($local_state));
		kiwi::output('the remote is currently on state '.kiwi::formatState($remote_state));
		if( $local_state < $remote_state ) { kiwi::output('you need to pull first before you push'); }
		else { kiwi::output('nothing to pull'); }
		$diff = magicdiff::diff();
		if(empty($diff)) { kiwi::outputAndStop('nothing to push'); }
		else {
			$diff_output = '';
			foreach($diff as $diff__value) {
				$diff_output .= $diff__value['diff']['all'].PHP_EOL;
			}
			kiwi::output('you are ahead of '.kiwi::formatState($local_state));
			kiwi::outputAndStop('difference: '.$diff_output);
		}
	}

	/* push */

	public static function push() {
		kiwi::checkInitAndStop();
		if( kiwi::getLocalState() < kiwi::getRemoteState() ) { kiwi::outputAndStop('you need to pull first before you push'); }
		$diff = magicdiff::diff();
		if(empty($diff)) { kiwi::outputAndStop('nothing to push'); }
		$state = kiwi::generateNextState();
		magicdiff::init(); // update local reference dump
		kiwi::updateLocalState($state);
		kiwi::updateRemoteDump($diff, $state); // update remote dumps (also applying magicreplace)
		kiwi::updateRemoteState($state);
		kiwi::outputAndStop('successfully pushed state '.$state);
	}

	public static function updateRemoteDump($diff, $state) {
		$state = kiwi::formatState($state);
		foreach($diff as $diff__value) {
			foreach([['patch','schema'],['patch','data'],['diff','schema'],['diff','data']] as $type) {
				file_put_contents(kiwi::path().'/tmp', $diff__value[$type[0]][$type[1]]);
				if( kiwi::conf('replace') !== null && !empty(kiwi::conf('replace')) ) {
					magicreplace::run(kiwi::path().'/tmp',kiwi::path().'/tmp',kiwi::conf('replace'));
				}
				kiwi::copyToRemote('tmp',$state.'_'.$diff__value['table'].'_'.$type[1].'.'.$type[0].'');
				@unlink(kiwi::path().'/tmp');
			}
		}		
	}

	/* rollback */

	public static function rollback() {
		kiwi::checkInitAndStop();
		kiwi::deleteDatabase();
		kiwi::importSql(kiwi::path().'/reference_schema.sql');
		kiwi::importSql(kiwi::path().'/reference_data.sql');
	}

	/* pull */

	public static function pull() {
		kiwi::checkInitAndStop();

		$local_state = kiwi::getLocalState();
		$remote_state = kiwi::getRemoteState();
		
		if( $local_state >= $remote_state ) { kiwi::outputAndStop('nothing to pull'); }

		// setup sql connection
		$db = kiwi::sql();

		// fetch not applied
		for($i = 1; $i <= ($remote_state-$local_state); $i++) {
			$next_state = kiwi::formatState($local_state+$i);

				// fetch from remote
				kiwi::copyFromRemote($next_state.'_*.*');

				// do magicreplace on fetched files
				if( kiwi::conf('replace') !== null && !empty(kiwi::conf('replace')) ) {
					foreach(glob(kiwi::path().'/'.$next_state.'_*.*') as $file) {
						magicreplace::run($file,$file,array_flip(kiwi::conf('replace')));
					}
				}

				// get all table names based on fetched files
				$tables = [];
				foreach(glob(kiwi::path().'/'.$next_state.'_*.*') as $file) {
					$tables[] = substr($file,strpos($file,'_')+1,strrpos($file,'_')-strpos($file,'_')-1);
				}
				$tables = array_unique($tables);
				
				foreach($tables as $tables__value) {

					// apply query by query in interactive manner (to overcome transactions not possible with ddl schema changes)
					$diff = '';
					if( file_exists(kiwi::path().'/'.$next_state.'_'.$tables__value.'_schema.diff') ) {
						$diff .= file_get_contents(kiwi::path().'/'.$next_state.'_'.$tables__value.'_schema.diff');
					}
					if( file_exists(kiwi::path().'/'.$next_state.'_'.$tables__value.'_data.diff') ) {
						$diff .= file_get_contents(kiwi::path().'/'.$next_state.'_'.$tables__value.'_data.diff');
					}
					foreach( kiwi::splitQuery($diff) as $diff_single ) {
						kiwi::output($diff_single);
						$answer = kiwi::ask('do you want to commit this? (y/n)',['y','n']);
						if($answer == 'n') { continue; }
						try { $db->exec($diff_single); }
						catch (PDOException $e) { echo $e->getMessage(); }
					}

					// apply patch to local reference file (with all queries, even if not applied all [this is important!])
					if( !file_exists(kiwi::path().'/_reference_'.$tables__value.'_schema.sql') ) {
						touch(kiwi::path().'/_reference_'.$tables__value.'_schema.sql');
					}
					if( !file_exists(kiwi::path().'/_reference_'.$tables__value.'_data.sql') ) {
						touch(kiwi::path().'/_reference_'.$tables__value.'_data.sql');
					}				
					kiwi::command('patch '.kiwi::path().'/_reference_'.$tables__value.'_schema.sql '.kiwi::path().'/'.$next_state.'_'.$tables__value.'_schema.patch');
					kiwi::command('patch '.kiwi::path().'/_reference_'.$tables__value.'_data.sql '.kiwi::path().'/'.$next_state.'_'.$tables__value.'_data.patch');
				
				}

				kiwi::command('rm '.kiwi::path().'/'.$next_state.'_*.*');
		}

		// update local state
		kiwi::updateLocalState($remote_state);

	}

	/* helpers */

    public static function path() {
        $path = getcwd();
		if( strpos($path,'\src') !== false ) { $path = str_replace('\src','',$path); }
        $path .= '/.kiwi/';
        return $path;
	}

	public static function pathScp() {
		$path = kiwi::path();
		if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ) {
			$path = str_replace('C:\\','/cygdrive/c/',$path);
		}
		return $path;
	}

    public static function output($message) {
        echo $message;
        echo PHP_EOL;
    }

    public static function outputAndStop($message) {
        magicdiff::output($message);
        die();
    }

	public static function copyFromRemote($remote, $local = '') {
		kiwi::command('scp -r -i "'.kiwi::conf('remote.key').'" '.kiwi::conf('remote.username').'@'.kiwi::conf('remote.host').':'.kiwi::conf('remote.path').'/'.$remote.' '.kiwi::pathScp().'/'.$local);
	}

	public static function copyToRemote($local, $remote) {
		kiwi::command('scp -r -i "'.kiwi::conf('remote.key').'" '.kiwi::pathScp().'/'.$local.' '.kiwi::conf('remote.username').'@'.kiwi::conf('remote.host').':'.kiwi::conf('remote.path').'/'.$remote);
	}

	public static function sql() {
		return new PDO('mysql:host='.kiwi::conf('database.host').';port='.kiwi::conf('database.port').';dbname='.kiwi::conf('database.database'), kiwi::conf('database.username'), kiwi::conf('database.password'), [
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
			PDO::ATTR_EMULATE_PREPARES => false,
			PDO::ATTR_PERSISTENT => true,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
		]);
	}

	public static function commandOnRemote($command) {
		kiwi::command('ssh -i "'.kiwi::conf('remote.key').'" '.kiwi::conf('remote.username').'@'.kiwi::conf('remote.host').' "'.$command.'"');
	}

	public static function command($command, $verbose = false) {
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

	public static function conf($path) {
		$config = file_get_contents(kiwi::path().'/config.json');
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

	public static function getLocalState() {
		if( !file_exists(kiwi::path().'/state.local') ) { return 0; }
		$state = file_get_contents(kiwi::path().'/state.local');
		$state = str_replace(["\r\n", "\r", "\n"],'',$state);				
		if( !kiwi::isValidState($state) ) { return 0; }
		$state = intval($state);
		return $state;
	}

	public static function getRemoteState() {
		kiwi::copyFromRemote('state.remote', 'state.remote');
		if( !file_exists(kiwi::path().'/state.remote') ) { return 0; }
		$state = file_get_contents(kiwi::path().'/state.remote');
		$state = str_replace(["\r\n", "\r", "\n"],'',$state);
		if( !kiwi::isValidState($state) ) { return 0; }
		unlink(kiwi::path().'/state.remote');
		$state = intval($state);
		return $state;
	}

	public static function generateNextState() {
		$state = kiwi::getRemoteState();
		$state++;
		return $state;
	}

	public static function formatState($state) {
		return str_pad($state, 10, '0', STR_PAD_LEFT);
	}

	public static function isValidState($state) {
		if($state === null) { return false; }
		if($state == '') { return false; }
		$state = intval($state);
		if($state < 0 || $state > 9999999999) { return false; }
		return true;
	}

	public static function updateLocalState($state = null) {
		if( $state === null ) {
			$state = kiwi::getRemoteState();
			$state++;
		}
		file_put_contents(kiwi::path().'/state.local',kiwi::formatState($state));
	}

	public static function updateRemoteState($state = null) {
		if( $state === null ) {
			$state = kiwi::getRemoteState();
			$state++;
		}
		file_put_contents(kiwi::path().'/state.remote',kiwi::formatState($state));
		kiwi::copyToRemote('state.remote','state.remote');
		unlink(kiwi::path().'/state.remote');
	}

	public static function importSql($file) {
		if(!file_exists($file)) { kiwi::outputAndStop('file cannot be imported'); }
		kiwi::command(kiwi::conf('database.import').' -h '.kiwi::conf('database.host').' --port '.kiwi::conf('database.port').' -u '.kiwi::conf('database.username').' -p"'.kiwi::conf('database.password').'" --default-character-set=utf8 '.kiwi::conf('database.database').' < '.$file);
	}

	public static function deleteDatabase() {
		$db = kiwi::sql();
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
				return kiwi::ask($question, $answers);
			}
		}
		return $answer;
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

}



// cli usage
if( php_sapi_name() == 'cli' )
{

	if (!isset($argv) || empty($argv) || !isset($argv[1]) || !in_array($argv[1],['init','status','pull','push','rollback','initTest']))
	{
		kiwi::outputAndStop('missing options');
	}

	if( $argv[1] == 'initTest' )
	{
		kiwi::initTest();
	}

	if( $argv[1] == 'init' )
	{
		kiwi::init();
	}

	if( $argv[1] == 'status' )
	{
		kiwi::status();
	}

	if( $argv[1] == 'pull' )
	{
		kiwi::pull();
	}

	if( $argv[1] == 'push' )
	{
		kiwi::push();
	}

	if( $argv[1] == 'rollback' )
	{
		kiwi::rollback();
	}

	if( $argv[1] == 'test' )
	{
		kiwi::test();
	}

}