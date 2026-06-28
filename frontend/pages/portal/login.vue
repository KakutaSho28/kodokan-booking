<script setup lang="ts">
import type { Patient } from "~/types/booking";

type LoginResponse = {
  token: string;
  user: Patient;
  can_book_rehab: boolean;
  message: string;
};

definePageMeta({
  layout: "portal",
});

const router = useRouter();
const config = useRuntimeConfig();
const { setSession } = useAuth();

const loginForm = reactive({
  card_number: "100001",
  birth_date: "1984-04-12",
});
const loading = ref(false);
const errorMessage = ref("");

function apiError(err: unknown) {
  const anyError = err as {
    data?: { message?: string; errors?: Record<string, string[]> };
    message?: string;
  };
  const firstValidationError = anyError.data?.errors
    ? Object.values(anyError.data.errors)[0]?.[0]
    : null;

  return (
    firstValidationError ||
    anyError.data?.message ||
    anyError.message ||
    "通信に失敗しました。"
  );
}

async function loginPatient() {
  loading.value = true;
  errorMessage.value = "";

  try {
    const response = await $fetch<LoginResponse>(
      `${config.public.apiBase}/auth/patient`,
      {
        method: "POST",
        body: loginForm,
      },
    );

    setSession(response.token, "patient", response.user);
    await router.push("/portal/book");
  } catch (err) {
    errorMessage.value = apiError(err);
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="mx-auto max-w-md pt-8">
    <header class="border-b border-gray-200 pb-5 text-center">
      <p class="text-lg font-bold text-[#2C5F8A]">講道館ビルクリニック</p>
      <h1 class="mt-2 text-2xl font-bold text-[#333333]">患者ログイン</h1>
    </header>

    <section
      class="mt-6 rounded-lg border border-gray-100 bg-white p-5 shadow-sm"
    >
      <div
        class="rounded-r-lg border-l-4 border-[#2C5F8A] bg-[#F0F7FF] p-4 text-sm leading-6 text-[#333333]"
      >
        初めてリハビリを受けられる方は、まず医師の診察が必要です。予約はその後にご利用いただけます。
      </div>

      <p
        v-if="errorMessage"
        class="mt-4 rounded-lg border border-red-200 bg-[#FEF2F2] p-3 text-sm font-semibold text-[#E74C3C]"
      >
        {{ errorMessage }}
      </p>

      <form class="mt-5 space-y-4" @submit.prevent="loginPatient">
        <div class="grid gap-1.5">
          <label class="text-sm font-semibold text-[#333333]" for="card-number"
            >診察券番号</label
          >
          <input
            id="card-number"
            v-model="loginForm.card_number"
            class="h-[52px] w-full rounded-lg border border-gray-300 bg-white px-3 text-base text-[#333333] transition-all duration-150 ease-in-out focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[#2C5F8A]"
            type="text"
          />
        </div>

        <div class="grid gap-1.5">
          <label class="text-sm font-semibold text-[#333333]" for="birth-date"
            >生年月日</label
          >
          <input
            id="birth-date"
            v-model="loginForm.birth_date"
            class="h-[52px] w-full rounded-lg border border-gray-300 bg-white px-3 text-base text-[#333333] transition-all duration-150 ease-in-out focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[#2C5F8A]"
            type="date"
          />
        </div>

        <button
          class="flex h-[52px] w-full items-center justify-center rounded-lg bg-[#2C5F8A] px-4 text-base font-semibold text-white transition-all duration-150 ease-in-out hover:bg-[#4A90B8] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A] disabled:cursor-not-allowed disabled:opacity-60"
          type="submit"
          :disabled="loading"
        >
          ログイン
        </button>
      </form>

      <p class="mt-4 text-sm leading-6 text-gray-500">
        診察券番号・生年月日でログインできます。ご不明な場合は受付
        (03-5842-6311) にお問い合わせください。
      </p>
    </section>
  </div>
</template>
