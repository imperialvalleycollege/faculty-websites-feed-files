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
		$query = "select ssrmeet_term_code || '.' || ssrmeet_crn course_id,
					    ssrmeet_term_code,
					    ssrmeet_crn,
						ssrmeet_start_date,
						ssrmeet_end_date,
						ssrmeet_mon_day,
						ssrmeet_tue_day,
						ssrmeet_wed_day,
						ssrmeet_thu_day,
						ssrmeet_fri_day,
						ssrmeet_sat_day,
						ssrmeet_begin_time,
						ssrmeet_end_time,
						ssrmeet_over_ride,
						ssrmeet_bldg_code,
						ssrmeet_room_code,
						ssrmeet_schd_code,
						ssrmeet_credit_hr_sess,
						ssrmeet_break_ind,
						ssrmeet_hrs_week,
						ssrmeet_hrs_day,
						ssrmeet_hrs_total,
						stvschd.stvschd_desc
					from ssrmeet, stvschd
					where ssrmeet_term_code IN (select STVTERM_CODE
                                                   from stvterm
                                                   where SYSDATE <= STVTERM_END_DATE
                                                   AND STVTERM_CODE != 999999)
					and ssrmeet_schd_code = stvschd_code
					order by ssrmeet_term_code ASC, ssrmeet_crn ASC, ssrmeet_schd_code ASC, ssrmeet_start_date ASC, ssrmeet_end_date ASC, ssrmeet_begin_time ASC, ssrmeet_end_time ASC";

		//$this->logAndPrintLn('Collecting rows to process...');

		$oracle->setQuery($query);
		//$daysToContinueShowing = 45;
		//$oracle->getQuery()->bind(':days_to_continue_showing', $daysToContinueShowing);

		$rows = $oracle->loadAssocList();

		//$this->logAndPrintLn('Need to process a total of ' . count($rows) . ' term records.');
		$file = __DIR__.'/../files/meetingtime.txt';

		$headerRow = 'sis_course_id|sis_term_key|sis_crn|sis_schedule_code|sis_schedule_code_long|sis_primary_schedule_code_ind|sis_start_date|sis_end_date|sis_begin_time|sis_end_time|sis_monday_ind|sis_tuesday_ind|sis_wednesday_ind|sis_thursday_ind|sis_friday_ind|sis_saturday_ind|sis_override_ind|sis_building|sis_room|sis_units|sis_break_ind|sis_weekly_hours|sis_daily_hours|sis_total_hours|row_status' . "\n";

		$contents = '';
		$contents .= $headerRow;
		$courseIds = array();
		foreach($rows as &$row)
		{
			$primary_schedule_code_ind = 1;

			// Check if this is a primary schedule code or a secondary one:
			// We also need to check if the
			if (isset($courseIds[$row['COURSE_ID']]))
			{
				$primary_schedule_code_ind = 0;
			}

			$contents .= $row['COURSE_ID'] . '|'
					   . $row['SSRMEET_TERM_CODE'] . '|'
					   . $row['SSRMEET_CRN'] . '|'
					   . $row['SSRMEET_SCHD_CODE'] . '|'
					   . $row['STVSCHD_DESC'] . '|'
					   . $primary_schedule_code_ind . '|'
					   . $row['SSRMEET_START_DATE'] . '|'
					   . $row['SSRMEET_END_DATE'] . '|'
					   . $row['SSRMEET_BEGIN_TIME'] . '|'
					   . $row['SSRMEET_END_TIME'] . '|'
					   . $row['SSRMEET_MON_DAY'] . '|'
					   . $row['SSRMEET_TUE_DAY'] . '|'
					   . $row['SSRMEET_WED_DAY'] . '|'
					   . $row['SSRMEET_THU_DAY'] . '|'
					   . $row['SSRMEET_FRI_DAY'] . '|'
					   . $row['SSRMEET_SAT_DAY'] . '|'
					   . $row['SSRMEET_OVER_RIDE'] . '|'
					   . $row['SSRMEET_BLDG_CODE'] . '|'
					   . $row['SSRMEET_ROOM_CODE'] . '|'
					   . $row['SSRMEET_CREDIT_HR_SESS'] . '|'
					   . $row['SSRMEET_BREAK_IND'] . '|'
					   . $row['SSRMEET_HRS_WEEK'] . '|'
					   . $row['SSRMEET_HRS_DAY'] . '|'
					   . $row['SSRMEET_HRS_TOTAL'] . '|'
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
