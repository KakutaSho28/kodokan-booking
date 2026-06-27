<script setup lang="ts">
import { InboxIcon } from '@heroicons/vue/24/outline'

withDefaults(defineProps<{
  message?: string
}>(), {
  message: 'データがありません',
})
</script>

<template>
  <div class="flex min-h-44 flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white px-4 py-8 text-center">
    <div class="grid size-12 place-items-center rounded-full bg-gray-100 text-gray-500">
      <InboxIcon class="size-6" aria-hidden="true" />
    </div>
    <p class="mt-3 text-sm font-semibold text-gray-700">{{ message }}</p>
    <div v-if="$slots.default" class="mt-4">
      <slot />
    </div>
  </div>
</template>
