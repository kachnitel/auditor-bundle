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
final class ChangesPreviewTest extends KernelTestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->twig = self::getContainer()->get('twig');
    }

    public function testEmptyDiffsShowsNoChangesMessage(): void
    {
        $item = $this->createEntry([
            'id' => 1,
            'diffs' => '{}',
        ]);

        $html = $this->twig->render('test_changes_preview.html.twig', [
            'item' => $item,
            'dataSource' => $this->createDataSourceMock(),
        ]);

        $this->assertStringContainsString('No changes', $html);
        $this->assertStringNotContainsString('Show data', $html);
    }

    public function testFieldChangesDisplaysShowDataButton(): void
    {
        $item = $this->createEntry([
            'id' => 123,
            'diffs' => json_encode([
                'name' => ['old' => 'Old Name', 'new' => 'New Name'],
            ]),
        ]);

        $html = $this->twig->render('test_changes_preview.html.twig', [
            'item' => $item,
            'dataSource' => $this->createDataSourceMock(),
        ]);

        $this->assertStringContainsString('Show data', $html);
        $this->assertStringContainsString('auditModal123', $html);
        $this->assertStringContainsString('Audit data', $html);
    }

    public function testRequestIdContextShowsViewRelatedLink(): void
    {
        $item = $this->createEntry([
            'id' => 456,
            'blame_user' => 'testuser',
            'created_at' => new \DateTimeImmutable('2024-01-15 10:30:00'),
            'diffs' => json_encode([
                'status' => ['old' => 'pending', 'new' => 'active'],
                '@context' => ['request_id' => 'req-abc123'],
            ]),
        ]);

        $html = $this->twig->render('test_changes_preview.html.twig', [
            'item' => $item,
            'dataSource' => $this->createDataSourceMock(hasTimelineSupport: true),
        ]);

        $this->assertStringContainsString('View Related', $html);
        $this->assertStringContainsString('req-abc123', $html);
    }

    public function testNoTimelineSupportHidesTimelineLink(): void
    {
        $item = $this->createEntry([
            'id' => 789,
            'blame_user' => 'testuser',
            'created_at' => new \DateTimeImmutable('2024-01-15 10:30:00'),
            'diffs' => json_encode([
                'field' => ['old' => 'a', 'new' => 'b'],
                '@context' => ['request_id' => 'req-xyz'],
            ]),
        ]);

        $html = $this->twig->render('test_changes_preview.html.twig', [
            'item' => $item,
            'dataSource' => $this->createDataSourceMock(hasTimelineSupport: false),
        ]);

        $this->assertStringNotContainsString('View Related', $html);
        $this->assertStringNotContainsString('User Timeline', $html);
    }

    public function testEntitySummaryDiffTypeRendersCorrectly(): void
    {
        $item = $this->createEntry([
            'id' => 100,
            'diffs' => json_encode([
                'class' => 'App\Entity\User',
                'label' => 'User#42',
                'id' => 42,
            ]),
        ]);

        $html = $this->twig->render('test_changes_preview.html.twig', [
            'item' => $item,
            'dataSource' => $this->createDataSourceMock(),
        ]);

        $this->assertStringContainsString('Show data', $html);
        $this->assertStringContainsString('User', $html);
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
