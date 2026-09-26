<?php

namespace Tests\Unit;

use App\Actions\CalculateScore;
use PHPUnit\Framework\TestCase;

class ScoreCalculationTest extends TestCase
{
    public function test_each_component_is_trimmed_independently_with_integer_arithmetic(): void
    {
        $calculator = new CalculateScore;
        $result = $calculator->calculate([
            ['accuracy' => 100, 'presentation' => 690], ['accuracy' => 200, 'presentation' => 500], ['accuracy' => 250, 'presentation' => 600], ['accuracy' => 280, 'presentation' => 650], ['accuracy' => 300, 'presentation' => 400],
        ], ['algorithm' => 'component_trimmed_mean_v1', 'accuracy_max' => 300, 'presentation_max' => 700, 'discard_each_end' => 1], 5);
        $this->assertSame('8.266666', $result['score']);
        $this->assertSame([200, 250, 280], $result['components']['accuracy']['kept_hundredths']);
        $this->assertSame(201, $calculator->hundredths('2.01'));
    }

    public function test_unknown_rule_never_silently_calculates(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CalculateScore)->calculate([], ['algorithm' => 'unknown'], 5);
    }
}
