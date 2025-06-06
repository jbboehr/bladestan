<?php

declare(strict_types=1);

namespace Bladestan\Tests\Rules;

use Bladestan\Rules\BladeRule;
use Bladestan\Rules\IncludedViewCollector;
use Bladestan\Rules\UnusedViewRule;
use Illuminate\Support\Collection;
use PhpParser\Node;
use PhpParser\NodeAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @extends RuleTestCase<Rule<NodeAbstract>>
 */
final class UnusedViewRuleTest extends RuleTestCase
{
    public function testUnusedViews(): void
    {
        // Apparently the container isn't reset between tests, need to flush the queue
        self::getContainer()
            ->getByType(IncludedViewCollector::class)
            ->flush();

        $extensions = ['blade.php', 'php', 'css', 'html'];

        $finder = Finder::create()
            ->files()
            ->in(__DIR__ . '/../skeleton/resources/views')
            ->filter(static function (SplFileInfo $file): bool {
                return match ($file->getRelativePathname()) {
                    'included_view.blade.php',
                    'include_with_parameters.blade.php',
                    'bar.blade.php',
                    'file_with_include.blade.php' => false,
                    default => true,
                };
            });

        /** @var list<array{string, int}> $expectedErrors */
        $expectedErrors = Collection::make($finder)
            ->map(static function (SplFileInfo $file) use ($extensions): string {
                return UnusedViewRule::guessViewName($file->getRelativePathname(), $extensions) ?? 'ERR';
            })
            ->sort()
            ->map(self::mapNameToError(...))
            ->values()
            ->toArray();

        $this->analyse([
            __DIR__ . '/Fixture/file-with-include.php',
            __DIR__ . '/Fixture/include_with_parameters.php',
        ], $expectedErrors);
    }

    public function getCollectors(): array
    {
        return array_merge(parent::getCollectors(), [
            self::getContainer()->getByType(IncludedViewCollector::class),
        ]);
    }

    /**
     * @return list<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/config/configured_extension.neon'];
    }

    /**
     * @return Rule<NodeAbstract>
     */
    protected function getRule(): Rule
    {
        $bladeRule = self::getContainer()->getByType(BladeRule::class);
        $rule = self::getContainer()->getByType(UnusedViewRule::class);

        return new /** @implements Rule<NodeAbstract> */ class($bladeRule, $rule) implements Rule {
            public function __construct(
                private readonly BladeRule $bladeRule,
                private readonly UnusedViewRule $unusedViewRule,
            ) {
            }

            public function getNodeType(): string
            {
                return NodeAbstract::class;
            }

            public function processNode(Node $node, Scope $scope): array
            {
                /* @phpstan-ignore-next-line phpstanApi.runtimeReflection */
                if (is_a($node, $this->bladeRule->getNodeType(), true)) {
                    $errors = $this->bladeRule->processNode($node, $scope);
                    // discard $errors
                    return [];

                }

                /* @phpstan-ignore-next-line phpstanApi.runtimeReflection */
                if (is_a($node, $this->unusedViewRule->getNodeType(), true)) {
                    return $this->unusedViewRule->processNode($node, $scope);
                }

                return [];
            }
        };
    }

    /**
     * @return array{string, int}
     */
    private static function mapNameToError(string $name)
    {
        return ['Unused view: ' . $name, -1];
    }
}
