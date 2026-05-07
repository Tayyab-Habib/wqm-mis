<script setup>
import './KPITable.scss'

defineProps({
  kpis: { type: Array, default: () => [] },
})
</script>

<template>
  <div class="kpi-table">
    <table role="table">
      <thead>
        <tr>
          <th scope="col">KPI</th>
          <th scope="col">Target</th>
          <th scope="col">Actual</th>
          <th scope="col">Status</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="kpi in kpis" :key="kpi.id">
          <td>{{ kpi.name }}</td>
          <td>{{ kpi.target }}</td>
          <td>{{ kpi.actual }}</td>
          <td>{{ kpi.status }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
