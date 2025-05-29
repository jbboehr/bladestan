<?php

declare(strict_types=1);

namespace Bladestan;

use Bladestan\Compiler\BladeToPHPCompiler;
use Bladestan\ErrorReporting\Blade\TemplateErrorsFactory;
use Bladestan\NodeAnalyzer\TemplateFilePathResolver;
use Bladestan\TemplateCompiler\ErrorFilter;
use Bladestan\TemplateCompiler\PHPStan\FileAnalyserProvider;
use Bladestan\TemplateCompiler\ValueObject\RenderTemplateWithParameters;
use Bladestan\ValueObject\CompiledTemplate;
use InvalidArgumentException;
use PhpParser\Node\Expr\CallLike;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Registry;
use PHPStan\Rules\IdentifierRuleError;

final class ViewRuleHelper
{
    public function __construct(
        private readonly FileAnalyserProvider $fileAnalyserProvider,
        private readonly TemplateErrorsFactory $templateErrorsFactory,
        private readonly BladeToPHPCompiler $bladeToPhpCompiler,
        private readonly TemplateFilePathResolver $templateFilePathResolver,
        private readonly ErrorFilter $errorFilter,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @return list<IdentifierRuleError>
     */
    public function processNode(
        CallLike $callLike,
        Scope $scope,
        RenderTemplateWithParameters $renderTemplateWithParameters
    ): array {
        $compiledTemplate = $this->compileToPhp(
            $renderTemplateWithParameters,
            $scope->getFile(),
            $callLike->getLine()
        );

        if (! $compiledTemplate instanceof CompiledTemplate) {
            return [];
        }

        return $this->processTemplateFilePath($compiledTemplate);
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function processTemplateFilePath(CompiledTemplate $compiledTemplate): array
    {
        $fileAnalyser = $this->fileAnalyserProvider->provide();
        $templateRulesRegistry = $this->fileAnalyserProvider->getRules();
        $collectorsRegistry = $this->fileAnalyserProvider->getCollectorRegistry();

        /** @phpstan-ignore phpstanApi.method */
        $fileAnalyserResult = $fileAnalyser->analyseFile(
            $compiledTemplate->phpFilePath,
            [],
            $templateRulesRegistry,
            $collectorsRegistry,
            null
        );

        /** @phpstan-ignore phpstanApi.method */
        $ruleErrors = $fileAnalyserResult->getErrors();

        $usefulRuleErrors = $this->errorFilter->filterErrors($ruleErrors);

        return $this->templateErrorsFactory->createErrors(
            $usefulRuleErrors,
            $compiledTemplate->phpLine,
            $compiledTemplate->bladeFilePath,
            $compiledTemplate->phpFileContentsWithLineMap,
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    private function compileToPhp(
        RenderTemplateWithParameters $renderTemplateWithParameters,
        string $filePath,
        int $phpLine
    ): ?CompiledTemplate {
        $resolvedTemplateFilePath = $this->templateFilePathResolver->resolveExistingFilePath(
            $renderTemplateWithParameters->templateName
        );
        $fileContents = file_get_contents($resolvedTemplateFilePath);
        if ($fileContents === false) {
            return null;
        }

        $phpFileContentsWithLineMap = $this->bladeToPhpCompiler->compileContent(
            $resolvedTemplateFilePath,
            $renderTemplateWithParameters->templateName,
            $fileContents,
            $renderTemplateWithParameters->parametersArray
        );

        $phpFileContents = $phpFileContentsWithLineMap->phpFileContents;

        $tmpFilePath = sys_get_temp_dir() . '/' . md5($filePath) . '-blade-compiled.php';
        file_put_contents($tmpFilePath, $phpFileContents);

        return new CompiledTemplate($filePath, $tmpFilePath, $phpFileContentsWithLineMap, $phpLine);
    }
}
