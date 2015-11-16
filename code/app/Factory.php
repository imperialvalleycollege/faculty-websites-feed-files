<?php
namespace App;

class Factory
{
	public static function getDbo()
	{
		$dbFactory = new \Joomla\Database\DatabaseFactory;

		$db = $dbFactory->getDriver(
		    'mysqli',
		     \App\Config::db()
		);

		return $db;
	}

	public static function getOracleDbo()
	{
		$dbFactory = new \Joomla\Database\DatabaseFactory;

		$db = $dbFactory->getDriver(
		    'oracle',
		     \App\Config::oracleDb()
		);

		return $db;
	}
}

