<script setup lang="ts">
import type { Appointment } from '~/types/booking'

definePageMeta({
  layout: 'portal',
})

const appointment = ref<Appointment | null>(null)

function slotTime(appointmentValue: Appointment) {
  return appointmentValue.slot?.time || appointmentValue.slot?.starts_at?.slice(0, 5) || ''
}

onMounted(() => {
  const saved = localStorage.getItem('portal_last_appointment')
  appointment.value = saved ? JSON.parse(saved) : null
})
</script>

<template>
  <div class="mx-auto max-w-xl space-y-6">
    <section class="rounded-lg border border-gray-200 bg-white p-6 text-center shadow-sm">
      <div class="mx-auto grid size-16 place-items-center rounded-full border-2 border-[#2C5F8A]">
        <span class="block size-8 rotate-45 border-b-4 border-r-4 border-[#2C5F8A] animate-[check_420ms_ease-out_forwards]" />
      </div>
      <h1 class="mt-5 text-2xl font-bold text-gray-900">ご予約が完了しました</h1>

      <dl v-if="appointment" class="mt-6 grid gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-left text-sm">
        <div class="flex justify-between gap-4">
          <dt class="text-gray-500">担当者</dt>
          <dd class="font-bold text-gray-900">{{ appointment.slot?.therapist?.name || '-' }}</dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt class="text-gray-500">日付</dt>
          <dd class="font-bold text-gray-900">{{ appointment.slot?.date }}</dd>
        </div>
        <div class="flex justify-between gap-4">
          <dt class="text-gray-500">時間</dt>
          <dd class="font-bold text-gray-900">{{ slotTime(appointment) }}</dd>
        </div>
      </dl>

      <NuxtLink class="mt-6 inline-flex min-h-11 items-center justify-center rounded-lg bg-[#2C5F8A] px-5 py-2 text-sm font-semibold text-white transition hover:bg-[#244f73] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2" to="/portal/mypage">
        マイページへ
      </NuxtLink>
    </section>
  </div>
</template>

<style scoped>
@keyframes check {
  from {
    opacity: 0;
    transform: rotate(45deg) scale(0.6);
  }
  to {
    opacity: 1;
    transform: rotate(45deg) scale(1);
  }
}
</style>
