<?php
require_once('config.dev.php');
require_once('Pipeline.php');

try {
	$db = new PDO("mysql:host=$dbServer;port=3306;dbname=$dbName;",
		$dbUser, $dbPasswd,
		array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
	Sql::$pdo = $db;
}
catch (Exception $e) {
	//FIXME
	die($e->getMessage());
}
