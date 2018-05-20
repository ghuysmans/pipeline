<?php
require_once('lib.php');
require_once('Cache.php');
session_start();


function f($i, $x) {
	return $x->$i;
}

function g($x) {
	return $x+10;
}

function render($row) {
	sleep(2);
	return "{$row->id_upload}: {$row->fn} <i>{$row->mime}</i>";
}

function flt($row) {
	return $row->id_user == 42;
}


Debug::$html = true;
Debug::$enabled = true;
Cache::$providers = array(new Session_cache("cache"));

$n = new Sql('SELECT 1 a, 2 b UNION ALL SELECT 4, 5');
$n->debug()->map('f', 'b')->cache(10) . "\n";
$o = new Sql('SELECT * FROM upload WHERE id_upload<?', 13);
echo $o->filter('flt')->apply('array_map', 'render')->cache(10) . "\n";
//echo wrap(9)->apply(function($x){})->cache() . "\n";
