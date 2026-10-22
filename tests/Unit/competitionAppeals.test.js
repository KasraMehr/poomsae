import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { SourceTextModule, SyntheticModule, createContext } from 'node:vm';
import { computed, reactive } from 'vue';
import { scoreComponentsComplete, scoreDetailFields, scorePayload } from '../../resources/js/Shared/Services/scoring.js';

async function decisionForm(method = 'deductions_and_components_v1') {
    const requests = [];
    const freestyle = method === 'components_and_deductions_v1';
    const score = {
        judge_assignment_id: 101, revision: 2, seat: 1,
        values: freestyle ? { accuracy: 450, presentation: 390 } : { accuracy: 340, presentation: 600 },
        breakdown: freestyle
            ? { accuracy_components_hundredths: [150, 150, 150], presentation_penalties_hundredths: [10] }
            : { accuracy_penalties_hundredths: [30, 30], presentation_components_hundredths: [200, 200, 200] },
    };
    const props = { endpoint: '/tournaments/1/appeals/5/decision', appeal: { id: 5, version: 2, current: { rules: { input_method: method }, scores: [score, { ...score, judge_assignment_id: 102, seat: 2 }] } } };
    const useForm = defaults => {
        const form = reactive({ ...defaults, processing: false, errors: {} });
        let transform = data => data;
        form.transform = callback => { transform = callback; return form; };
        form.post = url => {
            const data = Object.fromEntries(Object.entries(form).filter(([key, value]) => !['processing', 'errors'].includes(key) && typeof value !== 'function'));
            requests.push({ url, data: JSON.parse(JSON.stringify(transform(data))) });
        };
        return form;
    };
    const context = createContext({ defineProps: () => props });
    const source = (await readFile(new URL('../../resources/js/Components/competition/AppealDecisionForm.vue', import.meta.url), 'utf8')).match(/<script setup>([\s\S]*?)<\/script>/)[1];
    const module = new SourceTextModule(source + '\nexport { form, corrections, ready, submit };', { context });
    const imports = { vue: { computed, reactive }, '@inertiajs/vue3': { useForm }, '../../Shared/Services/scoring': { scoreComponentsComplete, scoreDetailFields, scorePayload } };
    await module.link(specifier => {
        const values = imports[specifier] || { default: {} };
        return new SyntheticModule(Object.keys(values), function () { for (const [key, value] of Object.entries(values)) this.setExport(key, value); }, { context });
    });
    await module.evaluate();
    return { ...module.namespace, requests };
}

test('rejecting an appeal submits a reason and version without any selected correction', async () => {
    const editor = await decisionForm();
    editor.form.decision_reason = 'بررسی ناظر نشان می‌دهد نمره صحیح است';
    editor.corrections[0].selected = true;
    assert.equal(editor.ready.value, true);
    editor.submit();
    assert.equal(editor.requests[0].data.decision, 'rejected');
    assert.equal(editor.requests[0].data.expected_version, 2);
    assert.equal('corrections' in editor.requests[0].data, false);
});

test('acceptance requires a selected seat and submits only its reviewed detailed score', async () => {
    const editor = await decisionForm();
    editor.form.decision = 'accepted';
    editor.form.decision_reason = 'ناظر اصلاح نمرهٔ صندلی اول را تأیید می‌کند';
    assert.equal(editor.ready.value, false);
    editor.corrections[0].selected = true;
    editor.corrections[0].accuracy_penalties.push('0.10');
    editor.corrections[0].accuracy = '3.30';
    assert.equal(editor.ready.value, true);
    editor.submit();
    const corrections = editor.requests[0].data.corrections;
    assert.equal(corrections.length, 1);
    assert.equal(corrections[0].judge_assignment_id, 101);
    assert.equal(corrections[0].expected_revision, 2);
    assert.deepEqual(corrections[0].accuracy_penalties, ['0.30', '0.30', '0.10']);
    assert.equal(corrections[0].accuracy, '3.30');
    assert.equal('selected' in corrections[0], false);
    assert.equal('seat' in corrections[0], false);
});

test('freestyle decisions preserve accuracy components and presentation deductions', async () => {
    const editor = await decisionForm('components_and_deductions_v1');
    editor.form.decision = 'accepted';
    editor.form.decision_reason = 'اصلاح مؤلفه‌های نمرهٔ اجرای ابداعی تأیید شد';
    editor.corrections[1].selected = true;
    editor.submit();
    const correction = editor.requests[0].data.corrections[0];
    assert.equal(correction.judge_assignment_id, 102);
    assert.deepEqual(correction.accuracy_components, ['1.50', '1.50', '1.50']);
    assert.deepEqual(correction.presentation_penalties, ['0.10']);
    assert.equal('accuracy_penalties' in correction, false);
    assert.equal('presentation_components' in correction, false);
});
