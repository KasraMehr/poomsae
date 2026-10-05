import assert from 'node:assert/strict';
import test from 'node:test';
import { adjustPenalties, hasScoreDetails, scoreComponentsComplete, scoreDetailFields, scorePayload } from '../../resources/js/scoring.js';

test('deduction controls add and restore tenths and thirds without crossing zero or four', () => {
    for (let total = 0; total <= 400; total += 10) {
        const penalties = [...Array(Math.floor(total / 30)).fill('0.30'), ...Array(total % 30 / 10).fill('0.10')];
        for (const amount of [-30, -10, 10, 30]) {
            const adjusted = adjustPenalties(penalties, amount);
            const expected = total - amount < 0 || total - amount > 400 ? total : total - amount;
            assert.equal(adjusted.reduce((sum, value) => sum + Math.round(Number(value) * 100), 0), expected);
            assert.ok(adjusted.every(value => ['0.10', '0.30'].includes(value)));
            assert.equal(penalties.reduce((sum, value) => sum + Math.round(Number(value) * 100), 0), total);
        }
    }
    assert.deepEqual(adjustPenalties(['0.30'], 10), ['0.10', '0.10']);
    assert.deepEqual(adjustPenalties(['invalid'], 10), ['invalid']);
});

test('freestyle draft reload and request use accuracy components and presentation deductions', () => {
    const method = 'components_and_deductions_v1';
    const fields = scoreDetailFields(method, { accuracy_components_hundredths: [200, 175, 175], presentation_penalties_hundredths: [30, 10] });
    const payload = scorePayload({ accuracy: '5.50', presentation: '3.60', ...fields }, method);
    assert.deepEqual(payload, { accuracy: '5.50', presentation: '3.60', accuracy_components: ['2.00', '1.75', '1.75'], presentation_penalties: ['0.30', '0.10'] });
    assert.equal(hasScoreDetails(payload, method), true);
    assert.equal(hasScoreDetails({ accuracy: '9.10', presentation: '0.00' }, method), false);
    assert.equal(scoreComponentsComplete(scoreDetailFields(method)), false);
    assert.equal(scoreComponentsComplete(payload), true);
});

test('standard and legacy freestyle payloads retain their existing formats', () => {
    const method = 'deductions_and_components_v1';
    const fields = scoreDetailFields(method, { accuracy_penalties_hundredths: [30], presentation_components_hundredths: [200, 150, 175] });
    assert.deepEqual(scorePayload({ accuracy: '3.70', presentation: '5.25', ...fields }, method), {
        accuracy: '3.70', presentation: '5.25', accuracy_penalties: ['0.30'], presentation_components: ['2.00', '1.50', '1.75'],
    });
    assert.deepEqual(scorePayload({ accuracy: '8.70', presentation: '', ...scoreDetailFields('single_score_v1') }, 'single_score_v1'), { score: '8.70' });
});
