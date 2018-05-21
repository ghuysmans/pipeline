<?php
abstract class Pipeline {
	protected $parent;
	public function __construct($p) { $this->parent = $p; }

	abstract function describe();
	abstract function evaluate();

	public function __toString() {
		$v = $this->evaluate();
		return is_array($v) ? implode($v, "\n") : (string)$v;
	}

	public function apply($f) {
		$args = func_get_args();
		array_shift($args);
		return new Apply($f, $args, $this);
	}

	public function map($f) {
		//we need this to allow partially applying $f
		$args = func_get_args();
		array_shift($args);
		return new Map($f, $args, $this);
	}

	public function filter($f) {
		//we need this to allow partially applying $f
		$args = func_get_args();
		array_shift($args);
		return new Filter($f, $args, $this);
	}

	public function debug() { return new Cache(0, array(new Debug()), $this); }
	public function cache($p, $exp=0) { return new Cache($exp, $p, $this); }
	public function once() { return new Cache(0, array(new Once()), $this); }
}

class Wrapper extends Pipeline {
	private $constant;

	public function __construct($c) {
		parent::__construct(null);
		$this->constant = $c;
	}

	public function describe() {
		return 'wrap(' . var_export($this->constant, true) . ')';
	}

	public function evaluate() {
		return $this->constant;
	}
}

function wrap($c) {
	return new Wrapper($c);
}

class Sql extends Pipeline {
	public static $pdo;
	private $sql, $params;

	public function __construct($sql) {
		parent::__construct(null);
		$this->sql = $sql;
		$args = func_get_args();
		array_shift($args); //discard $sql
		$this->params = $args;
	}

	public function describe() {
		if (empty($this->params))
			return 'sql(' . var_export($this->sql, true) . ')';
		else
			return 'sql(' . var_export($this->sql, true) . ', ' .
				var_export($this->params, true) . ')';
	}

	public function evaluate() {
		if (isset(self::$pdo)) {
			$stm = self::$pdo->prepare($this->sql);
			if ($stm->execute($this->params))
				return $stm->fetchAll(PDO::FETCH_OBJ);
			else
				trigger_error(htmlentities($this->sql), E_USER_WARNING);
		}
		else
			//FIXME
			return array(array("a"=>1, "b"=>2), array("a"=>4, "b"=>5));
	}
}

class Apply extends Pipeline {
	protected $args;
	protected $f;

	public function __construct($f, $a, $p) {
		if (!is_string($f))
			throw new InvalidArgumentException("expected a function name");
		else {
			parent::__construct($p);
			$this->f = $f;
			$this->args = $a;
		}
	}

	protected function describeArgs() {
		return '(' . implode(', ', array_map(
			function($x) {return var_export($x, true);},
			array_merge(array($this->f), $this->args))) . ')';
	}

	public function describe() {
		return $this->parent->describe() . '->apply' . $this->describeArgs();
	}

	public function evaluate() {
		$args = array_merge($this->args, array($this->parent->evaluate()));
		return call_user_func_array($this->f, $args);
	}
}

class Map extends Apply {
	public function __construct($f, $a, $p) {
		parent::__construct($f, $a, $p);
	}

	public function describe() {
		return $this->parent->describe() . '->map' . $this->describeArgs();
	}

	public function evaluate() {
		return array_map(function($x) {
			$args = array_merge($this->args, array($x));
			return call_user_func_array($this->f, $args);
		}, $this->parent->evaluate());
	}
}

//FIXME extend something more general than Apply
class Filter extends Apply {
	public function __construct($f, $a, $p) {
		parent::__construct($f, $a, $p);
	}

	public function describe() {
		return $this->parent->describe() . '->filter' . $this->describeArgs();
	}

	public function evaluate() {
		return array_filter($this->parent->evaluate(), function($x) {
			$args = array_merge($this->args, array($x));
			return call_user_func_array($this->f, $args);
		});
	}
}


interface CacheProvider {
	public function get($key);
	public function set($key, $value, $exp);
}

class Once implements CacheProvider {
	private $cache = false; //"not in cache"

	public function get($key) {
		return $this->cache;
	}

	public function set($key, $value, $exp) {
		$this->cache = $value;
	}
}

/**
 * Fake provider to allow tracing cache accesses
 */
class Debug implements CacheProvider {
	public static $enabled = false;

	public function get($key) {
		return false;
	}

	public function set($key, $value, $exp) {
		//TODO append to a static array
		if (self::$enabled)
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
