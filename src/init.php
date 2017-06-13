<?php
namespace vielhuber\kiwi;
use vielhuber\dbhelper\dbhelper;
use vielhuber\magicdiff\magicdiff;
use vielhuber\magicreplace\magicreplace;
use PDO;
require_once(__DIR__.'/../vendor/autoload.php');

class init
{

	public static function do()
	{
		if( init::check() ) { helper::outputAndStop('already kiwied'); }
		init::setupInitFiles();
		helper::output('done. now edit config.json.');
	}

	public static function check()
	{
		if( is_dir(helper::path()) && file_exists(helper::path().'/config.json') ) { return true; }
		return false;
	}

	public static function checkAndStop()
	{
		if( init::check() === true ) { return true; }
		helper::outputAndStop('init first');
	}

	public static function setupInitFiles()
	{
		if( !is_dir(helper::path()) ) { mkdir(helper::path()); }
		file_put_contents( helper::path().'/config.json', init::getConfigBoilerplate() );
		file_put_contents( helper::path().'/state.local', '' );
	}

	public static function test()
	{
		if( init::check() ) { helper::commandOnRemote('rm '.helper::conf('remote.path').'/*'); }
		helper::command('rm '.kiwi::path().'/*');
		init::do();
		helper::deleteDatabase();
		helper::createRandomData();
	}

	public static function createRandomData()
	{
		$db = helper::sql();
		$db->query('CREATE TABLE MyGuests ( id INT(6) UNSIGNED AUTO_INCREMENT, firstname VARCHAR(30) NOT NULL, lastname VARCHAR(30) NOT NULL, email VARCHAR(50), PRIMARY KEY (id))');
		$db->query('INSERT INTO MyGuests(firstname, lastname, email) VALUES (\'foo\',\'bar\',\'baz\')');
	}

	public static function getConfigBoilerplate()
	{
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
        $config = str_replace('\\','\\\\',(str_replace('			','	',str_replace('		}','}',$config))));
        return $config;
	}

}