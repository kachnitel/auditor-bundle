<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Tests\Service;

use Kachnitel\AuditorBundle\Service\AuditContext;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
#[Group('context')]
final class AuditContextTest extends TestCase
{
    private AuditContext $context;

    protected function setUp(): void
    {
        $this->context = new AuditContext();
    }

    // -------------------------------------------------------------------------
    // Baseline (pre-existing behaviour, must remain green)
    // -------------------------------------------------------------------------

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

    public function testClear(): void
    {
        $this->context->set(['note' => 'Test']);
        $this->assertTrue($this->context->has());

        $this->context->clear();

        $this->assertFalse($this->context->has());
        $this->assertNull($this->context->get());
    }

    public function testEmptyArrayIsNotHas(): void
    {
        $this->context->set([]);

        $this->assertFalse($this->context->has());
    }

    public function testSetRequestId(): void
    {
        $this->context->setRequestId('abc-123-def');

        $this->assertTrue($this->context->has());
        $this->assertSame('abc-123-def', $this->context->getRequestId());
        $this->assertSame(['request_id' => 'abc-123-def'], $this->context->get());
    }

    public function testGetRequestIdWhenNotSet(): void
    {
        $this->assertNull($this->context->getRequestId());
    }

    public function testRequestIdWithOtherContext(): void
    {
        $this->context
            ->setNote('Manual adjustment')
            ->setReason('inventory')
            ->setRequestId('req-456')
        ;

        $this->assertSame('req-456', $this->context->getRequestId());
        $this->assertSame([
            'note' => 'Manual adjustment',
            'reason' => 'inventory',
            'request_id' => 'req-456',
        ], $this->context->get());
    }

    // -------------------------------------------------------------------------
    // First-wins: set()
    // -------------------------------------------------------------------------

    public function testSetIsNoOpWhenPrimaryAlreadySet(): void
    {
        $this->context->set(['note' => 'First caller']);
        $this->context->set(['note' => 'Second caller — should be ignored']);

        $this->assertSame(['note' => 'First caller'], $this->context->get());
    }

    public function testSetAfterSetNoteIsNoOp(): void
    {
        $this->context->setNote('Set via setNote first');
        $this->context->set(['note' => 'Should be ignored', 'reason' => 'also ignored']);

        $this->assertSame(['note' => 'Set via setNote first'], $this->context->get());
    }

    public function testSetAfterSetReasonIsNoOp(): void
    {
        $this->context->setReason('first_reason');
        $this->context->set(['reason' => 'should be ignored']);

        $this->assertSame(['reason' => 'first_reason'], $this->context->get());
    }

    public function testSetPreservesRequestIdAlreadyPresent(): void
    {
        $this->context->setRequestId('req-999');
        $this->context->set(['note' => 'Primary context']);

        $result = $this->context->get();
        $this->assertSame('Primary context', $result['note']);
        $this->assertSame('req-999', $result['request_id']);
    }

    // -------------------------------------------------------------------------
    // First-wins: setNote()
    // -------------------------------------------------------------------------

    public function testSetNoteIsNoOpWhenNoteAlreadySet(): void
    {
        $this->context->setNote('Original note');
        $this->context->setNote('Should be ignored');

        $this->assertSame(['note' => 'Original note'], $this->context->get());
    }

    public function testSetNoteIsNoOpWhenNoteSetViaSet(): void
    {
        $this->context->set(['note' => 'Set via set()', 'reason' => 'x']);
        $this->context->setNote('Should be ignored');

        $this->assertSame('Set via set()', $this->context->get()['note']);
    }

    public function testSetNoteDoesNotBlockSettingReason(): void
    {
        $this->context->setNote('A note');
        $this->context->setReason('a_reason');

        $this->assertSame('A note', $this->context->get()['note']);
        $this->assertSame('a_reason', $this->context->get()['reason']);
    }

    // -------------------------------------------------------------------------
    // First-wins: setReason()
    // -------------------------------------------------------------------------

    public function testSetReasonIsNoOpWhenReasonAlreadySet(): void
    {
        $this->context->setReason('original_reason');
        $this->context->setReason('should_be_ignored');

        $this->assertSame(['reason' => 'original_reason'], $this->context->get());
    }

    public function testSetReasonIsNoOpWhenReasonSetViaSet(): void
    {
        $this->context->set(['reason' => 'set_via_set']);
        $this->context->setReason('should_be_ignored');

        $this->assertSame('set_via_set', $this->context->get()['reason']);
    }

    // -------------------------------------------------------------------------
    // setRequestId() never locks primary
    // -------------------------------------------------------------------------

    public function testSetRequestIdDoesNotBlockSubsequentSet(): void
    {
        $this->context->setRequestId('req-111');
        $this->context->set(['note' => 'Should be set']);

        $this->assertSame('Should be set', $this->context->get()['note']);
    }

    public function testSetRequestIdDoesNotBlockSetNote(): void
    {
        $this->context->setRequestId('req-222');
        $this->context->setNote('Should be set');

        $this->assertSame('Should be set', $this->context->get()['note']);
    }

    public function testSetRequestIdAlwaysUpdatesEvenAfterPrimaryLocked(): void
    {
        $this->context->set(['note' => 'Primary']);
        $this->context->setRequestId('req-late');

        $this->assertSame('req-late', $this->context->getRequestId());
    }

    // -------------------------------------------------------------------------
    // override()
    // -------------------------------------------------------------------------

    public function testOverrideReplacesPrimaryContext(): void
    {
        $this->context->set(['note' => 'Original']);
        $this->context->override(['note' => 'Overridden']);

        $this->assertSame('Overridden', $this->context->get()['note']);
    }

    public function testOverridePreservesRequestId(): void
    {
        $this->context->setRequestId('req-keep');
        $this->context->set(['note' => 'Original']);
        $this->context->override(['note' => 'Overridden']);

        $this->assertSame('req-keep', $this->context->getRequestId());
        $this->assertSame('Overridden', $this->context->get()['note']);
    }

    public function testOverrideDoesNotInheritOldPrimaryKeys(): void
    {
        $this->context->set(['note' => 'Old note', 'reason' => 'old_reason']);
        $this->context->override(['note' => 'New note']);

        $this->assertNull($this->context->get()['reason'] ?? null);
        $this->assertSame('New note', $this->context->get()['note']);
    }

    public function testAfterOverrideSetIsNoOpAgain(): void
    {
        $this->context->set(['note' => 'First']);
        $this->context->override(['note' => 'Override']);
        $this->context->set(['note' => 'Should be blocked again']);

        $this->assertSame('Override', $this->context->get()['note']);
    }

    public function testOverrideWithoutPriorSetWorks(): void
    {
        $this->context->override(['note' => 'Direct override']);

        $this->assertSame('Direct override', $this->context->get()['note']);
    }

    // -------------------------------------------------------------------------
    // clear() resets lock so set() works again
    // -------------------------------------------------------------------------

    public function testClearResetsLockAllowingNewSet(): void
    {
        $this->context->set(['note' => 'Original']);
        $this->context->clear();
        $this->context->set(['note' => 'After clear']);

        $this->assertSame('After clear', $this->context->get()['note']);
    }

    public function testClearResetsLockAllowingNewSetNote(): void
    {
        $this->context->setNote('Original');
        $this->context->clear();
        $this->context->setNote('After clear');

        $this->assertSame('After clear', $this->context->get()['note']);
    }

    // -------------------------------------------------------------------------
    // Chaining still works
    // -------------------------------------------------------------------------

    public function testChainedFirstCallsWork(): void
    {
        $this->context
            ->setNote('Adjustment note')
            ->setReason('sale')
        ;

        $this->assertSame([
            'note' => 'Adjustment note',
            'reason' => 'sale',
        ], $this->context->get());
    }

    public function testSetIsFirstWinsNotLastWins(): void
    {
        // Previously set() always replaced. Now first call wins.
        $this->context->set(['original' => 'value']);
        $this->context->set(['new' => 'value']);

        $this->assertSame(['original' => 'value'], $this->context->get());
        $this->assertArrayNotHasKey('new', $this->context->get() ?? []);
    }
}
