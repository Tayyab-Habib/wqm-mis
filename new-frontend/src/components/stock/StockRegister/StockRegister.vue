<script setup>
import './StockRegister.scss'
import DataTable from '../../common/DataTable/DataTable.vue'

defineProps({
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const columns = [
  { key: 'itemCode', label: 'Item Code' },
  { key: 'description', label: 'Description' },
  { key: 'quantity', label: 'Quantity' },
  { key: 'unit', label: 'Unit' },
]
</script>

<template>
  <div class="stock-register">
    <DataTable :columns="columns" :rows="rows" :loading="loading" />
  </div>
</template>
