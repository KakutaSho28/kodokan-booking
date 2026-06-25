<script setup lang="ts">
type TableColumn = {
  key: string
  label: string
}

type TableRow = Record<string, unknown>

defineProps<{
  columns: TableColumn[]
  rows: TableRow[]
}>()

defineSlots<{
  actions?: (props: { row: TableRow, index: number }) => unknown
}>()

function displayValue(value: unknown) {
  if (value === null || value === undefined || value === '') {
    return '-'
  }

  return String(value)
}
</script>

<template>
  <div>
    <div class="hidden overflow-hidden rounded-lg border border-gray-200 bg-white lg:block">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th
              v-for="column in columns"
              :key="column.key"
              class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
              scope="col"
            >
              {{ column.label }}
            </th>
            <th v-if="$slots.actions" class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500" scope="col">
              操作
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white">
          <tr v-for="(row, index) in rows" :key="index" class="hover:bg-gray-50">
            <td v-for="column in columns" :key="column.key" class="px-4 py-3 text-sm text-gray-700">
              {{ displayValue(row[column.key]) }}
            </td>
            <td v-if="$slots.actions" class="px-4 py-3 text-right">
              <slot name="actions" :row="row" :index="index" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="grid gap-3 lg:hidden">
      <article
        v-for="(row, index) in rows"
        :key="index"
        class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
      >
        <dl class="grid gap-3">
          <div v-for="column in columns" :key="column.key" class="grid gap-1">
            <dt class="text-xs font-semibold text-gray-500">{{ column.label }}</dt>
            <dd class="text-sm font-medium text-gray-900">{{ displayValue(row[column.key]) }}</dd>
          </div>
        </dl>
        <div v-if="$slots.actions" class="mt-4 flex flex-wrap justify-end gap-2 border-t border-gray-100 pt-3">
          <slot name="actions" :row="row" :index="index" />
        </div>
      </article>
    </div>
  </div>
</template>
