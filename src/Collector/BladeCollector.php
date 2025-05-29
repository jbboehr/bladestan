<?php

declare(strict_types=1);

namespace Bladestan\Collector;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\CollectedData;
use PHPStan\Collectors\Collector;

/**
 * @implements Collector<CallLike, array<mixed>>
 * @phpstan-import-type CollectorData from CollectedData
 */
final class BladeCollector implements Collector
{
    /** @var list<array<mixed>> */
    private array $collectedData = [];

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        if (count($this->collectedData) <= 0) {
            return null;
        }

        $collectedData = $this->collectedData;
        $this->collectedData = [];
        return $collectedData;
    }

    /**
     * @phpstan-param CollectorData $collectedData
     */
    public function pushCollectedData(array $collectedData): void
    {
        $this->collectedData[] = $collectedData;
    }
}
