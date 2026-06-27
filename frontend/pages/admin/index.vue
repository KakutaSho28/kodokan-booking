<script setup lang="ts">
import type { Appointment, Staff } from '~/types/booking'

type StaffLoginResponse = {
  token: string
  user: Staff
}

type SummaryResponse = {
  today_count: number
  upcoming_count: number
  cancelled_count: number
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
const summary = ref<SummaryResponse>({
  today_count: 0,
  upcoming_count: 0,
  cancelled_count: 0,
})
const appointments = ref<Appointment[]>([])
const cancelledAppointments = ref<Appointment[]>([])
const loading = ref(false)
const activeTab = ref<'today' | 'upcoming' | 'cancelled'>('today')

const today = new Date().toISOString().slice(0, 10)
const metrics = computed(() => [
  { label: '本日の予約', value: summary.value.today_count },
  { label: '今後の予約', value: summary.value.upcoming_count },
  { label: 'キャンセル件数', value: summary.value.cancelled_count },
])

const todayAppointments = computed(() => appointments.value
  .filter((appointment) => appointment.status === 'booked' && appointment.slot?.date === today)
  .sort((a, b) => slotTime(a).localeCompare(slotTime(b))))

const upcomingAppointments = computed(() => appointments.value
  .filter((appointment) => appointment.status === 'booked' && appointment.slot?.date >= today)
  .sort((a, b) => `${a.slot.date} ${slotTime(a)}`.localeCompare(`${b.slot.date} ${slotTime(b)}`)))

const tabRows = computed(() => {
  if (activeTab.value === 'today') return todayAppointments.value
  if (activeTab.value === 'upcoming') return upcomingAppointments.value

  return cancelledAppointments.value
})

function apiError(err: unknown) {
  const anyError = err as { data?: { message?: string, errors?: Record<string, string[]> }, message?: string }
  const firstValidationError = anyError.data?.errors ? Object.values(anyError.data.errors)[0]?.[0] : null

  return firstValidationError || anyError.data?.message || anyError.message || '通信に失敗しました。'
}

function slotTime(appointment: Appointment) {
  return appointment.slot?.time || appointment.slot?.starts_at?.slice(0, 5) || ''
}

function reservationDateTime(appointment: Appointment) {
  return `${appointment.slot?.date || '-'} ${slotTime(appointment)}`
}

function cancelledAt(appointment: Appointment) {
  if (!appointment.cancelled_at) return '-'

  return new Intl.DateTimeFormat('ja-JP', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(appointment.cancelled_at))
}

async function fetchDashboard() {
  if (!token.value) return

  loading.value = true

  try {
    const [summaryResponse, appointmentResponse, cancelledResponse] = await Promise.all([
      $fetch<SummaryResponse>(`${config.public.apiBase}/admin/reservations/summary`, {
        headers: { Authorization: `Bearer ${token.value}` },
      }),
      $fetch<{ data: Appointment[] }>(`${config.public.apiBase}/appointments`, {
        headers: { Authorization: `Bearer ${token.value}` },
      }),
      $fetch<{ data: Appointment[] }>(`${config.public.apiBase}/admin/reservations/cancelled`, {
        headers: { Authorization: `Bearer ${token.value}` },
      }),
    ])

    summary.value = summaryResponse
    appointments.value = appointmentResponse.data
    cancelledAppointments.value = cancelledResponse.data
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
        <p class="mt-1 text-sm text-gray-500">リハビリ予約の稼働状況を確認します。</p>
      </div>
      <div v-if="staff" class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700">
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
        <button class="min-h-11 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2 disabled:opacity-50" type="submit" :disabled="loading">
          ログイン
        </button>
      </form>
    </section>

    <template v-else>
      <section class="grid gap-4 md:grid-cols-3">
        <article
          v-for="metric in metrics"
          :key="metric.label"
          class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm"
        >
          <div v-if="loading" class="space-y-3">
            <div class="h-4 w-24 animate-pulse rounded bg-gray-100" />
            <div class="h-8 w-14 animate-pulse rounded bg-gray-100" />
          </div>
          <template v-else>
            <p class="text-sm font-medium text-gray-500">{{ metric.label }}</p>
            <p class="mt-2 text-3xl font-bold text-gray-900">{{ metric.value }}</p>
          </template>
        </article>
      </section>

      <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex rounded-lg bg-gray-100 p-1">
            <button
              class="min-h-11 rounded-md px-3 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2"
              :class="activeTab === 'today' ? 'bg-white text-primary-600 shadow-sm' : 'text-gray-600'"
              type="button"
              @click="activeTab = 'today'"
            >
              本日の予約
            </button>
            <button
              class="min-h-11 rounded-md px-3 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2"
              :class="activeTab === 'upcoming' ? 'bg-white text-primary-600 shadow-sm' : 'text-gray-600'"
              type="button"
              @click="activeTab = 'upcoming'"
            >
              今後の予約
            </button>
            <button
              class="min-h-11 rounded-md px-3 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2"
              :class="activeTab === 'cancelled' ? 'bg-white text-primary-600 shadow-sm' : 'text-gray-600'"
              type="button"
              @click="activeTab = 'cancelled'"
            >
              キャンセル履歴
            </button>
          </div>
          <button class="min-h-11 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2" type="button" @click="fetchDashboard">
            更新
          </button>
        </div>

        <div v-if="loading" class="mt-5 space-y-2">
          <div v-for="index in 5" :key="index" class="h-12 animate-pulse rounded-lg bg-gray-100" />
        </div>

        <div v-else-if="tabRows.length === 0" class="mt-5 rounded-lg border border-gray-200 bg-gray-50 p-6 text-center text-sm font-semibold text-gray-500">
          データがありません
        </div>

        <div v-else class="mt-5 overflow-x-auto rounded-lg border border-gray-200">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">患者名</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">担当者</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">予約日時</th>
                <th v-if="activeTab === 'cancelled'" class="px-4 py-3 text-left text-xs font-semibold text-gray-500">キャンセル日時</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <tr v-for="appointment in tabRows" :key="appointment.id">
                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ appointment.patient?.name || '-' }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ appointment.slot?.therapist?.name || '-' }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ reservationDateTime(appointment) }}</td>
                <td v-if="activeTab === 'cancelled'" class="px-4 py-3 text-sm text-gray-700">{{ cancelledAt(appointment) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <UiToastNotification />
  </div>
</template>
