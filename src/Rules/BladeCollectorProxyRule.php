<?php

declare(strict_types=1);

namespace Bladestan\Rules;

use Bladestan\Collector\BladeCollector;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Registry as RuleRegistry;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<CollectedDataNode>
 */
final class BladeCollectorProxyRule implements Rule
{
    public function __construct(
        private readonly RuleRegistry $ruleRegistry,
    ) {
    }

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $nodeType = CollectedDataNode::class;
        /** @phpstan-ignore-next-line phpstanApi.constructor */
        $collectedData = new CollectedDataNode($node->get(BladeCollector::class), false);
        $errors = [];

        /** @phpstan-ignore-next-line phpstanApi.method */
        foreach ($this->ruleRegistry->getRules($nodeType) as $rule) {
            if ($rule instanceof self) {
                // don't blow the stack
                continue;
            }

            $errors = array_merge(
                $rule->processNode($collectedData, $scope),
                $errors,
            );
        }

        return $errors;
    }
}
