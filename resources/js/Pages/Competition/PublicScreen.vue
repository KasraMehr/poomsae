<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useDisplayUpdates } from '../../Shared/Composables/useDisplayUpdates';

const props = defineProps({ display: Object, screen: String, dataUrl: String });
const { display } = useDisplayUpdates(props.display, props.dataUrl, false, 2000);
const tournament = computed(() => display.value.tournament);
const courts = computed(() => display.value.courts);
const title = computed(() => ({ tv_wall: 'TV WALL', warm_up: 'WARM UP', public: 'PUBLIC DISPLAY' })[props.screen] ?? 'DISPLAY');
const recent = computed(() => tournament.value.categories.flatMap(category => category.rounds.flatMap(round => round.bouts
    .filter(bout => bout.status === 'completed' && bout.performances?.some(performance => performance.result_published_at))
    .map(bout => ({ ...bout, category: category.name, round: round.name }))))
    .sort((left, right) => Math.max(...right.performances.map(item => Date.parse(item.result_published_at) || 0))
        - Math.max(...left.performances.map(item => Date.parse(item.result_published_at) || 0))).slice(0, 8));
const courtName = id => courts.value.find(court => court.id === id)?.name ?? '—';
const names = entries => (entries ?? []).map(entry => entry.short_name || entry.name).join(' / ') || '—';
</script>

<template>
    <main class="public-screen" dir="ltr">
        <Head :title="`${title} · ${tournament.name}`" />
        <header class="screen-header">
            <div><span class="eyebrow">POOMSAE · {{ title }}</span><h1>{{ tournament.name }}</h1></div>
            <div class="screen-meta"><strong>#{{ tournament.id }}</strong><span>{{ tournament.venue || '' }}</span></div>
        </header>

        <section v-if="screen !== 'public'" class="court-grid" :class="{ 'warm-up': screen === 'warm_up' }" aria-label="Court activity">
            <article v-for="court in courts" :key="court.id" class="court-card">
                <header><span>COURT</span><h2>{{ court.name }}</h2></header>
                <div v-if="screen === 'tv_wall'" class="court-current">
                    <span class="eyebrow">{{ court.current ? 'NOW ON COURT' : 'WAITING' }}</span>
                    <strong>{{ court.current ? names(court.current.entries) : 'Ready for the next match' }}</strong>
                    <p v-if="court.current">{{ court.current.category_name }} · {{ court.current.round_name }} · {{ court.current.form_name }}</p>
                </div>
                <div class="queue"><h3>{{ screen === 'warm_up' ? 'UP NEXT · WARM UP' : 'NEXT' }}</h3>
                    <ol><li v-for="(item, index) in court.upcoming" :key="item.id"><b>{{ String(index + 1).padStart(2, '0') }}</b><span>{{ names(item.entries) }}<small>{{ item.category_name }} · {{ item.form_name }}</small></span></li></ol>
                    <p v-if="!court.upcoming.length" class="empty">No upcoming matches</p>
                </div>
            </article>
            <p v-if="!courts.length" class="empty">No courts have been configured.</p>
        </section>

        <section v-if="screen === 'public'" class="public-content">
            <div class="live-list"><h2>NOW ON COURT</h2><div v-for="court in courts" :key="court.id" class="live-row"><span>{{ court.name }}</span><strong>{{ court.current ? names(court.current.entries) : 'WAITING' }}</strong><small>{{ court.current?.category_name || '' }}</small></div></div>
            <div class="results-list"><h2>RECENT RESULTS</h2><div v-for="bout in recent" :key="bout.id" class="result-row"><span>{{ courtName(bout.court_id) }}</span><strong>{{ names(bout.entries) }}</strong><small>{{ bout.category }} · {{ bout.round }}</small></div><p v-if="!recent.length" class="empty">Results will appear here after approval.</p></div>
        </section>
        <footer>LIVE COMPETITION DISPLAY <span>{{ title }}</span></footer>
    </main>
</template>

<style scoped>
.public-screen{min-height:100vh;padding:clamp(18px,2.5vw,42px);background:#0b1725;color:#f4f7fb;font:500 clamp(14px,1.1vw,20px)/1.4 system-ui,sans-serif}.screen-header{display:flex;justify-content:space-between;align-items:end;gap:24px;padding-bottom:22px;border-bottom:1px solid #3c5268}.eyebrow{color:#85cdb0;font-size:.72em;font-weight:800;letter-spacing:.16em}.screen-header h1{margin:8px 0 0;font-size:clamp(28px,3.4vw,58px);line-height:1.1}.screen-meta{display:grid;justify-items:end;color:#9eafc1}.screen-meta strong{color:#e6c785;font-size:1.4em}.court-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,380px),1fr));gap:16px;margin-top:24px}.court-card{min-width:0;border:1px solid #36516b;border-radius:14px;background:#122438;overflow:hidden}.court-card>header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px;background:#203b54}.court-card>header span,.queue h3,.public-content h2{color:#a7bacb;font-size:.74em;letter-spacing:.13em}.court-card h2{margin:0;font-size:1.35em}.court-current{min-height:150px;padding:22px 20px;border-bottom:1px solid #36516b}.court-current strong{display:block;margin-top:10px;font-size:clamp(22px,2.1vw,38px);line-height:1.16;overflow-wrap:anywhere}.court-current p{margin:10px 0 0;color:#b9c8d5}.queue{padding:18px 20px}.queue h3{margin:0 0 12px}.queue ol{margin:0;padding:0;list-style:none}.queue li{display:flex;gap:14px;padding:11px 0;border-top:1px solid #2c4157}.queue li b{color:#e6c785}.queue li span{overflow-wrap:anywhere}.queue li small{display:block;margin-top:3px;color:#95abc0}.empty{color:#8298ac}.warm-up .queue li{font-size:1.14em;padding:17px 0}.public-content{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-top:24px}.live-list,.results-list{padding:22px;border:1px solid #36516b;border-radius:14px;background:#122438}.public-content h2{margin:0 0 16px}.live-row,.result-row{display:grid;grid-template-columns:minmax(90px,20%) 1fr;gap:4px 16px;padding:16px 0;border-top:1px solid #2c4157}.live-row span,.result-row span{color:#e6c785}.live-row strong,.result-row strong{font-size:1.3em;overflow-wrap:anywhere}.live-row small,.result-row small{grid-column:2;color:#95abc0}.public-screen footer{display:flex;justify-content:space-between;gap:12px;margin-top:30px;color:#7290a8;font-size:.65em;letter-spacing:.15em}@media(max-width:760px){.public-content{grid-template-columns:1fr}.screen-header{align-items:start}.screen-meta{flex:none}}
</style>
