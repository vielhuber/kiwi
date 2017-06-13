<?php
namespace vielhuber\kiwi;
use vielhuber\dbhelper\dbhelper;
use vielhuber\magicdiff\magicdiff;
use vielhuber\magicreplace\magicreplace;
use PDO;
require_once(__DIR__.'/../vendor/autoload.php');

class status
{

	public static function do()
	{
		init::checkAndStop();
		$local_state = helper::getLocalState();
		$remote_state = helper::getRemoteState();
		helper::output('you are currently on state '.helper::formatState($local_state));
		helper::output('the remote is currently on state '.helper::formatState($remote_state));
		if( $local_state < $remote_state ) { helper::output('you need to pull first before you push'); }
		else { helper::output('nothing to pull'); }
		$diff = magicdiff::diff();
		if(empty($diff)) { helper::outputAndStop('nothing to push'); }
		else {
			$diff_output = '';
			foreach($diff as $diff__value) {
				$diff_output .= $diff__value['diff']['all'].PHP_EOL;
			}
			helper::output('you are ahead of '.helper::formatState($local_state));
			helper::outputAndStop('difference: '.$diff_output);
		}
	}

}