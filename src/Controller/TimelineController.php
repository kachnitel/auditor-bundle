<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Controller;

use Kachnitel\AuditorBundle\Service\AuditReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for viewing cross-entity audit timelines.
 *
 * Provides a visual timeline view of all audit entries across all entity types
 * for a given user within a time range.
 */
class TimelineController extends AbstractController
{
    public function __construct(
        private readonly AuditReader $auditReader,
    ) {}

    #[Route('/admin/audit/timeline', name: 'dh_auditor_timeline', methods: ['GET'])]
    public function timeline(Request $request): Response
    {
        $user = $request->query->getString('user');
        $fromStr = $request->query->getString('from');
        $toStr = $request->query->getString('to');
        $anchorEntity = $request->query->getString('anchor_entity');
        $anchorId = $request->query->getString('anchor_id');
        $anchorIsSystem = $request->query->getBoolean('anchor_is_system', false);

        // Default includeSystem to false, unless viewing a system event
        $includeSystemDefault = $anchorIsSystem;
        $includeSystem = $request->query->has('include_system')
            ? $request->query->getBoolean('include_system')
            : $includeSystemDefault;

        // Filter parameters
        /** @var array<string> $filterEntities */
        $filterEntities = $request->query->all('entities');
        /** @var array<string> $filterActions */
        $filterActions = $request->query->all('actions');

        // Validate required parameters
        if ('' === $user || '' === $fromStr || '' === $toStr) {
            return $this->render('@KachnitelAuditor/Admin/Audit/timeline.html.twig', [
                'error' => 'Missing required parameters: user, from, and to are required.',
                'entries' => [],
                'user' => $user,
                'from' => null,
                'to' => null,
                'anchorEntity' => $anchorEntity,
                'anchorId' => $anchorId,
                'availableEntities' => [],
                'availableActions' => [],
                'filterEntities' => [],
                'filterActions' => [],
                'includeSystem' => $includeSystem,
            ]);
        }

        try {
            $timezone = new \DateTimeZone(
                $this->auditReader->getReader()->getProvider()->getAuditor()->getConfiguration()->getTimezone()
            );
            $from = new \DateTimeImmutable($fromStr, $timezone);
            $to = new \DateTimeImmutable($toStr, $timezone);
        } catch (\Exception $e) {
            return $this->render('@KachnitelAuditor/Admin/Audit/timeline.html.twig', [
                'error' => 'Invalid date format: '.$e->getMessage(),
                'entries' => [],
                'user' => $user,
                'from' => null,
                'to' => null,
                'anchorEntity' => $anchorEntity,
                'anchorId' => $anchorId,
                'availableEntities' => [],
                'availableActions' => [],
                'filterEntities' => [],
                'filterActions' => [],
                'includeSystem' => $includeSystem,
            ]);
        }

        // Fetch timeline data across all entities
        $timelineData = $this->auditReader->findGlobalTimeline($user, $from, $to, $includeSystem);

        // Flatten entries and collect available filter options
        $allEntries = [];
        $availableEntities = [];
        $availableActions = [];

        foreach ($timelineData as $entityClass => $entries) {
            $shortName = $this->getShortClassName($entityClass);
            $availableEntities[$entityClass] = $shortName;

            foreach ($entries as $entry) {
                $actionType = $entry->getType();
                if (null !== $actionType && !\in_array($actionType, $availableActions, true)) {
                    $availableActions[] = $actionType;
                }

                $allEntries[] = [
                    'entityClass' => $entityClass,
                    'entry' => $entry,
                ];
            }
        }

        sort($availableActions);
        asort($availableEntities);

        // Apply filters
        if ([] !== $filterEntities || [] !== $filterActions) {
            $allEntries = array_filter($allEntries, function (array $item) use ($filterEntities, $filterActions): bool {
                // Filter by entity type
                if ([] !== $filterEntities && !\in_array($item['entityClass'], $filterEntities, true)) {
                    return false;
                }

                // Filter by action type
                if ([] !== $filterActions && !\in_array($item['entry']->getType(), $filterActions, true)) {
                    return false;
                }

                return true;
            });
            $allEntries = array_values($allEntries);
        }

        // Sort by created_at ascending
        usort($allEntries, function (array $a, array $b): int {
            $aTime = $a['entry']->getCreatedAt();
            $bTime = $b['entry']->getCreatedAt();

            if (null === $aTime && null === $bTime) {
                return 0;
            }
            if (null === $aTime) {
                return -1;
            }
            if (null === $bTime) {
                return 1;
            }

            $cmp = $aTime <=> $bTime;
            if (0 !== $cmp) {
                return $cmp;
            }

            // Same timestamp - sort by ID
            return ($a['entry']->getId() ?? 0) <=> ($b['entry']->getId() ?? 0);
        });

        return $this->render('@KachnitelAuditor/Admin/Audit/timeline.html.twig', [
            'entries' => $allEntries,
            'user' => $user,
            'from' => $from,
            'to' => $to,
            'anchorEntity' => $anchorEntity,
            'anchorId' => $anchorId,
            'includeSystem' => $includeSystem,
            'availableEntities' => $availableEntities,
            'availableActions' => $availableActions,
            'filterEntities' => $filterEntities,
            'filterActions' => $filterActions,
            'error' => null,
        ]);
    }

    private function getShortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts) ?: $fqcn;
    }
}
