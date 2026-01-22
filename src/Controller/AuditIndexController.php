<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Controller;

use Kachnitel\AdminBundle\DataSource\DataSourceRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for audit data source index pages.
 *
 * Provides a custom index view with a prominent "Hide System Events" toggle.
 * This takes priority over the admin-bundle's generic datasource_index.
 */
class AuditIndexController extends AbstractController
{
    public function __construct(
        private readonly DataSourceRegistry $dataSourceRegistry,
    ) {}

    /**
     * List audit entries with custom template featuring "Hide System Events" toggle.
     *
     * The route pattern matches only audit data sources (prefixed with "audit-").
     * Priority 20 ensures this takes precedence over admin-bundle's route (priority 10).
     */
    #[Route('/admin/data/{dataSourceId}', name: 'kachnitel_auditor_index', methods: ['GET'], requirements: ['dataSourceId' => 'audit-.*'], priority: 20)]
    public function index(string $dataSourceId): Response
    {
        $dataSource = $this->dataSourceRegistry->get($dataSourceId);
        if (!$dataSource) {
            throw new NotFoundHttpException(\sprintf('Data source "%s" not found.', $dataSourceId));
        }

        return $this->render('@KachnitelAuditor/Admin/Audit/index.html.twig', [
            'dataSourceId' => $dataSourceId,
            'dataSource' => $dataSource,
        ]);
    }
}
