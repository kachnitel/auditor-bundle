<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Event;

use Kachnitel\AuditorBundle\Service\AuditContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Captures the X-Request-Id header (or generates one) and stores it in AuditContext.
 *
 * This allows all audit entries created during the same HTTP request to be
 * correlated, enabling "view related audits" functionality.
 */
class RequestIdSubscriber implements EventSubscriberInterface
{
    public const HEADER_NAME = 'X-Request-Id';

    public function __construct(
        private readonly AuditContext $context
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Run early to ensure request ID is set before any audit operations
            KernelEvents::REQUEST => ['onKernelRequest', 255],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Use existing X-Request-Id header or generate a new UUID
        $requestId = $request->headers->get(self::HEADER_NAME);

        if (null === $requestId || '' === $requestId) {
            $requestId = self::generateUuidV4();
            // Store the generated ID on the request for potential downstream use
            $request->headers->set(self::HEADER_NAME, $requestId);
        }

        $this->context->setRequestId($requestId);
    }

    /**
     * Generate a UUID v4 string using native PHP.
     */
    private static function generateUuidV4(): string
    {
        $data = random_bytes(16);
        // Set version to 0100 (UUID v4)
        $data[6] = \chr(\ord($data[6]) & 0x0F | 0x40);
        // Set variant to 10xx
        $data[8] = \chr(\ord($data[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', mb_str_split(bin2hex($data), 4));
    }
}
