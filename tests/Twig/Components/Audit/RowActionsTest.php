<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Tests\Twig\Components\Audit;

use DH\Auditor\Model\Entry;
use Kachnitel\AuditorBundle\Admin\AuditDataSource;
use Kachnitel\AuditorBundle\Tests\App\Kernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * @internal
 */
#[Small]
#[CoversNothing]
final class RowActionsTest extends KernelTestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->twig = self::getContainer()->get('twig');
    }

    public function testRequestIdDisplaysLinkIcon(): void
    {
        $item = $this->createEntry([
            'id' => 1,
            'blame_user' => 'testuser',
            'created_at' => new \DateTimeImmutable('2024-01-15 10:30:00'),
            'diffs' => json_encode([
                'field' => ['old' => 'a', 'new' => 'b'],
                '@context' => ['request_id' => 'req-12345'],
            ]),
        ]);

        $html = $this->twig->render('test_row_actions.html.twig', [
            'item' => $item,
            'dataSource' => $this->createDataSourceMock(hasTimelineSupport: true),
        ]);

        $this->assertStringContainsString('columnFilters[request_id]=req-12345', $html);
        $this->assertStringContainsString('btn-outline-info', $html);
        $this->assertStringContainsString('link', $html);
    }

    public function testUsernameWithCreatedAtRendersTimelineLink(): void
    {
        $item = $this->createEntry([
            'id' => 2,
            'blame_user' => 'testuser',
            'created_at' => new \DateTimeImmutable('2024-01-15 10:30:00'),
            'diffs' => '{}',
        ]);

        $html = $this->twig->render('test_row_actions.html.twig', [
            'item' => $item,
            'dataSource' => $this->createDataSourceMock(hasTimelineSupport: true),
        ]);

        $this->assertStringContainsString('btn-outline-primary', $html);
        $this->assertStringContainsString('/admin/audit/timeline', $html);
        $this->assertStringContainsString('user=testuser', $html);
    }

    public function testUsernameWithoutCreatedAtShowsUserFilterLink(): void
    {
        $item = $this->createEntry([
            'id' => 3,
            'blame_user' => 'testuser',
            'created_at' => null,
            'diffs' => '{}',
        ]);

        $html = $this->twig->render('test_row_actions.html.twig', [
            'item' => $item,
            'dataSource' => $this->createDataSourceMock(hasTimelineSupport: true),
        ]);

        $this->assertStringContainsString('columnFilters[blame_user]=testuser', $html);
        $this->assertStringContainsString('person', $html);
        $this->assertStringNotContainsString('/admin/audit/timeline', $html);
    }

    public function testNoTimelineSupportRendersEmptyGroup(): void
    {
        $item = $this->createEntry([
            'id' => 4,
            'blame_user' => 'testuser',
            'created_at' => new \DateTimeImmutable('2024-01-15 10:30:00'),
            'diffs' => json_encode([
                '@context' => ['request_id' => 'req-xyz'],
            ]),
        ]);

        $html = $this->twig->render('test_row_actions.html.twig', [
            'item' => $item,
            'dataSource' => $this->createDataSourceMock(hasTimelineSupport: false),
        ]);

        $this->assertStringContainsString('btn-group', $html);
        $this->assertStringNotContainsString('columnFilters[request_id]', $html);
        $this->assertStringNotContainsString('/admin/audit/timeline', $html);
    }

    public function testNoUsernameRendersNoUserActions(): void
    {
        $item = $this->createEntry([
            'id' => 5,
            'blame_user' => null,
            'created_at' => new \DateTimeImmutable('2024-01-15 10:30:00'),
            'diffs' => '{}',
        ]);

        $html = $this->twig->render('test_row_actions.html.twig', [
            'item' => $item,
            'dataSource' => $this->createDataSourceMock(hasTimelineSupport: true),
        ]);

        $this->assertStringNotContainsString('btn-outline-primary', $html);
        $this->assertStringNotContainsString('/admin/audit/timeline', $html);
    }

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    /**
     * Create an Entry object with the given properties using reflection.
     *
     * @param array<string, mixed> $properties
     */
    private function createEntry(array $properties): Entry
    {
        $entry = new Entry();
        $reflection = new \ReflectionClass($entry);

        foreach ($properties as $name => $value) {
            $property = $reflection->getProperty($name);
            $property->setValue($entry, $value);
        }

        return $entry;
    }

    /**
     * @return AuditDataSource&MockObject
     */
    private function createDataSourceMock(
        string $entityClass = 'App\Entity\TestEntity',
        bool $hasTimelineSupport = false,
    ): AuditDataSource {
        $dataSource = $this->createMock(AuditDataSource::class);
        $dataSource->method('getEntityClass')->willReturn($entityClass);
        $dataSource->method('hasTimelineSupport')->willReturn($hasTimelineSupport);

        return $dataSource;
    }
}
