<script setup>
import { displayLabel } from './displayLabel';
defineProps({ match: { type: Object, default: null } });
defineEmits(['details']);
const number = value => value == null ? '—' : Number(value).toFixed(2);
const labels = { pending: 'Pending', running: 'Live', scoring: 'Awaiting confirmation', approved: 'Published', cancelled: 'Cancelled', completed: 'Completed' };
</script>

<template>
    <section class="confirmed-summary" aria-label="Published match results">
        <template v-if="match">
            <h2>Match {{ match.sequence }} · {{ labels[match.status] ?? match.status }}</h2>
            <p>{{ match.category_name }} · {{ displayLabel(match.round_name) }}</p>
            <p v-if="match.status !== 'completed'">Published performances are listed below. The match may still require more performances or a decision.</p>
            <div class="confirmed-entries" :class="{ solo: match.entries.length === 1 }">
                <article v-for="entry in match.entries" :key="entry.id">
                    <strong>{{ entry.short_name || entry.name }}</strong>
                    <span v-if="match.winner_entry_id === entry.id" class="winner">WINNER</span>
                    <span>Official total: <b>{{ number(match.totals?.[entry.id]) }}</b></span>
                    <ul>
                        <li v-for="item in match.performances.filter(item => item.entry_id === entry.id)" :key="item.id">
                            <span>FORM {{ item.form_number }} · {{ displayLabel(item.form_name) }}</span>
                            <span>{{ labels[item.status] ?? item.status }} · {{ item.status === 'approved' ? number(item.result) : '—' }}</span>
                        </li>
                    </ul>
                </article>
            </div>
            <button type="button" @click="$emit('details')">Match controls and decisions ↗</button>
        </template>
        <p v-else>Select a match to view its published results.</p>
    </section>
</template>

<style scoped>
.confirmed-summary{padding:18px;color:#0d192b;line-height:1.5}
.confirmed-summary h2{font-size:18px;margin:0 0 8px}
.confirmed-summary p{font-size:13px;color:#586478}
.confirmed-entries{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:16px 0}
.confirmed-entries.solo{grid-template-columns:minmax(0,680px);justify-content:center}
.confirmed-entries article{min-width:0;padding:16px;border:1px solid #c4c9d2;background:#edf2f9;display:grid;gap:8px;overflow-wrap:anywhere}
.confirmed-entries article>strong{font-size:18px}
.winner{color:#167651;font-weight:800}
.confirmed-entries ul{list-style:none;padding:0;margin:0}
.confirmed-entries li{display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;padding:8px 0;border-top:1px solid #c4c9d2;font-size:12px}
.confirmed-summary button{min-height:42px;padding:10px 14px;background:#e4eaf5;border:1px solid #bfc6d2;font-weight:700;cursor:pointer}
@media(max-width:600px){.confirmed-entries{grid-template-columns:minmax(0,1fr)}}
</style>
