<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Twig\Components\Audit;

use DH\Auditor\Model\Entry;
use Kachnitel\AuditorBundle\Admin\AuditDataSource;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * Timeline link component - shows all changes by user within ±5 minutes.
 */
#[AsTwigComponent('K:Audit:TimelineLink', template: '@KachnitelAuditor/components/Audit/TimelineLink.html.twig')]
final class TimelineLink
{
    public Entry $item;

    public AuditDataSource $dataSource;

    public bool $showLabel = false;

    public string $class = 'btn btn-outline-primary btn-sm';
}
