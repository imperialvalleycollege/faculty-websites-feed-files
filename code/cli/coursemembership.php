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
		$query = "select SFRSTCR_TERM_CODE || '.' || SFRSTCR_CRN key, SFRSTCR_TERM_CODE term, SFRSTCR_CRN crn, SFRSTCR_PIDM pidm,
		                 SPRIDEN_ID, SPRIDEN_FIRST_NAME, SPRIDEN_LAST_NAME, SPRIDEN_MI, GOBTPAC_EXTERNAL_USER, GOBTPAC_LDAP_USER,
		                 'Student' role, to_char(sfrstcr_add_date, 'YYYY-MM-DD') enrollment_date
					from (select SFRSTCR_PIDM, SFRSTCR_TERM_CODE, SFRSTCR_CRN, sfrstcr_rsts_code, sfrstcr_add_date
						  from SFRSTCR
						  where SFRSTCR_TERM_CODE IN (select STVTERM_CODE
                                                   from stvterm
                                                   where (SYSDATE - 2) <= STVTERM_END_DATE
                                                   AND STVTERM_CODE != 999999)
						  and (sfrstcr_rsts_code = 'RW' or sfrstcr_rsts_code = 'RE')
						  )
					INNER JOIN
					  SPRIDEN
					ON
					  SFRSTCR_PIDM = SPRIDEN_PIDM
					LEFT OUTER JOIN
					  GOBTPAC
					ON
					  SFRSTCR_PIDM = GOBTPAC_PIDM
					WHERE SPRIDEN_CHANGE_IND IS NULL
					AND GOBTPAC_EXTERNAL_USER IS NOT NULL
					UNION
					SELECT SIRASGN_TERM_CODE || '.' || SIRASGN_CRN key, SIRASGN_TERM_CODE term, SIRASGN_CRN crn, SIRASGN_PIDM pidm,
					       SPRIDEN_ID, SPRIDEN_FIRST_NAME, SPRIDEN_LAST_NAME, SPRIDEN_MI, GOBTPAC_EXTERNAL_USER, GOBTPAC_LDAP_USER,
					       'Instructor' role, to_char(sirasgn_activity_date, 'YYYY-MM-DD') enrollment_date
					FROM SIRASGN
					INNER JOIN
					 SPRIDEN
					ON
					  SIRASGN_PIDM = SPRIDEN_PIDM
          			INNER JOIN
					 STVTERM
					ON
					  SIRASGN_TERM_CODE = STVTERM_CODE
					LEFT OUTER JOIN
					   GOBTPAC
					ON
					  SIRASGN_PIDM = GOBTPAC_PIDM
					WHERE SIRASGN_TERM_CODE IN (select STVTERM_CODE
                                                   from stvterm
                                                   where (SYSDATE - 2) <= STVTERM_END_DATE
                                                   AND STVTERM_CODE != 999999)
					AND SPRIDEN_CHANGE_IND IS NULL
					AND GOBTPAC_EXTERNAL_USER IS NOT NULL
					ORDER BY term, crn, SPRIDEN_FIRST_NAME, SPRIDEN_LAST_NAME";

		//$this->logAndPrintLn('Collecting rows to process...');

		$oracle->setQuery($query);
		//$daysToContinueShowing = 45;
		//$oracle->getQuery()->bind(':days_to_continue_showing', $daysToContinueShowing);

		$rows = $oracle->loadAssocList();

		//$this->logAndPrintLn('Need to process a total of ' . count($rows) . ' term records.');
		$file = __DIR__.'/../files/coursemembership.txt';

		$headerRow = 'sis_course_id|sis_term_key|sis_crn|sis_internal_id|sis_role|row_status' . "\n";

		$contents = '';
		$contents .= $headerRow;
		foreach($rows as &$row)
		{

			$contents .= $row['KEY'] . '|'
					   . $row['TERM'] . '|'
					   . $row['CRN'] . '|'
					   . $row['PIDM'] . '|'
					   . $row['ROLE'] . '|'
					   . 'A' . "\n";
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
