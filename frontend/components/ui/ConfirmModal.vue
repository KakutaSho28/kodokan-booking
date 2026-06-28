<script setup lang="ts">
const props = withDefaults(defineProps<{
  title: string
  message: string
  confirmLabel?: string
  isDestructive?: boolean
}>(), {
  confirmLabel: '確定',
  isDestructive: false,
})

const emit = defineEmits<{
  confirm: []
  cancel: []
}>()

const modalRef = ref<HTMLElement | null>(null)
const titleId = `confirm-title-${Math.random().toString(36).slice(2)}`

const focusableSelector = [
  'button:not([disabled])',
  '[href]',
  'input:not([disabled])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',')

function focusableElements() {
  return Array.from(modalRef.value?.querySelectorAll<HTMLElement>(focusableSelector) || [])
}

function cancel() {
  emit('cancel')
}

function confirm() {
  emit('confirm')
}

function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    event.preventDefault()
    cancel()
    return
  }

  if (event.key !== 'Tab') return

  const elements = focusableElements()
  if (elements.length === 0) return

  const first = elements[0]
  const last = elements[elements.length - 1]

  // モーダル外へフォーカスが抜けないように循環させる。
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first.focus()
  }
}

onMounted(async () => {
  document.addEventListener('keydown', handleKeydown)
  await nextTick()
  focusableElements()[0]?.focus()
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <Teleport to="body">
    <div
      class="fixed inset-0 z-50 flex min-h-dvh items-center justify-center bg-slate-950/45 px-4 py-6"
      role="presentation"
      @click.self="cancel"
    >
      <section
        ref="modalRef"
        aria-modal="true"
        class="w-full max-w-md rounded-lg bg-white p-6 shadow-2xl ring-1 ring-slate-200"
        role="dialog"
        :aria-labelledby="titleId"
      >
        <h2 :id="titleId" class="text-lg font-bold text-slate-900">
          {{ props.title }}
        </h2>
        <p class="mt-3 text-sm leading-6 text-slate-600">
          {{ props.message }}
        </p>

        <div class="mt-6 flex justify-end gap-3">
          <button
            class="min-h-11 rounded-md bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-slate-400"
            type="button"
            @click="cancel"
          >
            キャンセル
          </button>
          <button
            class="min-h-11 rounded-md px-4 py-2 text-sm font-semibold text-white transition focus:outline-none focus:ring-2"
            :class="props.isDestructive
              ? 'bg-red-600 hover:bg-red-700 focus:ring-red-400'
              : 'bg-sky-700 hover:bg-sky-800 focus:ring-sky-400'"
            type="button"
            @click="confirm"
          >
            {{ props.confirmLabel }}
          </button>
        </div>
      </section>
    </div>
  </Teleport>
</template>
