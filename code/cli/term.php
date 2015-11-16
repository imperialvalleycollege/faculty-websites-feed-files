<?php
// Include the autoloader (which helps to autoload PHP classes on the fly):
require dirname(__DIR__).'/vendor/autoload.php';

use Aura\Cli\CliFactory;
use Aura\Cli\Status;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;

// get the context and stdio objects
$cli_factory = new CliFactory;
$context = $cli_factory->newContext($GLOBALS);
$stdio = $cli_factory->newStdio();


// Get the Oracle Database Connection:
		$oracle = App\Factory::getOracleDbo();

		// Query to get Class Roster Information for Current Term:
		$query = "SELECT stvterm_code, stvterm_desc, to_char(stvterm_start_date, 'YYYY-MM-DD') stvterm_start_date, to_char(stvterm_end_date, 'YYYY-MM-DD') stvterm_end_date
		          FROM STVTERM
		          WHERE stvterm_end_date > (SYSDATE - 45)
		          AND STVTERM_CODE != '999999'";

		//$this->logAndPrintLn('Collecting rows to process...');

		$oracle->setQuery($query);
		//$daysToContinueShowing = 45;
		//$oracle->getQuery()->bind(':days_to_continue_showing', $daysToContinueShowing);

		$rows = $oracle->loadAssocList();

		//$this->logAndPrintLn('Need to process a total of ' . count($rows) . ' term records.');
		$file = __DIR__.'/../files/term.txt';

		$headerRow = 'sis_term_key|sis_term_name|sis_term_start_date|sis_term_end_date' . "\n";

		$contents = '';
		$contents .= $headerRow;
		foreach($rows as &$row)
		{
			$contents .= $row['STVTERM_CODE'] . '|' . $row['STVTERM_DESC'] . '|' . $row['STVTERM_START_DATE'] . '|' . $row['STVTERM_END_DATE'] . "\n";
			//$stdio->outln(print_r($row, true));
		}

		App\Feed\Helper::write($file, $contents);

		App\Feed\Helper::send('imperial', 'YfHMY9nHHc7FPEFRQkys2LfYbRk3S8qZ', 'http://localhost/faculty-websites/api/1.0/submission', realpath($file));
		//$this->logAndPrintLn("\n".'Processed ' . count($rows) . ' term records.');

/*$filesFolder = __DIR__.'/../files';
foreach (new DirectoryIterator($filesFolder) as $fileInfo) {
    if($fileInfo->isDot()) continue;

    if ($fileInfo->isDir())
    {
		$organization = $fileInfo->getFilename();
		$organizationFolder = $filesFolder . '/' . $organization;
		foreach (new DirectoryIterator($organizationFolder) as $subFileInfo)
		{
			if($subFileInfo->isDot()) continue;

			// Record Start Time:
			$startTime = microtime(true);

			$importFile = $subFileInfo->getPath() . '/' . $subFileInfo->getFilename();

			if(($handle = fopen($importFile, 'r')) !== false)
			{
			    // get the first row, which contains the column-titles (if necessary)
			    $headers = fgetcsv($handle, null, '|');

				$importType = \App\Import\Helper::importType($headers);

				if (!empty($importType))
				{
					$stdio->outln('Processing ' . "'" . $subFileInfo->getFilename() . "'" . '...');
					$stdio->outln('Type: ' . "'" . $importType . "'");
					$stdio->outln('Organization: ' . "'" . $organization . "'");

					$importObject = \App\Import\Helper::createImportObject($importType);

					$importObject->setOrganization($organization);
					$importObject->setHeaders($headers);
				    //$stdio->outln($importType);

				    // loop through the file line-by-line
				    while(($data = fgetcsv($handle, null, '|')) !== false)
				    {
				    	if (isset($data[0]))
				    	{
							$stdio->outln($data[0]);
							//$stdio->outln(print_r($data, true));
				    	}

						$importObject->setData($data);

						$result = $importObject->store();

				        unset($data);
				    }
				}
				else
				{
					$stdio->outln('Could not automatically determine import type. Please check header fields and resubmit.');
				}

			    fclose($handle);

			    unlink($importFile);
			}

			//echo $contents;

			$endTime = microtime(true);
			$elapsedTime = $endTime - $startTime;

			$stdio->outln("Processed file in $elapsedTime seconds");
		}

		rmdir($organizationFolder);
    }

}
*/
// done!
exit(Status::SUCCESS);
