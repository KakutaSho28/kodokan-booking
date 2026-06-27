<script setup lang="ts">
withDefaults(defineProps<{
  rows?: number
}>(), {
  rows: 3,
})
</script>

<template>
  <div class="animate-pulse space-y-3">
    <div
      v-for="row in rows"
      :key="row"
      class="h-12 rounded-lg bg-gray-100"
    />
  </div>
</template>
