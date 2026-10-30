import assert from 'node:assert/strict';
import test from 'node:test';
import { criterionScore, isLiveFinish, judgeCriteria, judgeScoreInitial, restoreJudgeDraft, stepScore } from '../../resources/js/Shared/Services/judgeScoring.js';

const recognized = { input_method: 'deductions_and_components_v1', accuracy_max: 400, presentation_max: 600, presentation_component_max: 200 };
const freestyle = { input_method: 'freestyle_components_v1', accuracy_max: 400, presentation_max: 600, accuracy_component_max: 100, presentation_component_max: 100 };
const performance = { id: 7, version: 2, status: 'running', started_at: '2026-10-30T12:00:00Z', own_score: null };

test('judge pad chooses the scoring controls required by each ruleset', () => {
    assert.deepEqual(judgeCriteria(recognized).map(item => item.type), ['penalty', 'components']);
    assert.deepEqual(judgeCriteria({ input_method: 'components_and_deductions_v1', accuracy_max: 600, presentation_max: 400 }).map(item => item.type), ['components', 'penalty']);
    assert.deepEqual(judgeCriteria(freestyle).map(item => item.type), ['components', 'components']);
    assert.deepEqual(judgeCriteria({ input_method: 'single_score_v1' }).map(item => item.type), ['direct']);
    const initial = judgeScoreInitial(performance, freestyle, 'request-1');
    assert.equal(initial.accuracy_components.length, 4);
    assert.equal(initial.presentation_components.length, 6);
    assert.equal(criterionScore(initial, judgeCriteria(freestyle)[0]), null);
});

test('score stepper clamps at zero and the criterion maximum', () => {
    assert.equal(stepScore('', 10, 100), '0.10');
    assert.equal(stepScore('0.00', -10, 100), '0.00');
    assert.equal(stepScore('0.95', 10, 100), '1.00');
    assert.equal(stepScore('1.00', 10, 100), '1.00');
});

test('a live draft survives normal finish but not a different revision or restart', () => {
    const data = judgeScoreInitial(performance, recognized, 'request-1');
    data.presentation_components = ['1.50', '1.50', '1.50'];
    const cached = { rules: JSON.stringify(recognized), status: 'running', started_at: performance.started_at, data };
    const finished = { ...performance, version: 3, status: 'scoring' };
    assert.equal(isLiveFinish({ ...performance }, finished), true);
    assert.deepEqual(restoreJudgeDraft(cached, finished, recognized), { ...data, expected_version: 3 });
    assert.equal(restoreJudgeDraft(cached, { ...finished, own_score: { revision: 1 } }, recognized), null);
    assert.equal(restoreJudgeDraft(cached, { ...finished, started_at: 'later' }, recognized), null);
    assert.equal(restoreJudgeDraft(cached, { ...finished, version: 4 }, recognized), null);
    assert.deepEqual(restoreJudgeDraft(data, performance, recognized), data);
    assert.equal(restoreJudgeDraft(data, finished, recognized), null);
});
