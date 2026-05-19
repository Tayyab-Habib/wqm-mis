<script setup>
import { ref, onMounted, watch } from 'vue'
import { seService } from '../../services/seService.js'
import SeSkelRow from './SeSkelRow.vue'
import SeTrailModal from './SeTrailModal.vue'
import SeLogActionModal from './SeLogActionModal.vue'

const loading = ref(true)
const data    = ref(null)
const filters = ref({ q: '', status: '' })

async function load() {
  loading.value = true
  try {
    const params = {}
    if (filters.value.q)      params.q = filters.value.q
    if (filters.value.status) params.status = filters.value.status
    data.value = await seService.unfitTrail(params)
  } catch { data.value = null }
  finally { loading.value = false }
}
onMounted(load)
let t = null
watch(filters, () => { clearTimeout(t); t = setTimeout(load, 300) }, { deep: true })

const fmtDate = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }) }
  catch { return d }
}
function statusPill(s) {
  if (s === 'No Action')    return 'p-red'
  if (s === 'Action Taken') return 'p-amber'
  if (s === 'Re-notified')  return 'p-violet'
  return 'p-grey'
}

// ── Trail + Log modals ─────────────────────────────────────────
const showTrailModal = ref(false)
const trailSampleId  = ref(null)
function openTrail(row) { trailSampleId.value = row.id; showTrailModal.value = true }

const showLogModal = ref(false)
const logSample    = ref({ id: null, slug: '', wss: '' })
function openLog(row) {
  logSample.value = { id: row.id, slug: row.slug || '', wss: row.wss_name || '' }
  showLogModal.value = true
}
function onActionSaved() { load() }

function exportCsv() {
  const rows = (data.value?.groups || []).flatMap(g => g.rows.map(r => ({ district: g.district, ...r })))
  if (!rows.length) return
  const head = ['Sample ID', 'WSS', 'District', 'Date', 'Cause', 'Parameter', 'Status', 'Stage']
  const csv = [head, ...rows.map(r => [r.slug, r.wss_name, r.district, fmtDate(r.sampled_at), r.cause, r.parameter, r.status, r.stage])]
    .map(r => r.map(c => `"${(c ?? '').toString().replace(/"/g, '""')}"`).join(',')).join('\n')
  const blob = new Blob([csv], { type: 'text/csv' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  a.download = `se_unfit_trail_${new Date().toISOString().slice(0,10)}.csv`
  a.click()
}
</script>

<template>
  <div class="sd">
    <div class="sd-scope">
      <span class="pin">📍</span>
      <span><b>Unfit Trail — your circle only.</b></span>
    </div>

    <div class="sd-cards cards-5">
      <div class="c c-red">
        <div class="lbl">TOTAL UNFIT</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.total_unfit ?? 0 }}</div>
      </div>
      <div class="c c-red">
        <div class="lbl">NO ACTION</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.no_action ?? 0 }}</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">ACTION TAKEN</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.action_taken ?? 0 }}</div>
      </div>
      <div class="c">
        <div class="lbl">RE-NOTIFIED</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.re_notified ?? 0 }}</div>
      </div>
      <div class="c c-green">
        <div class="lbl">RESOLVED</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.resolved ?? 0 }}</div>
      </div>
    </div>

    <div class="sd-toolbar">
      <div class="fg"><label>Search</label><input v-model="filters.q" type="text" placeholder="Sample ID, WSS…"></div>
      <div class="fg"><label>Status</label>
        <select v-model="filters.status">
          <option value="">All Status</option>
          <option value="No Action">No Action</option>
          <option value="Action Taken">Action Taken</option>
          <option value="Re-notified">Re-notified</option>
        </select>
      </div>
      <div class="spacer"></div>
      <button class="sd-btn sd-btn-sec" @click="exportCsv">⬇ Export</button>
    </div>

    <table class="sd-tbl">
      <thead>
        <tr>
          <th>Sample ID</th><th>WSS</th><th>District</th><th>Date</th>
          <th>Cause</th><th>Parameter</th><th>Status</th><th>Stage</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <template v-if="loading">
          <SeSkelRow v-for="n in 5" :key="'ut' + n" :cols="[90, 160, 80, 80, 80, 110, 80, 40, 100]" />
        </template>
        <template v-else>
          <template v-for="g in (data?.groups || [])" :key="g.district">
            <tr class="group-h"><td colspan="9">{{ g.district }}</td></tr>
            <tr v-for="r in g.rows" :key="r.id">
              <td class="sid" :class="{ red: r.status === 'No Action' || r.status === 'Re-notified' }">{{ r.slug }}</td>
              <td><b>{{ r.wss_name }}</b></td>
              <td>{{ r.district }}</td>
              <td>{{ fmtDate(r.sampled_at) }}</td>
              <td><span class="sd-pill p-red">{{ r.cause }}</span></td>
              <td>{{ r.parameter }}</td>
              <td><span class="sd-pill" :class="statusPill(r.status)">{{ r.status }}</span></td>
              <td>{{ r.stage }}</td>
              <td>
                <button v-if="r.status === 'No Action'" class="sd-btn sd-btn-pri" @click="openLog(r)">Log</button>
                <button v-else class="sd-btn sd-btn-pri" @click="openLog(r)">Retest</button>
                <button class="sd-btn sd-btn-sec" style="margin-left:4px" @click="openTrail(r)">Trail</button>
              </td>
            </tr>
          </template>
          <tr v-if="!(data?.groups || []).length"><td colspan="9" class="empty">No unfit samples in your circle.</td></tr>
        </template>
      </tbody>
    </table>

    <SeTrailModal     v-model="showTrailModal" :sample-id="trailSampleId" @saved="onActionSaved" />
    <SeLogActionModal v-model="showLogModal"   :sample-id="logSample.id" :sample-slug="logSample.slug" :wss-name="logSample.wss" @saved="onActionSaved" />
  </div>
</template>

<style scoped lang="scss">
@use './se-shared.scss' as *;
</style>
