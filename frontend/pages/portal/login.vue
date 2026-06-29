<script setup lang="ts">
import { InformationCircleIcon } from "@heroicons/vue/24/outline";
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
  birth_date: "",
});
const birthDateForm = reactive({
  year: "",
  month: "",
  day: "",
});
const loading = ref(false);
const errorMessage = ref("");
const isUndiagnosedError = ref(false);

const currentYear = new Date().getFullYear();
const yearOptions = computed(() =>
  Array.from(
    { length: currentYear - 1930 + 1 },
    (_, index) => currentYear - index,
  ),
);
const dayOptions = computed(() => {
  const year = Number(birthDateForm.year || currentYear);
  const month = Number(birthDateForm.month || 1);
  const lastDay = new Date(year, month, 0).getDate();

  return Array.from({ length: lastDay }, (_, index) => index + 1);
});

function japaneseEra(year: number) {
  if (year >= 2019) return `令和${year - 2018}年`;
  if (year >= 1989) return `平成${year - 1988}年`;
  if (year >= 1926) return `昭和${year - 1925}年`;
  if (year >= 1912) return `大正${year - 1911}年`;

  return "";
}

function yearLabel(year: number) {
  const era = japaneseEra(year);

  return era ? `${year}年 (${era})` : `${year}年`;
}

function buildBirthDate() {
  if (!birthDateForm.year || !birthDateForm.month || !birthDateForm.day) {
    return "";
  }

  return [
    birthDateForm.year,
    birthDateForm.month.padStart(2, "0"),
    birthDateForm.day.padStart(2, "0"),
  ].join("-");
}

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
  isUndiagnosedError.value = false;

  const birthDate = buildBirthDate();

  if (!birthDate) {
    errorMessage.value = "生年月日を年・月・日すべて選択してください。";
    loading.value = false;
    return;
  }

  loginForm.birth_date = birthDate;

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
    const anyError = err as {
      status?: number;
      statusCode?: number;
      response?: { status?: number };
    };
    isUndiagnosedError.value =
      anyError.status === 403 ||
      anyError.statusCode === 403 ||
      anyError.response?.status === 403;
    errorMessage.value = apiError(err);
  } finally {
    loading.value = false;
  }
}

watch(
  () => [birthDateForm.year, birthDateForm.month],
  () => {
    if (
      birthDateForm.day &&
      Number(birthDateForm.day) > dayOptions.value.length
    ) {
      birthDateForm.day = "";
    }
  },
);
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

      <div
        v-if="isUndiagnosedError"
        class="mt-4 flex gap-3 rounded-lg border border-yellow-300 bg-[#FEF9C3] p-4"
      >
        <InformationCircleIcon
          class="mt-0.5 size-5 shrink-0 text-yellow-600"
          aria-hidden="true"
        />
        <div class="text-sm leading-6 text-[#333333]">
          <p class="font-semibold">
            現在、初診診断前のためオンライン予約はご利用いただけません。
          </p>
          <p class="mt-1">リハビリのご予約はお電話にてお承りしております。</p>
          <a
            href="tel:03-5842-6311"
            class="mt-2 block font-bold text-[#2C5F8A] underline"
          >
            📞 03-5842-6311（受付時間：平日 9:00〜18:00）
          </a>
        </div>
      </div>

      <p
        v-else-if="errorMessage"
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
          <label class="text-sm font-semibold text-[#333333]" for="birth-year"
            >生年月日</label
          >
          <div class="flex gap-2">
            <select
              id="birth-year"
              v-model="birthDateForm.year"
              class="h-[52px] min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2 text-base text-[#333333] transition-all duration-150 ease-in-out focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[#2C5F8A]"
            >
              <option value="">年を選択</option>
              <option
                v-for="year in yearOptions"
                :key="year"
                :value="String(year)"
              >
                {{ yearLabel(year) }}
              </option>
            </select>
            <select
              v-model="birthDateForm.month"
              class="h-[52px] min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2 text-base text-[#333333] transition-all duration-150 ease-in-out focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[#2C5F8A]"
            >
              <option value="">月を選択</option>
              <option v-for="month in 12" :key="month" :value="String(month)">
                {{ month }}月
              </option>
            </select>
            <select
              v-model="birthDateForm.day"
              class="h-[52px] min-w-0 flex-1 rounded-lg border border-gray-300 bg-white px-2 text-base text-[#333333] transition-all duration-150 ease-in-out focus:border-transparent focus:outline-none focus:ring-2 focus:ring-[#2C5F8A]"
            >
              <option value="">日を選択</option>
              <option v-for="day in dayOptions" :key="day" :value="String(day)">
                {{ day }}日
              </option>
            </select>
          </div>
        </div>

        <button
          class="flex h-[52px] w-full items-center justify-center rounded-lg bg-[#2C5F8A] px-4 text-base font-semibold text-white transition-all duration-150 ease-in-out hover:bg-[#4A90B8] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2C5F8A] disabled:cursor-not-allowed disabled:opacity-60"
          type="submit"
          :disabled="loading"
        >
          ログイン
        </button>
      </form>

      <p class="mt-3 text-center text-xs text-gray-400">
        ログインすることで
        <NuxtLink
          class="text-[#2C5F8A] underline hover:text-[#4A90B8]"
          rel="noopener noreferrer"
          target="_blank"
          to="/terms"
        >
          利用規約
        </NuxtLink>
        と
        <NuxtLink
          class="text-[#2C5F8A] underline hover:text-[#4A90B8]"
          rel="noopener noreferrer"
          target="_blank"
          to="/privacy"
        >
          プライバシーポリシー
        </NuxtLink>
        に同意したものとみなします
      </p>

      <p class="mt-4 text-sm leading-6 text-gray-500">
        診察券番号・生年月日でログインできます。ご不明な場合は受付
        (03-5842-6311) にお問い合わせください。
      </p>
    </section>
  </div>
</template>
