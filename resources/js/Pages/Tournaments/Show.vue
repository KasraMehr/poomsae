<script setup>
import { useCompetitionUpdates } from "../../Shared/Composables/useCompetitionUpdates";
import { computed, ref } from "vue";
import { Head, Link, useForm, usePoll } from "@inertiajs/vue3";
import AppLayout from "../../Shared/Layouts/AppLayout.vue";
import CompetitionSetupPanel from "../../Components/tournaments/CompetitionSetupPanel.vue";
import CategoryRegistration from "../../Components/tournaments/CategoryRegistration.vue";
import BoutPanel from "../../Components/competition/BoutPanel.vue";
import FormErrors from "../../Shared/Components/FormErrors.vue";
const props = defineProps({
    tournament: Object,
    can: Object,
    competitionUrls: Object,
});
useCompetitionUpdates(props.tournament.id);
const tab = ref("setup");
const selectedId = ref(props.tournament.categories[0]?.id);
const selected = computed(
    () =>
        props.tournament.categories.find((c) => c.id === selectedId.value) ||
        props.tournament.categories[0],
);
const completion = useForm({});
const labels = {
    draft: "آماده‌سازی",
    ready: "آمادهٔ شروع",
    running: "در حال برگزاری",
    completed: "پایان‌یافته",
    archived: "آرشیو",
};
usePoll(5000, { only: ["tournament", "can"] });
</script>
<template>
    <AppLayout
        ><Head :title="tournament.name" />
        <section
            class="mb-6 flex flex-wrap items-center justify-between gap-4 border-b border-[#e2e9df] pb-5"
        >
            <div>
                <span class="text-[11px] tracking-wide text-[#618575]"
                    >آماده‌سازی مسابقه</span
                >
                <h1>{{ tournament.name }}</h1>
                <p class="text-xs text-[#8b988a]">
                    {{ tournament.venue || "محل تعیین نشده" }} ·
                    {{ labels[tournament.status] }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <Link
                    :href="competitionUrls.base"
                    class="inline-flex items-center justify-center rounded-lg bg-[#286957] px-[22px] py-[11px] text-[13px] text-white"
                    >بازگشت به میز اجرا</Link
                ><Link
                    :href="competitionUrls.scoreboard"
                    class="inline-flex items-center justify-center rounded-lg bg-[#e8eee7] px-[22px] py-[11px] text-[13px] text-[#42664f]"
                    >نمایشگر سالن</Link
                >
            </div>
        </section>
        <div class="mb-[25px] grid grid-cols-2 gap-2.5 min-[501px]:grid-cols-4">
            <span
                class="rounded-lg bg-[#e9eee7] p-3.5 text-center text-xs text-[#8b9685]"
                :class="
                    tournament.courts.length
                        ? 'bg-[#d9ecdf] text-[#2e7050]'
                        : ''
                "
                >۱ · زمین و داور</span
            ><span
                class="rounded-lg bg-[#e9eee7] p-3.5 text-center text-xs text-[#8b9685]"
                :class="
                    tournament.categories.length
                        ? 'bg-[#d9ecdf] text-[#2e7050]'
                        : ''
                "
                >۲ · رده و ثبت‌نام</span
            ><span
                class="rounded-lg bg-[#e9eee7] p-3.5 text-center text-xs text-[#8b9685]"
                :class="
                    tournament.categories.some((c) => c.rounds.length)
                        ? 'bg-[#d9ecdf] text-[#2e7050]'
                        : ''
                "
                >۳ · قرعه و اجرا</span
            ><span
                class="rounded-lg bg-[#e9eee7] p-3.5 text-center text-xs text-[#8b9685]"
                :class="
                    tournament.status === 'completed'
                        ? 'bg-[#d9ecdf] text-[#2e7050]'
                        : ''
                "
                >۴ · نتیجهٔ نهایی</span
            >
        </div>
        <nav class="mb-[25px] flex gap-2 border-b border-[#dbe4d6]">
            <button
                type="button"
                class="px-5 py-3 text-[#84947e]"
                :class="
                    tab === 'setup'
                        ? 'border-b-[3px] border-[#286957] font-bold text-[#285d40]'
                        : ''
                "
                @click="tab = 'setup'"
            >
                آماده‌سازی</button
            ><button
                type="button"
                class="px-5 py-3 text-[#84947e]"
                :class="
                    tab === 'control'
                        ? 'border-b-[3px] border-[#286957] font-bold text-[#285d40]'
                        : ''
                "
                @click="tab = 'control'"
            >
                رده‌ها و میز اجرا
            </button>
        </nav>
        <CompetitionSetupPanel
            v-if="
                tab === 'setup' &&
                can.manage &&
                tournament.status !== 'completed'
            "
            :tournament="tournament"
            :base="competitionUrls.base"
        />
        <div v-else>
            <div
                v-if="!tournament.categories.length"
                class="rounded-xl border border-dashed border-[#ccd9ca] bg-[#fafcf8] px-5 py-[50px] text-center"
            >
                <h2>ابتدا ردهٔ مسابقه را بسازید</h2>
                <p>از آماده‌سازی، زمین، داور و رده را تعریف کنید.</p>
            </div>
            <template v-if="selected">
                <div class="my-5 flex flex-wrap items-center gap-[18px]">
                    <label for="active-category">ردهٔ فعال</label
                    ><select id="active-category" v-model.number="selectedId">
                        <option
                            v-for="category in tournament.categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option></select
                    ><span
                        class="inline-block rounded-[5px] bg-[#f2f4ed] px-[9px] py-[3px] text-[10px] text-[#81896b]"
                        >{{ selected.judge_count }} داور ·
                        {{
                            selected.format === "knockout"
                                ? "تک‌حذفی"
                                : "دورهای"
                        }}</span
                    >
                </div>
                <details
                    class="mb-6 rounded-xl border border-[#e2e9df] bg-white p-5 min-[761px]:p-[27px]"
                    v-if="can.manage && tournament.status !== 'completed'"
                    :open="!selected.rounds.length"
                >
                    <summary>ثبت ورزشکار، پذیرش و ساخت دور</summary>
                    <CategoryRegistration
                        :key="selected.id"
                        :category="selected"
                        :tournament="tournament"
                        :base="competitionUrls.base"
                    />
                </details>
                <div
                    v-if="selected.completed"
                    class="mb-5 rounded-lg bg-[#e1f1e7] px-[18px] py-3 text-[#386748]"
                >
                    این رده پایان یافته است.<span v-if="selected.champion_id">
                        قهرمان:
                        {{
                            selected.entries.find(
                                (e) => e.id === selected.champion_id,
                            )?.name
                        }}</span
                    >
                </div>
                <section v-for="round in selected.rounds" :key="round.id">
                    <div
                        class="mb-5 flex flex-wrap items-center justify-between gap-3"
                    >
                        <h2>{{ round.name }}</h2>
                        <span class="text-xs text-[#8b988a]"
                            >{{ round.bouts.length }} رقابت</span
                        >
                    </div>
                    <BoutPanel
                        v-for="bout in round.bouts"
                        :key="bout.id"
                        :bout="bout"
                        :category="selected"
                        :tournament="tournament"
                        :base="competitionUrls.base"
                        :can-operate="can.operate"
                    />
                </section>
                <section
                    v-if="selected.standings.length"
                    class="mb-6 rounded-xl border border-[#e2e9df] bg-white p-5 min-[761px]:p-[27px]"
                >
                    <h2>
                        جدول بردها
                        {{ selected.completed ? "· پایان رده" : "· موقت" }}
                    </h2>
                    <p class="text-xs text-[#8b988a]">
                        برد مساوی، رتبهٔ مشترک دارد؛ tie-break جدول دورهای
                        خودکار اعمال نمی‌شود.
                    </p>
                    <table>
                        <thead>
                            <tr>
                                <th>رتبه</th>
                                <th>ورزشکار</th>
                                <th>رقابت پایان‌یافته</th>
                                <th>برد</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="entry in selected.standings"
                                :key="entry.id"
                            >
                                <td>{{ entry.rank }}</td>
                                <td>{{ entry.name }}</td>
                                <td>{{ entry.played }}</td>
                                <td>{{ entry.wins }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </template>
        </div>
        <section
            v-if="can.manage && tournament.status === 'running'"
            class="mb-6 rounded-xl border border-[#e2e9df] bg-white p-5 min-[761px]:p-[27px]"
        >
            <h2>پایان مسابقه</h2>
            <p class="text-xs text-[#8b988a]">
                فقط وقتی همهٔ رده‌ها، از جمله فینال تک‌حذفی، پایان یافته‌اند
                فعال می‌شود.
            </p>
            <FormErrors :errors="completion.errors" /><button
                type="button"
                class="inline-flex items-center justify-center rounded-lg bg-[#286957] px-[22px] py-[11px] text-[13px] text-white"
                :disabled="
                    completion.processing ||
                    !tournament.categories.length ||
                    !tournament.categories.every((c) => c.completed)
                "
                @click="
                    completion.post(competitionUrls.base + '/complete', {
                        preserveScroll: true,
                    })
                "
            >
                تأیید پایان مسابقه
            </button>
        </section>
    </AppLayout>
</template>
