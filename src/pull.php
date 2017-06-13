<?php
namespace vielhuber\kiwi;
use vielhuber\dbhelper\dbhelper;
use vielhuber\magicdiff\magicdiff;
use vielhuber\magicreplace\magicreplace;
use PDO;
require_once(__DIR__.'/../vendor/autoload.php');

class pull
{

	public static function do()
	{
		init::checkAndStop();
		$local_state = helper::getLocalState();
		$remote_state = helper::getRemoteState();		
		if( $local_state >= $remote_state ) { helper::outputAndStop('nothing to pull'); }
		$db = helper::sql();

		// fetch not applied
		for($i = 1; $i <= ($remote_state-$local_state); $i++) {
			$next_state = helper::formatState($local_state+$i);

				// fetch from remote
				helper::copyFromRemote($next_state.'_*.*');

				// do magicreplace on fetched files
				if( helper::conf('replace') !== null && !empty(helper::conf('replace')) ) {
					foreach(glob(helper::path().'/'.$next_state.'_*.*') as $file) {
						magicreplace::run($file,$file,array_flip(helper::conf('replace')));
					}
				}

				// get all table names based on fetched files
				$tables = [];
				foreach(glob(helper::path().'/'.$next_state.'_*.*') as $file) {
					$tables[] = substr($file,strpos($file,'_')+1,strrpos($file,'_')-strpos($file,'_')-1);
				}
				$tables = array_unique($tables);
				
				foreach($tables as $tables__value) {

					// apply query by query in interactive manner (to overcome transactions not possible with ddl schema changes)
					$diff = '';
					if( file_exists(helper::path().'/'.$next_state.'_'.$tables__value.'_schema.diff') ) {
						$diff .= file_get_contents(helper::path().'/'.$next_state.'_'.$tables__value.'_schema.diff');
					}
					if( file_exists(helper::path().'/'.$next_state.'_'.$tables__value.'_data.diff') ) {
						$diff .= file_get_contents(helper::path().'/'.$next_state.'_'.$tables__value.'_data.diff');
					}
					foreach( helper::splitQuery($diff) as $diff_single ) {
						helper::output($diff_single);
						$answer = helper::ask('do you want to commit this? (y/n)',['y','n']);
						if($answer == 'n') { continue; }
						try { $db->exec($diff_single); }
						catch (PDOException $e) { echo $e->getMessage(); }
					}

					// apply patch to local reference file (with all queries, even if not applied all [this is important!])
					if( !file_exists(helper::path().'/_reference_'.$tables__value.'_schema.sql') ) {
						touch(helper::path().'/_reference_'.$tables__value.'_schema.sql');
					}
					if( !file_exists(helper::path().'/_reference_'.$tables__value.'_data.sql') ) {
						touch(helper::path().'/_reference_'.$tables__value.'_data.sql');
					}				
					helper::command('patch '.helper::path().'/_reference_'.$tables__value.'_schema.sql '.helper::path().'/'.$next_state.'_'.$tables__value.'_schema.patch');
					helper::command('patch '.helper::path().'/_reference_'.$tables__value.'_data.sql '.helper::path().'/'.$next_state.'_'.$tables__value.'_data.patch');
					//die('patch '.helper::path().'/_reference_'.$tables__value.'_data.sql '.helper::path().'/'.$next_state.'_'.$tables__value.'_data.patch');
				
				}

				helper::command('rm '.helper::path().'/'.$next_state.'_*.*');
		}

		// update local state
		helper::updateLocalState($remote_state);

	}
}