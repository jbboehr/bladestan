<?php

declare(strict_types=1);

namespace Bladestan\Rules;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Collection;
use Illuminate\View\FileViewFinder;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

/**
 * @implements Rule<CollectedDataNode>
 */
final class UnusedViewRule implements Rule
{
    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $data = $node->get(IncludedViewCollector::class);
        $finder = resolve(ViewFactory::class)->getFinder();

        $allIncludedViews = [];

        foreach ($data as $perFileData) {
            foreach ($perFileData as $includedViews) {
                foreach ($includedViews as $view) {
                    try {
                        /** @throws \InvalidArgumentException|FileNotFoundException */
                        $allIncludedViews[] = $finder->find($view);
                    } catch (\InvalidArgumentException|FileNotFoundException) {
                    }
                }
            }
        }

        $allIncludedViews = array_unique($allIncludedViews);

        if (! ($finder instanceof FileViewFinder)) {
            return [];
        }

        /** @var list<string> $paths */
        $paths = Collection::make($finder->getPaths())
            ->add(resource_path('views'))
            ->unique()
            ->filter(static fn (string $path) => ! str_contains($path, '/vendor/'))
            ->values()
            ->toArray();

        if (count($paths) <= 0) {
            return [
                RuleErrorBuilder::message('Unable to detect view paths')
                    ->identifier('bladestan.unknownViewPaths')
                    ->build(),
            ];
        }

        try {
            $symfonyFinder = Finder::create()
                ->files()
                ->in($paths)
                ->name(array_map(static function (string $suffix): string {
                    return '~\.' . preg_quote($suffix, '~') . '$~';
                }, $finder->getExtensions()));
        } catch (DirectoryNotFoundException $e) {
            return [
                RuleErrorBuilder::message('Unable to detect view paths: ' . $e->getMessage())
                    ->identifier('bladestan.unknownViewPaths')
                    ->build(),
            ];
        }

        $allViews = [];

        foreach ($symfonyFinder as $file) {
            $allViews[] = $file->getPathname();
        }

        $errors = [];

        $unusedViews = array_diff($allViews, $allIncludedViews);

        sort($unusedViews, SORT_NATURAL);

        foreach ($unusedViews as $notIncludedView) {
            $relativePath = self::detectRelativePath($paths, $notIncludedView);
            $guessedViewName = $relativePath ? self::guessViewName($relativePath, $finder->getExtensions()) : null;

            if ($guessedViewName || $relativePath) {
                $message = sprintf('Unused view: %s', $guessedViewName ?? $relativePath);
            } else {
                $message = 'Unused view';
            }

            $errors[] = RuleErrorBuilder::message($message)
                ->identifier('bladestan.unusedView')
                ->file($notIncludedView)
                ->build();
        }

        return $errors;
    }

    /**
     * @param array<string> $extensions
     */
    public static function guessViewName(string $relativeViewPath, array $extensions): ?string
    {
        /** @var array<string> $possibleNames */
        $possibleNames = Collection::make($extensions)
            ->map(static function (string $extension) use ($relativeViewPath): ?string {
                if (str_ends_with($relativeViewPath, '.' . $extension)) {
                    return substr($relativeViewPath, 0, -(strlen($extension) + 1));
                }

                return null;
            })
            ->filter()
            ->toArray();

        $shortestPossibleNameWithoutExtension = array_reduce(
            $possibleNames,
            static function (?string $ax, string $dx): string {
                if ($ax === null) {
                    return $dx;
                }

                return strlen($dx) < strlen($ax) ? $dx : $ax;
            }
        );

        if ($shortestPossibleNameWithoutExtension === null) {
            return null;
        }

        return str_replace('/', '.', $shortestPossibleNameWithoutExtension);
    }

    /**
     * @param list<string> $paths
     */
    private static function detectRelativePath(array $paths, string $viewPath): ?string
    {
        /** @var array<string> $possiblePaths */
        $possiblePaths = Collection::make($paths)
            ->map(static function (string $path) use ($viewPath): ?string {
                if (str_starts_with($viewPath, $path)) {
                    return substr($viewPath, strlen($path) + 1);
                }
                return null;
            })
            ->filter()
            ->toArray();

        return array_reduce($possiblePaths, static function (?string $ax, string $dx): string {
            if ($ax === null) {
                return $dx;
            }

            return strlen($dx) > strlen($ax) ? $dx : $ax;
        });
    }
}
