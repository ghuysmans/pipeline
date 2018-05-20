<?php
require_once('Pipeline.php');

interface CacheProvider {
	public function get($key);
	public function set($key, $value, $exp);
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
		if (!empty($exp))
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
	public static $providers;

	public function __construct($exp, $p) {
		parent::__construct($p);
		$this->expiration = $exp;
	}

	public function describe() {
		//don't print cache(), it'd influence caching
		return $this->parent->describe(); // . "->cache($this->expiration)";
	}

	private function getFirst($key) {
		if (!empty(self::$providers))
			foreach (self::$providers as $x)
				if ($cached = $x->get($key))
					return $cached;
		return false;
	}

	private function setAll($key, $value, $exp) {
		if (!empty(self::$providers))
			foreach (self::$providers as $x)
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
