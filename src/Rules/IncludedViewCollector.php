<?php

declare(strict_types=1);

namespace Bladestan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * @implements Collector<CallLike, list<string>>
 */
final class IncludedViewCollector implements Collector
{
    /**
     * @var list<string>
     */
    private array $queued = [];

    public function getNodeType(): string
    {
        // this should be the same node type as BladeRule so it gets called immediately afterwards in the same process
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        if (count($this->queued) > 0) {
            $queued = $this->queued;
            $this->queued = [];
            return $queued;
        }

        return null;
    }

    /**
     * @param list<string> $includedViewNames
     */
    public function push(array $includedViewNames): void
    {
        $this->queued = array_merge($this->queued, $includedViewNames);
    }

    /**
     * @internal
     */
    public function flush(): void
    {
        $this->queued = [];
    }
}
