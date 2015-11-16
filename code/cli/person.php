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
		$query = "select DISTINCT(SFRSTCR_PIDM), SPRIDEN_ID, TO_NUMBER(SUBSTR(SPRIDEN_ID, 2)) UIDNUMBER, SPRIDEN_LAST_NAME, SPRIDEN_FIRST_NAME, SPRIDEN_MI, GORPAUD_PIN, GOBTPAC_EXTERNAL_USER, GOBTPAC_LDAP_USER
					from (select SFRSTCR_PIDM
						  from SFRSTCR
						  where SFRSTCR_TERM_CODE IN (select STVTERM_CODE
                                                   from stvterm
                                                   where (SYSDATE - 2) <= STVTERM_END_DATE
                                                   AND STVTERM_CODE != 999999)
						  GROUP BY SFRSTCR_PIDM
						  UNION
						  select sirasgn_pidm
						  from sirasgn
						  WHERE SIRASGN_TERM_CODE IN (select STVTERM_CODE
                                                   from stvterm
                                                   where (SYSDATE - 2) <= STVTERM_END_DATE
                                                   AND STVTERM_CODE != 999999)
						  UNION
						  SELECT PEBEMPL_PIDM
						  FROM PEBEMPL
						  WHERE PEBEMPL_EMPL_STATUS = 'A'
						  AND PEBEMPL_ECLS_CODE IN ('F0', 'F1', 'F2', 'FN', 'FP', 'FS', 'NC', 'CM','CC','C0', 'C1', 'C2', 'C3', 'C4', 'C5', 'C9', 'CP', 'CS', 'AD', 'AP', 'OP', 'OR', 'ST', 'SW'))
					INNER JOIN
					  SPRIDEN
					ON
					  SFRSTCR_PIDM = SPRIDEN_PIDM
					LEFT OUTER JOIN
						 GORPAUD
					ON
						SFRSTCR_PIDM = GORPAUD_PIDM
					LEFT OUTER JOIN
						GOBTPAC
					ON
						SFRSTCR_PIDM = GOBTPAC_PIDM
					WHERE SPRIDEN_CHANGE_IND IS NULL
					AND GORPAUD_ACTIVITY_DATE = (SELECT MAX(GORPAUD_ACTIVITY_DATE) FROM GORPAUD WHERE GORPAUD_PIDM = SFRSTCR_PIDM AND GORPAUD_CHG_IND = 'P')
					AND GORPAUD_CHG_IND = 'P'
					AND GOBTPAC_EXTERNAL_USER IS NOT NULL";

		//$this->logAndPrintLn('Collecting rows to process...');

		$oracle->setQuery($query);
		//$daysToContinueShowing = 45;
		//$oracle->getQuery()->bind(':days_to_continue_showing', $daysToContinueShowing);

		$rows = $oracle->loadAssocList();

		//$this->logAndPrintLn('Need to process a total of ' . count($rows) . ' term records.');
		$file = __DIR__.'/../files/person.txt';

		$headerRow = 'sis_internal_id|sis_id|sis_username|sis_password|sis_email|sis_first_name|sis_last_name|is_employee|row_status|system_role' . "\n";

		$contents = '';
		$contents .= $headerRow;
		foreach($rows as &$row)
		{
			if (!empty($row['GOBTPAC_LDAP_USER']))
			{
				$username = $row['GOBTPAC_LDAP_USER'];
				$email = $row['GOBTPAC_LDAP_USER'] . '@imperial.edu';
				$is_employee = 1;
			}
			else
			{
				$username = $row['GOBTPAC_EXTERNAL_USER'];
				$email = $row['GOBTPAC_EXTERNAL_USER'] . '@students.imperial.edu';
				$is_employee = 0;
			}

			$contents .= $row['SFRSTCR_PIDM'] . '|'
					   . $row['SPRIDEN_ID'] . '|'
					   . $username . '|'
					   . '' . '|'
					   . $email . '|'
					   . $row['SPRIDEN_FIRST_NAME'] . '|'
					   . $row['SPRIDEN_LAST_NAME'] . '|'
					   . $is_employee . '|'
					   . 'A' . '|'
					   . '' . "\n";
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
