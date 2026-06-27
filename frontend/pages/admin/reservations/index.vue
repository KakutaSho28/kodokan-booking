<script setup lang="ts">
import {
  CheckCircleIcon,
  NoSymbolIcon,
  UsersIcon,
  XCircleIcon,
} from '@heroicons/vue/24/outline'
import type { Appointment, AppointmentSlot, Staff, Therapist, Waitlist } from '~/types/booking'

type StaffLoginResponse = {
  token: string
  user: Staff
}

type CalendarCell = {
  date: string
  time: string
  slot: AppointmentSlot | null
  appointment: Appointment | null
  state: 'available' | 'full' | 'waitlisted' | 'dayoff'
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
const selectedDate = ref(new Date().toISOString().slice(0, 10))
const selectedTherapistId = ref<number | null>(null)
const therapists = ref<Therapist[]>([])
const slotsByDate = ref<Record<string, AppointmentSlot[]>>({})
const appointments = ref<Appointment[]>([])
const waitlists = ref<Waitlist[]>([])
const selectedCell = ref<CalendarCell | null>(null)
const loading = ref(false)
const slotLoading = ref(false)
const waitlistLoading = ref(false)

const selectedTherapist = computed(() => therapists.value.find((therapist) => therapist.id === selectedTherapistId.value) || null)
const weekDates = computed(() => {
  const base = new Date(`${selectedDate.value}T00:00:00`)
  const start = new Date(base)
  start.setDate(base.getDate() - base.getDay())

  return Array.from({ length: 7 }, (_, index) => {
    const date = new Date(start)
    date.setDate(start.getDate() + index)

    return date.toISOString().slice(0, 10)
  })
})
const times = computed(() => Array.from({ length: 16 }, (_, index) => {
  const hour = 9 + Math.floor(index / 2)
  const minute = index % 2 === 0 ? '00' : '30'

  return `${String(hour).padStart(2, '0')}:${minute}`
}))
const dailyCells = computed(() => times.value.map((time) => buildCell(selectedDate.value, time)))
const weeklyCells = computed(() => weekDates.value.map((date) => ({
  date,
  cells: times.value.map((time) => buildCell(date, time)),
})))

function apiError(err: unknown) {
  const anyError = err as { data?: { message?: string, errors?: Record<string, string[]> }, message?: string }
  const firstValidationError = anyError.data?.errors ? Object.values(anyError.data.errors)[0]?.[0] : null

  return firstValidationError || anyError.data?.message || anyError.message || '通信に失敗しました。'
}

function slotId(slot: AppointmentSlot | null) {
  return slot?.appointment_slot_id || slot?.id || null
}

function slotTime(slot: AppointmentSlot) {
  return slot.time || slot.starts_at.slice(0, 5)
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat('ja-JP', { month: 'numeric', day: 'numeric', weekday: 'short' }).format(new Date(`${value}T00:00:00`))
}

function appointmentForSlot(slot: AppointmentSlot | null) {
  const id = slotId(slot)

  return id ? appointments.value.find((appointment) => appointment.slot?.id === id || appointment.slot?.appointment_slot_id === id) || null : null
}

function buildCell(date: string, time: string): CalendarCell {
  const slot = slotsByDate.value[date]?.find((item) => slotTime(item) === time) || null
  const appointment = appointmentForSlot(slot)
  const state = !slot
    ? 'dayoff'
    : (slot.waitlist_count || 0) > 0
        ? 'waitlisted'
        : slot.status === 'full'
          ? 'full'
          : 'available'

  return { date, time, slot, appointment, state }
}

function cellClass(cell: CalendarCell) {
  if (cell.state === 'available') return 'border-[#2C5F8A] bg-white text-gray-900 hover:bg-blue-50'
  if (cell.state === 'waitlisted') return 'border-amber-400 bg-amber-50 text-amber-900 hover:bg-amber-100'
  if (cell.state === 'full') return 'border-gray-200 bg-gray-200 text-gray-400'

  return 'border-gray-200 bg-gray-100 text-gray-500'
}

function cellIcon(cell: CalendarCell) {
  if (cell.state === 'available') return CheckCircleIcon
  if (cell.state === 'waitlisted') return UsersIcon
  if (cell.state === 'full') return XCircleIcon

  return NoSymbolIcon
}

function cellLabel(cell: CalendarCell) {
  if (cell.state === 'available') return '○ 空き'
  if (cell.state === 'waitlisted') return `待${cell.slot?.waitlist_count || 0}名`
  if (cell.state === 'full') return '× 満枠'

  return '休'
}

function cellFor(day: { date: string, cells: CalendarCell[] }, time: string) {
  return day.cells.find((cell) => cell.time === time) || buildCell(day.date, time)
}

async function fetchTherapists() {
  const response = await $fetch<{ data: Therapist[] }>(`${config.public.apiBase}/therapists`)
  therapists.value = response.data
  selectedTherapistId.value = selectedTherapistId.value || response.data[0]?.id || null
}

async function fetchAppointments() {
  if (!token.value) return

  const response = await $fetch<{ data: Appointment[] }>(`${config.public.apiBase}/appointments`, {
    headers: { Authorization: `Bearer ${token.value}` },
  })
  appointments.value = response.data
}

async function fetchSlots() {
  if (!selectedDate.value || !selectedTherapistId.value) return

  slotLoading.value = true
  selectedCell.value = null
  waitlists.value = []

  try {
    const responses = await Promise.all(weekDates.value.map((date) => {
      return $fetch<{ data: AppointmentSlot[] }>(`${config.public.apiBase}/slots`, {
        query: {
          date,
          therapist_id: selectedTherapistId.value,
        },
      })
    }))

    slotsByDate.value = Object.fromEntries(weekDates.value.map((date, index) => [date, responses[index]?.data || []]))
    await fetchAppointments()
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    slotLoading.value = false
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
    showToast('スタッフ認証が完了しました', 'success')
    await fetchSlots()
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    loading.value = false
  }
}

async function openSlotPanel(cell: CalendarCell) {
  selectedCell.value = cell
  waitlists.value = []

  const id = slotId(cell.slot)
  if (!id || !token.value || cell.state === 'available' || cell.state === 'dayoff') return

  waitlistLoading.value = true

  try {
    const response = await $fetch<{ data: Waitlist[] }>(`${config.public.apiBase}/waitlists`, {
      query: { slot_id: id },
      headers: { Authorization: `Bearer ${token.value}` },
    })
    waitlists.value = response.data
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    waitlistLoading.value = false
  }
}

watch([selectedDate, selectedTherapistId], () => {
  fetchSlots()
})

onMounted(async () => {
  const savedToken = localStorage.getItem('admin_token')
  const savedStaff = localStorage.getItem('admin_staff')

  if (savedToken) {
    token.value = savedToken
    staff.value = savedStaff ? JSON.parse(savedStaff) : null
  }

  try {
    await fetchTherapists()
    await fetchSlots()
  } catch (err) {
    showToast(apiError(err), 'error')
  }
})
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">予約管理</h1>
        <p class="mt-1 text-sm text-gray-500">スマートフォンでは日次、PCでは週次で枠状況を確認できます。</p>
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

    <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <UiFormGrid v-slot="{ fieldClass, labelClass, controlClass }">
          <div :class="fieldClass">
            <label :class="labelClass" for="reservation-date">基準日</label>
            <input id="reservation-date" v-model="selectedDate" :class="controlClass" type="date">
          </div>
          <div :class="fieldClass">
            <label :class="labelClass" for="therapist">担当者</label>
            <select id="therapist" v-model.number="selectedTherapistId" :class="controlClass">
              <option v-for="therapist in therapists" :key="therapist.id" :value="therapist.id">
                {{ therapist.name }}
              </option>
            </select>
          </div>
        </UiFormGrid>
        <button class="min-h-11 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2" type="button" @click="fetchSlots">
          更新
        </button>
      </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1fr_22rem]">
      <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold text-gray-900">枠状況</h2>
            <p class="mt-1 text-sm text-gray-500">{{ selectedTherapist?.name || '担当者未選択' }}</p>
          </div>
          <div class="flex flex-wrap items-center gap-3 text-xs font-semibold text-gray-600">
            <span class="inline-flex items-center gap-1"><CheckCircleIcon class="size-4 text-[#2C5F8A]" />空き</span>
            <span class="inline-flex items-center gap-1"><XCircleIcon class="size-4 text-gray-400" />満枠</span>
            <span class="inline-flex items-center gap-1"><UsersIcon class="size-4 text-amber-600" />待機あり</span>
            <span class="inline-flex items-center gap-1"><NoSymbolIcon class="size-4 text-gray-500" />休日</span>
          </div>
        </div>

        <UiSkeletonBlock v-if="slotLoading" class="mt-5" :rows="8" />

        <template v-else>
          <div class="mt-5 grid gap-3 lg:hidden">
            <button
              v-for="cell in dailyCells"
              :key="`${cell.date}-${cell.time}`"
              class="min-h-11 rounded-lg border p-3 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2"
              :class="cellClass(cell)"
              type="button"
              @click="openSlotPanel(cell)"
            >
              <div class="flex items-center justify-between gap-3">
                <span class="text-sm font-bold">{{ cell.time }}</span>
                <span class="inline-flex items-center gap-1 text-xs font-bold">
                  <component :is="cellIcon(cell)" class="size-4" aria-hidden="true" />
                  {{ cellLabel(cell) }}
                </span>
              </div>
            </button>
          </div>

          <div class="mt-5 hidden overflow-x-auto lg:block">
            <div class="grid min-w-[980px] grid-cols-[5rem_repeat(7,minmax(7rem,1fr))] gap-2">
              <div />
              <div v-for="date in weekDates" :key="date" class="rounded-lg bg-gray-50 px-3 py-2 text-center text-xs font-bold text-gray-600">
                {{ formatDate(date) }}
              </div>

              <template v-for="time in times" :key="time">
                <div class="flex min-h-11 items-center text-sm font-bold text-gray-500">{{ time }}</div>
                <button
                  v-for="day in weeklyCells"
                  :key="`${day.date}-${time}`"
                  class="min-h-11 rounded-lg border px-2 py-2 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2"
                  :class="cellClass(cellFor(day, time))"
                  type="button"
                  @click="openSlotPanel(cellFor(day, time))"
                >
                  <span class="inline-flex items-center gap-1 text-xs font-bold">
                    <component :is="cellIcon(cellFor(day, time))" class="size-4" aria-hidden="true" />
                    {{ cellLabel(cellFor(day, time)) }}
                  </span>
                </button>
              </template>
            </div>
          </div>
        </template>
      </div>

      <aside class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm lg:sticky lg:top-6 lg:self-start">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold text-gray-900">詳細</h2>
            <p class="mt-1 text-sm text-gray-500">
              <span v-if="selectedCell">{{ formatDate(selectedCell.date) }} {{ selectedCell.time }}</span>
              <span v-else>枠を選択してください</span>
            </p>
          </div>
          <button v-if="selectedCell" class="min-h-11 rounded-lg px-3 text-sm font-semibold text-gray-500 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2" type="button" @click="selectedCell = null">
            閉じる
          </button>
        </div>

        <UiEmptyState v-if="!selectedCell" class="mt-5" message="枠が選択されていません" />

        <div v-else class="mt-5 space-y-4">
          <div class="rounded-lg border p-4" :class="cellClass(selectedCell)">
            <p class="inline-flex items-center gap-2 text-sm font-bold">
              <component :is="cellIcon(selectedCell)" class="size-5" aria-hidden="true" />
              {{ cellLabel(selectedCell) }}
            </p>
            <p class="mt-2 text-sm">{{ selectedTherapist?.name }}</p>
          </div>

          <dl class="grid gap-3 text-sm">
            <div class="flex justify-between gap-4 border-b border-gray-100 pb-3">
              <dt class="text-gray-500">予約患者</dt>
              <dd class="font-bold text-gray-900">{{ selectedCell.appointment?.patient?.name || '-' }}</dd>
            </div>
            <div class="flex justify-between gap-4 border-b border-gray-100 pb-3">
              <dt class="text-gray-500">時間</dt>
              <dd class="font-bold text-gray-900">{{ selectedCell.time }}</dd>
            </div>
            <div class="flex justify-between gap-4">
              <dt class="text-gray-500">残枠</dt>
              <dd class="font-bold text-gray-900">{{ selectedCell.slot?.available_count ?? 0 }}</dd>
            </div>
          </dl>

          <UiSkeletonBlock v-if="waitlistLoading" :rows="3" />

          <div v-else-if="waitlists.length > 0" class="space-y-3">
            <h3 class="text-sm font-bold text-gray-900">キャンセル待ち</h3>
            <article v-for="waitlist in waitlists" :key="waitlist.id" class="rounded-lg border border-gray-200 p-3">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="font-bold text-gray-900">{{ waitlist.patient?.name }}</p>
                  <p class="mt-1 text-xs text-gray-500">診察券番号 {{ waitlist.patient?.card_number }}</p>
                </div>
                <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">{{ waitlist.priority }}番目</span>
              </div>
            </article>
          </div>
        </div>
      </aside>
    </section>

    <UiToastNotification />
  </div>
</template>
