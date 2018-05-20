<?php
require_once('Pipeline.php');

interface CacheProvider {
	public function get($key);
	public function set($key, $value, $exp);
}

/**
 * Old-style in-database cache
 */
class Database_cache implements CacheProvider {
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
		$stm->execute(array($key, var_export($value, true), $exp));
	}
}

/**
 * Flaky session-based implementation (no garbage collection)
 */
class Session_cache implements CacheProvider {
	public function get($key) {
		$exp = "$key.exp";
		if (isset($_SESSION[$key]) &&
				(!isset($_SESSION[$exp]) || time()<=$_SESSION[$exp]))
			return eval("return {$_SESSION[$key]};");
		else
			return false;
	}

	public function set($key, $value, $exp) {
		$_SESSION["$key"] = var_export($value, true);
		if (!is_null($exp))
			$_SESSION["$key.exp"] = time() + $exp;
	}
}

/**
 * Fake provider to allow tracing cache accesses
 */
class Trace implements CacheProvider {
	public function get($key) {
		return false;
	}

	public function set($key, $value, $exp) {
		//TODO append to a static array
		trigger_error(
			htmlentities("$key <- ".var_export($value, true)),
			E_USER_NOTICE);
	}
}


class Cache extends Pipeline {
	private $expiration;
	private $providers;

	public function __construct($exp, $prov, $p) {
		if (!is_array($prov))
			throw new InvalidArgumentException("providers must be an array");
		parent::__construct($p);
		$this->expiration = $exp;
		$this->providers = $prov;
	}

	public function describe() {
		//don't print cache(), it'd influence caching
		return $this->parent->describe();
	}

	private function getFirst($key) {
		foreach ($this->providers as $x)
			if ($cached = $x->get($key))
				return $cached;
		return false;
	}

	private function setAll($key, $value, $exp) {
		foreach ($this->providers as $x)
			$x->set($key, $value, $exp);
	}

	public function evaluate() {
		$key = $this->parent->describe();
		if ($cached = $this->getFirst($key))
			return $cached;
		else {
			$value = $this->parent->evaluate();
			$this->setAll($key, $value, $this->expiration);
			return $value;
		}
	}
}
