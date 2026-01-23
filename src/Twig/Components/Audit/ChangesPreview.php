<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Twig\Components\Audit;

use DH\Auditor\Model\Entry;
use Kachnitel\AuditorBundle\Admin\AuditDataSource;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Inline preview of audit changes for table display.
 */
#[AsTwigComponent('K:Audit:ChangesPreview', template: '@KachnitelAuditor/components/Audit/ChangesPreview.html.twig')]
final class ChangesPreview
{
    public Entry $item;

    public AuditDataSource $dataSource;
}
