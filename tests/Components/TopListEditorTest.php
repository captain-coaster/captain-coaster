<?php

declare(strict_types=1);

namespace App\Tests\Components;

use App\Components\TopListEditor;
use PHPUnit\Framework\TestCase;

class TopListEditorTest extends TestCase
{
    public function testFlagDuplicatesMarksExistingCoasters(): void
    {
        $items = [
            ['id' => 123, 'coaster' => 'Ride A', 'park' => 'Park X', 'rating' => null, 'image' => null],
            ['id' => 456, 'coaster' => 'Ride B', 'park' => 'Park Y', 'rating' => 4.5, 'image' => 'b.jpg'],
            ['id' => 789, 'coaster' => 'Ride C', 'park' => 'Park Z', 'rating' => null, 'image' => null],
        ];

        $result = TopListEditor::flagDuplicates($items, [123, 789]);

        $this->assertTrue($result[0]['alreadyInList']);
        $this->assertFalse($result[1]['alreadyInList']);
        $this->assertTrue($result[2]['alreadyInList']);
    }

    public function testFlagDuplicatesReturnsEmptyForEmptyInput(): void
    {
        $this->assertSame([], TopListEditor::flagDuplicates([], []));
    }

    public function testFlagDuplicatesAllFalseWhenNoExisting(): void
    {
        $items = [['id' => 1, 'coaster' => 'X', 'park' => 'Y', 'rating' => null, 'image' => null]];
        $result = TopListEditor::flagDuplicates($items, []);
        $this->assertFalse($result[0]['alreadyInList']);
    }
}
