<script setup lang="ts">
import {
  CalendarDaysIcon,
  ClockIcon,
  ExclamationTriangleIcon,
  UserCircleIcon,
} from '@heroicons/vue/24/outline'
import type { Appointment, Patient, Staff, Therapist, AppointmentSlot } from '~/types/booking'

type StaffLoginResponse = {
  token: string
  user: Staff
}

definePageMeta({
  layout: 'admin',
})

const config = useRuntimeConfig()
const { showToast } = useToast()

const loginForm = reactive({
  staff_id: 'KB001',
  password: 'staffpass',
})
const token = ref('')
const staff = ref<Staff | null>(null)
const appointments = ref<Appointment[]>([])
const undiagnosedPatients = ref<Patient[]>([])
const therapists = ref<Therapist[]>([])
const todaySlots = ref<AppointmentSlot[]>([])
const loading = ref(false)

const today = new Date().toISOString().slice(0, 10)
const tomorrowDate = new Date()
tomorrowDate.setDate(tomorrowDate.getDate() + 1)
const tomorrow = tomorrowDate.toISOString().slice(0, 10)

const todayAppointments = computed(() => appointments.value
  .filter((appointment) => appointment.slot?.date === today && appointment.status === 'booked')
  .sort((a, b) => a.slot.starts_at.localeCompare(b.slot.starts_at)))

const tomorrowAppointments = computed(() => appointments.value
  .filter((appointment) => appointment.slot?.date === tomorrow && appointment.status === 'booked'))

const nextTodayAppointments = computed(() => todayAppointments.value.slice(0, 5))
const waitlistCount = computed(() => todaySlots.value.reduce((sum, slot) => sum + (slot.waitlist_count || 0), 0))

const kpis = computed(() => [
  { label: '本日の予約数', value: todayAppointments.value.length, icon: CalendarDaysIcon },
  { label: '明日の予約数', value: tomorrowAppointments.value.length, icon: ClockIcon },
  { label: 'キャンセル待ち人数', value: waitlistCount.value, icon: ExclamationTriangleIcon },
  { label: '未診断患者数', value: undiagnosedPatients.value.length, icon: UserCircleIcon },
])

function apiError(err: unknown) {
  const anyError = err as { data?: { message?: string, errors?: Record<string, string[]> }, message?: string }
  const firstValidationError = anyError.data?.errors ? Object.values(anyError.data.errors)[0]?.[0] : null

  return firstValidationError || anyError.data?.message || anyError.message || '通信に失敗しました。'
}

function formatTime(value: string) {
  return value.slice(0, 5)
}

async function fetchDashboard() {
  if (!token.value) return

  loading.value = true

  try {
    const [appointmentResponse, patientResponse, therapistResponse] = await Promise.all([
      $fetch<{ data: Appointment[] }>(`${config.public.apiBase}/appointments`, {
        headers: { Authorization: `Bearer ${token.value}` },
      }),
      $fetch<{ data: Patient[] }>(`${config.public.apiBase}/patients`, {
        headers: { Authorization: `Bearer ${token.value}` },
        query: { is_diagnosed: '0' },
      }),
      $fetch<{ data: Therapist[] }>(`${config.public.apiBase}/therapists`),
    ])

    appointments.value = appointmentResponse.data
    undiagnosedPatients.value = patientResponse.data
    therapists.value = therapistResponse.data

    const slotResponses = await Promise.all(therapists.value.map((therapist) => {
      return $fetch<{ data: AppointmentSlot[] }>(`${config.public.apiBase}/slots`, {
        query: { date: today, therapist_id: therapist.id },
      })
    }))
    todaySlots.value = slotResponses.flatMap((response) => response.data)
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    loading.value = false
  }
}

async function loginStaff() {
  loading.value = true

  try {
    const response = await $fetch<StaffLoginResponse>(`${config.public.apiBase}/auth/staff`, {
      method: 'POST',
      body: loginForm,
    })

    token.value = response.token
    staff.value = response.user
    localStorage.setItem('admin_token', response.token)
    localStorage.setItem('admin_staff', JSON.stringify(response.user))
    await fetchDashboard()
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  const savedToken = localStorage.getItem('admin_token')
  const savedStaff = localStorage.getItem('admin_staff')

  if (savedToken) {
    token.value = savedToken
    staff.value = savedStaff ? JSON.parse(savedStaff) : null
    await fetchDashboard()
  }
})
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">ダッシュボード</h1>
        <p class="mt-1 text-sm text-gray-500">本日と明日の予約状況、未対応の患者情報を確認します。</p>
      </div>
      <div v-if="staff" class="rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-sm font-semibold text-[#2C5F8A]">
        {{ staff.name }}
      </div>
    </header>

    <section v-if="!token" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
      <h2 class="text-lg font-bold text-gray-900">スタッフ認証</h2>
      <form class="mt-4 space-y-4" @submit.prevent="loginStaff">
        <UiFormGrid v-slot="{ fieldClass, labelClass, controlClass }">
          <div :class="fieldClass">
            <label :class="labelClass" for="staff-id">スタッフID</label>
            <input id="staff-id" v-model="loginForm.staff_id" :class="controlClass" type="text">
          </div>
          <div :class="fieldClass">
            <label :class="labelClass" for="staff-password">パスワード</label>
            <input id="staff-password" v-model="loginForm.password" :class="controlClass" type="password">
          </div>
        </UiFormGrid>
        <button class="min-h-11 rounded-lg bg-[#2C5F8A] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#244f73] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2 disabled:opacity-50" type="submit" :disabled="loading">
          ログイン
        </button>
      </form>
    </section>

    <template v-else>
      <section class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <article
          v-for="kpi in kpis"
          :key="kpi.label"
          class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm"
        >
          <div v-if="loading" class="animate-pulse space-y-4">
            <div class="size-10 rounded-lg bg-gray-100" />
            <div class="h-8 w-16 rounded bg-gray-100" />
            <div class="h-4 w-24 rounded bg-gray-100" />
          </div>
          <template v-else>
            <div class="grid size-10 place-items-center rounded-lg bg-blue-50 text-[#2C5F8A]">
              <component :is="kpi.icon" class="size-6" aria-hidden="true" />
            </div>
            <p class="mt-4 text-3xl font-bold text-gray-900">{{ kpi.value }}</p>
            <p class="mt-1 text-sm font-medium text-gray-500">{{ kpi.label }}</p>
          </template>
        </article>
      </section>

      <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold text-gray-900">本日の予約</h2>
            <p class="mt-1 text-sm text-gray-500">時間順に次の5件を表示します。</p>
          </div>
          <button class="min-h-11 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2" type="button" @click="fetchDashboard">
            更新
          </button>
        </div>

        <UiSkeletonBlock v-if="loading" class="mt-5" :rows="5" />

        <UiEmptyState v-else-if="nextTodayAppointments.length === 0" class="mt-5" />

        <div v-else class="mt-5 divide-y divide-gray-100">
          <article v-for="appointment in nextTodayAppointments" :key="appointment.id" class="flex min-h-14 items-center justify-between gap-4 py-3">
            <div>
              <p class="font-bold text-gray-900">{{ formatTime(appointment.slot.starts_at) }} - {{ formatTime(appointment.slot.ends_at) }}</p>
              <p class="mt-1 text-sm text-gray-500">{{ appointment.patient?.name }} / {{ appointment.slot.therapist?.name || '-' }}</p>
            </div>
            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-[#2C5F8A]">予約中</span>
          </article>
        </div>
      </section>
    </template>

    <UiToastNotification />
  </div>
</template>
