<?php

declare(strict_types=1);

namespace Bladestan\ValueObject;

use PHPStan\Type\Type;

final class CompiledTemplate
{
    public function __construct(
        public readonly string $bladeFilePath,
        public readonly string $phpFilePath,
        public readonly PhpFileContentsWithLineMap $phpFileContentsWithLineMap,
        public readonly int $phpLine,
        public readonly Type $viewComposerExtraType,
    ) {
    }
}
