<?php

declare(strict_types=1);

namespace Bladestan\ValueObject;

final class CompiledTemplate
{
    /**
     * @param list<string> $includedViewNames
     */
    public function __construct(
        public readonly string $bladeFilePath,
        public readonly string $phpFilePath,
        public readonly PhpFileContentsWithLineMap $phpFileContentsWithLineMap,
        public readonly int $phpLine,
        public readonly array $includedViewNames = [],
    ) {
    }
}
