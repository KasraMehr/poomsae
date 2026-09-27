<script setup>
import { Link, usePage } from '@inertiajs/vue3';

defineProps({ connection: { type: String, default: 'polling' } });
const page = usePage();
const dotClasses = {
    connected: 'bg-[#3ce0a5] shadow-[0_0_13px_#3ce0a5]',
    connecting: 'bg-[#f3c85c] animate-[pulse-live_1.2s_infinite]',
};
</script>

<template>
    <div class="flex min-h-screen flex-col bg-[#050b14] text-[#ecf5ff]">
        <header class="sticky top-0 z-20 grid h-[82px] grid-cols-[1fr_auto_1fr] items-center border-b border-[#19283a] bg-[#08111ddd] px-[clamp(22px,4vw,64px)] backdrop-blur-[18px] max-[900px]:h-auto max-[900px]:min-h-[75px] max-[900px]:grid-cols-[1fr_auto] max-[900px]:py-3">
            <Link :href="page.props.urls.dashboard" class="flex items-center gap-[11px] justify-self-start [direction:ltr]">
                <span class="grid h-[42px] w-[42px] place-items-center rounded-[11px] bg-[linear-gradient(145deg,#10bdb6,#087873)] text-[24px] font-black text-white shadow-[0_8px_25px_#0aa49e35]">پ</span>
                <div class="grid leading-[1.2] max-[560px]:hidden"><b class="text-[12px] tracking-[2.5px]">POOMSAE</b><small class="text-[8px] tracking-[1.5px] text-[#71859a]">COMPETITION OS</small></div>
            </Link>
            <div class="flex items-center gap-[9px] rounded-[30px] border border-[#24364a] px-[14px] py-[7px] text-[9px] tracking-[1.5px] text-[#94a8bc] [direction:ltr] max-[900px]:hidden"><i class="h-2 w-2 rounded-full bg-[#6e7c8c]" :class="dotClasses[connection]"></i><span>{{ connection === 'connected' ? 'MERCURE LIVE' : connection === 'connecting' ? 'CONNECTING SSE' : 'POLLING SAFE MODE' }}</span></div>
            <nav class="flex items-center gap-2 justify-self-end [&_a]:rounded-lg [&_a]:border [&_a]:border-transparent [&_a]:px-[14px] [&_a]:py-[9px] [&_a]:text-[11px] [&_a]:text-[#9fb3c7] [&_a:hover]:border-[#2a4056] [&_a:hover]:bg-[#101d2c] [&_a:hover]:text-white max-[900px]:gap-[2px] max-[900px]:[&_a]:p-2 max-[560px]:[&_a]:text-[9px]"><slot name="actions" /></nav>
        </header>
        <main class="mx-auto w-full max-w-[1720px] flex-1 px-[clamp(18px,3.2vw,52px)] py-[28px] max-[560px]:px-3 max-[560px]:py-[18px]"><slot /></main>
        <footer class="flex justify-between border-t border-[#142236] px-[clamp(22px,4vw,64px)] py-5 text-[8px] tracking-[1.5px] text-[#4e6277] [direction:ltr] max-[900px]:hidden"><span>POOMSAE COMPETITION SYSTEM</span><span>LAN FIRST · AUDITED SCORING · LIVE COURT</span></footer>
    </div>
</template>
