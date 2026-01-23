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
final class TimelineLinkTest extends KernelTestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->twig = self::getContainer()->get('twig');
    }

    public function testTimelineLinkTemplateRendersWithAllProps(): void
    {
        $item = $this->createEntry([
            'id' => 123,
            'blame_user' => 'testuser',
            'created_at' => new \DateTimeImmutable('2024-01-15 10:30:00'),
        ]);

        $html = $this->twig->render('test_timeline_link.html.twig', [
            'item' => $item,
            'dataSource' => $this->createDataSourceMock(),
            'showLabel' => true,
            'class' => 'btn btn-primary',
        ]);

        $this->assertStringContainsString('User Timeline', $html);
        $this->assertStringContainsString('btn btn-primary', $html);
        $this->assertStringContainsString('/admin/audit/timeline', $html);
        $this->assertStringContainsString('user=testuser', $html);
        $this->assertStringContainsString('anchor_id=123', $html);
    }

    public function testTimelineLinkTemplateRendersWithDefaultProps(): void
    {
        $item = $this->createEntry([
            'id' => 456,
            'blame_user' => 'testuser',
            'created_at' => new \DateTimeImmutable('2024-01-15 10:30:00'),
        ]);

        $html = $this->twig->render('test_timeline_link.html.twig', [
            'item' => $item,
            'dataSource' => $this->createDataSourceMock(),
            'showLabel' => false,
            'class' => 'btn btn-outline-primary btn-sm',
        ]);

        $this->assertStringNotContainsString('User Timeline', $html);
        $this->assertStringContainsString('btn btn-outline-primary btn-sm', $html);
    }

    public function testTimelineLinkTemplateRendersNothingWithoutUsername(): void
    {
        $item = $this->createEntry([
            'blame_user' => null,
            'created_at' => new \DateTimeImmutable('2024-01-15 10:30:00'),
        ]);

        $html = $this->twig->render('test_timeline_link.html.twig', [
            'item' => $item,
            'dataSource' => $this->createDataSourceMock(),
            'showLabel' => true,
            'class' => 'btn btn-primary',
        ]);

        $this->assertEmpty(mb_trim($html));
    }

    public function testTimelineLinkTemplateRendersNothingWithoutCreatedAt(): void
    {
        $item = $this->createEntry([
            'blame_user' => 'testuser',
            'created_at' => null,
        ]);

        $html = $this->twig->render('test_timeline_link.html.twig', [
            'item' => $item,
            'dataSource' => $this->createDataSourceMock(),
            'showLabel' => true,
            'class' => 'btn btn-primary',
        ]);

        $this->assertEmpty(mb_trim($html));
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
    private function createDataSourceMock(string $entityClass = 'App\Entity\TestEntity'): AuditDataSource
    {
        $dataSource = $this->createMock(AuditDataSource::class);
        $dataSource->method('getEntityClass')->willReturn($entityClass);

        return $dataSource;
    }
}
