<script setup lang="ts">
import { CalendarDaysIcon, UserIcon } from "@heroicons/vue/24/outline";
import type { Appointment, Patient, Waitlist } from "~/types/booking";

type LoginResponse = {
  token: string;
  user: Patient;
  can_book_rehab: boolean;
  message: string;
};

definePageMeta({
  layout: "portal",
});

const config = useRuntimeConfig();
const { showToast } = useToast();
const { token, user, userType, loadSession, setSession } = useAuth();

const loginForm = reactive({
  card_number: "100001",
  birth_date: "1984-04-12",
});
const reservations = ref<Appointment[]>([]);
const waitlists = ref<Waitlist[]>([]);
const loading = ref(false);
const cancelling = ref(false);
const deletingWaitlistId = ref<number | null>(null);
const confirmTarget = ref<Appointment | null>(null);

const patient = computed(() =>
  userType.value === "patient" ? (user.value as Patient | null) : null,
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

function slotTime(appointment: Appointment) {
  return (
    appointment.slot?.time || appointment.slot?.starts_at?.slice(0, 5) || ""
  );
}

function waitlistTime(waitlist: Waitlist) {
  return waitlist.slot?.time || waitlist.slot?.starts_at?.slice(0, 5) || "";
}

function appointmentDateTime(appointment: Appointment) {
  return new Date(`${appointment.slot.date}T${slotTime(appointment)}:00`);
}

function canCancel(appointment: Appointment) {
  const diffMs = appointmentDateTime(appointment).getTime() - Date.now();
  return diffMs > 24 * 60 * 60 * 1000;
}

async function fetchReservations() {
  if (!token.value) return;

  loading.value = true;

  try {
    const [reservationResponse, waitlistResponse] = await Promise.all([
      $fetch<{ data: Appointment[] }>(
        `${config.public.apiBase}/portal/my-reservations`,
        {
          headers: { Authorization: `Bearer ${token.value}` },
        },
      ),
      $fetch<{ data: Waitlist[] }>(
        `${config.public.apiBase}/portal/waitlists`,
        {
          headers: { Authorization: `Bearer ${token.value}` },
        },
      ),
    ]);

    reservations.value = reservationResponse.data;
    waitlists.value = waitlistResponse.data;
  } catch (err) {
    showToast(apiError(err), "error");
  } finally {
    loading.value = false;
  }
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
    await fetchReservations();
  } catch (err) {
    showToast(apiError(err), "error");
  } finally {
    loading.value = false;
  }
}

async function cancelReservation() {
  if (!confirmTarget.value) return;

  cancelling.value = true;

  try {
    await $fetch(
      `${config.public.apiBase}/portal/reservations/${confirmTarget.value.id}`,
      {
        method: "DELETE",
        headers: { Authorization: `Bearer ${token.value}` },
      },
    );

    showToast("予約をキャンセルしました", "success");
    confirmTarget.value = null;
    await fetchReservations();
  } catch (err) {
    showToast(apiError(err), "error");
  } finally {
    cancelling.value = false;
  }
}

async function cancelWaitlist(waitlist: Waitlist) {
  deletingWaitlistId.value = waitlist.id;

  try {
    await $fetch(`${config.public.apiBase}/portal/waitlists/${waitlist.id}`, {
      method: "DELETE",
      headers: { Authorization: `Bearer ${token.value}` },
    });

    waitlists.value = waitlists.value.filter((item) => item.id !== waitlist.id);
    showToast("キャンセル待ちを取り消しました", "success");
  } catch (err) {
    showToast(apiError(err), "error");
  } finally {
    deletingWaitlistId.value = null;
  }
}

onMounted(async () => {
  loadSession();

  if (token.value && userType.value === "patient") {
    await fetchReservations();
  }
});
</script>

<template>
  <div class="space-y-6 text-[#333333]">
    <header
      v-if="patient"
      class="-mx-4 border-b border-[#2C5F8A]/20 bg-[#F0F7FF] px-4 py-3 md:-mx-6 md:px-6"
    >
      <h1 class="font-semibold text-[#2C5F8A]">
        こんにちは、{{ patient.name }}さん
      </h1>
    </header>
    <header v-else>
      <p class="text-sm font-semibold text-[#2C5F8A]">マイページ</p>
      <h1 class="mt-1 text-2xl font-bold text-gray-900">患者確認</h1>
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
          class="h-[52px] w-full rounded-lg bg-[#2C5F8A] px-4 text-base font-semibold text-white transition-all duration-150 ease-in-out hover:bg-[#4A90B8] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A] disabled:opacity-50"
          type="submit"
          :disabled="loading"
        >
          確認する
        </button>
      </form>
    </section>

    <template v-else>
      <section>
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-lg font-bold">予約一覧</h2>
          <button
            class="min-h-11 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-600 transition-all duration-150 ease-in-out hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A]"
            type="button"
            @click="fetchReservations"
          >
            更新
          </button>
        </div>

        <div v-if="loading" class="mt-5 grid gap-3">
          <div
            v-for="index in 3"
            :key="index"
            class="h-28 animate-pulse rounded-lg bg-gray-100"
          />
        </div>

        <div v-else-if="reservations.length === 0" class="pt-16 text-center">
          <CalendarDaysIcon
            class="mx-auto size-12 text-gray-300"
            aria-hidden="true"
          />
          <p class="mt-3 font-semibold text-gray-500">現在予約はありません</p>
          <NuxtLink
            class="mt-4 inline-flex min-h-11 items-center justify-center rounded-lg bg-[#2C5F8A] px-6 py-2 text-sm font-semibold text-white transition-all duration-150 ease-in-out hover:bg-[#4A90B8] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A]"
            to="/portal/book"
          >
            新しく予約する
          </NuxtLink>
        </div>

        <div v-else>
          <article
            v-for="reservation in reservations"
            :key="reservation.id"
            class="mb-3 rounded-lg border border-gray-100 bg-white p-4 shadow-sm"
          >
            <div
              class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
              <div>
                <p class="text-lg font-bold text-gray-900">
                  {{ reservation.slot.date }} {{ slotTime(reservation) }}
                </p>
                <p
                  class="mt-2 inline-flex items-center gap-1.5 text-sm text-gray-600"
                >
                  <UserIcon class="size-4" aria-hidden="true" />
                  <span>{{ reservation.slot.therapist?.name || "-" }}</span>
                </p>
                <p class="mt-1 text-sm text-gray-500">
                  {{ reservation.slot.therapist?.specialty || "リハビリ担当" }}
                </p>
              </div>
              <button
                v-if="canCancel(reservation)"
                class="min-h-11 rounded-lg border border-red-200 bg-white px-3 py-1 text-sm font-semibold text-red-500 transition-all duration-150 ease-in-out hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#E74C3C]"
                type="button"
                @click="confirmTarget = reservation"
              >
                キャンセル
              </button>
              <p v-else class="mt-1 text-xs text-gray-400 sm:max-w-64">
                ※
                前日までキャンセル可能です。ご変更はお電話にてご連絡ください。03-5842-6311
              </p>
            </div>
          </article>
        </div>
      </section>

      <section
        v-if="waitlists.length > 0"
        class="rounded-lg border border-gray-100 bg-white p-5 shadow-sm"
      >
        <h2 class="text-lg font-bold">キャンセル待ち</h2>
        <div class="mt-4 grid gap-3">
          <article
            v-for="waitlist in waitlists"
            :key="waitlist.id"
            class="rounded-lg border border-amber-200 bg-amber-50 p-4"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="font-bold text-gray-900">
                  {{ waitlist.slot?.date }} {{ waitlistTime(waitlist) }}
                </p>
                <p class="mt-1 text-sm text-gray-600">
                  {{ waitlist.slot?.therapist?.name }}
                </p>
                <p class="mt-2 text-sm font-semibold text-amber-800">
                  {{ waitlist.priority }}番目
                </p>
              </div>
              <button
                class="min-h-11 rounded-lg border border-amber-300 bg-white px-3 py-2 text-sm font-semibold text-amber-800 transition-all duration-150 ease-in-out hover:bg-amber-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A] disabled:opacity-50"
                type="button"
                :disabled="deletingWaitlistId === waitlist.id"
                @click="cancelWaitlist(waitlist)"
              >
                取り消す
              </button>
            </div>
          </article>
        </div>
      </section>
    </template>

    <UiConfirmModal
      v-if="confirmTarget"
      title="予約キャンセル"
      message="この予約をキャンセルしますか？"
      confirm-label="キャンセルする"
      :is-destructive="true"
      @cancel="confirmTarget = null"
      @confirm="cancelReservation"
    />

    <UiToastNotification />
  </div>
</template>
