import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import { SourceTextModule, SyntheticModule, createContext } from 'node:vm';

async function loadUpdates(file, echo, reload = () => {}) {
    const mounted = [];
    const unmounted = [];
    const context = createContext({ setTimeout, clearTimeout });
    const imports = {
        vue: { onMounted: callback => mounted.push(callback), onUnmounted: callback => unmounted.push(callback), ref: value => ({ value }) },
        '@inertiajs/vue3': { router: { reload } },
        '../Services/realtime': { getRealtime: () => echo },
    };
    const source = await readFile(
        new URL(
            `../../resources/js/Shared/Composables/${file}.js`,
            import.meta.url,
        ),
        'utf8',
    );
    const module = new SourceTextModule(source, { context });
    await module.link(specifier => {
        const exports = imports[specifier];
        return new SyntheticModule(Object.keys(exports), function () {
            for (const [name, value] of Object.entries(exports)) this.setExport(name, value);
        }, { context });
    });
    await module.evaluate();
    return { exports: module.namespace, mount: () => mounted.forEach(callback => callback()), unmount: () => unmounted.forEach(callback => callback()) };
}

test('live updates refresh after connection and coalesce events, then unsubscribe on leaving', async t => {
    t.mock.timers.enable({ apis: ['setTimeout'] });
    let statusListener;
    let eventListener;
    let disposed = false;
    const left = [];
    const reloads = [];
    const echo = {
        connector: { onConnectionChange: callback => { statusListener = callback; return () => { disposed = true; }; } },
        connectionStatus: () => 'connecting',
        private: name => {
            assert.equal(name, 'tournaments.4');
            return { listen: (event, callback) => { assert.equal(event, '.competition.updated'); eventListener = callback; } };
        },
        leave: name => left.push(name),
    };
    const module = await loadUpdates('useCompetitionUpdates', echo, options => reloads.push(options));
    const state = module.exports.useCompetitionUpdates(4, ['console']);
    module.mount();
    statusListener('connected');
    eventListener();
    eventListener();
    t.mock.timers.tick(200);
    assert.equal(state.value, 'connected');
    assert.equal(reloads.length, 1);
    assert.deepEqual(Array.from(reloads[0].only), ['console']);
    statusListener('reconnecting');
    assert.equal(state.value, 'polling');
    statusListener('connected');
    t.mock.timers.tick(200);
    assert.equal(reloads.length, 2);
    eventListener();
    module.unmount();
    t.mock.timers.tick(200);
    assert.equal(reloads.length, 2);
    assert.equal(disposed, true);
    assert.deepEqual(left, ['tournaments.4']);
});

test('when realtime is disabled the page remains in polling mode', async () => {
    const module = await loadUpdates('useCompetitionUpdates', null);
    const state = module.exports.useCompetitionUpdates(4);
    module.mount();
    assert.equal(state.value, 'polling');
    module.unmount();
});

test('judge presence handles connection and disconnection with numeric user identifiers', async () => {
    const callbacks = {};
    const left = [];
    const channel = {};
    for (const name of ['here', 'joining', 'leaving', 'error']) {
        channel[name] = callback => { callbacks[name] = callback; return channel; };
    }
    const echo = { join: name => { assert.equal(name, 'tournaments.4.judges'); return channel; }, leave: name => left.push(name) };
    const module = await loadUpdates('useJudgePresence', echo);
    const online = module.exports.useJudgePresence(4);
    module.mount();
    callbacks.here([{ id: '12' }]);
    callbacks.joining({ id: '12' });
    callbacks.joining({ id: '13' });
    assert.deepEqual(Array.from(online.value), [12, 13]);
    callbacks.leaving({ id: '12' });
    assert.deepEqual(Array.from(online.value), [13]);
    callbacks.error();
    assert.deepEqual(Array.from(online.value), []);
    module.unmount();
    assert.deepEqual(left, ['tournaments.4.judges']);
});
