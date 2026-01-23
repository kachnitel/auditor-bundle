<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Twig\Components\Audit;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Inline preview for field changes (update/association_change).
 *
 * @phpstan-type DiffsArray array<string, mixed>
 */
#[AsTwigComponent('K:Audit:DiffInlineFieldChanges', template: '@KachnitelAuditor/components/Audit/DiffInlineFieldChanges.html.twig')]
final class DiffInlineFieldChanges
{
    /** @var DiffsArray */
    public array $diffs;
}
