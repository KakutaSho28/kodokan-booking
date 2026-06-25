<script setup lang="ts">
definePageMeta({
  layout: 'admin',
})

const metrics = [
  { label: '本日の予約', value: '24' },
  { label: '空き枠', value: '18' },
  { label: 'キャンセル', value: '2' },
]
</script>

<template>
  <div class="space-y-6">
    <header>
      <h1 class="text-2xl font-bold text-gray-900">ダッシュボード</h1>
      <p class="mt-1 text-sm text-gray-500">リハビリ予約の稼働状況を確認します。</p>
    </header>

    <section class="grid gap-4 md:grid-cols-3">
      <article
        v-for="metric in metrics"
        :key="metric.label"
        class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm"
      >
        <p class="text-sm font-medium text-gray-500">{{ metric.label }}</p>
        <p class="mt-2 text-3xl font-bold text-gray-900">{{ metric.value }}</p>
      </article>
    </section>
  </div>
</template>
