<?php

try {
    $path = \Composer\InstalledVersions::getInstallPath('phpstan/phpstan');
    $fileAnalyzerResultPath = 'phar://' . $path . '/phpstan.phar/src/Analyser/FileAnalyserResult.php';

    $tmpFile = tempnam(sys_get_temp_dir(), '');
    register_shutdown_function(function () use ($tmpFile) {
        unlink($tmpFile);
    });

    function super_duper_unsafe_hack_function(\PHPStan\Analyser\FileAnalyserResult $result, $collectedData)
    {
        $gottenData = \Bladestan\Rules\BladeRule::$collectedData;
        \Bladestan\Rules\BladeRule::$collectedData = [];

        foreach ($gottenData as $item) {
            $collectedData = array_merge($collectedData, $item);
        }

        return $collectedData;
    }

    $parser = (new \PhpParser\ParserFactory())->createForHostVersion();
    $stmts = $parser->parse(file_get_contents($fileAnalyzerResultPath));
    $traverser = new \PhpParser\NodeTraverser();
    $prettyPrinter = new \PhpParser\PrettyPrinter\Standard();
    $traverser->addVisitor(new class implements \PhpParser\NodeVisitor {
        public function beforeTraverse(array $nodes)
        {
        }

        public function enterNode(PhpParser\Node $node)
        {
        }

        public function afterTraverse(array $nodes)
        {
        }

        public function leaveNode(PhpParser\Node $node)
        {
            if ($node instanceof \PhpParser\Node\Stmt\ClassMethod) {
                if ($node->name->name === 'getCollectedData') {
                    $inner = $node->stmts[0]->expr;
                    $node->stmts[0]->expr = new \PhpParser\Node\Expr\FuncCall(
                        new \PhpParser\Node\Name\FullyQualified('super_duper_unsafe_hack_function'),
                        [new \PhpParser\Node\Expr\Variable('this'), new \PhpParser\Node\Arg($inner)]
                    );
                }
            }
        }
    });
    $traverser->traverse($stmts);
    file_put_contents($tmpFile, $prettyPrinter->prettyPrintFile($stmts));
    require $tmpFile;
} catch (\Throwable $exception) {
    error_log($exception);
}
