<?php

declare(strict_types=1);

use DH\AuditorBundle\DHAuditorBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

$bundles = [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    SecurityBundle::class => ['all' => true],
    DHAuditorBundle::class => ['all' => true],
];

// Only load TwigExtraBundle if it's installed
if (class_exists('Twig\Extra\TwigExtraBundle\TwigExtraBundle')) {
    $bundles['Twig\Extra\TwigExtraBundle\TwigExtraBundle'] = ['all' => true];
}

return $bundles;
