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
		$query = "select sections.ssbsect_term_code,
	                   sections.ssbsect_crn,
	                   sections.ssbsect_term_code || '.' || sections.ssbsect_crn key,
	                   sections.ssbsect_max_enrl,
	                   sections.ssbsect_enrl,
	                   sections.ssbsect_seats_avail,
	                   sections.ssbsect_wait_count,
	                   sections.ssbsect_ssts_code as ROW_STATUS,
	                   to_char(sections.ssbsect_ptrm_start_date, 'YYYY-MM-DD') course_start_date,
	                   to_char(sections.ssbsect_ptrm_end_date, 'YYYY-MM-DD') course_end_date,
	                   coursedesc.scbdesc_subj_code,
	                   stvsubj_desc,
	                   coursedesc.scbdesc_crse_numb,
	                   coursedesc.scbdesc_term_code_eff,
	                   courseinfo.scbcrse_divs_code,
	                   stvdivs_desc,
	                   courseinfo.scbcrse_dept_code,
	                   stvdept_desc,
	                   nvl(courseinfo.scbcrse_credit_hr_low,0) units,
	                   courseinfo.scbcrse_title,
	                   CAST(coursedesc.scbdesc_text_narrative AS VARCHAR2(4000)) scbdesc_text_narrative,
                       CAST(ssbdesc_text_narrative AS VARCHAR2(4000)) ssbdesc_text_narrative,
	                   (SELECT SSRXLST_XLST_GROUP
	                     FROM SSRXLST
	                     WHERE SSRXLST_TERM_CODE = sections.ssbsect_term_code
	                     AND SSRXLST_CRN = sections.ssbsect_crn) crosslist_group,
	                   (SELECT sections.ssbsect_term_code || '.XLST_GRP.' || SSRXLST_XLST_GROUP
	                     FROM SSRXLST
	                     WHERE SSRXLST_TERM_CODE = sections.ssbsect_term_code
	                     AND SSRXLST_CRN = sections.ssbsect_crn) crosslist_group_key,
	                   (SELECT SSRXLST_CRN
	                     FROM SSRXLST
	                     WHERE SSRXLST_TERM_CODE = sections.ssbsect_term_code
	                     AND ROWNUM <= 1
	                     AND SSRXLST_XLST_GROUP = (SELECT SSRXLST_XLST_GROUP
	                                               FROM SSRXLST
	                                               WHERE SSRXLST_TERM_CODE = sections.ssbsect_term_code
	                                               AND SSRXLST_CRN = sections.ssbsect_crn)) crosslist_crn
	            from ssbsect sections,
	                 scbcrse courseinfo,
	                 scbdesc coursedesc,
	                 stvsubj,
	                 stvdivs,
	                 stvdept,
                     ssbdesc
	            where sections.ssbsect_term_code IN (select STVTERM_CODE
                                                   from stvterm
                                                   where SYSDATE <= STVTERM_END_DATE
                                                   AND STVTERM_CODE != 999999)
	            and sections.ssbsect_subj_code = courseinfo.scbcrse_subj_code
	            and sections.ssbsect_crse_numb = courseinfo.scbcrse_crse_numb
	            and courseinfo.scbcrse_subj_code = coursedesc.scbdesc_subj_code
	            and courseinfo.scbcrse_crse_numb = coursedesc.scbdesc_crse_numb
	            and coursedesc.scbdesc_term_code_eff = (select max(b.scbdesc_term_code_eff)
							                                        from scbdesc b
							                                        where coursedesc.scbdesc_subj_code = b.scbdesc_subj_code
							                                        and coursedesc.scbdesc_crse_numb = b.scbdesc_crse_numb
							                                        and b.scbdesc_term_code_eff <= sections.ssbsect_term_code)
	            and courseinfo.scbcrse_eff_term = (select max(c.scbcrse_eff_term)
						                                       from scbcrse c
						                                       where courseinfo.scbcrse_subj_code = c.scbcrse_subj_code
						                                       and courseinfo.scbcrse_crse_numb = c.scbcrse_crse_numb
						                                       and c.scbcrse_eff_term <= sections.ssbsect_term_code)
				and sections.ssbsect_subj_code = stvsubj_code
				and courseinfo.scbcrse_divs_code = stvdivs_code
				and courseinfo.scbcrse_dept_code = stvdept_code
                and ssbsect_term_code = ssbdesc_term_code(+)
                and ssbsect_crn = ssbdesc_crn(+)
				order by ssbsect_term_code ASC, ssbsect_crn ASC, coursedesc.scbdesc_term_code_eff desc";

		//$this->logAndPrintLn('Collecting rows to process...');

		$oracle->setQuery($query);
		//$daysToContinueShowing = 45;
		//$oracle->getQuery()->bind(':days_to_continue_showing', $daysToContinueShowing);

		$rows = $oracle->loadAssocList();

		//$this->logAndPrintLn('Need to process a total of ' . count($rows) . ' term records.');
		$file = __DIR__.'/../files/course.txt';

		$headerRow = 'sis_course_id|sis_term_key|sis_crn|sis_course_name|sis_subject|sis_subject_long|sis_course_number|sis_division|sis_division_long|sis_department|sis_department_long|sis_start_date|sis_end_date|sis_master_course_id|sis_units|sis_max_enrollment|sis_enrollment|sis_available|sis_waitlisted|sis_description|row_status' . "\n";

		$contents = '';
		$contents .= $headerRow;
		foreach($rows as &$row)
		{
			if (!empty($row['SSBDESC_TEXT_NARRATIVE']))
	        {
	            // Then we use the special course description:
	            $description = str_replace("\n", '<br />', $row['SSBDESC_TEXT_NARRATIVE']);
	        }
	        else
	        {
	            // We use the default one from the catalog:
	            $description = str_replace("\n", '<br />', $row['SCBDESC_TEXT_NARRATIVE']);
	        }

			$contents .= $row['KEY'] . '|'
					   . $row['SSBSECT_TERM_CODE'] . '|'
					   . $row['SSBSECT_CRN'] . '|'
					   . $row['SCBCRSE_TITLE'] . '|'
					   . $row['SCBDESC_SUBJ_CODE'] . '|'
					   . $row['STVSUBJ_DESC'] . '|'
					   . $row['SCBDESC_CRSE_NUMB'] . '|'
					   . $row['SCBCRSE_DIVS_CODE'] . '|'
					   . $row['STVDIVS_DESC'] . '|'
					   . $row['SCBCRSE_DEPT_CODE'] . '|'
					   . $row['STVDEPT_DESC'] . '|'
					   . $row['COURSE_START_DATE'] . '|'
					   . $row['COURSE_END_DATE'] . '|'
					   . "" . '|'
					   . $row['UNITS'] . '|'
					   . $row['SSBSECT_MAX_ENRL'] . '|'
					   . $row['SSBSECT_ENRL'] . '|'
					   . $row['SSBSECT_SEATS_AVAIL'] . '|'
					   . $row['SSBSECT_WAIT_COUNT'] . '|'
					   . $description . '|'
					   . $row['ROW_STATUS'] . "\n";
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
