<?php
//fatal but it gives a stack trace...
class Opaque_callable extends Exception {
	public function __construct() {
		parent::__construct("expected a function name");
	}
}

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
		if (!isset($f))
			;
		elseif (!is_string($f))
			throw new Opaque_callable();
		else {
			$args = func_get_args();
			array_shift($args);
			return new Apply($f, $args, $this);
		}
	}

	public function map($f) {
		if (!isset($f))
			;
		elseif (!is_string($f))
			throw new Opaque_callable();
		else {
			//we need this to allow partially applying $f
			$args = func_get_args();
			array_shift($args);
			return new Map($f, $args, $this);
		}
	}

	public function filter($f) {
		if (!isset($f))
			;
		elseif (!is_string($f))
			throw new Opaque_callable();
		else {
			//we need this to allow partially applying $f
			$args = func_get_args();
			array_shift($args);
			return new Filter($f, $args, $this);
		}
	}

	public function debug() { return new Debug($this); }
	public function cache($exp=null) { return new Cache($exp, $this); }
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
		parent::__construct($p);
		$this->f = $f;
		$this->args = $a;
	}

	protected function describe_args() {
		return '(' . implode(', ', array_map(
			function($x) {return var_export($x, true);},
			array_merge(array($this->f), $this->args))) . ')';
	}

	public function describe() {
		return $this->parent->describe() . '->apply' . $this->describe_args();
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
		return $this->parent->describe() . '->map' . $this->describe_args();
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
		return $this->parent->describe() . '->filter' . $this->describe_args();
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

class Debug extends Pipeline {
	public static $enabled;
	public static $html;

	public function __construct($p) {
		parent::__construct($p);
	}

	public function describe() {
		//don't print debug(), it'd influence caching
		return $this->parent->describe();
	}

	public function evaluate() {
		$v = $this->parent->evaluate();
		if (self::$enabled) {
			if (self::$html)
				echo "<pre>";
			echo $this->parent->describe() . ': ';
			print_r($v);
			if (self::$html)
				echo "</pre>\n";
		}
		return $v;
	}
}
