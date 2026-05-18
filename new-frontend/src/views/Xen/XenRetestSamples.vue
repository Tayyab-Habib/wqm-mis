<script setup>
import { ref, onMounted } from 'vue'
import { xenService } from '../../services/xenService.js'
import SkelRow from './SkelRow.vue'
import XenTrailModal from './XenTrailModal.vue'

const loading = ref(true)
const retests = ref([])
const stats   = ref({ awaiting_analysis: 0, fit_resolved: 0, still_unfit: 0 })

async function load() {
  loading.value = true
  try {
    const res = await xenService.retestSamples()
    retests.value = res.retests || []
    stats.value = res.stats || stats.value
  } catch { retests.value = [] } finally { loading.value = false }
}
onMounted(load)

const fmtDate = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }) }
  catch { return d }
}
function statusClass(s) {
  s = (s || '').toLowerCase()
  if (s.includes('awaiting')) return 'st-grey'
  if (s.includes('in analysis')) return 'st-amber'
  if (s.includes('analysed')) return 'st-green'
  return 'st-grey'
}
function resultClass(r) {
  r = (r || '').toLowerCase()
  if (r === 'fit') return 'st-green'
  if (r === 'unfit') return 'st-red'
  return 'st-grey'
}

// ── Trail modal ───────────────────────────────────────────────
const showTrailModal = ref(false)
const trailSampleId  = ref(null)
function openTrail(r) {
  trailSampleId.value = r.water_sample_id || r.id
  showTrailModal.value = true
}
function onActionSaved() { load() }
</script>

<template>
  <div class="xd">
    <div class="xd-scope">
      <span class="pin">📍</span>
      <span class="t1">Retest Samples — your division only.</span>
    </div>

    <div class="xd-cards" style="grid-template-columns: repeat(3,1fr)">
      <div class="c c-amber">
        <div class="lbl">AWAITING ANALYSIS</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ stats.awaiting_analysis }}</div>
      </div>
      <div class="c c-green">
        <div class="lbl">FIT (RESOLVED)</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ stats.fit_resolved }}</div>
      </div>
      <div class="c c-red">
        <div class="lbl">STILL UNFIT</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ stats.still_unfit }}</div>
      </div>
    </div>

    <div class="panel">
      <table class="tbl">
        <thead>
          <tr>
            <th>Retest ID</th><th>Original Sample</th><th>WSS</th><th>Stage</th><th>Date</th><th>Cause</th><th>Status</th><th>Result</th><th>Trail</th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <SkelRow v-for="n in 6" :key="'rs' + n" :cols="[90, 90, 160, 40, 90, 80, 90, 60, 80]" />
          </template>
          <template v-else>
            <tr v-for="r in retests" :key="r.id">
              <td class="sid">{{ r.retest_slug }}</td>
              <td><RouterLink :to="`/xen/isr/${r.water_sample_id}`" class="link">{{ r.original_slug }}</RouterLink></td>
              <td>{{ r.wss_name }}</td>
              <td><span class="pill st-blue">{{ r.stage }}</span></td>
              <td>{{ fmtDate(r.sampled_at) }}</td>
              <td><span class="pill pill-red">{{ r.cause }}</span></td>
              <td><span class="pill" :class="statusClass(r.status)">{{ r.status }}</span></td>
              <td><span class="pill" :class="resultClass(r.result)">{{ r.result }}</span></td>
              <td><button class="btn btn-sec" @click="openTrail(r)">👁 Trail</button></td>
            </tr>
            <tr v-if="retests.length === 0"><td colspan="9" class="empty">No retests in your division.</td></tr>
          </template>
        </tbody>
      </table>
    </div>

    <XenTrailModal v-model="showTrailModal" :sample-id="trailSampleId" @saved="onActionSaved" />
  </div>
</template>

<style scoped lang="scss">
@use './xen-shared.scss' as *;
</style>
