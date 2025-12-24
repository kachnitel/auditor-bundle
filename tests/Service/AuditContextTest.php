<?php

declare(strict_types=1);

namespace DH\AuditorBundle\Tests\Service;

use DH\AuditorBundle\Service\AuditContext;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class AuditContextTest extends TestCase
{
    private AuditContext $context;

    protected function setUp(): void
    {
        $this->context = new AuditContext();
    }

    public function testInitiallyEmpty(): void
    {
        $this->assertFalse($this->context->has());
        $this->assertNull($this->context->get());
    }

    public function testSetContext(): void
    {
        $data = ['note' => 'Test note', 'reason' => 'manual'];

        $this->context->set($data);

        $this->assertTrue($this->context->has());
        $this->assertSame($data, $this->context->get());
    }

    public function testSetNote(): void
    {
        $this->context->setNote('Manual stock adjustment');

        $this->assertTrue($this->context->has());
        $this->assertSame(['note' => 'Manual stock adjustment'], $this->context->get());
    }

    public function testSetReason(): void
    {
        $this->context->setReason('inventory_count');

        $this->assertTrue($this->context->has());
        $this->assertSame(['reason' => 'inventory_count'], $this->context->get());
    }

    public function testChainedCalls(): void
    {
        $this->context
            ->setNote('Adjustment note')
            ->setReason('sale');

        $this->assertTrue($this->context->has());
        $this->assertSame([
            'note' => 'Adjustment note',
            'reason' => 'sale',
        ], $this->context->get());
    }

    public function testClear(): void
    {
        $this->context->set(['note' => 'Test']);
        $this->assertTrue($this->context->has());

        $this->context->clear();

        $this->assertFalse($this->context->has());
        $this->assertNull($this->context->get());
    }

    public function testSetOverwritesExisting(): void
    {
        $this->context->set(['old' => 'data']);
        $this->context->set(['new' => 'data']);

        $this->assertSame(['new' => 'data'], $this->context->get());
    }

    public function testEmptyArrayIsNotHas(): void
    {
        $this->context->set([]);

        $this->assertFalse($this->context->has());
    }
}
