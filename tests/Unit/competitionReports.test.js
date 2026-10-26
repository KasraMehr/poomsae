import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { createContext, SourceTextModule, SyntheticModule } from 'node:vm';
import { computed, effectScope, nextTick, reactive, watch } from 'vue';

async function loadReports(type = 'judges') {
    const props = reactive({
        type, title: 'گزارش', tournament: { id: 6, status: 'completed' },
        filters: { judge_id: 17 }, report: { decimal_columns: [], numeric_columns: [] },
        reportUrls: { judges: '/judges', audit: '/audit', medals: '/medals', archive: '/archive', restore: '/restore' },
    });
    const requests = [];
    const useForm = initial => {
        let defaults = structuredClone(initial);
        let transform = value => value;
        const state = reactive({ ...initial });
        Object.assign(state, {
            defaults(value) { defaults = structuredClone(value); },
            reset(...fields) {
                for (const key of fields.length ? fields : Object.keys(defaults)) state[key] = defaults[key];
            },
            transform(callback) { transform = callback; return state; },
        });
        for (const method of ['get', 'post']) state[method] = (url, options) => {
            const data = Object.fromEntries(Object.keys(defaults).map(key => [key, state[key]]));
            requests.push({ method, url, data: transform(data), options });
        };
        return state;
    };
    const imports = {
        vue: { computed, watch },
        '@inertiajs/vue3': { Head: {}, Link: {}, router: { get() {} }, useForm, usePoll() {} },
        '../../Shared/Layouts/AppLayout.vue': { default: {} },
        '../../Shared/Components/FormErrors.vue': { default: {} },
        '../../Components/competition/AuditSnapshot.vue': { default: {} },
        '../../Shared/Composables/useCompetitionUpdates': {
            useCompetitionUpdates() {},
        },
    };
    const context = createContext({ defineProps: () => props, window: { print() {} } });
    const source = await readFile(new URL('../../resources/js/Pages/Competition/Reports.vue', import.meta.url), 'utf8');
    const script = source.match(/<script setup>([\s\S]*?)<\/script>/)[1];
    const module = new SourceTextModule(script + '\nexport { filters, archive, applyFilters, updateArchive };', { context });
    await module.link(specifier => new SyntheticModule(Object.keys(imports[specifier]), function () {
        for (const [name, value] of Object.entries(imports[specifier])) this.setExport(name, value);
    }, { context }));
    const scope = effectScope();
    await scope.run(() => module.evaluate());
    return { props, requests, api: module.namespace, stop: () => scope.stop() };
}

test('report filtering keeps only relevant filters and restores them after a server response', async t => {
    const editor = await loadReports();
    t.after(editor.stop);
    editor.api.filters.category_id = 22;
    editor.api.filters.action = 'tournament.updated';
    editor.api.applyFilters();
    assert.equal(editor.requests[0].url, '/judges');
    assert.deepEqual(JSON.parse(JSON.stringify(editor.requests[0].data)), { category_id: 22, judge_id: 17, from: '', to: '' });
    editor.props.filters = { category_id: 22, judge_id: 19 };
    await nextTick();
    assert.equal(editor.api.filters.judge_id, 19);
    assert.equal(editor.api.filters.action, '');
});

test('archive and restore submit the current status and clear the completed reason', async t => {
    const editor = await loadReports();
    t.after(editor.stop);
    editor.api.archive.reason = 'Final results reviewed';
    editor.api.updateArchive();
    assert.equal(editor.requests[0].url, '/archive');
    assert.deepEqual(JSON.parse(JSON.stringify(editor.requests[0].data)), { expected_status: 'completed', reason: 'Final results reviewed' });
    editor.props.tournament.status = 'archived';
    await nextTick();
    assert.equal(editor.api.archive.expected_status, 'archived');
    assert.equal(editor.api.archive.reason, '');
    editor.api.archive.reason = 'Return for review';
    editor.api.updateArchive();
    assert.equal(editor.requests[1].url, '/restore');
    assert.equal(editor.requests[1].data.expected_status, 'archived');
    editor.requests[1].options.onSuccess();
    assert.equal(editor.api.archive.reason, '');
});

test('medal filters cannot accidentally carry judge or audit restrictions', async t => {
    const editor = await loadReports('medals');
    t.after(editor.stop);
    editor.api.filters.category_id = 22;
    editor.api.filters.from = '2026-10-07';
    editor.api.applyFilters();
    assert.deepEqual(JSON.parse(JSON.stringify(editor.requests[0].data)), { category_id: 22 });
});
