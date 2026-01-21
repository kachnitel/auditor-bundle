<?php

declare(strict_types=1);

namespace Kachnitel\AuditorBundle\Tests\Helper;

use Kachnitel\AuditorBundle\Helper\DiffFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[Small]
final class DiffFormatterTest extends TestCase
{
    public function testDetectDiffTypeForUpdate(): void
    {
        $diffs = [
            'name' => ['old' => 'John', 'new' => 'Jane'],
            'age' => ['old' => 25, 'new' => 26],
        ];

        $this->assertSame('update', DiffFormatter::detectDiffType($diffs));
    }

    public function testDetectDiffTypeForAssociationChange(): void
    {
        $diffs = [
            'tags' => [
                'removed' => [['id' => 1, 'label' => 'Tag1']],
                'added' => [['id' => 2, 'label' => 'Tag2']],
            ],
        ];

        $this->assertSame('association_change', DiffFormatter::detectDiffType($diffs));
    }

    public function testDetectDiffTypeForEntitySummary(): void
    {
        $diffs = [
            'id' => 123,
            'class' => 'App\Entity\LineItem',
            'label' => 'LineItem #123',
            'table' => 'line_items',
        ];

        $this->assertSame('entity_summary', DiffFormatter::detectDiffType($diffs));
    }

    public function testDetectDiffTypeForAssociationLink(): void
    {
        $diffs = [
            'source' => [
                'class' => 'App\Entity\Author',
                'id' => 1,
                'label' => 'John Doe',
                'field' => 'posts',
            ],
            'target' => [
                'class' => 'App\Entity\Post',
                'id' => 5,
                'label' => 'My First Post',
                'field' => 'author',
            ],
            'is_owning_side' => true,
        ];

        $this->assertSame('association_link', DiffFormatter::detectDiffType($diffs));
    }

    public function testDetectDiffTypeIgnoresMetadata(): void
    {
        $diffs = [
            '@context' => ['request_id' => 'abc123'],
            '@source' => ['some' => 'data'],
            'name' => ['old' => 'John', 'new' => 'Jane'],
        ];

        $this->assertSame('update', DiffFormatter::detectDiffType($diffs));
    }

    public function testDetectDiffTypeForEmptyDiffs(): void
    {
        $this->assertSame('unknown', DiffFormatter::detectDiffType([]));
    }

    public function testDetectDiffTypeForOnlyMetadata(): void
    {
        $diffs = [
            '@context' => ['request_id' => 'abc123'],
        ];

        $this->assertSame('unknown', DiffFormatter::detectDiffType($diffs));
    }

    public function testIsEntitySummaryPositive(): void
    {
        $diffs = [
            'id' => 123,
            'class' => 'App\Entity\LineItem',
            'label' => 'LineItem #123',
            'table' => 'line_items',
        ];

        $this->assertTrue(DiffFormatter::isEntitySummary($diffs));
    }

    public function testIsEntitySummaryNegative(): void
    {
        // Update diff structure
        $diffs = [
            'name' => ['old' => 'John', 'new' => 'Jane'],
        ];

        $this->assertFalse(DiffFormatter::isEntitySummary($diffs));
    }

    public function testIsEntitySummaryRequiresBothClassAndLabel(): void
    {
        $onlyClass = ['class' => 'App\Entity\LineItem'];
        $onlyLabel = ['label' => 'Some Label'];

        $this->assertFalse(DiffFormatter::isEntitySummary($onlyClass));
        $this->assertFalse(DiffFormatter::isEntitySummary($onlyLabel));
    }

    public function testIsAssociationLinkPositive(): void
    {
        $diffs = [
            'source' => ['class' => 'App\Entity\Author', 'id' => 1],
            'target' => ['class' => 'App\Entity\Post', 'id' => 5],
            'is_owning_side' => true,
        ];

        $this->assertTrue(DiffFormatter::isAssociationLink($diffs));
    }

    public function testIsAssociationLinkWithFalseOwning(): void
    {
        $diffs = [
            'source' => ['class' => 'App\Entity\Author', 'id' => 1],
            'target' => ['class' => 'App\Entity\Post', 'id' => 5],
            'is_owning_side' => false,
        ];

        $this->assertTrue(DiffFormatter::isAssociationLink($diffs));
    }

    public function testIsAssociationLinkNegative(): void
    {
        // Update diff structure
        $diffs = [
            'name' => ['old' => 'John', 'new' => 'Jane'],
        ];

        $this->assertFalse(DiffFormatter::isAssociationLink($diffs));
    }

    public function testIsAssociationLinkRequiresAllKeys(): void
    {
        $missingTarget = [
            'source' => ['class' => 'App\Entity\Author', 'id' => 1],
            'is_owning_side' => true,
        ];

        $missingOwning = [
            'source' => ['class' => 'App\Entity\Author', 'id' => 1],
            'target' => ['class' => 'App\Entity\Post', 'id' => 5],
        ];

        $this->assertFalse(DiffFormatter::isAssociationLink($missingTarget));
        $this->assertFalse(DiffFormatter::isAssociationLink($missingOwning));
    }

    public function testFormatEntitySummary(): void
    {
        $diffs = [
            'id' => 123,
            'class' => 'App\Entity\LineItem',
            'label' => 'Line Item #123',
            'table' => 'line_items',
        ];

        $result = DiffFormatter::formatEntitySummary($diffs);

        $this->assertSame('Line Item #123', $result['label']);
        $this->assertSame('App\Entity\LineItem', $result['class']);
        $this->assertSame(123, $result['id']);
        $this->assertSame('LineItem', $result['shortClass']);
    }

    public function testFormatEntitySummaryWithCustomPkName(): void
    {
        $diffs = [
            'uuid' => 'abc-123-def',
            'pkName' => 'uuid',
            'class' => 'App\Entity\Document',
            'label' => 'My Document',
        ];

        $result = DiffFormatter::formatEntitySummary($diffs);

        $this->assertSame('abc-123-def', $result['id']);
    }

    public function testFormatEntitySummaryWithMissingData(): void
    {
        $diffs = [];

        $result = DiffFormatter::formatEntitySummary($diffs);

        $this->assertSame('', $result['label']);
        $this->assertSame('', $result['class']);
        $this->assertNull($result['id']);
        $this->assertSame('', $result['shortClass']);
    }

    public function testFormatAssociationLink(): void
    {
        $diffs = [
            'source' => [
                'class' => 'App\Entity\Author',
                'id' => 1,
                'label' => 'John Doe',
                'field' => 'posts',
            ],
            'target' => [
                'class' => 'App\Entity\Post',
                'id' => 5,
                'label' => 'My First Post',
                'field' => 'author',
            ],
            'is_owning_side' => true,
        ];

        $result = DiffFormatter::formatAssociationLink($diffs);

        $this->assertSame('John Doe', $result['source']);
        $this->assertSame('My First Post', $result['target']);
        $this->assertSame('App\Entity\Author', $result['sourceClass']);
        $this->assertSame('App\Entity\Post', $result['targetClass']);
    }

    public function testFormatAssociationLinkWithoutLabels(): void
    {
        $diffs = [
            'source' => [
                'class' => 'App\Entity\Author',
                'id' => 1,
            ],
            'target' => [
                'class' => 'App\Entity\Post',
                'id' => 5,
            ],
            'is_owning_side' => true,
        ];

        $result = DiffFormatter::formatAssociationLink($diffs);

        $this->assertSame('Author#1', $result['source']);
        $this->assertSame('Post#5', $result['target']);
    }

    public function testFormatAssociationLinkWithEmptyData(): void
    {
        $diffs = [
            'source' => [],
            'target' => [],
            'is_owning_side' => true,
        ];

        $result = DiffFormatter::formatAssociationLink($diffs);

        $this->assertSame('#?', $result['source']);
        $this->assertSame('#?', $result['target']);
    }

    /**
     * @param array<string, mixed> $diffs
     */
    #[DataProvider('provideDetectDiffTypeWithDataProviderCases')]
    public function testDetectDiffTypeWithDataProvider(array $diffs, string $expected): void
    {
        $this->assertSame($expected, DiffFormatter::detectDiffType($diffs));
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function provideDetectDiffTypeWithDataProviderCases(): iterable
    {
        return [
            'update with old/new' => [
                ['status' => ['old' => 'draft', 'new' => 'published']],
                'update',
            ],
            'update with only new' => [
                ['status' => ['new' => 'published']],
                'update',
            ],
            'update with only old' => [
                ['status' => ['old' => 'draft']],
                'update',
            ],
            'association change' => [
                ['items' => ['removed' => [], 'added' => [['id' => 1]]]],
                'association_change',
            ],
            'entity summary (remove)' => [
                ['id' => 1, 'class' => 'App\Entity\Item', 'label' => 'Item #1', 'table' => 'items'],
                'entity_summary',
            ],
            'association link (associate)' => [
                [
                    'source' => ['class' => 'A', 'id' => 1, 'label' => 'A#1'],
                    'target' => ['class' => 'B', 'id' => 2, 'label' => 'B#2'],
                    'is_owning_side' => false,
                ],
                'association_link',
            ],
            'empty' => [
                [],
                'unknown',
            ],
            'scalar values only' => [
                ['foo' => 'bar', 'baz' => 123],
                'unknown',
            ],
        ];
    }

    public function testCreatePreviewStillWorks(): void
    {
        $diffs = [
            'name' => ['old' => 'John', 'new' => 'Jane'],
            'tags' => ['removed' => [], 'added' => [['id' => 1]]],
        ];

        $preview = DiffFormatter::createPreview($diffs);

        $this->assertSame(2, $preview['total_changes']);
        $this->assertCount(2, $preview['changes']);
    }

    public function testGetDetailedStructureStillWorks(): void
    {
        $diffs = [
            'name' => ['old' => 'John', 'new' => 'Jane'],
            '@context' => ['request_id' => 'abc'],
        ];

        $result = DiffFormatter::getDetailedStructure($diffs);

        $this->assertArrayHasKey('updates', $result);
        $this->assertArrayHasKey('metadata', $result);
    }
}
