<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Twig;

use Kachnitel\AuditorBundle\Helper\DiffFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig extension for formatting audit diffs.
 */
class DiffFormatterExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('diff_type', $this->detectDiffType(...)),
            new TwigFilter('format_entity_summary', $this->formatEntitySummary(...)),
            new TwigFilter('format_association_link', $this->formatAssociationLink(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('diff_type', $this->detectDiffType(...)),
            new TwigFunction('is_entity_summary', $this->isEntitySummary(...)),
            new TwigFunction('is_association_link', $this->isAssociationLink(...)),
        ];
    }

    /**
     * @param array<string, mixed> $diffs
     */
    public function detectDiffType(array $diffs): string
    {
        return DiffFormatter::detectDiffType($diffs);
    }

    /**
     * @param array<string, mixed> $diffs
     */
    public function isEntitySummary(array $diffs): bool
    {
        return DiffFormatter::isEntitySummary($diffs);
    }

    /**
     * @param array<string, mixed> $diffs
     */
    public function isAssociationLink(array $diffs): bool
    {
        return DiffFormatter::isAssociationLink($diffs);
    }

    /**
     * @param array<string, mixed> $diffs
     *
     * @return array{label: string, class: string, id: mixed, shortClass: string}
     */
    public function formatEntitySummary(array $diffs): array
    {
        return DiffFormatter::formatEntitySummary($diffs);
    }

    /**
     * @param array<string, mixed> $diffs
     *
     * @return array{source: string, target: string, sourceClass: string, targetClass: string}
     */
    public function formatAssociationLink(array $diffs): array
    {
        return DiffFormatter::formatAssociationLink($diffs);
    }
}
