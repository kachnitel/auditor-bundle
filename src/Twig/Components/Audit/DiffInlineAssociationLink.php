<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Twig\Components\Audit;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Inline preview for association link (associate/dissociate).
 *
 * @phpstan-type DiffsArray array<string, mixed>
 */
#[AsTwigComponent('K:Audit:DiffInlineAssociationLink', template: '@KachnitelAuditor/components/Audit/DiffInlineAssociationLink.html.twig')]
final class DiffInlineAssociationLink
{
    /** @var DiffsArray */
    public array $diffs;
}
