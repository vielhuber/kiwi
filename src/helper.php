<?php
namespace vielhuber\kiwi;
use vielhuber\dbhelper\dbhelper;
use vielhuber\magicdiff\magicdiff;
use vielhuber\magicreplace\magicreplace;
use PDO;
require_once(__DIR__.'/../vendor/autoload.php');

class helper
{

    public static function path()
    {
        $path = getcwd();
		if( strpos($path,'\src') !== false ) { $path = str_replace('\src','',$path); }
        $path .= '/.kiwi/';
        return $path;
	}

	public static function pathScp()
	{
		$path = helper::path();
		if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' )
		{
			$path = str_replace('C:\\','/cygdrive/c/',$path);
		}
		return $path;
	}

    public static function output($message)
    {
        echo $message;
        echo PHP_EOL;
    }

    public static function outputAndStop($message)
    {
        helper::output($message);
        die();
    }

	public static function copyFromRemote($remote, $local = '')
	{
		helper::command('scp -r -i "'.helper::conf('remote.key').'" '.helper::conf('remote.username').'@'.helper::conf('remote.host').':'.helper::conf('remote.path').'/'.$remote.' '.helper::pathScp().'/'.$local);
	}

	public static function copyToRemote($local, $remote)
	{
		helper::command('scp -r -i "'.helper::conf('remote.key').'" '.helper::pathScp().'/'.$local.' '.helper::conf('remote.username').'@'.helper::conf('remote.host').':'.helper::conf('remote.path').'/'.$remote);
	}

	public static function sql()
	{
		return new PDO('mysql:host='.helper::conf('database.host').';port='.helper::conf('database.port').';dbname='.helper::conf('database.database'), helper::conf('database.username'), helper::conf('database.password'), [
			PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
			PDO::ATTR_EMULATE_PREPARES => false,
			PDO::ATTR_PERSISTENT => true,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
		]);
	}

	public static function commandOnRemote($command)
	{
		helper::command('ssh -i "'.helper::conf('remote.key').'" '.helper::conf('remote.username').'@'.helper::conf('remote.host').' "'.$command.'"');
	}

	public static function command($command, $verbose = false)
	{
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

	public static function conf($path)
	{
		$config = file_get_contents(helper::path().'/config.json');
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

	public static function getLocalState()
	{
		if( !file_exists(helper::path().'/state.local') ) { return 0; }
		$state = file_get_contents(helper::path().'/state.local');
		$state = str_replace(["\r\n", "\r", "\n"],'',$state);				
		if( !helper::isValidState($state) ) { return 0; }
		$state = intval($state);
		return $state;
	}

	public static function getRemoteState()
	{
		helper::copyFromRemote('state.remote', 'state.remote');
		if( !file_exists(helper::path().'/state.remote') ) { return 0; }
		$state = file_get_contents(helper::path().'/state.remote');
		$state = str_replace(["\r\n", "\r", "\n"],'',$state);
		if( !helper::isValidState($state) ) { return 0; }
		unlink(helper::path().'/state.remote');
		$state = intval($state);
		return $state;
	}

	public static function generateNextState()
	{
		$state = helper::getRemoteState();
		$state++;
		return $state;
	}

	public static function formatState($state)
	{
		return str_pad($state, 10, '0', STR_PAD_LEFT);
	}

	public static function isValidState($state)
	{
		if($state === null) { return false; }
		if($state == '') { return false; }
		$state = intval($state);
		if($state < 0 || $state > 9999999999) { return false; }
		return true;
	}

	public static function updateLocalState($state = null)
	{
		if( $state === null ) {
			$state = helper::getRemoteState();
			$state++;
		}
		file_put_contents(helper::path().'/state.local',helper::formatState($state));
	}

	public static function updateRemoteState($state = null)
	{
		if( $state === null ) {
			$state = helper::getRemoteState();
			$state++;
		}
		file_put_contents(helper::path().'/state.remote',helper::formatState($state));
		helper::copyToRemote('state.remote','state.remote');
		unlink(helper::path().'/state.remote');
	}

	public static function updateRemoteDump($diff, $state)
	{
		$state = helper::formatState($state);
		foreach($diff as $diff__value) {
			foreach([['patch','schema'],['patch','data'],['diff','schema'],['diff','data']] as $type) {
				file_put_contents(helper::path().'/tmp', $diff__value[$type[0]][$type[1]]);
				if( helper::conf('replace') !== null && !empty(helper::conf('replace')) ) {
					magicreplace::run(helper::path().'/tmp',helper::path().'/tmp',helper::conf('replace'));
				}
				helper::copyToRemote('tmp',$state.'_'.$diff__value['table'].'_'.$type[1].'.'.$type[0].'');
				@unlink(helper::path().'/tmp');
			}
		}		
	}

	public static function importSql($file)
	{
		if(!file_exists($file)) { helper::outputAndStop('file cannot be imported'); }
		helper::command(helper::conf('database.import').' -h '.helper::conf('database.host').' --port '.helper::conf('database.port').' -u '.helper::conf('database.username').' -p"'.helper::conf('database.password').'" --default-character-set=utf8 '.helper::conf('database.database').' < '.$file);
	}

	public static function deleteDatabase()
	{
		$db = helper::sql();
		$db->query('SET foreign_key_checks = 0');
		$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
		if(!empty($tables)) {
			foreach($tables as $tables__value) {
				$db->query('DROP TABLE IF EXISTS '.$tables__value);
			}
		}
		$db->query('SET foreign_key_checks = 1');
	}

	public static function ask($question, $answers = null)
	{
		echo $question.' ';
		$handle = fopen("php://stdin","r");
		$answer = fgets($handle);
		fclose($handle);
		$answer = trim($answer);
		if($answers != null) {
			if( !in_array($answer,$answers) ) {
				return helper::ask($question, $answers);
			}
		}
		return $answer;
	}

	public static function splitQuery($query)
	{
		$unique_delimiter = md5(uniqid(rand(), true));
		$query = str_replace(["\r\n", "\r", "\n"], $unique_delimiter, $query);
		$query = str_replace(';'.$unique_delimiter, ';'.PHP_EOL, $query);
		$query = str_replace($unique_delimiter, '', $query);
		$query = explode(PHP_EOL,$query);
		$query = array_filter($query);
		return $query;
	}

	public static function version()
	{
		helper::output('1.0.0');
	}

}