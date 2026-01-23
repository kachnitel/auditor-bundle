<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Twig\Components\Audit;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Modal content for association link (associate/dissociate).
 *
 * @phpstan-type DiffsArray array<string, mixed>
 */
#[AsTwigComponent('K:Audit:DiffModalAssociationLink', template: '@KachnitelAuditor/components/Audit/DiffModalAssociationLink.html.twig')]
final class DiffModalAssociationLink
{
    /** @var DiffsArray */
    public array $diffs;
}
