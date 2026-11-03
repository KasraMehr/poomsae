import assert from 'node:assert/strict';
import test from 'node:test';
import { courtResultFor, hasPublishedRanking } from '../../resources/js/Shared/Services/rtdsPresentation.js';

const focus = (id, category = { id: 7, rules: { aggregation: 'sum_two_forms' }, forms_per_round: 2 }) => ({
    category,
    bout: { id, status: 'completed', winner_entry_id: 11, entries: [{ id: 11 }, { id: 12 }], totals: { 11: '18.200000' } },
});

test('winner uses the official result when it belongs to this court bout', () => {
    const official = { status: 'completed', category_id: 7, bout_ids: [2], winner_entry_id: 11, round_count: 1 };
    assert.equal(courtResultFor(focus(2), official), official);
});

test('winner stays on this court even when global current_result points to another bout', () => {
    const result = courtResultFor(focus(3), { category_id: 7, bout_ids: [99], winner_entry_id: 88 });
    assert.deepEqual(result.bout_ids, [3]);
    assert.equal(result.winner_entry_id, 11);
    assert.equal(result.winner_total, '18.200000');
    assert.equal(result.total_max, 20);
});

test('ranking appears only after the category is complete and has placements', () => {
    assert.equal(hasPublishedRanking({ completed: false, placements: [] }), false);
    assert.equal(hasPublishedRanking({ completed: false, placements: [{ rank: 1 }] }), false);
    assert.equal(hasPublishedRanking({ completed: true, placements: [] }), false);
    assert.equal(hasPublishedRanking({ completed: true, placements: [{ rank: 1 }] }), true);
});

test('unfinished or single-entry bouts do not claim a winner', () => {
    const pending = focus(4);
    pending.bout.status = 'running';
    assert.equal(courtResultFor(pending, null), null);
    const solo = focus(5);
    solo.bout.entries = [{ id: 11 }];
    assert.equal(courtResultFor(solo, null), null);
});
