<script setup>
import { Link, usePage } from '@inertiajs/vue3';

defineProps({ connection: { type: String, default: 'polling' } });
const page = usePage();
</script>

<template>
    <div class="arena-shell">
        <header class="arena-topbar">
            <Link :href="page.props.urls.dashboard" class="arena-brand"><span>پ</span><div><b>POOMSAE</b><small>COMPETITION OS</small></div></Link>
            <div class="arena-live"><i :class="connection"></i><span>{{ connection === 'connected' ? 'MERCURE LIVE' : connection === 'connecting' ? 'CONNECTING SSE' : 'POLLING SAFE MODE' }}</span></div>
            <nav class="arena-actions"><slot name="actions" /></nav>
        </header>
        <main class="arena-workspace"><slot /></main>
        <footer class="arena-footer"><span>POOMSAE COMPETITION SYSTEM</span><span>LAN FIRST · AUDITED SCORING · LIVE COURT</span></footer>
    </div>
</template>
