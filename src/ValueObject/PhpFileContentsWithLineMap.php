<?php

declare(strict_types=1);

namespace Bladestan\ValueObject;

/** @see \Bladestan\TemplateCompiler\ValueObject\PhpFileContentsWithLineMap */
final class PhpFileContentsWithLineMap
{
    /**
     * @param array<int, array<string, int>> $phpToTemplateLines
     * @param list<string> $includedViewNames
     */
    public function __construct(
        public readonly string $phpFileContents,
        public readonly array $phpToTemplateLines,
        /** @var list<array<int, string>> */
        public array $errors,
        public readonly array $includedViewNames = [],
    ) {
    }
}
