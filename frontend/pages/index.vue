<script setup lang="ts">
import type { Appointment, AppointmentSlot, Patient, Role, Staff, Therapist } from '~/types/booking'

const config = useRuntimeConfig()
const apiBase = config.public.apiBase

const activeRole = ref<Role>('patient')
const token = ref('')
const role = ref<Role | null>(null)
const user = ref<Patient | Staff | null>(null)
const canBookRehab = ref(false)
const message = ref('')
const error = ref('')
const loading = ref(false)
const { showToast } = useToast()

const patientForm = reactive({
  card_number: '100001',
  birth_date: '1984-04-12',
})

const staffForm = reactive({
  staff_id: 'KB001',
  password: 'staffpass',
})

const selectedDate = ref(new Date().toISOString().slice(0, 10))
const selectedTherapistId = ref<number | ''>('')
const therapists = ref<Therapist[]>([])
const slots = ref<AppointmentSlot[]>([])
const appointments = ref<Appointment[]>([])
const appointmentColumns = [
  { key: 'patientName', label: '患者名' },
  { key: 'cardNumber', label: '診察券番号' },
  { key: 'therapistName', label: '担当者' },
  { key: 'schedule', label: '予約日時' },
  { key: 'statusLabel', label: '状態' },
  { key: 'staffNotes', label: 'メモ' },
]

type PendingReservationAction =
  | { kind: 'create', slot: AppointmentSlot }
  | { kind: 'update', appointment: Appointment }
  | { kind: 'delete', appointment: Appointment }

type AppointmentTableRow = {
  patientName: string
  cardNumber: string
  therapistName: string
  schedule: string
  statusLabel: string
  staffNotes: string
  appointment: Appointment
}

const pendingAction = ref<PendingReservationAction | null>(null)

const authHeaders = computed(() => token.value ? { Authorization: `Bearer ${token.value}` } : undefined)
const isPatient = computed(() => role.value === 'patient')
const isStaff = computed(() => role.value === 'staff')

const currentUserName = computed(() => user.value?.name || '')
const appointmentRows = computed<AppointmentTableRow[]>(() => appointments.value.map((appointment) => ({
  patientName: appointment.patient.name,
  cardNumber: appointment.patient.card_number,
  therapistName: appointment.slot.therapist.name,
  schedule: `${formatDate(appointment.slot.date)} ${formatTime(appointment.slot.starts_at)} - ${formatTime(appointment.slot.ends_at)}`,
  statusLabel: appointment.status === 'booked' ? '予約中' : 'キャンセル',
  staffNotes: appointment.staff_notes || '-',
  appointment,
})))

const confirmModal = computed(() => {
  if (!pendingAction.value) return null

  if (pendingAction.value.kind === 'create') {
    const slot = pendingAction.value.slot
    return {
      title: '予約登録の確認',
      message: `${formatDate(slot.date)} ${formatTime(slot.starts_at)}から、${slot.therapist.name}で予約します。`,
      confirmLabel: '予約する',
      isDestructive: false,
    }
  }

  if (pendingAction.value.kind === 'update') {
    const appointment = pendingAction.value.appointment
    return {
      title: '予約変更の確認',
      message: `${appointment.patient.name}さんの予約内容を変更します。`,
      confirmLabel: '変更する',
      isDestructive: false,
    }
  }

  return {
    title: '予約削除の確認',
    message: 'この予約を削除してもよいですか？',
    confirmLabel: '削除する',
    isDestructive: true,
  }
})

function clearFeedback() {
  message.value = ''
  error.value = ''
}

function apiError(err: unknown) {
  const anyError = err as { data?: { message?: string }, message?: string }
  return anyError.data?.message || anyError.message || '通信に失敗しました。'
}

function appointmentFromRow(row: Record<string, unknown>) {
  return row.appointment as Appointment
}

function updateRowSlot(row: Record<string, unknown>, event: Event) {
  const target = event.target as HTMLSelectElement
  appointmentFromRow(row).slot.id = Number(target.value)
}

function updateRowStatus(row: Record<string, unknown>, event: Event) {
  const target = event.target as HTMLSelectElement
  appointmentFromRow(row).status = target.value as Appointment['status']
}

function updateRowNotes(row: Record<string, unknown>, event: Event) {
  const target = event.target as HTMLTextAreaElement
  appointmentFromRow(row).staff_notes = target.value
}

async function patientLogin() {
  clearFeedback()
  loading.value = true
  try {
    const response = await $fetch<{ token: string, role: Role, user: Patient, can_book_rehab: boolean, message: string }>(`${apiBase}/auth/patient`, {
      method: 'POST',
      body: patientForm,
    })

    token.value = response.token
    role.value = response.role
    user.value = response.user
    canBookRehab.value = response.can_book_rehab
    message.value = response.message
    await loadWorkspace()
  } catch (err) {
    const errorMessage = apiError(err)
    error.value = errorMessage
    showToast(errorMessage, 'error')
  } finally {
    loading.value = false
  }
}

async function staffLogin() {
  clearFeedback()
  loading.value = true
  try {
    const response = await $fetch<{ token: string, role: Role, user: Staff }>(`${apiBase}/auth/staff`, {
      method: 'POST',
      body: staffForm,
    })

    token.value = response.token
    role.value = response.role
    user.value = response.user
    canBookRehab.value = false
    message.value = 'スタッフとしてログインしました。'
    await loadWorkspace()
  } catch (err) {
    const errorMessage = apiError(err)
    error.value = errorMessage
    showToast(errorMessage, 'error')
  } finally {
    loading.value = false
  }
}

async function logout() {
  if (token.value) {
    await $fetch(`${apiBase}/logout`, { method: 'POST', headers: authHeaders.value })
  }

  token.value = ''
  role.value = null
  user.value = null
  canBookRehab.value = false
  appointments.value = []
  message.value = 'ログアウトしました。'
}

async function loadWorkspace() {
  try {
    await Promise.all([loadTherapists(), loadSlots(), loadAppointments()])
  } catch (err) {
    showToast(apiError(err), 'error')
  }
}

async function loadTherapists() {
  const response = await $fetch<{ data: Therapist[] }>(`${apiBase}/therapists`)
  therapists.value = response.data
}

async function loadSlots() {
  const query: Record<string, string | number> = { date: selectedDate.value }
  if (selectedTherapistId.value) {
    query.therapist_id = selectedTherapistId.value
  }

  const response = await $fetch<{ data: AppointmentSlot[] }>(`${apiBase}/slots`, { query })
  slots.value = response.data
}

async function loadAppointments() {
  if (!token.value) return

  const response = await $fetch<{ data: Appointment[] }>(`${apiBase}/appointments`, {
    headers: authHeaders.value,
  })
  appointments.value = response.data
}

function requestBook(slot: AppointmentSlot) {
  clearFeedback()
  if (!slot.is_available || !canBookRehab.value) return

  pendingAction.value = { kind: 'create', slot }
}

function requestUpdateAppointment(appointment: Appointment) {
  clearFeedback()
  pendingAction.value = { kind: 'update', appointment }
}

function requestDeleteAppointment(appointment: Appointment) {
  clearFeedback()
  pendingAction.value = { kind: 'delete', appointment }
}

function closeConfirmModal() {
  pendingAction.value = null
}

async function confirmPendingAction() {
  const action = pendingAction.value
  if (!action) return

  closeConfirmModal()

  if (action.kind === 'create') {
    await book(action.slot)
  } else if (action.kind === 'update') {
    await updateAppointment(action.appointment)
  } else {
    await deleteAppointment(action.appointment)
  }
}

async function book(slot: AppointmentSlot) {
  loading.value = true
  try {
    await $fetch(`${apiBase}/appointments`, {
      method: 'POST',
      headers: authHeaders.value,
      body: slot.appointment_slot_id || slot.id
        ? { appointment_slot_id: slot.appointment_slot_id || slot.id }
        : {
            therapist_id: slot.therapist_id,
            date: slot.date,
            time: slot.time || slot.starts_at.slice(0, 5),
          },
    })
    showToast('予約を登録しました', 'success')
    await loadWorkspace()
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    loading.value = false
  }
}

async function updateAppointment(appointment: Appointment) {
  loading.value = true
  try {
    await $fetch(`${apiBase}/appointments/${appointment.id}`, {
      method: 'PATCH',
      headers: authHeaders.value,
      body: {
        appointment_slot_id: appointment.slot.id,
        status: appointment.status,
        staff_notes: appointment.staff_notes,
      },
    })
    showToast('予約を変更しました', 'success')
    await loadWorkspace()
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    loading.value = false
  }
}

async function deleteAppointment(appointment: Appointment) {
  loading.value = true
  try {
    await $fetch(`${apiBase}/appointments/${appointment.id}`, {
      method: 'DELETE',
      headers: authHeaders.value,
    })
    showToast('予約を削除しました', 'success')
    await loadWorkspace()
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    loading.value = false
  }
}

function formatTime(value: string) {
  return value.slice(0, 5)
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat('ja-JP', { month: 'numeric', day: 'numeric', weekday: 'short' }).format(new Date(`${value}T00:00:00`))
}

watch([selectedDate, selectedTherapistId], () => {
  if (token.value) {
    loadSlots()
  }
})
</script>

<template>
  <div class="page">
    <header class="topbar">
      <div class="topbar-inner">
        <div class="brand">
          <div class="brand-mark">K</div>
          <div>
            <h1>講道館ビルクリニック</h1>
            <p>整形外科・リハビリテーション科 予約システム</p>
          </div>
        </div>
        <div class="clinic-meta">
          <strong>03-5842-6311</strong>
          <p>東京都文京区春日1丁目16-30 講道館本館6階</p>
        </div>
      </div>
    </header>

    <main class="main">
      <div class="notice-band">
        <div>
          <strong>リハビリ予約専用</strong>
          <span class="hint">初診の方は医師の診察後に予約できます。空き枠は ○、満枠は × で表示します。</span>
        </div>
        <button v-if="token" class="btn secondary" type="button" @click="logout">ログアウト</button>
      </div>

      <div class="layout">
        <aside class="panel">
          <h2 class="section-title">ログイン</h2>
          <div class="tabs">
            <button class="tab" :class="{ active: activeRole === 'patient' }" type="button" @click="activeRole = 'patient'">患者</button>
            <button class="tab" :class="{ active: activeRole === 'staff' }" type="button" @click="activeRole = 'staff'">スタッフ</button>
          </div>

          <form v-if="activeRole === 'patient'" @submit.prevent="patientLogin">
            <div class="field">
              <label for="card-number">診察券番号</label>
              <input id="card-number" v-model="patientForm.card_number" class="input" autocomplete="username">
            </div>
            <div class="field">
              <label for="birth-date">生年月日</label>
              <input id="birth-date" v-model="patientForm.birth_date" class="input" type="date" autocomplete="bday">
            </div>
            <button class="btn" type="submit" :disabled="loading">患者ログイン</button>
          </form>

          <form v-else @submit.prevent="staffLogin">
            <div class="field">
              <label for="staff-id">スタッフID</label>
              <input id="staff-id" v-model="staffForm.staff_id" class="input" autocomplete="username">
            </div>
            <div class="field">
              <label for="staff-password">パスワード</label>
              <input id="staff-password" v-model="staffForm.password" class="input" type="password" autocomplete="current-password">
            </div>
            <button class="btn" type="submit" :disabled="loading">スタッフログイン</button>
          </form>

          <p v-if="message" class="message">{{ message }}</p>
          <p v-if="error" class="message error">{{ error }}</p>

          <div class="demo">
            <strong>デモログイン</strong><br>
            患者: 100001 / 1984-04-12<br>
            初診患者: 100002 / 1991-09-03<br>
            スタッフ: KB001 / staffpass
          </div>
        </aside>

        <section class="panel">
          <h2 class="section-title">
            <span v-if="token">{{ currentUserName }}さんの画面</span>
            <span v-else>予約枠</span>
          </h2>

          <div v-if="!token" class="empty">
            患者またはスタッフとしてログインしてください。
          </div>

          <template v-else>
            <div class="toolbar">
              <div class="field">
                <label for="date">日付</label>
                <input id="date" v-model="selectedDate" class="input" type="date">
              </div>
              <div class="field">
                <label for="therapist">担当者</label>
                <select id="therapist" v-model="selectedTherapistId" class="select">
                  <option value="">すべて</option>
                  <option v-for="therapist in therapists" :key="therapist.id" :value="therapist.id">
                    {{ therapist.name }}
                  </option>
                </select>
              </div>
              <button class="btn secondary" type="button" @click="loadWorkspace">更新</button>
            </div>

            <div class="slot-grid">
              <button
                v-for="slot in slots"
                :key="slot.id"
                class="card slot"
                type="button"
                :disabled="!isPatient || !canBookRehab || !slot.is_available || loading"
                @click="requestBook(slot)"
              >
                <div class="slot-header">
                  <strong>{{ slot.therapist.name }}</strong>
                  <span class="mark" :class="slot.is_available ? 'ok' : 'full'">{{ slot.availability_mark }}</span>
                </div>
                <div class="slot-time">{{ formatTime(slot.starts_at) }} - {{ formatTime(slot.ends_at) }}</div>
                <p>{{ formatDate(slot.date) }} / {{ slot.therapist.specialty }}</p>
                <p>予約 {{ slot.booked_count }} / {{ slot.capacity }}</p>
              </button>
            </div>

            <h2 class="section-title" style="margin-top: 24px;">予約一覧</h2>
            <div v-if="appointments.length === 0" class="empty">予約はありません。</div>
            <UiResponsiveTable
              v-else-if="isStaff"
              :columns="appointmentColumns"
              :rows="appointmentRows"
            >
              <template #actions="{ row }">
                <UiFormGrid v-slot="{ fieldClass, labelClass, controlClass, fullClass }">
                  <div :class="fieldClass">
                    <label :class="labelClass">枠変更</label>
                    <select
                      :class="controlClass"
                      :value="appointmentFromRow(row).slot.id"
                      @change="updateRowSlot(row, $event)"
                    >
                      <option
                        v-for="slot in slots"
                        :key="slot.id"
                        :value="slot.id"
                        :disabled="!slot.is_available && slot.id !== appointmentFromRow(row).slot.id"
                      >
                        {{ slot.therapist.name }} {{ formatTime(slot.starts_at) }} {{ slot.availability_mark }}
                      </option>
                    </select>
                  </div>

                  <div :class="fieldClass">
                    <label :class="labelClass">状態</label>
                    <select
                      :class="controlClass"
                      :value="appointmentFromRow(row).status"
                      @change="updateRowStatus(row, $event)"
                    >
                      <option value="booked">予約中</option>
                      <option value="cancelled">キャンセル</option>
                    </select>
                  </div>

                  <div :class="[fieldClass, fullClass]">
                    <label :class="labelClass">スタッフメモ</label>
                    <textarea
                      :class="controlClass"
                      :value="appointmentFromRow(row).staff_notes || ''"
                      rows="2"
                      @input="updateRowNotes(row, $event)"
                    />
                  </div>

                  <div :class="['flex flex-wrap justify-end gap-2', fullClass]">
                    <button
                      class="min-h-10 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                      type="button"
                      :disabled="loading"
                      @click="requestUpdateAppointment(appointmentFromRow(row))"
                    >
                      保存
                    </button>
                    <button
                      class="min-h-10 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                      type="button"
                      :disabled="loading"
                      @click="requestDeleteAppointment(appointmentFromRow(row))"
                    >
                      削除
                    </button>
                  </div>
                </UiFormGrid>
              </template>
            </UiResponsiveTable>
            <div v-else class="appointment-list">
              <article v-for="appointment in appointments" :key="appointment.id" class="card appointment">
                <div>
                  <h3>{{ appointment.patient.name }} / {{ appointment.slot.therapist.name }}</h3>
                  <p>{{ formatDate(appointment.slot.date) }} {{ formatTime(appointment.slot.starts_at) }} - {{ formatTime(appointment.slot.ends_at) }}</p>
                  <p>診察券番号: {{ appointment.patient.card_number }}</p>
                  <p v-if="appointment.staff_notes">メモ: {{ appointment.staff_notes }}</p>
                </div>
                <span class="status" :class="appointment.status">
                  {{ appointment.status === 'booked' ? '予約中' : 'キャンセル' }}
                </span>
              </article>
            </div>
          </template>
        </section>
      </div>
    </main>

    <UiConfirmModal
      v-if="confirmModal"
      :title="confirmModal.title"
      :message="confirmModal.message"
      :confirm-label="confirmModal.confirmLabel"
      :is-destructive="confirmModal.isDestructive"
      @confirm="confirmPendingAction"
      @cancel="closeConfirmModal"
    />
    <UiToastNotification />
  </div>
</template>
