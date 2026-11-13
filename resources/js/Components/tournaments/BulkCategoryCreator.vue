<script setup>
import { computed, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import CheckboxField from "../../Shared/Components/CheckboxField.vue";
import DropdownSelect from "../../Shared/Components/DropdownSelect.vue";
import FormErrors from "../../Shared/Components/FormErrors.vue";

const props = defineProps({
    ageCategories: Array,
    tournament: Object,
    base: String,
});
const form = useForm({
    age_category_ids: [],
    genders: ["male", "female"],
    format: "knockout",
    judge_count: 5,
    discard_each_end: 1,
    execution_mode: "alternating",
    performance_order: "consecutive",
    planned_round_count: 1,
    form_draw_timing: "morning",
    form_draw_time: "08:00",
    rules_acknowledged: false,
});
watch(
    () => form.judge_count,
    (count) => {
        form.discard_each_end = count === 3 ? 0 : 1;
    },
);
const groups = computed(() => [
    {
        title: "استاندارد · انفرادی",
        items: props.ageCategories.filter(
            (item) =>
                item.discipline === "recognized" &&
                item.entry_type === "individual",
        ),
    },
    {
        title: "استاندارد · دو نفره",
        items: props.ageCategories.filter(
            (item) =>
                item.discipline === "recognized" && item.entry_type === "pair",
        ),
    },
    {
        title: "استاندارد · تیمی سه نفره",
        items: props.ageCategories.filter(
            (item) =>
                item.discipline === "recognized" && item.entry_type === "team",
        ),
    },
    {
        title: "ابداعی · انفرادی",
        items: props.ageCategories.filter(
            (item) =>
                item.discipline === "freestyle" &&
                item.entry_type === "individual",
        ),
    },
    {
        title: "ابداعی · دو نفره",
        items: props.ageCategories.filter(
            (item) =>
                item.discipline === "freestyle" && item.entry_type === "pair",
        ),
    },
]);
const existing = computed(
    () =>
        new Set(
            props.tournament.categories.map(
                (category) =>
                    `${category.rules?.age_category?.id}:${category.gender}`,
            ),
        ),
);
const pendingCount = computed(() =>
    form.age_category_ids.reduce(
        (count, id) =>
            count +
            form.genders.filter(
                (gender) => !existing.value.has(`${id}:${gender}`),
            ).length,
        0,
    ),
);
const toggleGroup = (group) => {
    const ids = group.items.map((item) => item.id);
    form.age_category_ids = ids.every((id) =>
        form.age_category_ids.includes(id),
    )
        ? form.age_category_ids.filter((id) => !ids.includes(id))
        : [...new Set([...form.age_category_ids, ...ids])];
};
const clearGroupSelection = (group) => {
    const ids = new Set(group.items.map((item) => item.id));
    form.age_category_ids = form.age_category_ids.filter((id) => !ids.has(id));
};
const hasSelectedCategoryInGroup = (group) =>
    group.items.some((item) => form.age_category_ids.includes(item.id));
const clearSelectedCategories = () => {
    form.age_category_ids = [];
};
const submit = () =>
    form.post(`${props.base}/categories/bulk`, {
        preserveScroll: true,
        onSuccess: () => {
            form.age_category_ids = [];
            form.rules_acknowledged = false;
        },
    });
</script>

<template>
    <section class="bulk-panel wide">
        <div class="bulk-heading">
            <div>
                
                <p>
                    رده‌های دلخواه را یک‌جا انتخاب کنید. رده‌هایی که قبلاً برای
                    همین جنسیت ساخته شده‌اند دوباره ایجاد نمی‌شوند.
                </p>
            </div>
            <div class="bulk-heading-actions">
                <strong>{{ pendingCount.toLocaleString("fa-IR") }} ردهٔ جدید</strong>
                <button
                    type="button"
                    class="bulk-clear-selection"
                    :disabled="!form.age_category_ids.length"
                    @click="clearSelectedCategories"
                >
                    حذف همه
                </button>
            </div>
        </div>
        <form @submit.prevent="submit">
            <div class="bulk-options">
                <fieldset>
                    <legend>جنسیت</legend>
                    <CheckboxField
                        id="bulk-gender-female"
                        v-model="form.genders"
                        value="female"
                        label="بانوان"
                        variant="chip"
                    /><CheckboxField
                        id="bulk-gender-male"
                        v-model="form.genders"
                        value="male"
                        label="آقایان"
                        variant="chip"
                    />
                </fieldset>
                <DropdownSelect
                    id="bulk-format"
                    v-model="form.format"
                    label="مدل برگزاری"
                    :options="[
                        { value: 'knockout', label: 'تک‌حذفی' },
                        { value: 'round_robin', label: 'دوره‌ای' },
                    ]"
                />
                <DropdownSelect
                    id="bulk-judges"
                    v-model.number="form.judge_count"
                    label="تعداد داور"
                    :options="
                        [3, 5, 7].map((value) => ({
                            value,
                            label: `${value} داور`,
                        }))
                    "
                />
                <DropdownSelect
                    id="bulk-discard"
                    v-model.number="form.discard_each_end"
                    label="حذف نمره از هر طرف"
                    :options="
                        form.judge_count === 3
                            ? [{ value: 0, label: 'بدون حذف' }]
                            : form.judge_count === 5
                              ? [{ value: 1, label: 'یک نمره' }]
                              : [
                                    { value: 1, label: 'یک نمره' },
                                    { value: 2, label: 'دو نمره' },
                                ]
                    "
                />
                <DropdownSelect
                    v-if="form.format === 'knockout'"
                    id="bulk-execution"
                    v-model="form.execution_mode"
                    label="اجرای استاندارد تک‌حذفی"
                    :options="[
                        { value: 'alternating', label: 'نوبتی' },
                        { value: 'simultaneous', label: 'همزمان' },
                    ]"
                />
                <DropdownSelect
                    v-else
                    id="bulk-order"
                    v-model="form.performance_order"
                    label="ترتیب دوره‌ای"
                    :options="[
                        { value: 'consecutive', label: 'فرم‌ها پشت‌سرهم' },
                        { value: 'phased', label: 'فرم اول همه، سپس فرم دوم' },
                    ]"
                />
                <div v-if="form.format === 'round_robin'">
                    <label for="bulk-rounds">تعداد مراحل</label
                    ><input
                        id="bulk-rounds"
                        v-model.number="form.planned_round_count"
                        type="number"
                        min="1"
                        max="20"
                        required
                    />
                </div>
                <DropdownSelect
                    id="bulk-draw-timing"
                    v-model="form.form_draw_timing"
                    label="زمان قرعهٔ پومسه"
                    :options="[
                        { value: 'day_before', label: 'روز قبل' },
                        { value: 'morning', label: 'صبح مسابقه' },
                        { value: 'before_stage', label: 'قبل از هر مرحله' },
                    ]"
                />
                <div>
                    <label for="bulk-draw-time">ساعت قرعه</label
                    ><input
                        id="bulk-draw-time"
                        v-model="form.form_draw_time"
                        type="time"
                        required
                    />
                </div>
            </div>
            <div class="bulk-groups">
                <fieldset v-for="group in groups" :key="group.title">
                    <legend>
                        <span>{{ group.title }}</span>
                        <div class="bulk-group-actions">
                            <button type="button" class="bulk-group-select-all" @click="toggleGroup(group)">
                                انتخاب همه
                            </button>
                            <button
                                type="button"
                                class="bulk-group-clear"
                                :disabled="!hasSelectedCategoryInGroup(group)"
                                @click="clearGroupSelection(group)"
                            >
                                حذف همه
                            </button>
                        </div>
                    </legend>
                    <CheckboxField
                        v-for="item in group.items"
                        :id="`bulk-age-category-${item.id}`"
                        :key="item.id"
                        v-model="form.age_category_ids"
                        :value="item.id"
                        :label="item.label"
                        variant="chip"
                    />
                </fieldset>
            </div>
            <CheckboxField
                id="bulk-rules-acknowledged"
                v-model="form.rules_acknowledged"
                label="قوانین امتیازدهی و شرایط رده‌های انتخاب‌شده را تأیید می‌کنم."
                required
                class="bulk-ack"
            />
            <FormErrors :errors="form.errors" />
            <button
                class="button"
                :disabled="
                    form.processing ||
                    !form.age_category_ids.length ||
                    !form.genders.length ||
                    !pendingCount
                "
            >
                ساخت {{ pendingCount.toLocaleString("fa-IR") }} رده
            </button>
        </form>
    </section>
</template>

<style scoped>
.bulk-panel {
    background: transparent;
}
.bulk-heading {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    align-items: start;
}
.bulk-heading h2 {
    margin: 4px 0 7px;
}
.bulk-heading p {
    font-size: 12px;
    color: #667a6d;
}
.bulk-heading-actions {
    display: flex;
    flex: none;
    align-items: center;
    gap: 8px;
}
.bulk-heading-actions > strong {
    padding: 8px 12px;
    border-radius: 9px;
    background: #e2f0e4;
    color: #286957;
    font-size: 12px;
}
.bulk-clear-selection {
    min-height: 34px;
    padding: 6px 10px;
    border: 1px solid #d7ded8;
    border-radius: 8px;
    background: white;
    color: #64756a;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}
.bulk-clear-selection:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}
.bulk-options {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin: 20px 0;
}
.bulk-options fieldset {
    display: flex;
    gap: 12px;
    align-items: center;
}
.bulk-options legend {
    font-size: 12px;
    font-weight: 700;
    color: #42664f;
}
.bulk-options label {
    font-size: 12px;
}
.bulk-options input[type="number"],
.bulk-options input[type="time"] {
    display: block;
    width: 100%;
    min-height: 40px;
    padding: 8px;
    border: 1px solid #cfd9cc;
    border-radius: 8px;
    background: white;
}
.bulk-groups {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}
.bulk-groups fieldset {
    min-width: 0;
    padding: 12px;
    border: 1px solid #d4e1d5;
    border-radius: 10px;
    background: white;
}
.bulk-groups legend button {
    padding: 3px 8px;
    border: 0;
    border-radius: 6px;
    background: #e8f3e9;
    color: #286957;
    font-size: 12px;
    font-weight: 750;
    cursor: pointer;
}
.bulk-group-actions {
    display: flex;
    flex: none;
    align-items: center;
    gap: 6px;
}
.bulk-groups legend .bulk-group-clear {
    background: #f1f3f5;
    color: #64748b;
}
.bulk-groups legend .bulk-group-clear:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}
.bulk-groups legend {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}
.bulk-groups legend > span {
    color: #29463b;
    font-size: 14px;
    font-weight: 800;
}
.bulk-groups label {
    display: flex;
    gap: 8px;
    align-items: center;
    padding: 5px 2px;
    font-size: 12px;
    cursor: pointer;
}
.bulk-ack {
    display: flex;
    gap: 8px;
    align-items: center;
    margin: 18px 0;
    font-size: 12px;
    font-weight: 700;
}
@media (max-width: 900px) {
    .bulk-options {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 650px) {
    .bulk-options,
    .bulk-groups {
        grid-template-columns: 1fr;
    }
    .bulk-heading {
        flex-wrap: wrap;
    }
}
</style>
