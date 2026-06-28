<script setup lang="ts">
import type { Appointment } from "~/types/booking";

definePageMeta({
  layout: "portal",
});

const appointment = ref<Appointment | null>(null);

function slotTime(appointmentValue: Appointment) {
  return (
    appointmentValue.slot?.time ||
    appointmentValue.slot?.starts_at?.slice(0, 5) ||
    ""
  );
}

onMounted(() => {
  const saved = localStorage.getItem("portal_last_appointment");
  appointment.value = saved ? JSON.parse(saved) : null;
});
</script>

<template>
  <div class="mx-auto max-w-md pt-16">
    <section class="text-center">
      <div
        class="mx-auto grid size-20 place-items-center rounded-full bg-[#27AE60] animate-[checkCircle_400ms_ease-out_forwards]"
      >
        <span
          class="block size-9 rotate-45 border-b-4 border-r-4 border-white"
        />
      </div>
      <h1 class="mt-6 text-2xl font-bold text-gray-900">
        ご予約が完了しました
      </h1>

      <dl
        v-if="appointment"
        class="mt-6 grid gap-4 rounded-lg border border-gray-100 bg-white p-6 text-left text-sm shadow-sm"
      >
        <div>
          <dt class="text-gray-500">担当者</dt>
          <dd class="mt-1 font-medium text-gray-900">
            {{ appointment.slot?.therapist?.name || "-" }}
          </dd>
        </div>
        <div>
          <dt class="text-gray-500">日付</dt>
          <dd class="mt-1 font-medium text-gray-900">
            {{ appointment.slot?.date }}
          </dd>
        </div>
        <div>
          <dt class="text-gray-500">時間</dt>
          <dd class="mt-1 font-medium text-gray-900">
            {{ slotTime(appointment) }}
          </dd>
        </div>
      </dl>

      <NuxtLink
        class="mt-6 inline-flex h-[52px] w-full items-center justify-center rounded-lg bg-[#2C5F8A] px-5 text-sm font-semibold text-white transition-all duration-150 ease-in-out hover:bg-[#4A90B8] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A]"
        to="/portal/mypage"
      >
        マイページへ
      </NuxtLink>
    </section>
  </div>
</template>

<style scoped>
@keyframes checkCircle {
  from {
    opacity: 0;
    transform: scale(0);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}
</style>
