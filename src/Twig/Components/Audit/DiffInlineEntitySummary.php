<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Twig\Components\Audit;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Inline preview for entity summary (insert/remove).
 *
 * @phpstan-type DiffsArray array<string, mixed>
 */
#[AsTwigComponent('K:Audit:DiffInlineEntitySummary', template: '@KachnitelAuditor/components/Audit/DiffInlineEntitySummary.html.twig')]
final class DiffInlineEntitySummary
{
    /** @var DiffsArray */
    public array $diffs;
}
