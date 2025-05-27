<?php

declare(strict_types=1);

namespace Bladestan\Rules;

use Bladestan\ErrorReporting\Blade\TemplateErrorsFactory;
use Bladestan\NodeAnalyzer\BladeViewMethodsMatcher;
use Bladestan\NodeAnalyzer\LaravelViewFunctionMatcher;
use Bladestan\NodeAnalyzer\MailablesContentMatcher;
use Bladestan\ViewRuleHelper;
use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<CallLike>
 * @see \Bladestan\Tests\Rules\BladeRuleTest
 */
final class BladeRule implements Rule
{
    public static array $collectedData = [];

    public function __construct(
        private readonly BladeViewMethodsMatcher $bladeViewMethodsMatcher,
        private readonly LaravelViewFunctionMatcher $laravelViewFunctionMatcher,
        private readonly MailablesContentMatcher $mailablesContentMatcher,
        private readonly ViewRuleHelper $viewRuleHelper,
        private readonly TemplateErrorsFactory $templateErrorsFactory,
    ) {
    }

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $renderTemplatesWithParameters = match (true) {
            $node instanceof StaticCall,
            $node instanceof FuncCall => $this->laravelViewFunctionMatcher->match($node, $scope),
            $node instanceof MethodCall => $this->bladeViewMethodsMatcher->match($node, $scope),
            $node instanceof New_ => $this->mailablesContentMatcher->match($node, $scope),
            default => [],
        };

        $errors = [];
        foreach ($renderTemplatesWithParameters as $renderTemplateWithParameter) {
            try {
                [$newErrors, $collectedData] = $this->viewRuleHelper->processNode($node, $scope, $renderTemplateWithParameter);
                $errors = array_merge($errors, $newErrors);
                self::$collectedData[] = $collectedData;
                // BladeCollector::pushCollectedData($node, $collectedData);
            } catch (InvalidArgumentException $invalidArgumentException) {
                $errors[] = $this->templateErrorsFactory->createError(
                    $invalidArgumentException->getMessage(),
                    'bladestan.missing',
                    $node->getLine(),
                    $scope->getFile()
                );
            }
        }

        return $errors;
    }
}
