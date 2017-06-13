<?php
namespace vielhuber\kiwi;
use vielhuber\dbhelper\dbhelper;
use vielhuber\magicdiff\magicdiff;
use vielhuber\magicreplace\magicreplace;
use PDO;
require_once(__DIR__.'/../vendor/autoload.php');

class push
{

	public static function do()
	{
		init::checkAndStop();
		if( helper::getLocalState() < helper::getRemoteState() ) { helper::outputAndStop('you need to pull first before you push'); }
		$diff = magicdiff::diff();
		if(empty($diff)) { helper::outputAndStop('nothing to push'); }
		$state = helper::generateNextState();
		magicdiff::init(); // update local reference dump
		helper::updateLocalState($state);
		helper::updateRemoteDump($diff, $state); // update remote dumps (also applying magicreplace)
		helper::updateRemoteState($state);
		helper::outputAndStop('successfully pushed state '.helper::formatState($state));
	}

}