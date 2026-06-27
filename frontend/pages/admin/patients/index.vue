<script setup lang="ts">
import type { Patient, Staff } from '~/types/booking'

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
const filters = reactive({
  name: '',
  is_diagnosed: '',
})
const token = ref('')
const staff = ref<Staff | null>(null)
const patients = ref<Patient[]>([])
const loading = ref(false)

function apiError(err: unknown) {
  const anyError = err as { data?: { message?: string, errors?: Record<string, string[]> }, message?: string }
  const firstValidationError = anyError.data?.errors ? Object.values(anyError.data.errors)[0]?.[0] : null

  return firstValidationError || anyError.data?.message || anyError.message || '通信に失敗しました。'
}

function formatDate(value?: string) {
  if (!value) return '-'

  return new Intl.DateTimeFormat('ja-JP', { year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(value))
}

async function fetchPatients() {
  if (!token.value) return

  loading.value = true

  try {
    const response = await $fetch<{ data: Patient[] }>(`${config.public.apiBase}/patients`, {
      headers: { Authorization: `Bearer ${token.value}` },
      query: {
        name: filters.name || undefined,
        is_diagnosed: filters.is_diagnosed || undefined,
      },
    })
    patients.value = response.data
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
    await fetchPatients()
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
    await fetchPatients()
  }
})
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">患者管理</h1>
        <p class="mt-1 text-sm text-gray-500">初診診断ステータスと担当者を管理します。</p>
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
      <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
        <form class="grid gap-4 lg:grid-cols-[1fr_14rem_auto]" @submit.prevent="fetchPatients">
          <div>
            <label class="text-sm font-semibold text-gray-700" for="patient-name">氏名検索</label>
            <input id="patient-name" v-model="filters.name" class="mt-1 min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-blue-100" type="search" placeholder="患者名">
          </div>
          <div>
            <label class="text-sm font-semibold text-gray-700" for="diagnosis-status">診断ステータス</label>
            <select id="diagnosis-status" v-model="filters.is_diagnosed" class="mt-1 min-h-11 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-600 focus:outline-none focus:ring-2 focus:ring-blue-100">
              <option value="">すべて</option>
              <option value="1">診断済み</option>
              <option value="0">未診断</option>
            </select>
          </div>
          <div class="flex items-end">
            <button class="min-h-11 w-full rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2 disabled:opacity-50" type="submit" :disabled="loading">
              検索
            </button>
          </div>
        </form>
      </section>

      <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <UiSkeletonBlock v-if="loading" class="p-5" :rows="5" />

        <UiEmptyState v-else-if="patients.length === 0" class="m-5" />

        <div v-else class="overflow-hidden">
          <table class="hidden min-w-full divide-y divide-gray-200 lg:table">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">診察券番号</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">氏名</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">初診診断</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">担当者</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">登録日</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">操作</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="patient in patients" :key="patient.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ patient.card_number }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ patient.name }}</td>
                <td class="px-4 py-3">
                  <span class="rounded-full px-3 py-1 text-xs font-bold" :class="patient.is_diagnosed ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
                    {{ patient.is_diagnosed ? '診断済み' : '未診断' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ patient.assigned_therapist?.name || '-' }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ formatDate(patient.created_at) }}</td>
                <td class="px-4 py-3 text-right">
                  <NuxtLink class="inline-flex min-h-11 items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2" :to="`/admin/patients/${patient.id}`">
                    詳細
                  </NuxtLink>
                </td>
              </tr>
            </tbody>
          </table>

          <div class="grid gap-3 p-4 lg:hidden">
            <article v-for="patient in patients" :key="patient.id" class="rounded-lg border border-gray-200 bg-white p-4">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-xs font-semibold text-gray-500">診察券番号 {{ patient.card_number }}</p>
                  <h2 class="mt-1 font-bold text-gray-900">{{ patient.name }}</h2>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-bold" :class="patient.is_diagnosed ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
                  {{ patient.is_diagnosed ? '診断済み' : '未診断' }}
                </span>
              </div>
              <dl class="mt-3 grid gap-2 text-sm">
                <div class="flex justify-between gap-3">
                  <dt class="text-gray-500">担当者</dt>
                  <dd class="font-medium text-gray-900">{{ patient.assigned_therapist?.name || '-' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                  <dt class="text-gray-500">登録日</dt>
                  <dd class="font-medium text-gray-900">{{ formatDate(patient.created_at) }}</dd>
                </div>
              </dl>
              <div class="mt-4 flex justify-end">
                <NuxtLink class="inline-flex min-h-11 items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2" :to="`/admin/patients/${patient.id}`">
                  詳細
                </NuxtLink>
              </div>
            </article>
          </div>
        </div>
      </section>
    </template>

    <UiToastNotification />
  </div>
</template>
