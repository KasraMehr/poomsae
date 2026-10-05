export function scoreDetailFields(method, breakdown) {
    return {
        accuracy_penalties: method === 'deductions_and_components_v1' ? (breakdown?.accuracy_penalties_hundredths || []).map(value => (value / 100).toFixed(2)) : null,
        presentation_components: method === 'deductions_and_components_v1' ? (breakdown?.presentation_components_hundredths || ['', '', '']).map(value => value === '' ? '' : (value / 100).toFixed(2)) : null,
        presentation_penalties: method === 'components_and_deductions_v1' ? (breakdown?.presentation_penalties_hundredths || []).map(value => (value / 100).toFixed(2)) : null,
        accuracy_components: method === 'components_and_deductions_v1' ? (breakdown?.accuracy_components_hundredths || ['', '', '']).map(value => value === '' ? '' : (value / 100).toFixed(2)) : null,
    };
}

export function hasScoreDetails(data, method) {
    if (method === 'deductions_and_components_v1') return Array.isArray(data?.accuracy_penalties) && Array.isArray(data?.presentation_components) && data.presentation_components.length === 3;
    if (method === 'components_and_deductions_v1') return Array.isArray(data?.presentation_penalties) && Array.isArray(data?.accuracy_components) && data.accuracy_components.length === 3;
    return true;
}

export function scoreComponentsComplete(data) {
    const components = data.accuracy_components ?? data.presentation_components;
    return !Array.isArray(components) || components.length === 3 && components.every(value => value !== '' && value !== null);
}

export function scorePayload(data, method) {
    const payload = { ...data, accuracy: String(data.accuracy), presentation: String(data.presentation) };
    if (method === 'single_score_v1') {
        payload.score = payload.accuracy;
        delete payload.accuracy;
        delete payload.presentation;
    }
    for (const field of ['accuracy_penalties', 'presentation_components', 'presentation_penalties', 'accuracy_components']) {
        if (!Array.isArray(data[field])) delete payload[field];
        else if (field.endsWith('_components')) payload[field] = data[field].map(value => Number(value).toFixed(2));
    }
    return payload;
}

export function adjustPenalties(penalties, increaseHundredths) {
    if (!penalties.every(value => ['0.10', '0.30'].includes(value))) return [...penalties];
    const total = penalties.reduce((sum, value) => sum + Math.round(Number(value) * 100), 0);
    if (![10, 30, -10, -30].includes(increaseHundredths) || total - increaseHundredths < 0 || total - increaseHundredths > 400) return [...penalties];
    if (increaseHundredths < 0) return [...penalties, (-increaseHundredths / 100).toFixed(2)];
    const result = [...penalties];
    let remaining = increaseHundredths;
    while (remaining > 0) {
        const penalty = Math.round(Number(result.pop()) * 100);
        if (penalty <= remaining) remaining -= penalty;
        else {
            result.push(...Array((penalty - remaining) / 10).fill('0.10'));
            remaining = 0;
        }
    }
    return result;
}
