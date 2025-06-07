<?php

declare(strict_types=1);

namespace Bladestan\NodeAnalyzer;

use Illuminate\Events\Dispatcher;
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
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

final class ViewComposerAnalyzer
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
        private readonly DerivativeContainerFactory $derivativeContainerFactory
    ) {
    }

    public function analyzeFor(string $viewName): Type
    {
        $viewFactory = resolve(Factory::class);
        $eventDispatcher = $viewFactory->getDispatcher();

        if (! $eventDispatcher instanceof Dispatcher) {
            return new NeverType();
        }

        try {
            /** @var array<string, list<callable>> $wildcards */
            $wildcards = (new \ReflectionProperty($eventDispatcher, 'wildcards'))->getValue($eventDispatcher);
            /** @var array<string, list<callable>> $listeners */
            $listeners = (new \ReflectionProperty($eventDispatcher, 'listeners'))->getValue($eventDispatcher);
        } catch (\ReflectionException $e) {
            return new NeverType();
        }

        $aggregateType = new NeverType();

        foreach ($listeners as $eventName => $items) {
            if (! str_starts_with($eventName, 'composing: ')) {
                continue;
            }

            $type = $this->processListeners(substr($eventName, strlen('composing: ')), $items);
            $aggregateType = TypeCombinator::union($type, $aggregateType);
        }

        foreach ($wildcards as $eventName => $items) {
            if (! str_starts_with($eventName, 'composing: ')) {
                continue;
            }

            $type = $this->processListeners(substr($eventName, strlen('composing: ')), $items);
            $aggregateType = TypeCombinator::union($type, $aggregateType);
        }

        return self::flatten($aggregateType);
    }

    /**
     * @param list<callable> $listeners
     */
    private function processListeners(string $pattern, array $listeners): Type
    {
        /** @var list<array{class-string, string}> $viewComposerClasses */
        $viewComposerClasses = [];

        foreach ($listeners as $listener) {
            if ($listener instanceof \Closure) {
                $r = new \ReflectionFunction($listener);
                $vars = $r->getClosureUsedVariables();

                if (! empty($vars['callback']) && $vars['callback'] instanceof \Closure) {
                    $r = new \ReflectionFunction($vars['callback']);
                    $vars = $r->getClosureUsedVariables();
                }

                if (! empty($vars['class']) && ! empty($vars['method'])) {
                    ['class' => $class, 'method' => $method] = $vars;

                    if (is_string($class) && is_string($method) && method_exists($class, $method)) {
                        /** @var class-string $class */
                        $viewComposerClasses[] = [$class, $method];
                    }
                }
            }
        }

        /** @phpstan-ignore-next-line phpstanApi.method */
        $container = $this->derivativeContainerFactory->create(
            [__DIR__ . '/../../config/template-compiler/view-composer.neon'],
        );

        /** @phpstan-ignore phpstanApi.classConstant */
        $fileAnalyzer = $container->getByType(FileAnalyser::class);

        $finalRule = new /**
         * @implements Rule<FuncCall>
         */
        class() implements Rule {
            public ?Type $type = null;

            public function getNodeType(): string
            {
                return FuncCall::class;
            }

            public function processNode(Node $node, Scope $scope): array
            {
                if (! ($node->name instanceof Node\Name)) {
                    return [];
                }

                if ($node->name->name !== ViewComposerHackeyHackeyVisitor::FAKE_FUNC_NAME) {
                    return [];
                }

                $this->type = $scope->getType($node->getArgs()[0]->value);

                return [];
            }
        };

        $aggregateType = new NeverType();

        foreach ($viewComposerClasses as [$class, $method]) {
            $reflection = $this->reflectionProvider->getClass($class);

            if ($reflection->getFileName() === null) {
                continue;
            }

            /** @phpstan-ignore-next-line phpstanApi.method */
            $fileAnalyzer->analyseFile(
                $reflection->getFileName(),
                [],
                /** @phpstan-ignore-next-line phpstanApi.constructor */
                new DirectRegistry([]),
                /** @phpstan-ignore-next-line phpstanApi.constructor */
                new Registry([$finalRule]),
                static function () {}
            );

            $type = $finalRule->type;

            if ($type) {
                $aggregateType = TypeCombinator::union($aggregateType, $type);
            }
        }

        return $aggregateType;
    }

    private static function flatten(Type $unionType): Type
    {
        if (! ($unionType instanceof UnionType)) {
            return $unionType;
        }

        $builder = ConstantArrayTypeBuilder::createEmpty();

        foreach ($unionType->getTypes() as $type) {
            $constantArrayType = $type->getConstantArrays();

            if (count($constantArrayType) !== 1) {
                continue;
            }

            $constantArrayType = $constantArrayType[0];

            $c = count($constantArrayType->getKeyTypes());

            for ($i = 0; $i < $c; $i++) {
                $keyType = $constantArrayType->getKeyTypes()[$i];
                $valueType = $constantArrayType->getValueTypes()[$i];

                $builder->setOffsetValueType($keyType, $valueType, true);
            }
        }

        return $builder->getArray();
    }
}
