<script setup lang="ts">
import type { AppointmentSlot, Patient, Therapist } from '~/types/booking'

type LoginResponse = {
  token: string
  user: Patient
  can_book_rehab: boolean
  message: string
}

definePageMeta({
  layout: false,
})

const config = useRuntimeConfig()
const { showToast } = useToast()

const loginForm = reactive({
  card_number: '100001',
  birth_date: '1984-04-12',
})
const token = ref('')
const patient = ref<Patient | null>(null)
const canBookRehab = ref(false)
const step = ref(1)
const selectedTherapistId = ref<number | null>(null)
const selectedDate = ref(new Date().toISOString().slice(0, 10))
const selectedSlot = ref<AppointmentSlot | null>(null)
const loading = ref(false)

const { data: therapistResponse } = await useAsyncData('portal-book-therapists', () => {
  return $fetch<{ data: Therapist[] }>(`${config.public.apiBase}/therapists`)
})

const therapists = computed(() => therapistResponse.value?.data || [])
const selectedTherapist = computed(() => therapists.value.find((therapist) => therapist.id === selectedTherapistId.value) || null)
const dateOptions = computed(() => Array.from({ length: 14 }, (_, index) => {
  const date = new Date()
  date.setDate(date.getDate() + index)
  const value = date.toISOString().slice(0, 10)

  return {
    value,
    label: new Intl.DateTimeFormat('ja-JP', { month: 'numeric', day: 'numeric' }).format(date),
    weekday: new Intl.DateTimeFormat('ja-JP', { weekday: 'short' }).format(date),
  }
}))

function apiError(err: unknown) {
  const anyError = err as { data?: { message?: string }, message?: string }
  return anyError.data?.message || anyError.message || '通信に失敗しました。'
}

async function loginPatient() {
  loading.value = true

  try {
    const response = await $fetch<LoginResponse>(`${config.public.apiBase}/auth/patient`, {
      method: 'POST',
      body: loginForm,
    })

    token.value = response.token
    patient.value = response.user
    canBookRehab.value = response.can_book_rehab
    selectedTherapistId.value = response.user.assigned_therapist_id || therapists.value[0]?.id || null
    step.value = 1

    if (!response.can_book_rehab) {
      showToast(response.message, 'warning')
    }
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    loading.value = false
  }
}

function chooseTherapist(therapist: Therapist) {
  selectedTherapistId.value = therapist.id
  selectedSlot.value = null
}

function chooseDate(date: string) {
  selectedDate.value = date
  selectedSlot.value = null
}

function chooseSlot(slot: AppointmentSlot) {
  selectedSlot.value = slot
}

async function confirmBooking() {
  if (!selectedTherapistId.value || !selectedSlot.value) return

  loading.value = true

  try {
    await $fetch(`${config.public.apiBase}/appointments`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: selectedSlot.value.appointment_slot_id
        ? { appointment_slot_id: selectedSlot.value.appointment_slot_id }
        : {
            therapist_id: selectedTherapistId.value,
            date: selectedDate.value,
            time: selectedSlot.value.time || selectedSlot.value.starts_at.slice(0, 5),
          },
    })

    showToast('予約を確定しました', 'success')
    selectedSlot.value = null
    step.value = 2
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 px-4 py-6 text-gray-900 md:px-6 lg:px-8">
    <main class="mx-auto max-w-5xl space-y-6">
      <header class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold text-[#2C5F8A]">講道館ビルクリニック</p>
        <h1 class="mt-1 text-2xl font-bold">リハビリ予約</h1>
      </header>

      <section v-if="!token" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <h2 class="text-lg font-bold">患者確認</h2>
        <form class="mt-4 space-y-4" @submit.prevent="loginPatient">
          <UiFormGrid v-slot="{ fieldClass, labelClass, controlClass }">
            <div :class="fieldClass">
              <label :class="labelClass" for="card-number">診察券番号</label>
              <input id="card-number" v-model="loginForm.card_number" :class="controlClass" type="text">
            </div>
            <div :class="fieldClass">
              <label :class="labelClass" for="birth-date">生年月日</label>
              <input id="birth-date" v-model="loginForm.birth_date" :class="controlClass" type="date">
            </div>
          </UiFormGrid>
          <button
            class="rounded-lg bg-[#2C5F8A] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#244f73] disabled:cursor-not-allowed disabled:opacity-50"
            type="submit"
            :disabled="loading"
          >
            予約へ進む
          </button>
        </form>
      </section>

      <template v-else>
        <div class="grid gap-3 md:grid-cols-3">
          <div class="rounded-lg border bg-white p-4" :class="step === 1 ? 'border-[#2C5F8A]' : 'border-gray-200'">
            <p class="text-sm font-bold">1. 担当者選択</p>
          </div>
          <div class="rounded-lg border bg-white p-4" :class="step === 2 ? 'border-[#2C5F8A]' : 'border-gray-200'">
            <p class="text-sm font-bold">2. 日時選択</p>
          </div>
          <div class="rounded-lg border bg-white p-4" :class="step === 3 ? 'border-[#2C5F8A]' : 'border-gray-200'">
            <p class="text-sm font-bold">3. 確認</p>
          </div>
        </div>

        <section v-if="!canBookRehab" class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
          初診診断前のため、リハビリ予約はできません。
        </section>

        <section v-else-if="step === 1" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
          <h2 class="text-lg font-bold">担当者を選択</h2>
          <div class="mt-4 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
            <button
              v-for="therapist in therapists"
              :key="therapist.id"
              class="rounded-lg border p-4 text-left transition"
              :class="selectedTherapistId === therapist.id ? 'border-[#2C5F8A] bg-blue-50' : 'border-gray-200 bg-white hover:bg-gray-50'"
              type="button"
              @click="chooseTherapist(therapist)"
            >
              <p class="font-bold text-gray-900">{{ therapist.name }}</p>
              <p class="mt-1 text-sm text-gray-500">{{ therapist.specialty }}</p>
              <p v-if="patient?.assigned_therapist_id === therapist.id" class="mt-2 text-xs font-semibold text-[#2C5F8A]">担当</p>
            </button>
          </div>
          <div class="mt-5 flex justify-end">
            <button class="rounded-lg bg-[#2C5F8A] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" type="button" :disabled="!selectedTherapistId" @click="step = 2">
              日時選択へ
            </button>
          </div>
        </section>

        <section v-else-if="step === 2" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 class="text-lg font-bold">日時を選択</h2>
              <p class="mt-1 text-sm text-gray-500">{{ selectedTherapist?.name }}</p>
            </div>
            <button class="text-sm font-semibold text-[#2C5F8A]" type="button" @click="step = 1">担当者を変更</button>
          </div>

          <div class="mt-4 flex gap-2 overflow-x-auto pb-2">
            <button
              v-for="date in dateOptions"
              :key="date.value"
              class="min-w-20 rounded-lg border px-3 py-2 text-sm transition"
              :class="selectedDate === date.value ? 'border-[#2C5F8A] bg-[#2C5F8A] text-white' : 'border-gray-200 bg-white text-gray-700'"
              type="button"
              @click="chooseDate(date.value)"
            >
              <span class="block font-bold">{{ date.label }}</span>
              <span class="text-xs">{{ date.weekday }}</span>
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

          <div class="mt-5 flex justify-end">
            <button class="rounded-lg bg-[#2C5F8A] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" type="button" :disabled="!selectedSlot" @click="step = 3">
              確認へ
            </button>
          </div>
        </section>

        <section v-else class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
          <h2 class="text-lg font-bold">予約内容の確認</h2>
          <dl class="mt-4 grid gap-3 text-sm">
            <div class="flex justify-between gap-4 border-b border-gray-100 pb-3">
              <dt class="font-semibold text-gray-500">担当者</dt>
              <dd class="font-bold">{{ selectedTherapist?.name }}</dd>
            </div>
            <div class="flex justify-between gap-4 border-b border-gray-100 pb-3">
              <dt class="font-semibold text-gray-500">日付</dt>
              <dd class="font-bold">{{ selectedDate }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="font-semibold text-gray-500">時間</dt>
              <dd class="font-bold">{{ selectedSlot?.time || selectedSlot?.starts_at.slice(0, 5) }}</dd>
            </div>
          </dl>
          <div class="mt-6 flex flex-wrap justify-end gap-3">
            <button class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700" type="button" @click="step = 2">
              戻る
            </button>
            <button class="rounded-lg bg-[#2C5F8A] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" type="button" :disabled="loading" @click="confirmBooking">
              予約を確定する
            </button>
          </div>
        </section>
      </template>
    </main>

    <UiToastNotification />
  </div>
</template>
