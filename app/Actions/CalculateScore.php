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

    /** @param array<int,array{accuracy:int,presentation:int}> $scores */
    public function calculate(array $scores, array $rules, int $judgeCount): array
    {
        if (! in_array($judgeCount, [5, 7], true) || count($scores) !== $judgeCount || ($rules['algorithm'] ?? '') !== 'component_trimmed_mean_v1') {
            throw new InvalidArgumentException('Incomplete panel or unsupported rules.');
        }
        $discard = (int) $rules['discard_each_end'];
        if (! in_array($discard, [0, 1], true)) {
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
