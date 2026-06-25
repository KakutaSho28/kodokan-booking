<script setup lang="ts">
import type { ToastItem, ToastType } from '~/composables/useToast'

const { toasts, removeToast } = useToast()
const timers = new Map<number, ReturnType<typeof setTimeout>>()

const styles: Record<ToastType, string> = {
  success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
  error: 'border-red-200 bg-red-50 text-red-900',
  info: 'border-sky-200 bg-sky-50 text-sky-900',
  warning: 'border-amber-200 bg-amber-50 text-amber-900',
}

const labels: Record<ToastType, string> = {
  success: '成功',
  error: 'エラー',
  info: 'お知らせ',
  warning: '注意',
}

function scheduleDismiss(toast: ToastItem) {
  if (timers.has(toast.id)) return

  // 表示から3秒後に自動で閉じる。
  timers.set(toast.id, setTimeout(() => {
    removeToast(toast.id)
    timers.delete(toast.id)
  }, 3000))
}

watch(toasts, (items) => {
  items.forEach(scheduleDismiss)
}, { deep: true, immediate: true })

onBeforeUnmount(() => {
  timers.forEach((timer) => clearTimeout(timer))
  timers.clear()
})
</script>

<template>
  <Teleport to="body">
    <div class="fixed right-1/2 top-4 z-[60] flex w-[calc(100%-2rem)] translate-x-1/2 flex-col gap-3 sm:right-4 sm:w-96 sm:translate-x-0">
      <TransitionGroup
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-2 opacity-0 sm:translate-x-4 sm:translate-y-0"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-to-class="translate-y-2 opacity-0 sm:translate-x-4 sm:translate-y-0"
        tag="div"
        class="flex flex-col gap-3"
      >
        <article
          v-for="toast in toasts"
          :key="toast.id"
          class="rounded-lg border px-4 py-3 shadow-lg backdrop-blur"
          :class="styles[toast.type]"
          role="status"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xs font-bold">{{ labels[toast.type] }}</p>
              <p class="mt-1 text-sm leading-5">{{ toast.message }}</p>
            </div>
            <button
              class="rounded p-1 text-current/70 transition hover:bg-black/5 hover:text-current focus:outline-none focus:ring-2 focus:ring-current/30"
              type="button"
              aria-label="通知を閉じる"
              @click="removeToast(toast.id)"
            >
              ×
            </button>
          </div>
        </article>
      </TransitionGroup>
    </div>
  </Teleport>
</template>
