<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { xenService } from '../../services/xenService.js'
import SkelRow from './SkelRow.vue'

const router = useRouter()
const loading = ref(true)
const rows    = ref([])
const filters = ref({ q: '', wss: '', date: '', result: 'all' })

async function load() {
  loading.value = true
  try {
    const params = {}
    if (filters.value.q) params.q = filters.value.q
    if (filters.value.date) params.date = filters.value.date
    if (filters.value.result && filters.value.result !== 'all') params.result = filters.value.result
    const res = await xenService.isrList(params)
    rows.value = res.rows || []
  } catch { rows.value = [] } finally { loading.value = false }
}
onMounted(load)
watch(filters, load, { deep: true })

const fmtDate = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }) }
  catch { return d }
}
function resultClass(r) {
  r = (r || '').toLowerCase()
  if (r === 'fit') return 'st-green'
  if (r === 'unfit') return 'st-red'
  return 'st-grey'
}
function clear() {
  filters.value = { q: '', wss: '', date: '', result: 'all' }
}
</script>

<template>
  <div class="xd">
    <div class="xen-toolbar isr-toolbar">
      <div class="fg2"><label>Sample ID</label>
        <input v-model="filters.q" type="text" placeholder="e.g. 26/CLB/5040" style="min-width:230px" />
      </div>
      <div class="fg2"><label>WSS Name</label>
        <input v-model="filters.wss" type="text" placeholder="e.g. Shahi Bagh WSS" style="min-width:230px" />
      </div>
      <div class="fg2"><label>Sampling Date</label>
        <input v-model="filters.date" type="date" />
      </div>
      <div class="fg2"><label>Result</label>
        <select v-model="filters.result">
          <option value="all">All</option>
          <option value="Fit">Fit</option>
          <option value="Unfit">Unfit</option>
        </select>
      </div>
      <button class="btn-export" @click="clear">✕ Clear</button>
      <div class="spacer"></div>
      <button class="btn-export" @click="router.push('/xen/gsr')">← Back to GSR</button>
      <button class="btn-export" @click="() => window.print()">🖨 Print</button>
    </div>

    <div class="panel">
      <div class="panel-h panel-h-navy">
        <span>🗂 Select a Sample to View Report</span>
        <span class="link" style="opacity:.8">
          <span v-if="loading" class="skel" style="width: 80px; height: 11px; background: rgba(255,255,255,.18)"></span>
          <template v-else>{{ rows.length }} samples found</template>
        </span>
      </div>
      <table class="tbl">
        <thead>
          <tr>
            <th>Sample ID</th><th>WSS Name</th><th>Date</th><th>Type</th><th>Result</th><th>Cause / Parameter</th><th></th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <SkelRow v-for="n in 7" :key="'is' + n" :cols="[90, 180, 80, 60, 60, 160, 90]" />
          </template>
          <template v-else>
            <tr v-for="r in rows" :key="r.id" :class="{ 'is-no-action': r.result === 'Unfit' }">
              <td class="sid" :class="{ red: r.result === 'Unfit' }">{{ r.slug }}</td>
              <td><b>{{ r.wss_name }}</b></td>
              <td>{{ fmtDate(r.sampled_at) }}</td>
              <td><span class="pill st-blue">{{ r.type }}</span></td>
              <td><span class="pill" :class="resultClass(r.result)">{{ r.result }}</span></td>
              <td>{{ r.cause_param }}</td>
              <td><RouterLink :to="`/xen/isr/${r.id}`" class="btn btn-pri">📋 View ISR</RouterLink></td>
            </tr>
            <tr v-if="rows.length === 0"><td colspan="7" class="empty">No samples match these filters.</td></tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use './xen-shared.scss' as *;
.isr-toolbar { align-items: end; }
</style>
