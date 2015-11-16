<?php
namespace App;

class Config
{
	public static function db()
	{
		return array(
	        'host' => 'localhost',
	        'user' => 'root',
	        'password' => '',
	        'port' => '3306',
	        'database' => 'faculty-websites',
	    );
	}

	public static function oracleDb()
	{
		return array(
			'driver' => 'oracle',
	        'host' => 'localhost',
	        'user' => 'root',
	        'password' => '',
	        'port' => '1521',
	        'database' => 'faculty-websites',
	    );
	}
}

