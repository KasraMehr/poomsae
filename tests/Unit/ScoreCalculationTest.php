<?php

namespace Tests\Unit;

use App\Actions\CalculateScore;
use PHPUnit\Framework\TestCase;

class ScoreCalculationTest extends TestCase
{
    public function test_three_judges_are_averaged_without_discarding_scores(): void
    {
        $result = (new CalculateScore)->calculate([
            ['accuracy' => 100, 'presentation' => 400],
            ['accuracy' => 200, 'presentation' => 500],
            ['accuracy' => 300, 'presentation' => 600],
        ], ['algorithm' => 'component_trimmed_mean_v1', 'accuracy_max' => 400, 'presentation_max' => 600, 'discard_each_end' => 0], 3);

        $this->assertSame('7.000000', $result['score']);
        $this->assertSame([100, 200, 300], $result['components']['accuracy']['kept_hundredths']);
        $this->assertNull((new CalculateScore)->restoredMeanMicros($result));
    }

    public function test_each_component_is_trimmed_independently_with_integer_arithmetic(): void
    {
        $calculator = new CalculateScore;
        $result = $calculator->calculate([
            ['accuracy' => 100, 'presentation' => 690], ['accuracy' => 200, 'presentation' => 500], ['accuracy' => 250, 'presentation' => 600], ['accuracy' => 280, 'presentation' => 650], ['accuracy' => 300, 'presentation' => 400],
        ], ['algorithm' => 'component_trimmed_mean_v1', 'accuracy_max' => 300, 'presentation_max' => 700, 'discard_each_end' => 1], 5);
        $this->assertSame('8.266666', $result['score']);
        $this->assertSame([200, 250, 280], $result['components']['accuracy']['kept_hundredths']);
        $this->assertSame(7940000, $calculator->restoredMeanMicros($result));
        $this->assertSame(201, $calculator->hundredths('2.01'));
    }

    public function test_no_discarded_scores_leave_a_tie_for_operator_decision(): void
    {
        $calculator = new CalculateScore;
        $scores = array_fill(0, 5, ['accuracy' => 250, 'presentation' => 500]);
        $result = $calculator->calculate($scores, [
            'algorithm' => 'component_trimmed_mean_v1',
            'accuracy_max' => 300,
            'presentation_max' => 700,
            'discard_each_end' => 0,
        ], 5);

        $this->assertNull($calculator->restoredMeanMicros($result));
    }

    public function test_restored_mean_includes_all_seven_judges(): void
    {
        $calculator = new CalculateScore;
        $scores = array_fill(0, 7, ['accuracy' => 250, 'presentation' => 500]);
        $scores[6]['presentation'] = 600;
        $result = $calculator->calculate($scores, [
            'algorithm' => 'component_trimmed_mean_v1',
            'accuracy_max' => 300,
            'presentation_max' => 700,
            'discard_each_end' => 1,
        ], 7);

        $this->assertSame('7.500000', $result['score']);
        $this->assertSame(7642857, $calculator->restoredMeanMicros($result));
    }

    public function test_two_scores_from_each_end_can_be_discarded_with_seven_judges(): void
    {
        $calculator = new CalculateScore;
        $scores = [
            ['accuracy' => 100, 'presentation' => 700],
            ['accuracy' => 150, 'presentation' => 650],
            ['accuracy' => 200, 'presentation' => 600],
            ['accuracy' => 250, 'presentation' => 550],
            ['accuracy' => 275, 'presentation' => 500],
            ['accuracy' => 290, 'presentation' => 450],
            ['accuracy' => 300, 'presentation' => 400],
        ];
        $rules = ['algorithm' => 'component_trimmed_mean_v1', 'accuracy_max' => 300, 'presentation_max' => 700, 'discard_each_end' => 2];

        $result = $calculator->calculate($scores, $rules, 7);

        $this->assertSame('7.916667', $result['score']);
        $this->assertSame([200, 250, 275], $result['components']['accuracy']['kept_hundredths']);
        $this->assertSame([500, 550, 600], $result['components']['presentation']['kept_hundredths']);
        $this->assertSame(7735714, $calculator->restoredMeanMicros($result));

        $this->expectException(\InvalidArgumentException::class);
        $calculator->calculate(array_slice($scores, 0, 5), $rules, 5);
    }

    public function test_unknown_rule_never_silently_calculates(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CalculateScore)->calculate([], ['algorithm' => 'unknown'], 5);
    }
}
