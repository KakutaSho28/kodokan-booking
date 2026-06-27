<script setup lang="ts">
import type { Shift, Staff } from '~/types/booking'

type StaffLoginResponse = {
  token: string
  user: Staff
}

definePageMeta({
  layout: 'admin',
})

const route = useRoute()
const config = useRuntimeConfig()
const { showToast } = useToast()

const staffId = computed(() => Number(route.params.id))
const loginForm = reactive({
  staff_id: 'KB001',
  password: 'staffpass',
})
const shiftForm = reactive({
  work_date: '',
  is_day_off: false,
  start_time: '09:00',
  end_time: '17:00',
})
const token = ref('')
const currentStaff = ref<Staff | null>(null)
const staffs = ref<Staff[]>([])
const shifts = ref<Shift[]>([])
const selectedMonth = ref(new Date().toISOString().slice(0, 7))
const loading = ref(false)
const saving = ref(false)
const showShiftModal = ref(false)

const isAdmin = computed(() => currentStaff.value?.role === 'admin')
const targetStaff = computed(() => staffs.value.find((staff) => staff.id === staffId.value) || null)
const shiftMap = computed(() => new Map(shifts.value.map((shift) => [shift.work_date, shift])))

const calendarDays = computed(() => {
  const [year, month] = selectedMonth.value.split('-').map(Number)
  const first = new Date(year, month - 1, 1)
  const last = new Date(year, month, 0)
  const days: Array<{ date: string, day: number, weekday: string, isBlank?: boolean }> = []

  for (let index = 0; index < first.getDay(); index++) {
    days.push({ date: `blank-${index}`, day: 0, weekday: '', isBlank: true })
  }

  for (let day = 1; day <= last.getDate(); day++) {
    const date = new Date(year, month - 1, day)
    days.push({
      date: date.toISOString().slice(0, 10),
      day,
      weekday: new Intl.DateTimeFormat('ja-JP', { weekday: 'short' }).format(date),
    })
  }

  return days
})

function apiError(err: unknown) {
  const anyError = err as { data?: { message?: string, errors?: Record<string, string[]> }, message?: string }
  const firstValidationError = anyError.data?.errors ? Object.values(anyError.data.errors)[0]?.[0] : null

  return firstValidationError || anyError.data?.message || anyError.message || '通信に失敗しました。'
}

function shortTime(value?: string | null) {
  return value ? value.slice(0, 5) : ''
}

async function fetchStaffs() {
  if (!token.value) return

  const response = await $fetch<{ data: Staff[] }>(`${config.public.apiBase}/staffs`, {
    headers: { Authorization: `Bearer ${token.value}` },
  })
  staffs.value = response.data
}

async function fetchShifts() {
  if (!token.value) return

  loading.value = true

  try {
    const response = await $fetch<{ data: Shift[] }>(`${config.public.apiBase}/staffs/${staffId.value}/shifts`, {
      headers: { Authorization: `Bearer ${token.value}` },
      query: { month: selectedMonth.value },
    })
    shifts.value = response.data
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
    currentStaff.value = response.user
    localStorage.setItem('admin_token', response.token)
    localStorage.setItem('admin_staff', JSON.stringify(response.user))
    await Promise.all([fetchStaffs(), fetchShifts()])
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    loading.value = false
  }
}

function openShiftModal(date: string) {
  if (!isAdmin.value) {
    showToast('管理者権限が必要です。', 'warning')
    return
  }

  const shift = shiftMap.value.get(date)
  shiftForm.work_date = date
  shiftForm.is_day_off = shift?.is_day_off ?? false
  shiftForm.start_time = shortTime(shift?.start_time) || '09:00'
  shiftForm.end_time = shortTime(shift?.end_time) || '17:00'
  showShiftModal.value = true
}

async function saveShift() {
  saving.value = true

  try {
    const response = await $fetch<{ message: string }>(`${config.public.apiBase}/shifts`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${token.value}` },
      body: {
        staff_id: staffId.value,
        work_date: shiftForm.work_date,
        is_day_off: shiftForm.is_day_off,
        start_time: shiftForm.is_day_off ? null : shiftForm.start_time,
        end_time: shiftForm.is_day_off ? null : shiftForm.end_time,
      },
    })

    showToast(response.message, 'success')
    showShiftModal.value = false
    await fetchShifts()
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    saving.value = false
  }
}

watch(selectedMonth, () => {
  fetchShifts()
})

onMounted(async () => {
  const savedToken = localStorage.getItem('admin_token')
  const savedStaff = localStorage.getItem('admin_staff')

  if (savedToken) {
    token.value = savedToken
    currentStaff.value = savedStaff ? JSON.parse(savedStaff) : null
    await Promise.all([fetchStaffs(), fetchShifts()])
  }
})
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <NuxtLink class="text-sm font-semibold text-primary-600" to="/admin/staffs">スタッフ一覧へ戻る</NuxtLink>
        <h1 class="mt-2 text-2xl font-bold text-gray-900">シフト管理</h1>
        <p class="mt-1 text-sm text-gray-500">{{ targetStaff?.name || `スタッフID: ${staffId}` }}</p>
      </div>
      <input v-model="selectedMonth" class="min-h-11 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-blue-100" type="month">
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

    <section v-else class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
      <div class="grid grid-cols-7 border-b border-gray-200 text-center text-xs font-bold text-gray-500">
        <div class="py-2">日</div>
        <div class="py-2">月</div>
        <div class="py-2">火</div>
        <div class="py-2">水</div>
        <div class="py-2">木</div>
        <div class="py-2">金</div>
        <div class="py-2">土</div>
      </div>

      <UiSkeletonBlock v-if="loading" class="p-5" :rows="6" />

      <div v-else class="grid grid-cols-7">
        <button
          v-for="day in calendarDays"
          :key="day.date"
          class="min-h-28 border-b border-r border-gray-100 p-2 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2"
          :class="day.isBlank ? 'cursor-default bg-gray-50' : 'bg-white hover:bg-blue-50'"
          type="button"
          :disabled="day.isBlank"
          @click="!day.isBlank && openShiftModal(day.date)"
        >
          <template v-if="!day.isBlank">
            <div class="flex items-center justify-between gap-2">
              <span class="text-sm font-bold text-gray-900">{{ day.day }}</span>
              <span class="text-xs text-gray-400">{{ day.weekday }}</span>
            </div>
            <div v-if="shiftMap.get(day.date)" class="mt-3">
              <p v-if="shiftMap.get(day.date)?.is_day_off" class="rounded-lg bg-gray-100 px-2 py-1 text-xs font-bold text-gray-600">休日</p>
              <p v-else class="rounded-lg bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">
                出勤 {{ shortTime(shiftMap.get(day.date)?.start_time) }}〜{{ shortTime(shiftMap.get(day.date)?.end_time) }}
              </p>
            </div>
            <p v-else class="mt-3 text-xs text-gray-400">未設定</p>
          </template>
        </button>
      </div>
    </section>

    <Teleport to="body">
      <div v-if="showShiftModal" class="fixed inset-0 z-50 flex min-h-dvh items-center justify-center bg-slate-950/45 px-4 py-6" @click.self="showShiftModal = false">
        <section class="w-full max-w-md rounded-lg bg-white p-6 shadow-2xl">
          <h2 class="text-lg font-bold text-gray-900">{{ shiftForm.work_date }} のシフト</h2>
          <form class="mt-5 space-y-4" @submit.prevent="saveShift">
            <div class="grid grid-cols-2 gap-2 rounded-lg bg-gray-100 p-1">
              <button class="min-h-11 rounded-md px-3 py-2 text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2" :class="!shiftForm.is_day_off ? 'bg-white text-primary-600 shadow-sm' : 'text-gray-600'" type="button" @click="shiftForm.is_day_off = false">
                出勤
              </button>
              <button class="min-h-11 rounded-md px-3 py-2 text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2" :class="shiftForm.is_day_off ? 'bg-white text-primary-600 shadow-sm' : 'text-gray-600'" type="button" @click="shiftForm.is_day_off = true">
                休日
              </button>
            </div>

            <UiFormGrid v-if="!shiftForm.is_day_off" v-slot="{ fieldClass, labelClass, controlClass }">
              <div :class="fieldClass">
                <label :class="labelClass" for="start-time">開始</label>
                <input id="start-time" v-model="shiftForm.start_time" :class="controlClass" type="time">
              </div>
              <div :class="fieldClass">
                <label :class="labelClass" for="end-time">終了</label>
                <input id="end-time" v-model="shiftForm.end_time" :class="controlClass" type="time">
              </div>
            </UiFormGrid>

            <div class="flex justify-end gap-3">
              <button class="min-h-11 rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2" type="button" @click="showShiftModal = false">
                キャンセル
              </button>
              <button class="min-h-11 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2 disabled:opacity-50" type="submit" :disabled="saving">
                保存
              </button>
            </div>
          </form>
        </section>
      </div>
    </Teleport>

    <UiToastNotification />
  </div>
</template>
