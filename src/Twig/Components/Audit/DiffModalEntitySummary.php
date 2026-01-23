<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Twig\Components\Audit;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Modal content for entity summary (insert/remove).
 *
 * @phpstan-type DiffsArray array<string, mixed>
 */
#[AsTwigComponent('K:Audit:DiffModalEntitySummary', template: '@KachnitelAuditor/components/Audit/DiffModalEntitySummary.html.twig')]
final class DiffModalEntitySummary
{
    /** @var DiffsArray */
    public array $diffs;
}
