<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { seService } from '../../services/seService.js'
import SeSkelRow from './SeSkelRow.vue'

const route  = useRoute()
const router = useRouter()
const loading = ref(true)
const data    = ref(null)
const filters = ref({ q: '', wss: '', district_id: '', date: '', result: '' })

async function load() {
  loading.value = true
  try {
    const params = {}
    if (filters.value.q)           params.q = filters.value.q
    if (filters.value.wss)         params.wss = filters.value.wss
    if (filters.value.district_id) params.district_id = filters.value.district_id
    if (filters.value.date)        params.date = filters.value.date
    if (filters.value.result)      params.result = filters.value.result
    data.value = await seService.isrList(params)
  } catch { data.value = null }
  finally { loading.value = false }
}
onMounted(() => {
  if (route.query.q) filters.value.q = route.query.q
  load()
})
let t = null
watch(filters, () => { clearTimeout(t); t = setTimeout(load, 300) }, { deep: true })

const fmtDate = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }) }
  catch { return d }
}
function resultPill(r) {
  if (r === 'Fit')   return 'p-green'
  if (r === 'Unfit') return 'p-red'
  return 'p-grey'
}
function clearFilters() {
  filters.value = { q: '', wss: '', district_id: '', date: '', result: '' }
}
</script>

<template>
  <div class="sd">
    <div class="sd-toolbar">
      <div class="fg"><label>Sample ID</label>
        <input v-model="filters.q" type="text" placeholder="e.g. 26/CLB/5040">
      </div>
      <div class="fg"><label>WSS Name</label>
        <input v-model="filters.wss" type="text" placeholder="e.g. Shahi Bagh WSS">
      </div>
      <div class="fg"><label>District</label>
        <select v-model="filters.district_id">
          <option value="">All Districts</option>
          <option v-for="d in (data?.districts || [])" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>
      </div>
      <div class="fg"><label>Sampling Date</label>
        <input v-model="filters.date" type="date">
      </div>
      <div class="fg"><label>Result</label>
        <select v-model="filters.result">
          <option value="">All</option>
          <option value="Fit">Fit</option>
          <option value="Unfit">Unfit</option>
        </select>
      </div>
      <button class="sd-btn sd-btn-sec" @click="clearFilters">✕ Clear</button>
      <div class="spacer"></div>
      <button class="sd-btn sd-btn-sec" @click="() => router.push('/se/gsr')">← Back to GSR</button>
      <button class="sd-btn sd-btn-sec" @click="() => window.print()">🖨 Print</button>
    </div>

    <div class="sd-tbl-wrap head-navy">
      <div class="sd-tbl-head">📋 Select a Sample to View Report</div>
      <table class="sd-tbl">
        <thead>
          <tr><th>Sample ID</th><th>WSS Name</th><th>District</th><th>Date</th><th>Result</th><th>Cause</th></tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <SeSkelRow v-for="n in 6" :key="'is' + n" :cols="[100, 160, 80, 80, 60, 120]" />
          </template>
          <template v-else>
            <tr v-for="r in (data?.rows || [])" :key="r.id" style="cursor:pointer" @click="router.push(`/se/isr/${r.id}`)">
              <td class="sid" :class="{ red: r.result === 'Unfit' }">{{ r.slug }}</td>
              <td><b>{{ r.wss_name }}</b></td>
              <td>{{ r.district }}</td>
              <td>{{ fmtDate(r.sampled_at) }}</td>
              <td><span class="sd-pill" :class="resultPill(r.result)">{{ r.result }}</span></td>
              <td>{{ r.cause || '—' }}</td>
            </tr>
            <tr v-if="!(data?.rows || []).length"><td colspan="6" class="empty">Enter a Sample ID, WSS or filter to find samples.</td></tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use './se-shared.scss' as *;
</style>
