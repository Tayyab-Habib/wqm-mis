<script setup>
import './DataTable.scss'

defineProps({
  columns: { type: Array, required: true },
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})
</script>

<template>
  <div class="data-table">
    <table class="data-table__table" role="table">
      <thead>
        <tr>
          <th v-for="col in columns" :key="col.key" scope="col">{{ col.label }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading">
          <td :colspan="columns.length" class="data-table__loading">Loading…</td>
        </tr>
        <tr v-else-if="!rows.length">
          <td :colspan="columns.length" class="data-table__empty">No data available.</td>
        </tr>
        <tr v-for="(row, i) in rows" :key="i" v-else>
          <td v-for="col in columns" :key="col.key">{{ row[col.key] }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
