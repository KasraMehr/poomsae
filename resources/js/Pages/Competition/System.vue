<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../Shared/Layouts/AppLayout.vue';

const props = defineProps({ tournament: Object, system: Object, backUrl: String });
const ready = computed(() => props.system.database === 'online' && props.system.realtime === 'online'
    && ['online', 'inline'].includes(props.system.queue_worker));
const checks = computed(() => [
    { name: 'Database', value: props.system.database, detail: 'Application data connection', healthy: props.system.database === 'online' },
    { name: 'Mercure', value: props.system.realtime, detail: props.system.realtime === 'online' ? 'Hub responded to a live check' : props.system.realtime === 'polling' ? 'Polling fallback is in use' : 'Hub is unavailable or misconfigured', healthy: props.system.realtime === 'online' },
    { name: 'Queue worker', value: props.system.queue_worker, detail: `Background jobs · ${props.system.queue} connection`, healthy: ['online', 'inline'].includes(props.system.queue_worker) },
    { name: 'Cache', value: props.system.cache, detail: 'Configured cache store', healthy: Boolean(props.system.cache) },
]);
const refreshedAt = computed(() => new Date(props.system.server_time).toLocaleString('en-GB'));
</script>

<template>
    <AppLayout>
        <Head :title="`System · ${tournament.name}`" />
        <div class="system-dashboard" dir="ltr">
            <header class="system-hero">
                <div><span>POOMSAE / SYSTEM</span><h1>Competition systems</h1><p>{{ tournament.name }}</p></div>
                <div class="system-actions"><Link :href="backUrl">← Back to competition</Link><button type="button" @click="router.reload({ only: ['system'] })">Refresh status</button></div>
            </header>

            <section class="status-summary"><div class="summary-icon" :class="{ offline: !ready }">{{ ready ? '✓' : '!' }}</div><div><span>CURRENT STATUS</span><h2>{{ ready ? 'Competition services ready' : 'Service attention needed' }}</h2><p>Last checked {{ refreshedAt }}</p></div><span class="summary-environment">{{ system.environment }}</span></section>

            <div class="system-section-heading"><div><span>INFRASTRUCTURE</span><h2>Service checks</h2></div><p>Configuration and connection status for this competition</p></div>
            <section class="checks-grid" aria-label="Service checks"><article v-for="check in checks" :key="check.name"><div class="check-top"><span>{{ check.name }}</span><i :class="{ healthy: check.healthy, warning: !check.healthy }"></i></div><strong>{{ check.value }}</strong><p>{{ check.detail }}</p></article></section>

            <div class="system-section-heading"><div><span>COMPETITION</span><h2>Event footprint</h2></div></div>
            <section class="footprint"><div><strong>{{ system.courts }}</strong><span>Courts</span></div><div><strong>{{ system.categories }}</strong><span>Categories</span></div><div><strong>{{ refreshedAt }}</strong><span>Server time</span></div></section>

            <aside class="system-note"><strong>Judge device presence</strong><p>Judge connection and scoring status are shown in the operator match control. With polling fallback, the system can receive scores but cannot confirm whether a judge device is currently online.</p></aside>
        </div>
    </AppLayout>
</template>

<style scoped>
.system-dashboard{display:grid;gap:22px;color:#19352f}.system-hero{display:flex;align-items:flex-end;justify-content:space-between;gap:22px;padding:28px 32px;border-radius:20px;background:linear-gradient(125deg,#123e36,#246b54);color:white}.system-hero span,.system-section-heading span,.status-summary span{font-size:10px;font-weight:800;letter-spacing:.16em}.system-hero span{color:#9ed4bb}.system-hero h1{margin:6px 0;font-size:clamp(25px,3vw,38px);line-height:1.3}.system-hero p{font-size:13px;color:#d3e9d8}.system-actions{display:flex;flex-wrap:wrap;gap:9px}.system-actions a,.system-actions button{display:inline-flex;align-items:center;min-height:40px;padding:8px 14px;border:1px solid #ffffff75;border-radius:9px;background:#ffffff1a;color:white;font-size:12px;font-weight:700}.system-actions a:hover,.system-actions button:hover{background:#ffffff30}.status-summary{display:flex;align-items:center;gap:17px;padding:20px 23px;border:1px solid #dbe8df;border-radius:16px;background:#f7fbf8}.summary-icon{display:grid;flex:none;place-items:center;width:46px;height:46px;border-radius:14px;background:#d7efe1;color:#266b4d;font-size:24px;font-weight:800}.summary-icon.offline{background:#f8e5e4;color:#a64848}.status-summary h2{font-size:19px;margin:2px 0}.status-summary p{font-size:11px;color:#788b7c}.status-summary span{color:#6c9076}.summary-environment{margin-left:auto;padding:7px 10px;border-radius:8px;background:white;text-transform:uppercase}.system-section-heading{display:flex;align-items:end;justify-content:space-between;gap:15px}.system-section-heading span{color:#629077}.system-section-heading h2{margin-top:4px;font-size:18px}.system-section-heading p{font-size:11px;color:#82918a}.checks-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.checks-grid article{min-width:0;min-height:147px;padding:18px;border:1px solid #e0e9e2;border-radius:15px;background:white}.check-top{display:flex;justify-content:space-between;align-items:center;color:#687d70;font-size:12px}.check-top i{width:9px;height:9px;border-radius:50%}.check-top i.healthy{background:#389f69;box-shadow:0 0 0 4px #e3f5e9}.check-top i.warning{background:#d99b48;box-shadow:0 0 0 4px #fff2df}.checks-grid strong{display:block;margin:17px 0 5px;font-size:22px;line-height:1.2;text-transform:capitalize;overflow-wrap:anywhere}.checks-grid p{font-size:11px;color:#819087}.footprint{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.footprint>div{display:flex;flex-direction:column;gap:5px;padding:16px 19px;border:1px solid #e0e9e2;border-radius:14px;background:white}.footprint strong{font-size:24px;line-height:1.35;overflow-wrap:anywhere}.footprint span{font-size:11px;color:#71877a}.system-note{padding:16px 19px;border-left:3px solid #4c9870;border-radius:0 10px 10px 0;background:#eef6ef}.system-note strong{font-size:12px}.system-note p{margin-top:4px;font-size:11px;color:#6a8270}
@media(max-width:1050px){.checks-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.system-hero{align-items:flex-start;flex-direction:column}}
@media(max-width:620px){.system-dashboard{gap:16px}.system-hero{padding:20px}.system-actions{width:100%}.system-actions a,.system-actions button{flex:1;justify-content:center}.footprint{grid-template-columns:1fr}.status-summary{align-items:flex-start;flex-wrap:wrap}.summary-environment{margin-left:0}.system-section-heading{align-items:flex-start;flex-direction:column}.checks-grid{gap:8px}.checks-grid article{padding:12px}}
</style>
