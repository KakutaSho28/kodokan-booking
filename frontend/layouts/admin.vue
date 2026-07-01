<script setup lang="ts">
const route = useRoute()
const config = useRuntimeConfig()
const adminToken = ref(
  import.meta.client ? localStorage.getItem('admin_token') || '' : ''
)
const adminStaffName = ref('')

const navigationItems = [
  { label: 'ダッシュボード', to: '/admin', icon: '⌂' },
  { label: '予約管理', to: '/admin/reservations', icon: '□' },
  { label: '患者管理', to: '/admin/patients', icon: '人' },
  { label: 'スタッフ管理', to: '/admin/staffs', icon: '職' },
  { label: '設定', to: '/admin/settings', icon: '⚙' },
]

function isActive(path: string) {
  if (path === '/admin') {
    return route.path === path
  }

  return route.path.startsWith(path)
}

function syncAdminSession() {
  if (!import.meta.client) return

  const savedToken = localStorage.getItem('admin_token') || ''
  const savedStaff = localStorage.getItem('admin_staff')

  adminToken.value = savedToken

  try {
    adminStaffName.value = savedStaff ? JSON.parse(savedStaff)?.name || '' : ''
  } catch {
    adminStaffName.value = ''
  }
}

const showLogoutConfirm = ref(false)

function requestLogout() {
  showLogoutConfirm.value = true
}

async function confirmLogout() {
  showLogoutConfirm.value = false
  await logoutAdmin()
}

async function logoutAdmin() {
  const currentToken = adminToken.value

  if (currentToken) {
    try {
      await $fetch(`${config.public.apiBase}/logout`, {
        method: 'POST',
        headers: { Authorization: `Bearer ${currentToken}` },
      })
    } catch {
      // 通信失敗時もローカルセッション破棄を優先する。
    }
  }

  if (!import.meta.client) return

  localStorage.removeItem('admin_token')
  localStorage.removeItem('admin_staff')
  adminToken.value = ''
  adminStaffName.value = ''
  window.location.assign('/admin')
}

let sessionTimer: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  syncAdminSession()
  sessionTimer = setInterval(syncAdminSession, 1000)
})

onBeforeUnmount(() => {
  if (sessionTimer) {
    clearInterval(sessionTimer)
  }
})
</script>

<template>
  <div class="min-h-screen bg-surface-50 font-sans text-gray-900">
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col border-r border-gray-200 bg-white lg:flex">
      <div class="flex h-16 items-center border-b border-gray-200 px-5">
        <div>
          <p class="text-sm font-bold text-primary-600">講道館ビルクリニック</p>
          <p class="text-xs text-gray-500">スタッフ管理画面</p>
        </div>
      </div>

      <nav class="space-y-1 px-3 py-4">
        <NuxtLink
          v-for="item in navigationItems"
          :key="item.to"
          :to="item.to"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
          :class="isActive(item.to)
            ? 'bg-blue-50 text-primary-600'
            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
        >
          <span class="grid size-7 place-items-center rounded-lg bg-gray-100 text-xs">{{ item.icon }}</span>
          <span>{{ item.label }}</span>
        </NuxtLink>
      </nav>
    </aside>

    <main class="min-h-screen px-4 pb-24 pt-4 md:px-6 md:pt-6 lg:ml-60 lg:px-8 lg:pb-8">
      <div
        v-if="adminToken"
        class="sticky top-0 z-20 mb-4 flex items-center justify-end gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 shadow-sm"
      >
        <p class="truncate text-sm font-semibold text-gray-700">
          {{ adminStaffName || 'スタッフ' }}
        </p>
        <button
          class="min-h-11 shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2"
          type="button"
          @click="requestLogout"
        >
          ログアウト
        </button>
      </div>
      <slot />
    </main>

    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white lg:hidden">
      <div class="grid grid-cols-5">
        <NuxtLink
          v-for="item in navigationItems"
          :key="item.to"
          :to="item.to"
          class="flex min-h-16 flex-col items-center justify-center gap-1 px-1 text-xs font-medium transition"
          :class="isActive(item.to) ? 'text-primary-600' : 'text-gray-500'"
        >
          <span class="text-base leading-none">{{ item.icon }}</span>
          <span class="max-w-full truncate">{{ item.label }}</span>
        </NuxtLink>
      </div>
    </nav>

    <UiConfirmModal
      v-if="showLogoutConfirm"
      title="ログアウト"
      message="ログアウトしますか?"
      confirm-label="ログアウト"
      :is-destructive="true"
      @cancel="showLogoutConfirm = false"
      @confirm="confirmLogout"
    />
  </div>
</template>
