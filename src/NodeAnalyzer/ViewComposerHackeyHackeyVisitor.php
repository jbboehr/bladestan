<?php

declare(strict_types=1);

namespace Bladestan\NodeAnalyzer;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Node\ClassMethod;

class ViewComposerHackeyHackeyVisitor extends NodeVisitorAbstract
{
    public const FAKE_VAR_NAME = 'supercalifragilisticexpialidocious';

    public const FAKE_FUNC_NAME =  self::FAKE_VAR_NAME . '_func';

    public function leaveNode(Node $node) {
        if ($node instanceof Node\Stmt\ClassMethod) {
            if ($node->name->name === 'compose') {
                $node->stmts[] = new Node\Stmt\Expression(
                    new Node\Expr\FuncCall(new Node\Name(self::FAKE_FUNC_NAME), [
                        new Node\Arg(new Node\Expr\Variable(self::FAKE_VAR_NAME)),
                    ])
                );
                return $node;
            }
        }

        if ($node instanceof Node\Expr\MethodCall) {
            if (!($node->name->name === 'with')) {
                return null;
            }

            if (count($node->getArgs()) === 2) {
                return new NOde\Expr\Assign(
                    new Node\Expr\ArrayDimFetch(
                        new Node\Expr\Variable(self::FAKE_VAR_NAME),
                        $node->getArgs()[0]->value,
                    ),
                    $node->getArgs()[1]->value,
                );
            }
        }

        return null;
    }
}
