<script setup lang="ts">
import type { AppointmentSlot, Waitlist } from "~/types/booking";

const props = withDefaults(
  defineProps<{
    date: string;
    therapistId: number | null;
    readonly?: boolean;
    authToken?: string;
  }>(),
  {
    readonly: false,
    authToken: "",
  },
);

const emit = defineEmits<{
  select: [slot: AppointmentSlot];
  waitlisted: [waitlist: Waitlist];
  openWaitlist: [slot: AppointmentSlot];
}>();

const config = useRuntimeConfig();
const { showToast } = useToast();
const selectedTime = ref<string | null>(null);
const registeringSlotId = ref<number | null>(null);
const registeredWaitlists = ref<Record<number, Waitlist>>({});

const query = computed(() => ({
  date: props.date,
  therapist_id: props.therapistId || undefined,
}));

const enabled = computed(() => Boolean(props.date && props.therapistId));
const slotKey = computed(
  () => `slots-${props.date}-${props.therapistId || "none"}`,
);

const { data, pending, refresh } = await useAsyncData(
  slotKey,
  () => {
    if (!enabled.value) {
      return Promise.resolve({ data: [] as AppointmentSlot[] });
    }

    return $fetch<{ data: AppointmentSlot[] }>(
      `${config.public.apiBase}/slots`,
      {
        query: query.value,
      },
    );
  },
  {
    watch: [() => props.date, () => props.therapistId],
  },
);

const slots = computed(() => data.value?.data || []);

function apiError(err: unknown) {
  const anyError = err as {
    data?: { message?: string; errors?: Record<string, string[]> };
    message?: string;
  };
  const firstValidationError = anyError.data?.errors
    ? Object.values(anyError.data.errors)[0]?.[0]
    : null;

  return (
    firstValidationError ||
    anyError.data?.message ||
    anyError.message ||
    "通信に失敗しました。"
  );
}

function slotId(slot: AppointmentSlot) {
  return slot.appointment_slot_id || slot.id;
}

function slotTime(slot: AppointmentSlot) {
  return slot.time || slot.starts_at.slice(0, 5);
}

function localWaitlist(slot: AppointmentSlot) {
  const id = slotId(slot);

  return id ? registeredWaitlists.value[id] : null;
}

function selectSlot(slot: AppointmentSlot) {
  if (props.readonly || slot.status === "full") return;

  selectedTime.value = slotTime(slot);
  emit("select", slot);
}

async function joinWaitlist(slot: AppointmentSlot) {
  const id = slotId(slot);

  if (props.readonly) {
    emit("openWaitlist", slot);
    return;
  }

  if (!props.authToken) {
    showToast("ログイン後にキャンセル待ち登録できます。", "warning");
    return;
  }

  if (!id || registeringSlotId.value === id || localWaitlist(slot)) return;

  registeringSlotId.value = id;

  try {
    const response = await $fetch<{ data: Waitlist; message: string }>(
      `${config.public.apiBase}/waitlists`,
      {
        method: "POST",
        headers: { Authorization: `Bearer ${props.authToken}` },
        body: { slot_id: id },
      },
    );

    registeredWaitlists.value = {
      ...registeredWaitlists.value,
      [id]: response.data,
    };

    showToast(response.message || "キャンセル待ちに登録しました", "success");
    emit("waitlisted", response.data);
    await refresh();
  } catch (err) {
    showToast(apiError(err), "error");
  } finally {
    registeringSlotId.value = null;
  }
}

defineExpose({
  refresh,
});
</script>

<template>
  <div class="space-y-3">
    <div
      v-if="pending"
      class="flex items-center gap-2 text-sm font-medium text-[#2C5F8A]"
    >
      <span
        class="size-4 animate-spin rounded-full border-2 border-blue-100 border-t-[#2C5F8A]"
      />
      空き状況を確認中
    </div>

    <div
      v-else-if="!enabled"
      class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-500"
    >
      担当者と日付を選択してください。
    </div>

    <div v-else class="grid grid-cols-3 gap-3 lg:grid-cols-4">
      <article
        v-for="slot in slots"
        :key="`${slot.therapist_id}-${slotTime(slot)}`"
        class="rounded-lg border p-3 text-left transition-all duration-150 ease-in-out"
        :class="[
          selectedTime === slotTime(slot)
            ? 'border-[#2C5F8A] bg-[#2C5F8A] text-white'
            : slot.status === 'available'
              ? 'border-2 border-[#2C5F8A] bg-white text-[#2C5F8A] hover:bg-[#F0F7FF]'
              : 'cursor-not-allowed border-gray-200 bg-gray-100 text-gray-400',
        ]"
      >
        <button
          v-if="slot.status === 'available'"
          class="block min-h-11 w-full text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A]"
          type="button"
          :disabled="readonly"
          @click="selectSlot(slot)"
        >
          <div class="flex items-center justify-between gap-2">
            <span class="text-sm font-semibold">{{ slotTime(slot) }}</span>
            <span class="text-lg font-bold">○</span>
          </div>
          <p
            class="mt-1 text-xs"
            :class="
              selectedTime === slotTime(slot) ? 'text-blue-50' : 'text-gray-500'
            "
          >
            残り {{ slot.available_count ?? 0 }} 枠
          </p>
        </button>

        <div v-else class="space-y-2">
          <div class="flex items-center justify-between gap-2 line-through">
            <span class="text-sm font-semibold">{{ slotTime(slot) }}</span>
            <span class="text-lg font-bold">×</span>
          </div>
          <button
            v-if="localWaitlist(slot)"
            class="min-h-11 w-full rounded-lg border border-[#2C5F8A] bg-white px-3 py-2 text-xs font-semibold text-[#2C5F8A]"
            type="button"
            disabled
          >
            待機中 ({{ localWaitlist(slot)?.priority }}番目)
          </button>
          <button
            v-else
            class="min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition-all duration-150 ease-in-out hover:border-[#2C5F8A] hover:text-[#2C5F8A] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A] disabled:cursor-not-allowed disabled:opacity-50"
            type="button"
            :disabled="
              !readonly && (!slotId(slot) || registeringSlotId === slotId(slot))
            "
            @click="joinWaitlist(slot)"
          >
            <span v-if="readonly && (slot.waitlist_count || 0) > 0"
              >× (待{{ slot.waitlist_count }}名)</span
            >
            <span v-else-if="readonly">待機順を見る</span>
            <span v-else-if="registeringSlotId === slotId(slot)">登録中</span>
            <span v-else>キャンセル待ちに登録</span>
          </button>
        </div>
      </article>
    </div>
  </div>
</template>
