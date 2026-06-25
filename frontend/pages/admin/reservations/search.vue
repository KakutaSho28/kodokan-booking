<script setup lang="ts">
import type { Appointment, Staff } from '~/types/booking'

definePageMeta({
  layout: 'admin',
})

type ReservationSearchResponse = {
  data: Appointment[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

const config = useRuntimeConfig()
const apiBase = config.public.apiBase

const filters = reactive({
  patient_name: '',
  staff_id: '',
  date_from: '',
  date_to: '',
  status: '',
})
const page = ref(1)

const columns = [
  { key: 'datetime', label: '日時' },
  { key: 'patientName', label: '患者名' },
  { key: 'staffName', label: 'スタッフ' },
  { key: 'treatmentType', label: '治療種別' },
  { key: 'availability', label: '空き状況' },
  { key: 'statusLabel', label: 'ステータス' },
]

const statusOptions = [
  { value: '', label: 'すべて' },
  { value: 'booked', label: '予約中' },
  { value: 'cancelled', label: 'キャンセル' },
]

const { data: staffResponse } = await useAsyncData('reservation-search-staff', () => {
  return $fetch<{ data: Staff[] }>(`${apiBase}/staff`)
})

const queryParams = computed(() => {
  const params: Record<string, string | number> = { page: page.value }

  Object.entries(filters).forEach(([key, value]) => {
    if (value) {
      params[key] = value
    }
  })

  return params
})

const {
  data: reservationsResponse,
  pending,
  error,
  refresh,
} = await useAsyncData('reservation-search-results', () => {
  return $fetch<ReservationSearchResponse>(`${apiBase}/reservations/search`, {
    query: queryParams.value,
  })
}, {
  watch: [page],
})

const staffOptions = computed(() => staffResponse.value?.data || [])
const reservations = computed(() => reservationsResponse.value?.data || [])
const meta = computed(() => reservationsResponse.value?.meta || {
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0,
})

const rows = computed(() => reservations.value.map((reservation) => ({
  datetime: `${formatDate(reservation.slot.date)} ${formatTime(reservation.slot.starts_at)} - ${formatTime(reservation.slot.ends_at)}`,
  patientName: reservation.patient.name,
  staffName: reservation.staff?.name || '-',
  treatmentType: reservation.treatment_type?.name || '-',
  availability: reservation.slot.is_available ? '○' : '×',
  statusLabel: reservation.status === 'booked' ? '予約中' : 'キャンセル',
})))

function formatTime(value: string) {
  return value.slice(0, 5)
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat('ja-JP', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date(`${value}T00:00:00`))
}

async function submitSearch() {
  // 検索条件を変更したら先頭ページから再検索する。
  page.value = 1
  await refresh()
}

function goToPage(nextPage: number) {
  if (nextPage < 1 || nextPage > meta.value.last_page || nextPage === page.value) {
    return
  }

  page.value = nextPage
}
</script>

<template>
  <div class="space-y-6">
    <header>
      <h1 class="text-2xl font-bold text-gray-900">予約検索</h1>
      <p class="mt-1 text-sm text-gray-500">患者名、スタッフ、期間、ステータスで予約を絞り込みます。</p>
    </header>

    <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
      <form class="space-y-5" @submit.prevent="submitSearch">
        <UiFormGrid v-slot="{ fieldClass, labelClass, controlClass }">
          <div :class="fieldClass">
            <label :class="labelClass" for="patient-name">患者名</label>
            <input
              id="patient-name"
              v-model="filters.patient_name"
              :class="controlClass"
              placeholder="例: 春日"
              type="text"
            >
          </div>

          <div :class="fieldClass">
            <label :class="labelClass" for="staff-id">スタッフ</label>
            <select id="staff-id" v-model="filters.staff_id" :class="controlClass">
              <option value="">すべて</option>
              <option v-for="staff in staffOptions" :key="staff.id" :value="String(staff.id)">
                {{ staff.staff_id }} / {{ staff.name }}
              </option>
            </select>
          </div>

          <div :class="fieldClass">
            <label :class="labelClass" for="date-from">開始日</label>
            <input id="date-from" v-model="filters.date_from" :class="controlClass" type="date">
          </div>

          <div :class="fieldClass">
            <label :class="labelClass" for="date-to">終了日</label>
            <input id="date-to" v-model="filters.date_to" :class="controlClass" type="date">
          </div>

          <div :class="fieldClass">
            <label :class="labelClass" for="status">ステータス</label>
            <select id="status" v-model="filters.status" :class="controlClass">
              <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
          </div>
        </UiFormGrid>

        <div class="flex justify-end">
          <button
            class="inline-flex min-h-10 items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
            type="submit"
            :disabled="pending"
          >
            検索
          </button>
        </div>
      </form>
    </section>

    <section class="space-y-4">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-gray-600">検索結果: {{ meta.total }}件</p>
        <div v-if="pending" class="flex items-center gap-2 text-sm font-medium text-primary-600">
          <span class="size-4 animate-spin rounded-full border-2 border-blue-200 border-t-primary-600" />
          読み込み中
        </div>
      </div>

      <p v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        検索に失敗しました。
      </p>

      <UiResponsiveTable v-else :columns="columns" :rows="rows" />

      <div class="flex items-center justify-between gap-3">
        <button
          class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
          type="button"
          :disabled="page <= 1 || pending"
          @click="goToPage(page - 1)"
        >
          前へ
        </button>
        <span class="text-sm text-gray-600">{{ page }} / {{ meta.last_page }} ページ</span>
        <button
          class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
          type="button"
          :disabled="page >= meta.last_page || pending"
          @click="goToPage(page + 1)"
        >
          次へ
        </button>
      </div>
    </section>
  </div>
</template>
