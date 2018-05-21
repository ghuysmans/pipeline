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


Debug::$enabled = true;
if (class_exists('Memcached')) {
	$mc = new Memcached();
	$mc->addServer('localhost', 11211);
	$p1 = array(new Md5CacheProxy($mc));
}
else
	$p1 = array(new FileCache('/tmp'));
//$p1 = array(new DatabaseCache($db));
//$p1 = array(new SessionCache("cache"));

$n = new Sql('SELECT 1 a, 2 b UNION ALL SELECT 4, 5');
$n->debug()->map('f', 'b')->cache($p1, 10) . "\n";
$o = new Sql('SELECT * FROM upload WHERE id_upload<?', 13);
echo $o->filter('flt')->apply('array_map', 'render')->cache($p1, 10) . "\n";
$v = wrap(9)->debug()->once();
echo $v;
echo $v;
