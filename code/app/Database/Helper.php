<?php
namespace App\Database;

class Helper
{
	public static function quoteNames($columns)
	{
		$db = \App\Factory::getDbo();

		$quotedNames = array();
		foreach($columns as $column)
		{
			$quotedNames[] = $db->quoteName($column);
		}

		return $quotedNames;
	}

	public static function quoteValues($data)
	{
		$db = \App\Factory::getDbo();

		$quotedData = array();
		foreach($data as $value)
		{
			$quotedData[] = $db->quote($value);
		}

		return $quotedData;
	}

	public static function getSetString($columns, $data)
	{
		$db = \App\Factory::getDbo();
		$output = array();

		foreach($columns as $index => $column)
		{
			$output[] = $db->quoteName($column) . ' = ' . $db->quote($data[$index]);
		}

		return implode(', ', $output);
	}

	public static function getCurrentDateTime()
	{
		$date_utc = new \DateTime(null, new \DateTimeZone("UTC"));
		$currentDateTime = $date_utc->format('Y-m-d H:i:s');

		return $currentDateTime;
	}

	public static function insertOrUpdate(\App\Import\ImportInterface $object)
	{
		$db = \App\Factory::getDbo();

		$db->getQuery(true);

		$organization = $db->quote($object->organization);
		$fields = implode(',', \App\Database\Helper::quoteNames($object->headers));
		$values = implode(',', \App\Database\Helper::quoteValues($object->data));
		$setString = \App\Database\Helper::getSetString($object->headers, $object->data);
		$currentDateTime = $db->quote(\App\Database\Helper::getCurrentDateTime());

		$sql = <<<SQL
		INSERT INTO {$object->table} (
			$fields,
			organization,
			created,
			updated
		)
		VALUES (
			$values,
			{$organization},
			$currentDateTime,
			$currentDateTime
		)
		ON DUPLICATE KEY UPDATE
			$setString,
			updated = $currentDateTime
SQL;

		$db->setQuery($sql);

		$result = $db->execute();

		return $result;
	}
}
