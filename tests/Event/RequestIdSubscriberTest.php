<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Tests\Event;

use Kachnitel\AuditorBundle\Event\RequestIdSubscriber;
use Kachnitel\AuditorBundle\Service\AuditContext;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Small]
final class RequestIdSubscriberTest extends TestCase
{
    private AuditContext $context;
    private RequestIdSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->context = new AuditContext();
        $this->subscriber = new RequestIdSubscriber($this->context);
    }

    public function testSubscribedEvents(): void
    {
        $events = RequestIdSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertSame(['onKernelRequest', 255], $events[KernelEvents::REQUEST]);
    }

    public function testUsesExistingRequestIdHeader(): void
    {
        $request = new Request();
        $request->headers->set('X-Request-Id', 'existing-request-id-123');

        $event = $this->createRequestEvent($request, HttpKernelInterface::MAIN_REQUEST);
        $this->subscriber->onKernelRequest($event);

        $this->assertSame('existing-request-id-123', $this->context->getRequestId());
        $this->assertSame('existing-request-id-123', $request->headers->get('X-Request-Id'));
    }

    public function testGeneratesRequestIdWhenMissing(): void
    {
        $request = new Request();

        $event = $this->createRequestEvent($request, HttpKernelInterface::MAIN_REQUEST);
        $this->subscriber->onKernelRequest($event);

        $requestId = $this->context->getRequestId();
        $this->assertNotNull($requestId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $requestId
        );
        // Verify the generated ID is also set on the request
        $this->assertSame($requestId, $request->headers->get('X-Request-Id'));
    }

    public function testGeneratesRequestIdWhenEmpty(): void
    {
        $request = new Request();
        $request->headers->set('X-Request-Id', '');

        $event = $this->createRequestEvent($request, HttpKernelInterface::MAIN_REQUEST);
        $this->subscriber->onKernelRequest($event);

        $requestId = $this->context->getRequestId();
        $this->assertNotNull($requestId);
        $this->assertNotSame('', $requestId);
    }

    public function testIgnoresSubRequests(): void
    {
        $request = new Request();
        $request->headers->set('X-Request-Id', 'should-be-ignored');

        $event = $this->createRequestEvent($request, HttpKernelInterface::SUB_REQUEST);
        $this->subscriber->onKernelRequest($event);

        $this->assertNull($this->context->getRequestId());
    }

    public function testHeaderNameConstant(): void
    {
        $this->assertSame('X-Request-Id', RequestIdSubscriber::HEADER_NAME);
    }

    private function createRequestEvent(Request $request, int $requestType): RequestEvent
    {
        /** @var HttpKernelInterface&MockObject $kernel */
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, $requestType);
    }
}
