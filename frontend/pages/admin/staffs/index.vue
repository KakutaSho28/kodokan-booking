<script setup lang="ts">
import type { Staff } from '~/types/booking'

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
const staffForm = reactive({
  id: null as number | null,
  staff_id: '',
  name: '',
  password: '',
  role: 'staff' as 'staff' | 'admin',
  is_active: true,
})
const token = ref('')
const currentStaff = ref<Staff | null>(null)
const staffs = ref<Staff[]>([])
const loading = ref(false)
const saving = ref(false)
const showStaffModal = ref(false)

const isAdmin = computed(() => currentStaff.value?.role === 'admin')

function apiError(err: unknown) {
  const anyError = err as { data?: { message?: string, errors?: Record<string, string[]> }, message?: string }
  const firstValidationError = anyError.data?.errors ? Object.values(anyError.data.errors)[0]?.[0] : null

  return firstValidationError || anyError.data?.message || anyError.message || '通信に失敗しました。'
}

function resetStaffForm() {
  staffForm.id = null
  staffForm.staff_id = ''
  staffForm.name = ''
  staffForm.password = ''
  staffForm.role = 'staff'
  staffForm.is_active = true
}

function openCreateModal() {
  resetStaffForm()
  showStaffModal.value = true
}

function openEditModal(staff: Staff) {
  staffForm.id = staff.id
  staffForm.staff_id = staff.staff_id
  staffForm.name = staff.name
  staffForm.password = ''
  staffForm.role = staff.role || 'staff'
  staffForm.is_active = staff.is_active ?? true
  showStaffModal.value = true
}

async function fetchStaffs() {
  if (!token.value) return

  loading.value = true

  try {
    const response = await $fetch<{ data: Staff[] }>(`${config.public.apiBase}/staffs`, {
      headers: { Authorization: `Bearer ${token.value}` },
    })
    staffs.value = response.data
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
    await fetchStaffs()
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    loading.value = false
  }
}

async function saveStaff() {
  saving.value = true

  try {
    const body = {
      staff_id: staffForm.staff_id,
      name: staffForm.name,
      password: staffForm.password || undefined,
      role: staffForm.role,
      is_active: staffForm.is_active,
    }
    const response = await $fetch<{ data: Staff, message: string }>(
      staffForm.id ? `${config.public.apiBase}/staffs/${staffForm.id}` : `${config.public.apiBase}/staffs`,
      {
        method: staffForm.id ? 'PUT' : 'POST',
        headers: { Authorization: `Bearer ${token.value}` },
        body,
      },
    )

    showToast(response.message, 'success')
    showStaffModal.value = false
    await fetchStaffs()
  } catch (err) {
    showToast(apiError(err), 'error')
  } finally {
    saving.value = false
  }
}

async function deactivateStaff(staff: Staff) {
  if (!confirm(`${staff.name} を無効化しますか？`)) return

  try {
    const response = await $fetch<{ message: string }>(`${config.public.apiBase}/staffs/${staff.id}/deactivate`, {
      method: 'PUT',
      headers: { Authorization: `Bearer ${token.value}` },
    })
    showToast(response.message, 'success')
    await fetchStaffs()
  } catch (err) {
    showToast(apiError(err), 'error')
  }
}

onMounted(async () => {
  const savedToken = localStorage.getItem('admin_token')
  const savedStaff = localStorage.getItem('admin_staff')

  if (savedToken) {
    token.value = savedToken
    currentStaff.value = savedStaff ? JSON.parse(savedStaff) : null
    await fetchStaffs()
  }
})
</script>

<template>
  <div class="space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">スタッフ管理</h1>
        <p class="mt-1 text-sm text-gray-500">スタッフアカウントと勤務シフトを管理します。</p>
      </div>
      <button
        v-if="token"
        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
        type="button"
        :disabled="!isAdmin"
        @click="openCreateModal"
      >
        新規スタッフ追加
      </button>
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

    <section v-else class="rounded-lg border border-gray-200 bg-white shadow-sm">
      <div v-if="loading" class="flex items-center gap-2 p-5 text-sm font-medium text-primary-600">
        <span class="size-4 animate-spin rounded-full border-2 border-blue-100 border-t-primary-600" />
        読み込み中
      </div>

      <div v-else class="overflow-hidden">
        <table class="hidden min-w-full divide-y divide-gray-200 lg:table">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">氏名</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">役職</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500">ステータス</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500">アクション</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="staff in staffs" :key="staff.id" class="hover:bg-gray-50">
              <td class="px-4 py-3">
                <p class="text-sm font-bold text-gray-900">{{ staff.name }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ staff.staff_id }}</p>
              </td>
              <td class="px-4 py-3 text-sm text-gray-700">{{ staff.role === 'admin' ? '管理者' : 'スタッフ' }}</td>
              <td class="px-4 py-3">
                <span class="rounded-full px-3 py-1 text-xs font-bold" :class="staff.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600'">
                  {{ staff.is_active ? 'active' : 'inactive' }}
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex justify-end gap-2">
                  <NuxtLink class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50" :to="`/admin/staffs/${staff.id}/shifts`">
                    シフト
                  </NuxtLink>
                  <button class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:opacity-50" type="button" :disabled="!isAdmin" @click="openEditModal(staff)">
                    編集
                  </button>
                  <button class="rounded-lg border border-red-200 bg-white px-3 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-50 disabled:opacity-50" type="button" :disabled="!isAdmin || !staff.is_active" @click="deactivateStaff(staff)">
                    無効化
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <div class="grid gap-3 p-4 lg:hidden">
          <article v-for="staff in staffs" :key="staff.id" class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <h2 class="font-bold text-gray-900">{{ staff.name }}</h2>
                <p class="mt-1 text-xs text-gray-500">{{ staff.staff_id }} / {{ staff.role === 'admin' ? '管理者' : 'スタッフ' }}</p>
              </div>
              <span class="rounded-full px-3 py-1 text-xs font-bold" :class="staff.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600'">
                {{ staff.is_active ? 'active' : 'inactive' }}
              </span>
            </div>
            <div class="mt-4 flex flex-wrap justify-end gap-2">
              <NuxtLink class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700" :to="`/admin/staffs/${staff.id}/shifts`">
                シフト
              </NuxtLink>
              <button class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 disabled:opacity-50" type="button" :disabled="!isAdmin" @click="openEditModal(staff)">
                編集
              </button>
            </div>
          </article>
        </div>
      </div>
    </section>

    <Teleport to="body">
      <div v-if="showStaffModal" class="fixed inset-0 z-50 flex min-h-dvh items-center justify-center bg-slate-950/45 px-4 py-6" @click.self="showStaffModal = false">
        <section class="w-full max-w-lg rounded-lg bg-white p-6 shadow-2xl">
          <h2 class="text-lg font-bold text-gray-900">{{ staffForm.id ? 'スタッフ編集' : '新規スタッフ追加' }}</h2>
          <form class="mt-5 space-y-4" @submit.prevent="saveStaff">
            <UiFormGrid v-slot="{ fieldClass, labelClass, controlClass }">
              <div :class="fieldClass">
                <label :class="labelClass" for="modal-staff-id">スタッフID</label>
                <input id="modal-staff-id" v-model="staffForm.staff_id" :class="controlClass" type="text">
              </div>
              <div :class="fieldClass">
                <label :class="labelClass" for="modal-name">氏名</label>
                <input id="modal-name" v-model="staffForm.name" :class="controlClass" type="text">
              </div>
              <div :class="fieldClass">
                <label :class="labelClass" for="modal-password">パスワード</label>
                <input id="modal-password" v-model="staffForm.password" :class="controlClass" type="password" :placeholder="staffForm.id ? '変更時のみ入力' : ''">
              </div>
              <div :class="fieldClass">
                <label :class="labelClass" for="modal-role">役職</label>
                <select id="modal-role" v-model="staffForm.role" :class="controlClass">
                  <option value="staff">スタッフ</option>
                  <option value="admin">管理者</option>
                </select>
              </div>
            </UiFormGrid>

            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700">
              <input v-model="staffForm.is_active" class="size-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600" type="checkbox">
              active
            </label>

            <div class="flex justify-end gap-3">
              <button class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-200" type="button" @click="showStaffModal = false">
                キャンセル
              </button>
              <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:opacity-50" type="submit" :disabled="saving">
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
