<?php

declare(strict_types=1);

namespace Bladestan\Collector;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * @implements Collector<Node\Stmt\Trait_, array{string, int}>
 */
class BladeCollector implements Collector
{
    private static \WeakMap $lut;

    private static array $queue = [];

    public static function pushCollectedData(Node $node, array $collectedData) : void
    {
//        if (!isset(self::$lut)) {
//            self::$lut = new \WeakMap();
//        }
//        self::$lut[$node] = $collectedData;
    }

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope)
    {
//        if (!isset(self::$lut) || !isset(self::$lut[$node])) {
//            return null;
//        }
//
//        return self::$lut[$node];
    }
}
