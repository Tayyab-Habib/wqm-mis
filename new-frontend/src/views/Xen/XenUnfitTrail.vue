<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { xenService } from '../../services/xenService.js'
import SkelRow from './SkelRow.vue'

const router = useRouter()
const loading = ref(true)
const samples = ref([])
const stats   = ref({ total: 0, no_action: 0, action_taken: 0, resolved: 0 })
const q       = ref('')
const status  = ref('')

async function load() {
  loading.value = true
  try {
    const res = await xenService.trail('unfit')
    samples.value = res.samples || []
    stats.value = res.stats || stats.value
  } catch (e) {
    samples.value = []
  } finally {
    loading.value = false
  }
}
onMounted(load)

const filtered = computed(() => {
  return samples.value.filter(s => {
    const matchQ = !q.value ||
      (s.slug || '').toLowerCase().includes(q.value.toLowerCase()) ||
      (s.water_scheme_name || '').toLowerCase().includes(q.value.toLowerCase())
    const matchS = !status.value || (s.status || '').toLowerCase() === status.value.toLowerCase()
    return matchQ && matchS
  })
})

const fmtDate = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }) }
  catch { return d }
}

function statusClass(s) {
  s = (s || '').toLowerCase()
  if (s === 'no_action') return 'st-red'
  if (s === 'action_taken') return 'st-amber'
  if (s === 'resolved') return 'st-green'
  return 'st-grey'
}
function statusText(s) {
  s = (s || '').toLowerCase()
  if (s === 'no_action') return 'No Action'
  if (s === 'action_taken') return 'Action Taken'
  if (s === 'resolved') return 'Resolved'
  return s || '—'
}

function exportCsv() {
  const head = ['Sample ID','WSS','Date','Cause','Parameter','Status','Stage']
  const rows = filtered.value.map(s => [
    s.slug, s.water_scheme_name, fmtDate(s.analyzed_at),
    s.cause || 'Lab Test', s.unfit_parameters || '—',
    statusText(s.status), 'R' + (s.current_round || 0),
  ])
  const csv = [head, ...rows].map(r => r.map(c => `"${(c ?? '').toString().replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `unfit_trail_${new Date().toISOString().slice(0,10)}.csv`
  a.click()
}
</script>

<template>
  <div class="xd">
    <div class="xd-scope">
      <span class="pin">📍</span>
      <span class="t1">Unfit Trail — your division only.</span>
    </div>

    <div class="xd-cards">
      <div class="c">
        <div class="lbl">TOTAL UNFIT</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ stats.total }}</div>
      </div>
      <div class="c c-red">
        <div class="lbl">NO ACTION</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ stats.no_action }}</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">ACTION TAKEN</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ stats.action_taken }}</div>
      </div>
      <div class="c c-green">
        <div class="lbl">RESOLVED</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ stats.resolved }}</div>
      </div>
    </div>

    <div class="xen-toolbar">
      <input v-model="q" type="text" placeholder="Sample ID, WSS…" style="min-width:240px" />
      <select v-model="status">
        <option value="">All Status</option>
        <option value="no_action">No Action</option>
        <option value="action_taken">Action Taken</option>
        <option value="resolved">Resolved</option>
      </select>
      <div class="spacer"></div>
      <button class="btn-export" @click="exportCsv">⬇ Export</button>
    </div>

    <div class="panel">
      <table class="tbl">
        <thead>
          <tr>
            <th>Sample ID</th><th>WSS</th><th>Date</th><th>Cause</th><th>Parameter</th><th>Status</th><th>Stage</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <SkelRow v-for="n in 7" :key="'ut' + n" :cols="[90, 160, 90, 80, 110, 90, 40, 130]" />
          </template>
          <template v-else>
            <tr v-for="s in filtered" :key="s.id" :class="{ 'is-no-action': statusClass(s.status) === 'st-red' }">
              <td class="sid" :class="{ red: statusClass(s.status) === 'st-red' }">{{ s.slug }}</td>
              <td>{{ s.water_scheme_name }}</td>
              <td>{{ fmtDate(s.analyzed_at) }}</td>
              <td><span class="pill pill-red">{{ s.cause || 'Lab Test' }}</span></td>
              <td>{{ s.unfit_parameters || 'See Details' }}</td>
              <td><span class="pill" :class="statusClass(s.status)">{{ statusText(s.status) }}</span></td>
              <td>{{ s.current_round > 0 ? ('R' + s.current_round) : '—' }}</td>
              <td>
                <button v-if="statusClass(s.status) === 'st-red'" class="btn btn-pri" @click="router.push(`/xen/isr/${s.id}`)">▶ Log</button>
                <RouterLink :to="`/xen/isr/${s.id}`" class="btn btn-sec">👁 Trail</RouterLink>
              </td>
            </tr>
            <tr v-if="filtered.length === 0"><td colspan="8" class="empty">No unfit samples match the filters.</td></tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use './xen-shared.scss' as *;
</style>
