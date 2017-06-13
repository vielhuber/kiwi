<?php
namespace vielhuber\kiwi;
use vielhuber\dbhelper\dbhelper;
use vielhuber\magicdiff\magicdiff;
use vielhuber\magicreplace\magicreplace;
use PDO;
require_once(__DIR__.'/../vendor/autoload.php');

class cli
{

	public static function init($argv)
	{

		if( php_sapi_name() != 'cli' ) { return; }

		if (!isset($argv) || empty($argv) || !isset($argv[1]) || !in_array($argv[1],['init','status','push','pull','rollback','initTest','--version']))
		{
			helper::outputAndStop('missing options');
		}

		if( $argv[1] == 'init' )
		{
			init::do();
		}

		if( $argv[1] == 'status' )
		{
			status::do();
		}

		if( $argv[1] == 'push' )
		{
			push::do();
		}

		if( $argv[1] == 'pull' )
		{
			pull::do();
		}

		if( $argv[1] == 'rollback' )
		{
			rollback::do();
		}

		if( $argv[1] == 'test' )
		{
			init::test();
		}

		if( $argv[1] == '--version' ) {
			helper::version();
		}

	}

}
cli::init($argv);