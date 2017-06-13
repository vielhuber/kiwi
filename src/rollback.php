<?php
namespace vielhuber\kiwi;
use vielhuber\dbhelper\dbhelper;
use vielhuber\magicdiff\magicdiff;
use vielhuber\magicreplace\magicreplace;
use PDO;
require_once(__DIR__.'/../vendor/autoload.php');

class rollback
{

	public static function do()
	{
		helper::checkAndStop();
		helper::deleteDatabase();
		helper::importSql(helper::path().'/reference_schema.sql');
		helper::importSql(helper::path().'/reference_data.sql');
	}

}