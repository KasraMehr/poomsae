<script setup>
import { useForm } from "@inertiajs/vue3";
import FormErrors from "../common/FormErrors.vue";
import Button from "../common/Button.vue";
const props = defineProps({
    category: Object,
    tournament: Object,
    base: String,
});
const entry = useForm({
    first_name: "",
    last_name: "",
    birth_date: "",
    gender: props.category.gender === "female" ? "female" : "male",
    club: "",
});
const status = useForm({ status: "" });
const schedule = useForm({
    court_id: props.tournament.courts[0]?.id || "",
    judge_ids: [],
});
const setStatus = (id, value) => {
    status.status = value;
    status.patch(props.base + "/entries/" + id + "/status", {
        preserveScroll: true,
    });
};
const scheduleRound = () =>
    schedule.post(props.base + "/categories/" + props.category.id + "/rounds", {
        preserveScroll: true,
    });
</script>
<template>
    <div>
        <section
            class="rounded-xl border border-[#e2e9df] bg-white p-5 min-[761px]:p-[27px] mb-6"
            v-if="!category.rounds.length"
        >
            <h2>ثبت ورزشکار</h2>
            <p class="text-xs text-[#8b988a]">
                سن در روز شروع مسابقه محاسبه می‌شود. بعد از ساخت قرعه، فهرست رده
                قفل خواهد شد.
            </p>
            <form
                @submit.prevent="
                    entry.post(
                        base + '/categories/' + category.id + '/entries',
                        {
                            preserveScroll: true,
                            onSuccess: () => entry.reset(),
                        },
                    )
                "
                class="mt-[18px] grid grid-cols-1 gap-[18px] min-[761px]:grid-cols-3"
            >
                <div>
                    <label :for="'first-' + category.id">نام</label
                    ><input
                        :id="'first-' + category.id"
                        v-model="entry.first_name"
                        required
                        maxlength="100"
                    />
                </div>
                <div>
                    <label :for="'last-' + category.id">نام خانوادگی</label
                    ><input
                        :id="'last-' + category.id"
                        v-model="entry.last_name"
                        required
                        maxlength="100"
                    />
                </div>
                <div>
                    <label :for="'birth-' + category.id"
                        >تاریخ تولد (میلادی)</label
                    ><input
                        :id="'birth-' + category.id"
                        v-model="entry.birth_date"
                        type="date"
                        required
                        :max="tournament.starts_on"
                        dir="ltr"
                    />
                </div>
                <div>
                    <label :for="'sex-' + category.id">جنسیت</label
                    ><select :id="'sex-' + category.id" v-model="entry.gender">
                        <option value="male">مرد</option>
                        <option value="female">زن</option>
                    </select>
                </div>
                <div>
                    <label :for="'club-' + category.id">باشگاه</label
                    ><input
                        :id="'club-' + category.id"
                        v-model="entry.club"
                        maxlength="100"
                    />
                </div>
                <div class="flex items-end">
                    <Button type="submit" :disabled="entry.processing"
                        >ثبت ورزشکار</Button
                    >
                </div>
                <FormErrors class="col-span-full" :errors="entry.errors" />
            </form>
        </section>
        <section
            class="rounded-xl border border-[#e2e9df] bg-white p-5 min-[761px]:p-[27px] mb-6"
        >
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h2>فهرست و پذیرش ورزشکاران</h2>
                <span
                    class="inline-block rounded-[5px] bg-[#f2f4ed] px-[9px] py-[3px] text-[10px] text-[#81896b]"
                    >{{
                        category.entries.filter(
                            (e) => e.status === "checked_in",
                        ).length
                    }}
                    نفر حاضر</span
                >
            </div>
            <FormErrors :errors="status.errors" />
            <div class="overflow-x-auto">
                <table>
                    <thead>
                        <tr>
                            <th>ورزشکار</th>
                            <th>باشگاه</th>
                            <th>وضعیت</th>
                            <th v-if="!category.rounds.length">پذیرش</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="athlete in category.entries"
                            :key="athlete.id"
                        >
                            <td>{{ athlete.name }}</td>
                            <td>{{ athlete.club || "—" }}</td>
                            <td>
                                {{
                                    {
                                        registered: "در انتظار حضور",
                                        checked_in: "حاضر",
                                        withdrawn: "منصرف",
                                        disqualified: "حذف‌شده",
                                    }[athlete.status]
                                }}
                            </td>
                            <td
                                v-if="!category.rounds.length"
                                class="flex flex-wrap items-center gap-2.5"
                            >
                                <Button
                                    v-if="athlete.status !== 'checked_in'"
                                    size="small"
                                    :disabled="status.processing"
                                    @click="setStatus(athlete.id, 'checked_in')"
                                    >حاضر</Button
                                ><Button
                                    v-if="athlete.status !== 'withdrawn'"
                                    variant="secondary"
                                    size="small"
                                    :disabled="status.processing"
                                    @click="setStatus(athlete.id, 'withdrawn')"
                                    >انصراف</Button
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="!category.entries.length" class="text-xs text-[#8b988a]">
                هنوز ورزشکاری ثبت نشده است.
            </p>
        </section>
        <section
            class="rounded-xl border border-[#e2e9df] bg-white p-5 min-[761px]:p-[27px] mb-6"
            v-if="
                !category.completed &&
                (!category.rounds.length || category.format === 'knockout')
            "
        >
            <h2>
                {{
                    category.rounds.length
                        ? "ساخت دور بعد"
                        : "ساخت قرعه و برنامهٔ اجرا"
                }}
            </h2>
            <p class="text-xs text-[#8b988a]">
                {{
                    category.rounds.length
                        ? "پس از قطعی‌شدن برندهٔ تمام رقابت‌های دور قبل، دور بعد ساخته می‌شود."
                        : "تنها ورزشکاران حاضر وارد جدول می‌شوند. قرعه فقط یک بار ساخته می‌شود."
                }}
            </p>
            <form @submit.prevent="scheduleRound">
                <label :for="'schedule-court-' + category.id">زمین</label
                ><select
                    :id="'schedule-court-' + category.id"
                    v-model.number="schedule.court_id"
                    required
                >
                    <option value="" disabled>انتخاب زمین</option>
                    <option
                        v-for="court in tournament.courts"
                        :key="court.id"
                        :value="court.id"
                    >
                        {{ court.name }}
                    </option>
                </select>
                <p class="my-5 mb-2.5 text-xs text-[#5f7a60]">
                    پنل {{ category.judge_count }} نفره ·
                    {{ schedule.judge_ids.length }} انتخاب شده
                </p>
                <div class="flex flex-wrap gap-3">
                    <label
                        v-for="judge in tournament.members.filter(
                            (m) => m.role === 'judge' && m.is_active,
                        )"
                        :key="judge.id"
                        class="flex cursor-pointer items-center gap-[9px] text-xs"
                        ><input
                            v-model="schedule.judge_ids"
                            type="checkbox"
                            :value="judge.id"
                        />{{ judge.name }}</label
                    >
                </div>
                <p class="text-xs text-[#8b988a]">
                    ترتیب انتخاب، شمارهٔ صندلی داور را مشخص می‌کند.
                </p>
                <FormErrors :errors="schedule.errors" /><Button
                    type="submit"
                    :disabled="
                        schedule.processing ||
                        schedule.judge_ids.length !== category.judge_count
                    "
                    >تأیید و ساخت جدول</Button
                >
            </form>
        </section>
    </div>
</template>
