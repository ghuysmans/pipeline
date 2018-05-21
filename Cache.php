<?php
require_once('Pipeline.php');

/**
 * Proxy class, because Memcached doesn't like our strange keys
 */
class Md5CacheProxy implements CacheProvider {
	private $real;

	public function __construct($r) {
		$this->real = $r;
	}

	public function get($key) {
		return $this->real->get(md5($key));
	}

	public function set($key, $value, $exp) {
		return $this->real->set(md5($key), $value, $exp);
	}
}

/**
 * Filesystem cache (with future mtime)
 */
class FileCache implements CacheProvider {
	private $root;

	public function __construct($r) {
		$this->root = $r;
	}

	private function fn($key) {
		return $this->root . '/' . md5($key);
	}

	public function get($key) {
		$fn = $this->fn($key);
		if (($s=@stat($fn)) && (!$s['mtime'] || time()<=$s['mtime']))
			return eval('return ' . file_get_contents($fn) . ';');
		else
			return false;
	}

	public function set($key, $value, $exp) {
		$fn = $this->fn($key);
		file_put_contents($fn, var_export($value, true));
		$exp = $exp || time()+$exp;
		touch($fn, $exp);
	}
}

/**
 * Old-style in-database cache
 */
class DatabaseCache implements CacheProvider {
	private $pdo;

	public function __construct($db) {
		$this->pdo = $db;
	}

	public function get($key) {
		$stm = $this->pdo->prepare(
			'SELECT v FROM cache WHERE k=? AND (e IS NULL OR NOW()<=e)');
		$stm->execute(array($key));
		if ($stm->rowCount())
			return eval('return ' . $stm->fetch(PDO::FETCH_NUM)[0] . ';');
		else
			return false;
	}

	public function set($key, $value, $exp) {
		$stm = $this->pdo->prepare(
			'REPLACE INTO cache(k, v, e) VALUES (?, ?, ' .
			'DATE_ADD(NOW(), INTERVAL ? SECOND))');
		$stm->execute(array($key, var_export($value, true), $exp ? $exp : null));
	}
}

/**
 * Flaky session-based implementation (no garbage collection)
 */
class SessionCache implements CacheProvider {
	public function get($key) {
		$exp = "$key.exp";
		if (isset($_SESSION[$key]) &&
				(!isset($_SESSION[$exp]) || time()<=$_SESSION[$exp]))
			return $_SESSION[$key];
		else
			return false;
	}

	public function set($key, $value, $exp) {
		$_SESSION["$key"] = $value;
		if ($exp)
			$_SESSION["$key.exp"] = time() + $exp;
	}
}
