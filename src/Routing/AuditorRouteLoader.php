<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Routing;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Routing loader that auto-registers auditor bundle routes.
 *
 * This loader is tagged with 'routing.loader' and automatically
 * provides the timeline route when the bundle is loaded.
 */
class AuditorRouteLoader extends Loader
{
    private bool $isLoaded = false;

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->isLoaded) {
            throw new \RuntimeException('Do not add the "dh_auditor" loader twice');
        }

        $routes = new RouteCollection();

        // Timeline route
        $route = new Route(
            '/admin/audit/timeline',
            [
                '_controller' => 'DH\AuditorBundle\Controller\TimelineController::timeline',
            ],
            [],
            [],
            '',
            [],
            ['GET']
        );
        $routes->add('dh_auditor_timeline', $route);

        $this->isLoaded = true;

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'dh_auditor' === $type;
    }
}
