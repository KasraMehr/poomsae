import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { SourceTextModule, SyntheticModule, createContext } from 'node:vm';
import { computed, effectScope, nextTick, reactive, ref, watch } from 'vue';

async function loadEditor(file, props, exports) {
    const requests = [];
    const useForm = defaults => {
        const state = reactive(structuredClone(defaults));
        let transform = data => data;
        Object.assign(state, {
            clearErrors() {},
            reset() { Object.assign(state, structuredClone(defaults)); },
            transform(callback) { transform = callback; return state; },
        });
        for (const method of ['post', 'put']) state[method] = (url, options) => {
            const data = Object.fromEntries(Object.keys(defaults).map(field => [field, JSON.parse(JSON.stringify(state[field]))]));
            requests.push({ method, url, data: transform(data), options });
        };
        return state;
    };
    const context = createContext({ defineProps: () => reactive(props) });
    const imports = {
        vue: { computed, nextTick, ref, watch },
        '@inertiajs/vue3': { useForm },
        '../Shared/Components/FormErrors.vue': { default: {} },
        '../../Shared/Components/FormErrors.vue': { default: {} },
    };
    const componentPath = file === 'CompetitionSetupPanel'
        ? 'tournaments/CompetitionSetupPanel'
        : file;
    const source = await readFile(
        new URL(`../../resources/js/Components/${componentPath}.vue`, import.meta.url),
        'utf8',
    );
    const script = source.match(/<script setup>([\s\S]*?)<\/script>/)[1];
    const module = new SourceTextModule(`${script}\nexport { ${exports.join(', ')} };`, { context });
    await module.link(specifier => new SyntheticModule(Object.keys(imports[specifier]), function () {
        for (const [name, value] of Object.entries(imports[specifier])) this.setExport(name, value);
    }, { context }));
    const scope = effectScope();
    await scope.run(() => module.evaluate());
    return { api: module.namespace, requests, stop: () => scope.stop() };
}

function setupProps() {
    return {
        base: '/tournaments/6', ageTableYear: 1405,
        ageCategories: [
            { id: 'recognized_individual_under_12', discipline: 'recognized', entry_type: 'individual', label: 'زیر ۱۲ سال', forms: [2, 3, 4, 5, 6, 7, 8, 9] },
            { id: 'recognized_individual_18_30', discipline: 'recognized', entry_type: 'individual', label: '۱۸ تا ۳۰ سال', forms: [7, 8, 9, 10, 11, 12, 13, 14] },
            { id: 'freestyle_pair_over_18', discipline: 'freestyle', entry_type: 'pair', label: 'زوج ابداعی', forms: [] },
        ],
        tournament: { courts: [], categories: [{
            id: 12, name: 'عنوان اختصاصی', discipline: 'recognized', entry_type: 'individual', gender: 'male',
            minimum_age: 18, maximum_age: 30, format: 'knockout', execution_mode: 'alternating', performance_order: 'consecutive',
            judge_count: 7, planned_round_count: 3, allow_form_repetition: true, form_draw_timing: 'morning', form_draw_time: '08:15:00',
            rules: { age_category: { id: 'recognized_individual_18_30' }, discard_each_end: 2 },
            form_pool: [{ name: 'فرم 8' }, { name: 'فرم 9' }], entries: [], settings_editable: true,
        }] },
    };
}

test('loading an existing category preserves its chosen forms, title and custom judge filtering', async t => {
    const editor = await loadEditor('CompetitionSetupPanel', setupProps(), ['category', 'loadCategory', 'submitCategory']);
    t.after(editor.stop);

    await editor.api.loadCategory(12);
    await nextTick();
    assert.equal(editor.api.category.name, 'عنوان اختصاصی');
    assert.equal(editor.api.category.age_category, 'recognized_individual_18_30');
    assert.deepEqual(Array.from(editor.api.category.selected_form_numbers), [8, 9]);
    assert.equal(editor.api.category.discard_each_end, 2);
    assert.equal(editor.api.category.form_draw_time, '08:15');
    editor.api.submitCategory();
    assert.equal(editor.requests[0].method, 'put');
    assert.equal(editor.requests[0].url, '/tournaments/6/categories/12');
    assert.deepEqual(Array.from(editor.requests[0].data.selected_form_numbers), [8, 9]);
});

test('loading freestyle pairs does not reset the entry type or submit recognized forms', async t => {
    const props = setupProps();
    Object.assign(props.tournament.categories[0], { discipline: 'freestyle', entry_type: 'pair', rules: { age_category: { id: 'freestyle_pair_over_18' }, discard_each_end: 1 }, form_pool: [] });
    const editor = await loadEditor('CompetitionSetupPanel', props, ['category', 'loadCategory', 'submitCategory']);
    t.after(editor.stop);

    await editor.api.loadCategory(12);
    await nextTick();
    assert.equal(editor.api.category.entry_type, 'pair');
    assert.equal(editor.api.category.age_category, 'freestyle_pair_over_18');
    editor.api.submitCategory();
    assert.equal(editor.requests[0].data.accuracy_max, 600);
    assert.equal(editor.requests[0].data.execution_mode, 'alternating');
    assert.equal('selected_form_numbers' in editor.requests[0].data, false);
    assert.equal('form_names' in editor.requests[0].data, false);
});

test('editing a legacy category keeps its two forms and uses the existing age range', async t => {
    const props = setupProps();
    Object.assign(props.tournament.categories[0], { planned_round_count: null, rules: { discard_each_end: 1 } });
    const editor = await loadEditor('CompetitionSetupPanel', props, ['category', 'loadCategory', 'submitCategory']);
    t.after(editor.stop);

    await editor.api.loadCategory(12);
    editor.api.submitCategory();
    const payload = editor.requests[0].data;
    assert.equal('age_category' in payload, false);
    assert.equal('planned_round_count' in payload, false);
    assert.equal(payload.minimum_age, 18);
    assert.deepEqual(Array.from(payload.form_names), ['فرم 8', 'فرم 9']);
});

test('cancelling category edit restores creation and submits to the create route', async t => {
    const editor = await loadEditor('CompetitionSetupPanel', setupProps(), ['category', 'loadCategory', 'submitCategory']);
    t.after(editor.stop);

    await editor.api.loadCategory(12);
    await editor.api.loadCategory(null);
    editor.api.submitCategory();
    assert.equal(editor.requests[0].method, 'post');
    assert.equal(editor.requests[0].url, '/tournaments/6/categories');
    assert.equal(editor.requests[0].data.age_category, 'recognized_individual_under_12');
    assert.deepEqual(Array.from(editor.requests[0].data.selected_form_numbers), [2, 3, 4, 5, 6, 7, 8, 9]);
});

test('athlete editing submits existing member identifiers and leaves the current display unchanged until saved', async t => {
    const member = { id: 25, first_name: 'علی', last_name: 'احمدی', birth_date: '2005-01-01', gender: 'male', club: 'باشگاه' };
    const entry = { id: 4, members: [member] };
    const editor = await loadEditor('EntryDetailsEditor', { entry, category: { registration_locked: true }, tournament: {}, base: '/tournaments/6' }, ['form', 'beginEdit', 'save']);
    t.after(editor.stop);

    editor.api.beginEdit();
    editor.api.form.members[0].last_name = 'نام صحیح';
    assert.equal(member.last_name, 'احمدی');
    editor.api.save();
    assert.equal(editor.requests[0].url, '/tournaments/6/entries/4');
    assert.equal(editor.requests[0].data.members[0].id, 25);
    assert.equal(editor.requests[0].data.members[0].birth_date, '2005-01-01');
    assert.equal(editor.requests[0].data.members[0].last_name, 'نام صحیح');
});
