<?php

declare(strict_types=1);

namespace Bladestan\NodeAnalyzer;

use Bladestan\TemplateCompiler\TypeAnalyzer\TemplateVariableTypesResolver;
use Illuminate\View\Factory;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PHPStan\Analyser\FileAnalyser;
use PHPStan\Analyser\Scope;
use PHPStan\Type\Type;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ViewComposerAnalyzer
{
    public function __construct(
        FileAnalyser $analyser,
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

        foreach ($listeners as $eventName => $listeners) {
            if (!str_starts_with($eventName, 'composing: ')) {
                continue;
            }

            $this->processListeners(substr($eventName, strlen('composing: ')), $listeners);
        }

        foreach ($wildcards as $eventName => $listeners) {
            if (!str_starts_with($eventName, 'composing: ')) {
                continue;
            }

            $this->processListeners(substr($eventName, strlen('composing: ')), $listeners);
        }
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
                if (!empty($vars['class']) && !empty($vars['method'])) {
                    $viewComposerClasses[] = [$vars['class'], $vars['method']];
                }
            }
        }

        dump($viewComposerClasses);
    }
}
