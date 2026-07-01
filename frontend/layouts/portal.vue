<script setup lang="ts">
const router = useRouter();
const config = useRuntimeConfig();
const { token, user, userType, loadSession, clearSession } = useAuth();

const avatarClass = computed(() =>
  userType.value === "patient" ? "bg-[#2C5F8A]" : "bg-[#6B7280]",
);
const initials = computed(() => {
  const name = user.value?.name || "利用者";
  return name.slice(0, 1);
});

const showLogoutConfirm = ref(false);

function requestLogout() {
  showLogoutConfirm.value = true;
}

async function confirmLogout() {
  showLogoutConfirm.value = false;

  if (token.value) {
    try {
      await $fetch(`${config.public.apiBase}/logout`, {
        method: "POST",
        headers: { Authorization: `Bearer ${token.value}` },
      });
    } catch {
      // ログアウトはローカルセッションの破棄を優先する。
    }
  }

  clearSession();
  await router.push("/portal/login");
}

onMounted(() => {
  loadSession();
});
</script>

<template>
  <div class="min-h-screen bg-white text-[#333333]">
    <header class="border-b border-gray-200 bg-white">
      <div
        class="mx-auto flex min-h-16 max-w-5xl items-center justify-between gap-4 px-4 md:px-6"
      >
        <NuxtLink
          class="text-base font-bold text-[#2C5F8A] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2C5F8A] focus-visible:ring-offset-2"
          to="/portal/book"
        >
          講道館ビルクリニック
        </NuxtLink>

        <div v-if="user" class="flex items-center gap-3">
          <div
            class="grid size-11 place-items-center rounded-full text-sm font-bold text-white"
            :class="avatarClass"
          >
            {{ initials }}
          </div>
          <span class="hidden text-sm font-semibold text-gray-700 sm:inline">{{
            user.name
          }}</span>
          <button
            class="min-h-11 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-[#666666] transition-all duration-150 ease-in-out hover:bg-[#F5F7FA] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A]"
            type="button"
            @click="requestLogout"
          >
            ログアウト
          </button>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-6 md:px-6 lg:py-8">
      <slot />
    </main>

    <UiConfirmModal
      v-if="showLogoutConfirm"
      title="ログアウト"
      message="ログアウトしますか?"
      confirm-label="ログアウト"
      :is-destructive="true"
      @cancel="showLogoutConfirm = false"
      @confirm="confirmLogout"
    />

    <footer class="border-t border-gray-200 bg-white">
      <div
        class="mx-auto max-w-5xl space-y-2 px-4 py-5 text-xs leading-6 text-gray-500 md:px-6"
      >
        <p>
          講道館ビルクリニック｜〒112-0003 東京都文京区春日1-16-30｜TEL:
          03-5842-6311
        </p>
        <nav class="flex gap-3 text-gray-400">
          <NuxtLink class="underline hover:text-gray-600" to="/terms">
            利用規約
          </NuxtLink>
          <span>|</span>
          <NuxtLink class="underline hover:text-gray-600" to="/privacy">
            プライバシーポリシー
          </NuxtLink>
        </nav>
      </div>
    </footer>
  </div>
</template>
