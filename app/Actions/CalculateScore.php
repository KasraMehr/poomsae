<?php

namespace App\Actions;

use InvalidArgumentException;

class CalculateScore
{
    public function hundredths(string $value): int
    {
        if (! preg_match('/^\\d{1,2}(?:\\.\\d{1,2})?$/D', $value)) {
            throw new InvalidArgumentException('Invalid decimal score.');
        }
        [$whole,$fraction] = array_pad(explode('.', $value), 2, '');

        return ((int) $whole) * 100 + (int) str_pad($fraction, 2, '0');
    }

    public function format(int $micros): string
    {
        return intdiv($micros, 1000000).'.'.str_pad((string) ($micros % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function micros(string $decimal): int
    {
        [$whole,$fraction] = array_pad(explode('.', $decimal), 2, '');

        return (int) $whole * 1000000 + (int) str_pad($fraction, 6, '0');
    }

    /**
     * @param  array<int, string>  $accuracyPenalties
     * @param  array<int, string>  $presentationComponents
     * @param  array<string, mixed>  $rules
     * @return array{accuracy:int, presentation:int, breakdown:array<string, mixed>}
     */
    public function detailedInput(array $accuracyPenalties, array $presentationComponents, array $rules): array
    {
        if (($rules['input_method'] ?? null) !== 'deductions_and_components_v1'
            || (int) ($rules['accuracy_max'] ?? 0) !== 400
            || (int) ($rules['presentation_max'] ?? 0) !== 600
            || ! array_is_list($accuracyPenalties)
            || count($accuracyPenalties) > 40
            || ! array_is_list($presentationComponents)
            || count($presentationComponents) !== 3) {
            throw new InvalidArgumentException('Invalid detailed scoring rules or fields.');
        }

        $penalties = [];
        foreach ($accuracyPenalties as $penalty) {
            if (! in_array($penalty, ['0.10', '0.30'], true)) {
                throw new InvalidArgumentException('Invalid accuracy deduction.');
            }
            $penalties[] = $this->hundredths($penalty);
        }
        $accuracy = 400 - array_sum($penalties);
        if ($accuracy < 0) {
            throw new InvalidArgumentException('Accuracy deductions exceed four points.');
        }

        $components = [];
        foreach ($presentationComponents as $component) {
            if (! is_string($component)) {
                throw new InvalidArgumentException('Invalid presentation component.');
            }
            $value = $this->hundredths($component);
            if ($value > 200) {
                throw new InvalidArgumentException('Presentation component exceeds two points.');
            }
            $components[] = $value;
        }
        $presentation = array_sum($components);

        return [
            'accuracy' => $accuracy,
            'presentation' => $presentation,
            'breakdown' => [
                'method' => 'deductions_and_components_v1',
                'accuracy_penalties_hundredths' => $penalties,
                'presentation_components_hundredths' => $components,
                'accuracy_hundredths' => $accuracy,
                'presentation_hundredths' => $presentation,
            ],
        ];
    }

    /** @param array<string, mixed> $calculationSnapshot */
    public function restoredMeanMicros(array $calculationSnapshot): ?int
    {
        $discard = (int) ($calculationSnapshot['rules']['discard_each_end'] ?? -1);
        if (! in_array($discard, [0, 1, 2], true)) {
            throw new InvalidArgumentException('Invalid trimming rule in calculation snapshot.');
        }
        if ($discard === 0) {
            return null;
        }

        $judgeCount = (int) ($calculationSnapshot['judge_count'] ?? 0);
        if (! in_array($judgeCount, [5, 7], true) || ($discard === 2 && $judgeCount !== 7)) {
            throw new InvalidArgumentException('Invalid judge count in calculation snapshot.');
        }

        $scores = array_fill(0, $judgeCount, []);
        foreach (['accuracy', 'presentation'] as $criterion) {
            $values = $calculationSnapshot['components'][$criterion]['sorted_hundredths'] ?? null;
            if (! is_array($values) || count($values) !== $judgeCount || ! array_is_list($values)) {
                throw new InvalidArgumentException('Incomplete score components in calculation snapshot.');
            }
            foreach ($values as $index => $value) {
                if (! is_int($value)) {
                    throw new InvalidArgumentException('Invalid score component in calculation snapshot.');
                }
                $scores[$index][$criterion] = $value;
            }
        }

        $rules = [...$calculationSnapshot['rules'], 'discard_each_end' => 0];

        return $this->micros($this->calculate($scores, $rules, $judgeCount)['score']);
    }

    /** @param array<int,array{accuracy:int,presentation:int}> $scores */
    public function calculate(array $scores, array $rules, int $judgeCount): array
    {
        if (! in_array($judgeCount, [5, 7], true) || count($scores) !== $judgeCount || ($rules['algorithm'] ?? '') !== 'component_trimmed_mean_v1') {
            throw new InvalidArgumentException('Incomplete panel or unsupported rules.');
        }
        $discard = (int) $rules['discard_each_end'];
        if (! in_array($discard, [0, 1, 2], true) || ($discard === 2 && $judgeCount !== 7)) {
            throw new InvalidArgumentException('Invalid trimming rule.');
        }
        $total = 0;
        $details = [];
        foreach (['accuracy', 'presentation'] as $criterion) {
            $values = [];
            foreach ($scores as $score) {
                $value = $score[$criterion] ?? -1;
                if (! is_int($value) || $value < 0 || $value > $rules[$criterion.'_max']) {
                    throw new InvalidArgumentException('Score outside bounds.');
                }
                $values[] = $value;
            }
            sort($values, SORT_NUMERIC);
            $kept = array_slice($values, $discard, count($values) - 2 * $discard);
            $mean = intdiv(array_sum($kept) * 10000 + intdiv(count($kept), 2), count($kept));
            $total += $mean;
            $details[$criterion] = ['sorted_hundredths' => $values, 'kept_hundredths' => $kept, 'mean' => $this->format($mean)];
        }

        return ['score' => $this->format($total), 'components' => $details, 'rules' => $rules, 'judge_count' => $judgeCount];
    }
}
