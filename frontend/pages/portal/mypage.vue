<script setup lang="ts">
import type { Patient, Waitlist } from '~/types/booking'

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
const waitlists = ref<Waitlist[]>([])
const loading = ref(false)
const deletingId = ref<number | null>(null)

function apiError(err: unknown) {
  const anyError = err as { data?: { message?: string, errors?: Record<string, string[]> }, message?: string }
  const firstValidationError = anyError.data?.errors ? Object.values(anyError.data.errors)[0]?.[0] : null

  return firstValidationError || anyError.data?.message || anyError.message || '通信に失敗しました。'
}

function slotTime(waitlist: Waitlist) {
  return waitlist.slot?.time || waitlist.slot?.starts_at?.slice(0, 5) || ''
}

async function fetchWaitlists() {
  if (!token.value) return

  loading.value = true

  try {
    const response = await $fetch<{ data: Waitlist[] }>(`${config.public.apiBase}/waitlists`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    waitlists.value = response.data
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    loading.value = false
  }
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
    await fetchWaitlists()
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    loading.value = false
  }
}

async function cancelWaitlist(waitlist: Waitlist) {
  deletingId.value = waitlist.id

  try {
    await $fetch(`${config.public.apiBase}/waitlists/${waitlist.id}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${token.value}` },
    })

    waitlists.value = waitlists.value.filter((item) => item.id !== waitlist.id)
    showToast('キャンセル待ちを取り消しました', 'success')
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    deletingId.value = null
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 px-4 py-6 text-gray-900 md:px-6 lg:px-8">
    <main class="mx-auto max-w-4xl space-y-6">
      <header class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-semibold text-[#2C5F8A]">講道館ビルクリニック</p>
        <h1 class="mt-1 text-2xl font-bold">マイページ</h1>
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
          <button class="rounded-lg bg-[#2C5F8A] px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" type="submit" :disabled="loading">
            確認する
          </button>
        </form>
      </section>

      <section v-else class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold">キャンセル待ち</h2>
            <p class="mt-1 text-sm text-gray-500">{{ patient?.name }}</p>
          </div>
          <button class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700" type="button" @click="fetchWaitlists">
            更新
          </button>
        </div>

        <div v-if="loading" class="mt-5 flex items-center gap-2 text-sm font-medium text-[#2C5F8A]">
          <span class="size-4 animate-spin rounded-full border-2 border-blue-100 border-t-[#2C5F8A]" />
          読み込み中
        </div>

        <div v-else-if="waitlists.length === 0" class="mt-5 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-500">
          現在キャンセル待ちはありません。
        </div>

        <div v-else class="mt-5 space-y-3">
          <article v-for="waitlist in waitlists" :key="waitlist.id" class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="font-bold text-gray-900">{{ waitlist.slot?.date }} {{ slotTime(waitlist) }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ waitlist.slot?.therapist?.name }}</p>
                <p class="mt-2 text-sm font-semibold text-[#2C5F8A]">{{ waitlist.priority }}番目</p>
              </div>
              <button
                class="rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-50 disabled:opacity-50"
                type="button"
                :disabled="deletingId === waitlist.id"
                @click="cancelWaitlist(waitlist)"
              >
                取り消す
              </button>
            </div>
          </article>
        </div>
      </section>
    </main>

    <UiToastNotification />
  </div>
</template>
