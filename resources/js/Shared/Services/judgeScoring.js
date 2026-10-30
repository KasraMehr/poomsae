import { hasScoreDetails, scoreDetailFields } from './scoring.js';

export const scoreDecimal = value => (value / 100).toFixed(2);

export function scoreHundredths(value) {
    if (value === '' || value === null || value === undefined) return null;
    const number = Number(value);
    return Number.isFinite(number) ? Math.round(number * 100) : null;
}

export function stepScore(value, delta, maximum) {
    return scoreDecimal(Math.max(0, Math.min(maximum, (scoreHundredths(value) ?? 0) + delta)));
}

export function judgeCriteria(rules) {
    const method = rules.input_method;
    if (method === 'single_score_v1') return [{ field: 'accuracy', label: 'Score', maximum: rules.accuracy_max ?? 1000, type: 'direct' }];
    return ['accuracy', 'presentation'].map(field => {
        const penalty = field === 'accuracy' ? method === 'deductions_and_components_v1' : method === 'components_and_deductions_v1';
        const parts = method === 'freestyle_components_v1' || (field === 'accuracy' ? method === 'components_and_deductions_v1' : method === 'deductions_and_components_v1');
        return {
            field, label: field === 'accuracy' ? 'Accuracy' : 'Presentation',
            maximum: rules[`${field}_max`], type: penalty ? 'penalty' : parts ? 'components' : 'direct',
            componentMaximum: rules[`${field}_component_max`] ?? 200,
        };
    });
}

export function criterionScore(form, criterion) {
    if (criterion.type === 'penalty') return criterion.maximum - (form[`${criterion.field}_penalties`] ?? []).reduce((sum, value) => sum + (scoreHundredths(value) ?? 0), 0);
    if (criterion.type === 'components') {
        const parts = form[`${criterion.field}_components`] ?? [];
        const values = parts.map(scoreHundredths);
        return !parts.length || values.some(value => value === null || value < 0 || value > criterion.componentMaximum) ? null : values.reduce((sum, value) => sum + value, 0);
    }
    const value = scoreHundredths(form[criterion.field]);
    return value === null || value < 0 || value > criterion.maximum ? null : value;
}

export function judgeScoreInitial(performance, rules, requestId) {
    const own = performance.own_score;
    const breakdown = own?.breakdown?.method === rules.input_method ? own.breakdown : null;
    return {
        request_id: requestId, expected_version: performance.version, expected_revision: own?.revision ?? 0,
        accuracy: own ? scoreDecimal(own.values.accuracy ?? own.values.score ?? 0) : '',
        presentation: own ? scoreDecimal(own.values.presentation ?? 0) : '',
        ...scoreDetailFields(rules.input_method, breakdown), reason: '',
    };
}

export function isLiveFinish(previous, performance) {
    return previous.status === 'running' && performance.status === 'scoring'
        && performance.version === previous.version + 1 && previous.started_at === performance.started_at;
}

export function restoreJudgeDraft(cached, performance, rules) {
    if (cached && !cached.data) {
        return cached.expected_version === performance.version
            && cached.expected_revision === (performance.own_score?.revision ?? 0)
            && hasScoreDetails(cached, rules.input_method) ? cached : null;
    }
    if (!cached || cached.rules !== JSON.stringify(rules) || !cached.data || !hasScoreDetails(cached.data, rules.input_method)) return null;
    if (cached.data.expected_revision !== (performance.own_score?.revision ?? 0)) return null;
    if (cached.data.expected_version !== performance.version && !isLiveFinish({ ...cached, version: cached.data.expected_version }, performance)) return null;
    return { ...cached.data, expected_version: performance.version };
}
