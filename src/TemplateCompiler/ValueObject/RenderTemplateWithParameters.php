<?php

declare(strict_types=1);

namespace Bladestan\TemplateCompiler\ValueObject;

use PHPStan\Type\Type;

final class RenderTemplateWithParameters
{
    /**
     * @param array<string, Type> $parametersArray
     */
    public function __construct(
        public readonly string $templateName,
        public readonly array $parametersArray
    ) {
    }
}
