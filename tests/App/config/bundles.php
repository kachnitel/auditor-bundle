<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Kachnitel\AuditorBundle\KachnitelAuditorBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

$bundles = [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    TwigComponentBundle::class => ['all' => true],
    SecurityBundle::class => ['all' => true],
    KachnitelAuditorBundle::class => ['all' => true],
];

// Only load TwigExtraBundle if it's installed
if (class_exists('Twig\Extra\TwigExtraBundle\TwigExtraBundle')) {
    $bundles['Twig\Extra\TwigExtraBundle\TwigExtraBundle'] = ['all' => true];
}

return $bundles;
