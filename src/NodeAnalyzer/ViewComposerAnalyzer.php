<?php

declare(strict_types=1);

namespace Bladestan\NodeAnalyzer;

use Illuminate\View\Factory;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\FileAnalyser;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Registry;
use PHPStan\DependencyInjection\DerivativeContainerFactory;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\DirectRegistry;
use PHPStan\Rules\Rule;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;

final class ViewComposerAnalyzer
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly DerivativeContainerFactory $derivativeContainerFactory
    ) {
    }

    public function analyzeFor(string $viewName)
    {
        $viewFactory = resolve(Factory::class);
        $eventDispatcher = $viewFactory->getDispatcher();

        try {
            $wildcards = (new \ReflectionProperty($eventDispatcher, 'wildcards'))->getValue($eventDispatcher);
            $listeners = (new \ReflectionProperty($eventDispatcher, 'listeners'))->getValue($eventDispatcher);
        } catch (\ReflectionException $e) {
            return;
        }

        $aggregateType = new NeverType();

        foreach ($listeners as $eventName => $items) {
            if (!str_starts_with($eventName, 'composing: ')) {
                continue;
            }

            $type = $this->processListeners(substr($eventName, strlen('composing: ')), $items);
            $aggregateType = TypeCombinator::union($type, $aggregateType);
        }

        foreach ($wildcards as $eventName => $items) {
            if (!str_starts_with($eventName, 'composing: ')) {
                continue;
            }

            $type = $this->processListeners(substr($eventName, strlen('composing: ')), $items);
            $aggregateType = TypeCombinator::union($type, $aggregateType);
        }

        // FUCK Y'ALL ALL Y'ALL
        dump($aggregateType->describe(VerbosityLevel::precise()));
    }

    /**
     * @param list<callable> $listeners
     */
    private function processListeners(string $pattern, array $listeners)
    {
        $viewComposerClasses = [];

        foreach ($listeners as $listener) {
            if ($listener instanceof \Closure) {
                $r = new \ReflectionFunction($listener);
                $vars = $r->getClosureUsedVariables();

                if (!empty($vars['callback'])) {
                    $r = new \ReflectionFunction($vars['callback']);
                    $vars = $r->getClosureUsedVariables();
                }

                if (!empty($vars['class']) && !empty($vars['method'])) {
                    $viewComposerClasses[] = [$vars['class'], $vars['method']];
                }
            }
        }

        $container = $this->derivativeContainerFactory->create(
            [__DIR__ . '/../../config/template-compiler/view-composer.neon'],
        );

        /** @phpstan-ignore phpstanApi.classConstant */
        $fileAnalyzer = $container->getByType(FileAnalyser::class);

        $finalRule = new class implements Rule
        {
            public ?Type $type = null;

            public function getNodeType(): string
            {
                return FuncCall::class;
            }

            public function processNode(Node $node, Scope $scope): array
            {
                if ($node->name->name !== ViewComposerHackeyHackeyVisitor::FAKE_FUNC_NAME) {
                    return [];
                };

                $this->type = $scope->getType($node->args[0]->value);

                return [];
            }
        };

        $aggregateType = new NeverType();

        foreach ($viewComposerClasses as [$class, $method]) {
            $reflection = $this->reflectionProvider->getClass($class);

            $fileAnalyzer->analyseFile(
                $reflection->getFileName(),
                [],
                new DirectRegistry([]),
                new Registry([
                   $finalRule,
                ]),
                static function () {}
            );

            $type = $finalRule->type;

            if ($type) {
                $aggregateType = TypeCombinator::union($aggregateType, $type);
            }
        }

        return $aggregateType;
    }
}
