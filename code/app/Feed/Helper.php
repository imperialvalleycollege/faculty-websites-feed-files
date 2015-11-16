<?php
namespace App\Feed;

class Helper
{
	public static function write($filename, $contents = '')
	{
		// Let's make sure the file exists and is writable first.
		//if (is_writable($filename)) {

		    // In our example we're opening $filename in append mode.
		    // The file pointer is at the bottom of the file hence
		    // that's where $somecontent will go when we fwrite() it.
		    if (!$handle = fopen($filename, 'w')) {
		         // Couldn't open file
		    }

		    // Write $somecontent to our opened file.
		    if (fwrite($handle, $contents) === FALSE) {
		        // Couldn't Write to File
		    }

		    fclose($handle);

		//}
	}

	public static function send($organization, $apiKey, $url, $filename)
	{

		// This is the data to POST to the form. The KEY of the array is the name of the field. The value is the value posted.
		$data_to_post = array();
		$data_to_post['organization'] = $organization;
		$data_to_post['api_key'] = $apiKey;
		$data_to_post['submission_file'] = curl_file_create($filename, "text/plain", 'tmp.txt');

		// Initialize cURL
		$curl = curl_init();

		// Set the options
		curl_setopt($curl,CURLOPT_URL, $url);

		// This sets the number of fields to post
		curl_setopt($curl,CURLOPT_POST, sizeof($data_to_post));

		// This is the fields to post in the form of an array.
		curl_setopt($curl,CURLOPT_POSTFIELDS, $data_to_post);

		//execute the post
		$result = curl_exec($curl);

		//close the connection
		curl_close($curl);

		echo $result;
		exit();
	}
}
