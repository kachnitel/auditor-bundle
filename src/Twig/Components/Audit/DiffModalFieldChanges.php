<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Twig\Components\Audit;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Modal content for field changes (update/association_change) - accordion view.
 *
 * @phpstan-type DiffsArray array<string, mixed>
 */
#[AsTwigComponent('K:Audit:DiffModalFieldChanges', template: '@KachnitelAuditor/components/Audit/DiffModalFieldChanges.html.twig')]
final class DiffModalFieldChanges
{
    /** @var DiffsArray */
    public array $diffs;

    public int $entryId;
}
