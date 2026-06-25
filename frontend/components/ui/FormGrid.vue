<script setup lang="ts">
defineSlots<{
  default?: (props: {
    fieldClass: string
    labelClass: string
    controlClass: string
    fullClass: string
  }) => unknown
}>()

const fieldClass = 'grid gap-1.5'
const labelClass = 'text-sm font-semibold text-gray-700'
const controlClass = 'min-h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm outline-none transition focus:border-primary-600 focus:ring-2 focus:ring-blue-100 disabled:cursor-not-allowed disabled:bg-gray-100'
const fullClass = 'lg:col-span-2'
</script>

<template>
  <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <slot
      :field-class="fieldClass"
      :label-class="labelClass"
      :control-class="controlClass"
      :full-class="fullClass"
    />
  </div>
</template>
