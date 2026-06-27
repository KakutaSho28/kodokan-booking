<script setup lang="ts">
import type { Appointment, Patient, Staff, Therapist } from '~/types/booking'

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

const patientId = computed(() => String(route.params.id))
const loginForm = reactive({
  staff_id: 'KB001',
  password: 'staffpass',
})
const form = reactive({
  chart_number: '',
  name: '',
  birth_date: '',
  email: '',
  assigned_therapist_id: null as number | null,
})
const token = ref('')
const staff = ref<Staff | null>(null)
const patient = ref<Patient | null>(null)
const therapists = ref<Therapist[]>([])
const reservations = ref<Appointment[]>([])
const loading = ref(false)
const saving = ref(false)
const showDiagnoseConfirm = ref(false)

const isAdmin = computed(() => staff.value?.role === 'admin')

function apiError(err: unknown) {
  const anyError = err as { data?: { message?: string, errors?: Record<string, string[]> }, message?: string }
  const firstValidationError = anyError.data?.errors ? Object.values(anyError.data.errors)[0]?.[0] : null

  return firstValidationError || anyError.data?.message || anyError.message || '通信に失敗しました。'
}

function appointmentDate(appointment: Appointment) {
  return appointment.slot?.date || '-'
}

function appointmentTime(appointment: Appointment) {
  return appointment.slot ? appointment.slot.starts_at.slice(0, 5) : '-'
}

function statusLabel(status: Appointment['status']) {
  return status === 'booked' ? '予約中' : 'キャンセル'
}

function syncForm(nextPatient: Patient) {
  form.chart_number = nextPatient.chart_number || nextPatient.card_number
  form.name = nextPatient.name
  form.birth_date = nextPatient.birth_date
  form.email = nextPatient.email || ''
  form.assigned_therapist_id = nextPatient.assigned_therapist_id || null
}

async function fetchTherapists() {
  const response = await $fetch<{ data: Therapist[] }>(`${config.public.apiBase}/therapists`)
  therapists.value = response.data
}

async function fetchPatient() {
  if (!token.value) return

  loading.value = true

  try {
    const response = await $fetch<{ data: Patient }>(`${config.public.apiBase}/patients/${patientId.value}`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    patient.value = response.data
    reservations.value = response.data.reservations || []
    syncForm(response.data)
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    loading.value = false
  }
}

async function fetchReservations() {
  if (!token.value) return

  try {
    const response = await $fetch<{ data: Appointment[] }>(`${config.public.apiBase}/patients/${patientId.value}/reservations`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    reservations.value = response.data
  } catch (err) {
    showToast(apiError(err), 'error')
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
    await Promise.all([fetchPatient(), fetchReservations()])
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    loading.value = false
  }
}

async function savePatient() {
  if (!isAdmin.value) {
    showToast('管理者権限が必要です。', 'warning')
    return
  }

  saving.value = true

  try {
    const response = await $fetch<{ data: Patient, message: string }>(`${config.public.apiBase}/patients/${patientId.value}`, {
      method: 'PUT',
      headers: { Authorization: `Bearer ${token.value}` },
      body: form,
    })
    patient.value = response.data
    syncForm(response.data)
    showToast(response.message || '患者情報を更新しました', 'success')
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    saving.value = false
  }
}

async function diagnosePatient() {
  saving.value = true

  try {
    const response = await $fetch<{ data: Patient, message: string }>(`${config.public.apiBase}/admin/patients/${patientId.value}/diagnose`, {
      method: 'PUT',
      headers: { Authorization: `Bearer ${token.value}` },
    })
    patient.value = response.data
    syncForm(response.data)
    showToast(response.message || '診断済みに更新しました', 'success')
    showDiagnoseConfirm.value = false
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  await fetchTherapists()

  const savedToken = localStorage.getItem('admin_token')
  const savedStaff = localStorage.getItem('admin_staff')

  if (savedToken) {
    token.value = savedToken
    staff.value = savedStaff ? JSON.parse(savedStaff) : null
    await Promise.all([fetchPatient(), fetchReservations()])
  }
})
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <NuxtLink class="text-sm font-semibold text-primary-600" to="/admin/patients">患者一覧へ戻る</NuxtLink>
        <h1 class="mt-2 text-2xl font-bold text-gray-900">患者詳細</h1>
      </div>
      <div v-if="staff" class="rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-sm font-semibold text-primary-600">
        {{ staff.name }} / {{ staff.role === 'admin' ? '管理者' : 'スタッフ' }}
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
      <UiSkeletonBlock v-if="loading" :rows="6" />

      <template v-else-if="patient">
        <section v-if="!patient.is_diagnosed" class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <p class="font-semibold">この患者はまだ初診診断が完了していません。診断後に「診断済みにする」ボタンを押してください。</p>
            <button
              class="min-h-11 rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-amber-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
              type="button"
              :disabled="!isAdmin || saving"
              @click="showDiagnoseConfirm = true"
            >
              診断済みにする
            </button>
          </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <h2 class="text-lg font-bold text-gray-900">基本情報</h2>
              <p class="mt-1 text-sm text-gray-500">{{ isAdmin ? '編集後に保存してください。' : '管理者のみ編集できます。' }}</p>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-bold" :class="patient.is_diagnosed ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
              {{ patient.is_diagnosed ? '診断済み' : '未診断' }}
            </span>
          </div>

          <form class="mt-5 space-y-4" @submit.prevent="savePatient">
            <UiFormGrid v-slot="{ fieldClass, labelClass, controlClass }">
              <div :class="fieldClass">
                <label :class="labelClass" for="chart-number">診察券番号</label>
                <input id="chart-number" v-model="form.chart_number" :class="controlClass" type="text" :disabled="!isAdmin">
              </div>
              <div :class="fieldClass">
                <label :class="labelClass" for="patient-name">氏名</label>
                <input id="patient-name" v-model="form.name" :class="controlClass" type="text" :disabled="!isAdmin">
              </div>
              <div :class="fieldClass">
                <label :class="labelClass" for="birth-date">生年月日</label>
                <input id="birth-date" v-model="form.birth_date" :class="controlClass" type="date" :disabled="!isAdmin">
              </div>
              <div :class="fieldClass">
                <label :class="labelClass" for="patient-email">メールアドレス</label>
                <input id="patient-email" v-model="form.email" :class="controlClass" type="email" :disabled="!isAdmin">
              </div>
              <div :class="fieldClass">
                <label :class="labelClass" for="assigned-therapist">担当者</label>
                <select id="assigned-therapist" v-model.number="form.assigned_therapist_id" :class="controlClass" :disabled="!isAdmin">
                  <option :value="null">未設定</option>
                  <option v-for="therapist in therapists" :key="therapist.id" :value="therapist.id">
                    {{ therapist.name }}
                  </option>
                </select>
              </div>
            </UiFormGrid>

            <div class="flex justify-end">
              <button class="min-h-11 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" type="submit" :disabled="!isAdmin || saving">
                保存
              </button>
            </div>
          </form>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold text-gray-900">過去予約</h2>
            <button class="min-h-11 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2" type="button" @click="fetchReservations">
              更新
            </button>
          </div>

          <UiEmptyState v-if="reservations.length === 0" class="mt-5" message="予約履歴はありません" />

          <div v-else class="mt-5 overflow-hidden rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">日付</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">時間</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">担当者</th>
                  <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">状態</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 bg-white">
                <tr v-for="reservation in reservations" :key="reservation.id">
                  <td class="px-4 py-3 text-sm text-gray-700">{{ appointmentDate(reservation) }}</td>
                  <td class="px-4 py-3 text-sm text-gray-700">{{ appointmentTime(reservation) }}</td>
                  <td class="px-4 py-3 text-sm text-gray-700">{{ reservation.slot?.therapist?.name || '-' }}</td>
                  <td class="px-4 py-3 text-sm text-gray-700">{{ statusLabel(reservation.status) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>
    </template>

    <UiConfirmModal
      v-if="showDiagnoseConfirm"
      title="診断済みにする"
      message="この患者を診断済みにしますか？以降、リハビリ予約が可能になります。"
      confirm-label="診断済みにする"
      @cancel="showDiagnoseConfirm = false"
      @confirm="diagnosePatient"
    />

    <UiToastNotification />
  </div>
</template>
