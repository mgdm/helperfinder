<?php

require(__DIR__ . '/vendor/autoload.php');
ini_set('xdebug.max_nesting_level', 2000);
ini_set('xdebug.var_display_max_depth', '10');

class NodeVisitor extends PHPParser_NodeVisitorAbstract {

	protected $path;

	public function __construct($path) {
		$this->path = $path;
	}

	public function leaveNode(PHPParser_Node $node) {
		if ($node instanceof PHPParser_Node_Expr_FuncCall) {
			$parts = $node->name->parts;
			if (count($parts) > 1) return;

			$function = $parts[0];
			if (function_exists($function)) return;

			printf("Found unknown function %s() at %s:%s\n", $function, $this->path, $node->getLine());
		}
	}
}

function parseForHelpers($path) {
	$code = file_get_contents($path);
	$parser = new PHPParser_Parser(new PHPParser_Lexer());
	$traverser = new PHPParser_NodeTraverser();
//	$traverser->addVisitor(new PHPParser_NodeVisitor_NameResolver());
	$traverser->addVisitor(new NodeVisitor($path));

	try {
		$stmts = $parser->parse($code);
		$stmts = $traverser->traverse($stmts);
	} catch (PHPParser_Error $e) {
		fprintf(STDERR, "Parse error in file %s: %s\n", $path, $e->getMessage());
	}
}

if ($_SERVER['argc'] < 2) {
	die("Need a path to check\n");
}

$path = $_SERVER['argv'][1];

if (is_file($path)) {
	parseForHelpers($path);
}

if (is_dir($path)) {
	$iterator = new RecursiveDirectoryIterator($path, FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::SKIP_DOTS);
	$iterator = new RecursiveIteratorIterator($iterator);
	$iterator = new RegexIterator($iterator, '/\.php$/');

	foreach ($iterator as $item) {
		parseForHelpers($item->getPathName());
	}
}
