<?php
require_once('lib.php');

//FIXME allow testing with and without Memcached
class Memcached {
	public function get($k) {
	}
	public function set($k, $v, $e) {
	}
}


function f($i, $x) {
	return $x->$i;
}

function g($x) {
	return $x+10;
}

function render($row) {
	return "{$row->fn} <i>{$row->mime}</i>";
}

function flt($row) {
	return $row->id_user == 42;
}


Debug::$html = true;
Debug::$enabled = true;
Cache::$trace = true;
Cache::$memcached = new Memcached();

$n = new Sql('SELECT 1 a, 2 b UNION ALL SELECT 4, 5');
$n->debug()->map('f', 'b')->cache(10) . "\n";
$o = new Sql('SELECT * FROM upload WHERE id_upload<?', 13);
echo $o->filter('flt')->apply('array_map', 'render')->cache(10) . "\n";
//echo wrap(9)->apply(function($x){})->cache() . "\n";
