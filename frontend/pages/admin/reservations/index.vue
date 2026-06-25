<script setup lang="ts">
import type { AppointmentSlot, Staff, Therapist, Waitlist } from '~/types/booking'

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
const selectedDate = ref(new Date().toISOString().slice(0, 10))
const selectedTherapistId = ref<number | null>(null)
const therapists = ref<Therapist[]>([])
const slots = ref<AppointmentSlot[]>([])
const waitlists = ref<Waitlist[]>([])
const selectedSlot = ref<AppointmentSlot | null>(null)
const loading = ref(false)
const slotLoading = ref(false)
const waitlistLoading = ref(false)

const selectedTherapist = computed(() => therapists.value.find((therapist) => therapist.id === selectedTherapistId.value) || null)

function apiError(err: unknown) {
  const anyError = err as { data?: { message?: string, errors?: Record<string, string[]> }, message?: string }
  const firstValidationError = anyError.data?.errors ? Object.values(anyError.data.errors)[0]?.[0] : null

  return firstValidationError || anyError.data?.message || anyError.message || '通信に失敗しました。'
}

function slotId(slot: AppointmentSlot) {
  return slot.appointment_slot_id || slot.id
}

function slotTime(slot: AppointmentSlot) {
  return slot.time || slot.starts_at.slice(0, 5)
}

async function fetchTherapists() {
  const response = await $fetch<{ data: Therapist[] }>(`${config.public.apiBase}/therapists`)
  therapists.value = response.data
  selectedTherapistId.value = selectedTherapistId.value || response.data[0]?.id || null
}

async function fetchSlots() {
  if (!selectedDate.value || !selectedTherapistId.value) return

  slotLoading.value = true
  selectedSlot.value = null

  try {
    const response = await $fetch<{ data: AppointmentSlot[] }>(`${config.public.apiBase}/slots`, {
      query: {
        date: selectedDate.value,
        therapist_id: selectedTherapistId.value,
      },
    })
    slots.value = response.data
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
    showToast('スタッフ認証が完了しました', 'success')
    await fetchSlots()
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    loading.value = false
  }
}

async function openWaitlistPanel(slot: AppointmentSlot) {
  if (slot.status !== 'full') return

  const id = slotId(slot)
  selectedSlot.value = slot
  waitlists.value = []

  if (!id || !token.value) return

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
        <p class="mt-1 text-sm text-gray-500">満枠のキャンセル待ち人数と待機順を確認できます。</p>
      </div>
      <div v-if="staff" class="rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-sm font-semibold text-primary-600">
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
        <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50" type="submit" :disabled="loading">
          ログイン
        </button>
      </form>
    </section>

    <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <UiFormGrid v-slot="{ fieldClass, labelClass, controlClass }">
          <div :class="fieldClass">
            <label :class="labelClass" for="reservation-date">日付</label>
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
        <button class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50" type="button" @click="fetchSlots">
          更新
        </button>
      </div>
    </section>

    <section class="grid gap-6 lg:grid-cols-[1fr_22rem]">
      <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold text-gray-900">枠状況</h2>
            <p class="mt-1 text-sm text-gray-500">{{ selectedDate }} / {{ selectedTherapist?.name }}</p>
          </div>
          <div class="flex items-center gap-4 text-sm">
            <span class="flex items-center gap-2"><span class="size-3 rounded-full border border-[#2C5F8A] bg-white" />空き</span>
            <span class="flex items-center gap-2"><span class="size-3 rounded-full bg-gray-200" />満枠</span>
          </div>
        </div>

        <div v-if="slotLoading" class="mt-5 flex items-center gap-2 text-sm font-medium text-primary-600">
          <span class="size-4 animate-spin rounded-full border-2 border-blue-100 border-t-primary-600" />
          読み込み中
        </div>

        <div v-else class="mt-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
          <button
            v-for="slot in slots"
            :key="`${slot.therapist_id}-${slotTime(slot)}`"
            class="rounded-lg border p-3 text-left transition"
            :class="slot.status === 'available'
              ? 'cursor-default border-[#2C5F8A] bg-white text-gray-900'
              : 'border-gray-200 bg-gray-100 text-gray-600 hover:border-gray-300 hover:bg-gray-50'"
            type="button"
            :disabled="slot.status !== 'full' || !token"
            @click="openWaitlistPanel(slot)"
          >
            <div class="flex items-center justify-between gap-2">
              <span class="text-sm font-semibold">{{ slotTime(slot) }}</span>
              <span class="text-lg font-bold">{{ slot.status === 'available' ? '○' : '×' }}</span>
            </div>
            <p class="mt-1 text-xs" :class="slot.status === 'available' ? 'text-gray-500' : 'font-semibold text-gray-700'">
              <span v-if="slot.status === 'available'">残り {{ slot.available_count ?? 0 }} 枠</span>
              <span v-else>× (待{{ slot.waitlist_count || 0 }}名)</span>
            </p>
          </button>
        </div>
      </div>

      <aside class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm lg:sticky lg:top-6 lg:self-start">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold text-gray-900">待機順</h2>
            <p class="mt-1 text-sm text-gray-500">
              <span v-if="selectedSlot">{{ slotTime(selectedSlot) }} のキャンセル待ち</span>
              <span v-else>満枠を選択してください</span>
            </p>
          </div>
          <button v-if="selectedSlot" class="text-sm font-semibold text-gray-500" type="button" @click="selectedSlot = null">
            閉じる
          </button>
        </div>

        <div v-if="!token" class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
          待機順の確認にはスタッフ認証が必要です。
        </div>

        <div v-else-if="waitlistLoading" class="mt-5 flex items-center gap-2 text-sm font-medium text-primary-600">
          <span class="size-4 animate-spin rounded-full border-2 border-blue-100 border-t-primary-600" />
          読み込み中
        </div>

        <div v-else-if="selectedSlot && waitlists.length === 0" class="mt-5 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-500">
          キャンセル待ちはありません。
        </div>

        <ol v-else-if="selectedSlot" class="mt-5 space-y-3">
          <li v-for="waitlist in waitlists" :key="waitlist.id" class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="font-bold text-gray-900">{{ waitlist.patient?.name }}</p>
                <p class="mt-1 text-sm text-gray-500">診察券番号 {{ waitlist.patient?.card_number }}</p>
              </div>
              <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-primary-600">{{ waitlist.priority }}番目</span>
            </div>
          </li>
        </ol>
      </aside>
    </section>

    <UiToastNotification />
  </div>
</template>
