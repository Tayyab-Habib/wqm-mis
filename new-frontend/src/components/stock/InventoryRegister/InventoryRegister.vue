<script setup>
import './InventoryRegister.scss'
import DataTable from '../../common/DataTable/DataTable.vue'

defineProps({
  rows: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})

const columns = [
  { key: 'assetId', label: 'Asset ID' },
  { key: 'name', label: 'Name' },
  { key: 'location', label: 'Location' },
  { key: 'status', label: 'Status' },
]
</script>

<template>
  <div class="inventory-register">
    <DataTable :columns="columns" :rows="rows" :loading="loading" />
  </div>
</template>
