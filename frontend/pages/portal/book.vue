<script setup lang="ts">
import type {
  Appointment,
  AppointmentSlot,
  Patient,
  Therapist,
} from "~/types/booking";

type LoginResponse = {
  token: string;
  user: Patient;
  can_book_rehab: boolean;
  message: string;
};

definePageMeta({
  layout: "portal",
});

const router = useRouter();
const config = useRuntimeConfig();
const { showToast } = useToast();
const { token, user, userType, loadSession, setSession } = useAuth();

const loginForm = reactive({
  card_number: "100001",
  birth_date: "1984-04-12",
});
const patient = computed(() =>
  userType.value === "patient" ? (user.value as Patient | null) : null,
);
const step = ref(1);
const selectedTherapistId = ref<number | null>(null);
const selectedDate = ref(new Date().toISOString().slice(0, 10));
const selectedSlot = ref<AppointmentSlot | null>(null);
const loading = ref(false);
const submitting = ref(false);

const { data: therapistResponse } = await useAsyncData(
  "portal-book-therapists",
  () => {
    return $fetch<{ data: Therapist[] }>(`${config.public.apiBase}/therapists`);
  },
);

const therapists = computed(() => therapistResponse.value?.data || []);
const selectedTherapist = computed(
  () =>
    therapists.value.find(
      (therapist) => therapist.id === selectedTherapistId.value,
    ) || null,
);
const canBook = computed(() => Boolean(patient.value?.is_diagnosed));

const steps = [
  { number: 1, label: "担当者選択" },
  { number: 2, label: "日時選択" },
  { number: 3, label: "予約確認" },
];

const dateOptions = computed(() =>
  Array.from({ length: 14 }, (_, index) => {
    const date = new Date();
    date.setDate(date.getDate() + index);
    const value = date.toISOString().slice(0, 10);
    const isSunday = date.getDay() === 0;
    const disabled = isSunday;

    return {
      value,
      disabled,
      label: new Intl.DateTimeFormat("ja-JP", {
        month: "numeric",
        day: "numeric",
      }).format(date),
      weekday: new Intl.DateTimeFormat("ja-JP", { weekday: "short" }).format(
        date,
      ),
    };
  }),
);

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

function initials(name: string) {
  return name.slice(0, 1);
}

function slotTime(slot?: AppointmentSlot | null) {
  return slot?.time || slot?.starts_at?.slice(0, 5) || "";
}

function dateNumber(value: string) {
  return new Date(`${value}T00:00:00`).getDate();
}

function isToday(value: string) {
  return value === new Date().toISOString().slice(0, 10);
}

async function loginPatient() {
  loading.value = true;

  try {
    const response = await $fetch<LoginResponse>(
      `${config.public.apiBase}/auth/patient`,
      {
        method: "POST",
        body: loginForm,
      },
    );

    setSession(response.token, "patient", response.user);
    selectedTherapistId.value =
      response.user.assigned_therapist_id || therapists.value[0]?.id || null;
    step.value = 1;

    if (!response.user.is_diagnosed) {
      showToast("初診診断後にご予約いただけます", "warning");
    }
  } catch (err) {
    showToast(apiError(err), "error");
  } finally {
    loading.value = false;
  }
}

function chooseTherapist(therapist: Therapist) {
  selectedTherapistId.value = therapist.id;
  selectedSlot.value = null;
}

function chooseDate(date: string, disabled: boolean) {
  if (disabled) return;

  selectedDate.value = date;
  selectedSlot.value = null;
}

function chooseSlot(slot: AppointmentSlot) {
  selectedSlot.value = slot;
}

async function confirmBooking() {
  if (!selectedTherapistId.value || !selectedSlot.value || submitting.value)
    return;

  submitting.value = true;

  try {
    const response = await $fetch<{ data: Appointment }>(
      `${config.public.apiBase}/portal/reservations`,
      {
        method: "POST",
        headers: { Authorization: `Bearer ${token.value}` },
        body: selectedSlot.value.appointment_slot_id
          ? { appointment_slot_id: selectedSlot.value.appointment_slot_id }
          : {
              therapist_id: selectedTherapistId.value,
              date: selectedDate.value,
              time: slotTime(selectedSlot.value),
            },
      },
    );

    localStorage.setItem(
      "portal_last_appointment",
      JSON.stringify(response.data),
    );
    await router.push("/portal/complete");
  } catch (err) {
    showToast(apiError(err), "error");
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  loadSession();

  if (patient.value) {
    selectedTherapistId.value =
      patient.value.assigned_therapist_id || therapists.value[0]?.id || null;
  }
});
</script>

<template>
  <div class="space-y-6 text-[#333333]">
    <header>
      <p class="text-sm font-semibold text-[#2C5F8A]">オンライン予約</p>
      <h1 class="mt-1 text-2xl font-bold text-gray-900">リハビリ予約</h1>
    </header>

    <section
      v-if="!token || userType !== 'patient'"
      class="rounded-lg border border-gray-100 bg-white p-5 shadow-sm"
    >
      <h2 class="text-lg font-bold">患者確認</h2>
      <form class="mt-4 space-y-4" @submit.prevent="loginPatient">
        <UiFormGrid v-slot="{ fieldClass, labelClass, controlClass }">
          <div :class="fieldClass">
            <label :class="labelClass" for="card-number">診察券番号</label>
            <input
              id="card-number"
              v-model="loginForm.card_number"
              :class="controlClass"
              type="text"
            />
          </div>
          <div :class="fieldClass">
            <label :class="labelClass" for="birth-date">生年月日</label>
            <input
              id="birth-date"
              v-model="loginForm.birth_date"
              :class="controlClass"
              type="date"
            />
          </div>
        </UiFormGrid>
        <button
          class="h-[52px] w-full rounded-lg bg-[#2C5F8A] px-4 text-base font-semibold text-white transition-all duration-150 ease-in-out hover:bg-[#4A90B8] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A] disabled:cursor-not-allowed disabled:opacity-50"
          type="submit"
          :disabled="loading"
        >
          予約へ進む
        </button>
      </form>
    </section>

    <template v-else>
      <nav
        class="flex gap-2 overflow-x-auto rounded-full bg-[#F5F7FA] p-1.5 shadow-sm scrollbar-hide"
        aria-label="予約ステップ"
      >
        <div
          v-for="item in steps"
          :key="item.number"
          class="flex min-h-11 min-w-32 flex-1 items-center justify-center gap-2 rounded-full px-3 text-sm font-bold transition-all duration-300 ease-in-out"
          :class="
            step === item.number
              ? 'bg-[#2C5F8A] text-white'
              : step > item.number
                ? 'bg-[#2C5F8A] text-white'
                : 'bg-gray-200 text-gray-400'
          "
        >
          <span
            class="grid size-6 place-items-center rounded-full text-xs"
            :class="
              step >= item.number
                ? 'bg-white/15 text-white'
                : 'bg-white text-gray-400'
            "
          >
            {{ step > item.number ? "✓" : item.number }}
          </span>
          <span class="whitespace-nowrap">{{ item.label }}</span>
        </div>
      </nav>

      <section
        v-if="!canBook"
        class="rounded-lg border-l-4 border-[#2C5F8A] bg-[#F0F7FF] p-5 text-sm font-semibold text-[#333333]"
      >
        初診診断後にご予約いただけます
      </section>

      <section v-else-if="step === 1" class="rounded-lg bg-[#F5F7FA] p-4">
        <h2 class="text-lg font-bold">担当者を選択</h2>
        <div class="mt-4 grid gap-3 lg:grid-cols-2">
          <button
            v-for="therapist in therapists"
            :key="therapist.id"
            class="flex min-h-20 items-center gap-4 rounded-lg border bg-white p-4 text-left shadow-sm transition-all duration-150 ease-in-out hover:border-[#2C5F8A] hover:shadow-md focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A]"
            :class="
              selectedTherapistId === therapist.id
                ? 'border-2 border-[#2C5F8A] bg-[#F0F7FF]'
                : 'border-gray-200'
            "
            type="button"
            @click="chooseTherapist(therapist)"
          >
            <span
              class="grid size-12 shrink-0 place-items-center rounded-full bg-[#2C5F8A] text-base font-semibold text-white"
              >{{ initials(therapist.name) }}</span
            >
            <span>
              <span class="block font-bold text-gray-900">{{
                therapist.name
              }}</span>
              <span class="mt-1 block text-sm text-gray-500">{{
                therapist.specialty || "リハビリ担当"
              }}</span>
              <span
                v-if="patient?.assigned_therapist_id === therapist.id"
                class="mt-2 inline-flex rounded-full bg-white px-2 py-1 text-xs font-bold text-[#2C5F8A]"
                >担当</span
              >
            </span>
          </button>
        </div>
        <div class="mt-5 flex justify-end">
          <button
            class="min-h-11 w-full rounded-lg bg-[#2C5F8A] px-5 py-2 text-sm font-semibold text-white transition-all duration-150 ease-in-out hover:bg-[#4A90B8] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A] disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
            type="button"
            :disabled="!selectedTherapistId"
            @click="step = 2"
          >
            次へ
          </button>
        </div>
      </section>

      <section v-else-if="step === 2" class="rounded-lg bg-[#F5F7FA] p-4">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold">日時を選択</h2>
            <p class="mt-1 text-sm text-gray-500">
              {{ selectedTherapist?.name }}
            </p>
          </div>
          <button
            class="min-h-11 rounded-lg px-3 text-sm font-semibold text-[#2C5F8A] transition-all duration-150 ease-in-out hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A]"
            type="button"
            @click="step = 1"
          >
            変更
          </button>
        </div>

        <div class="mt-4 flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
          <button
            v-for="date in dateOptions"
            :key="date.value"
            class="grid h-14 w-14 shrink-0 place-items-center rounded-lg px-2 text-sm transition-all duration-150 ease-in-out focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A] disabled:cursor-not-allowed"
            :class="
              date.disabled
                ? 'bg-gray-100 text-gray-300'
                : selectedDate === date.value
                  ? 'bg-[#2C5F8A] text-white'
                  : 'bg-white text-[#333333] hover:bg-[#F0F7FF]'
            "
            type="button"
            :disabled="date.disabled"
            @click="chooseDate(date.value, date.disabled)"
          >
            <span
              class="text-xs"
              :class="
                isToday(date.value) && selectedDate !== date.value
                  ? 'underline decoration-[#2C5F8A] decoration-2 underline-offset-4'
                  : ''
              "
              >{{ date.weekday }}</span
            >
            <span class="text-base font-bold leading-none">{{
              dateNumber(date.value)
            }}</span>
          </button>
        </div>

        <div class="mt-5">
          <ReservationSlotGrid
            :date="selectedDate"
            :therapist-id="selectedTherapistId"
            :readonly="false"
            :auth-token="token"
            @select="chooseSlot"
          />
        </div>

        <div class="mt-5 grid gap-3 sm:flex sm:justify-between">
          <button
            class="min-h-11 w-full rounded-lg border border-gray-300 bg-white px-5 py-2 text-sm font-semibold text-gray-600 transition-all duration-150 ease-in-out hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A] sm:w-auto"
            type="button"
            @click="step = 1"
          >
            戻る
          </button>
          <button
            class="min-h-11 w-full rounded-lg bg-[#2C5F8A] px-5 py-2 text-sm font-semibold text-white transition-all duration-150 ease-in-out hover:bg-[#4A90B8] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A] disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
            type="button"
            :disabled="!selectedDate || !selectedSlot"
            @click="step = 3"
          >
            次へ
          </button>
        </div>
      </section>

      <section
        v-else
        class="rounded-lg border border-gray-100 bg-white p-6 shadow-sm"
      >
        <h2 class="text-lg font-bold">予約確認</h2>
        <dl class="mt-4 grid gap-4 text-sm">
          <div>
            <dt class="text-sm text-gray-500">担当者</dt>
            <dd class="mt-1 font-medium text-gray-900">
              {{ selectedTherapist?.name }}
            </dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">日付</dt>
            <dd class="mt-1 font-medium text-gray-900">{{ selectedDate }}</dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">時間</dt>
            <dd class="mt-1 font-medium text-gray-900">
              {{ slotTime(selectedSlot) }}
            </dd>
          </div>
        </dl>
        <div class="mt-6 space-y-3">
          <button
            class="min-h-11 w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition-all duration-150 ease-in-out hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A]"
            type="button"
            @click="step = 2"
          >
            戻る
          </button>
          <button
            class="flex h-[52px] w-full items-center justify-center gap-2 rounded-lg bg-[#2C5F8A] px-4 text-sm font-semibold text-white transition-all duration-150 ease-in-out hover:bg-[#4A90B8] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A] disabled:cursor-not-allowed disabled:opacity-60"
            type="button"
            :disabled="submitting"
            @click="confirmBooking"
          >
            <span
              v-if="submitting"
              class="size-4 animate-spin rounded-full border-2 border-white/30 border-t-white"
            />
            <span>予約を確定する</span>
          </button>
        </div>
      </section>
    </template>

    <UiToastNotification />
  </div>
</template>
