<script setup>
import { ref, computed, onMounted } from 'vue'
import { seService } from '../../services/seService.js'
import SeSkelRow from './SeSkelRow.vue'
import SeTrailModal from './SeTrailModal.vue'
import SeLogActionModal from './SeLogActionModal.vue'

const loading = ref(true)
const data    = ref(null)
const filters = ref({ q: '', status: '', district: '' })

async function load() {
  loading.value = true
  try { data.value = await seService.retestSamples() }
  catch { data.value = null }
  finally { loading.value = false }
}
onMounted(load)

const fmtDate = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }) }
  catch { return d }
}
function statusPill(s) {
  if (s === 'Awaiting Analysis') return 'p-amber'
  if (s === 'In Analysis')       return 'p-blue'
  if (s === 'Analysed')          return 'p-green'
  return 'p-grey'
}
function resultPill(r) {
  if (r === 'Fit')   return 'p-green'
  if (r === 'Unfit') return 'p-red'
  return 'p-grey'
}

function applyFilter(arr) {
  if (!arr) return []
  const q = filters.value.q.trim().toLowerCase()
  return arr.filter(r => {
    if (filters.value.district && r.district !== filters.value.district) return false
    if (q) {
      const hay = `${r.retest_slug} ${r.original_slug} ${r.wss_name}`.toLowerCase()
      if (!hay.includes(q)) return false
    }
    return true
  })
}

const active      = computed(() => applyFilter(data.value?.sections?.active))
const resolved    = computed(() => applyFilter(data.value?.sections?.resolved))
const stillUnfit  = computed(() => applyFilter(data.value?.sections?.still_unfit))

// ── Trail + Log modals (operate on the parent water_sample_id) ───
const showTrailModal = ref(false)
const trailSampleId  = ref(null)
function openTrail(r) { trailSampleId.value = r.water_sample_id || r.id; showTrailModal.value = true }

const showLogModal = ref(false)
const logSample    = ref({ id: null, slug: '', wss: '' })
function openLog(r) {
  logSample.value = { id: r.water_sample_id || r.id, slug: r.original_slug || '', wss: r.wss_name || '' }
  showLogModal.value = true
}
function onActionSaved() { load() }

const districts = computed(() => {
  const all = [
    ...(data.value?.sections?.active || []),
    ...(data.value?.sections?.resolved || []),
    ...(data.value?.sections?.still_unfit || []),
  ]
  return [...new Set(all.map(r => r.district).filter(Boolean))].sort()
})
</script>

<template>
  <div class="sd">
    <div class="sd-scope">
      <span class="pin">📍</span>
      <span><b>Retest Samples — your circle only.</b></span>
      <span class="sep">·</span>
      <span>All retests linked to original unfit samples in your circle. Click Trail to see the full action timeline.</span>
    </div>

    <div class="sd-cards cards-4">
      <div class="c c-amber">
        <div class="lbl">AWAITING ANALYSIS</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.awaiting ?? 0 }}</div>
      </div>
      <div class="c">
        <div class="lbl">IN ANALYSIS</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.in_analysis ?? 0 }}</div>
      </div>
      <div class="c c-green">
        <div class="lbl">FIT (RESOLVED)</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.fit ?? 0 }}</div>
      </div>
      <div class="c c-red">
        <div class="lbl">STILL UNFIT</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.still_unfit ?? 0 }}</div>
      </div>
    </div>

    <div class="sd-toolbar">
      <div class="fg"><label>Search</label><input v-model="filters.q" type="text" placeholder="Retest ID, Original ID, WSS…"></div>
      <div class="fg"><label>Status</label>
        <select v-model="filters.status">
          <option value="">All Status</option>
          <option value="active">Active / Pending</option>
          <option value="resolved">Resolved</option>
          <option value="still_unfit">Still Unfit</option>
        </select>
      </div>
      <div class="fg"><label>District</label>
        <select v-model="filters.district">
          <option value="">All Districts</option>
          <option v-for="d in districts" :key="d" :value="d">{{ d }}</option>
        </select>
      </div>
      <div class="spacer"></div>
    </div>

    <table class="sd-tbl">
      <thead>
        <tr>
          <th>Retest ID</th><th>Original Sample</th><th>WSS</th><th>District</th>
          <th>Stage</th><th>Collection Date</th><th>Cause</th><th>Status</th><th>Result</th><th>Trail</th>
        </tr>
      </thead>
      <tbody>
        <template v-if="loading">
          <SeSkelRow v-for="n in 5" :key="'rs' + n" :cols="[100, 100, 160, 80, 40, 90, 80, 90, 60, 40]" />
        </template>
        <template v-else>
          <!-- ACTIVE / PENDING -->
          <template v-if="!filters.status || filters.status === 'active'">
            <tr class="group-h"><td colspan="10">ACTIVE / PENDING</td></tr>
            <tr v-for="r in active" :key="'a' + r.id">
              <td class="sid">{{ r.retest_slug }}</td>
              <td><RouterLink :to="`/se/isr?q=${r.original_slug}`" style="color:#1d4ed8;text-decoration:none">{{ r.original_slug }}</RouterLink></td>
              <td><b>{{ r.wss_name }}</b></td>
              <td>{{ r.district }}</td>
              <td><span class="sd-pill p-cyan">{{ r.stage }}</span></td>
              <td>{{ fmtDate(r.collection_date) }}</td>
              <td><span class="sd-pill p-red">{{ r.cause }}</span></td>
              <td><span class="sd-pill" :class="statusPill(r.status)">{{ r.status }}</span></td>
              <td>—</td>
              <td><button class="sd-btn sd-btn-sec" @click="openTrail(r)">Trail</button></td>
            </tr>
            <tr v-if="!active.length"><td colspan="10" class="empty">No active retests.</td></tr>
          </template>

          <!-- RESOLVED -->
          <template v-if="!filters.status || filters.status === 'resolved'">
            <tr class="group-h"><td colspan="10">RESOLVED — RETEST CONFIRMED FIT</td></tr>
            <tr v-for="r in resolved" :key="'rv' + r.id">
              <td class="sid">{{ r.retest_slug }}</td>
              <td><RouterLink :to="`/se/isr?q=${r.original_slug}`" style="color:#1d4ed8;text-decoration:none">{{ r.original_slug }}</RouterLink></td>
              <td><b>{{ r.wss_name }}</b></td>
              <td>{{ r.district }}</td>
              <td><span class="sd-pill p-cyan">{{ r.stage }}</span></td>
              <td>{{ fmtDate(r.collection_date) }}</td>
              <td><span class="sd-pill p-red">{{ r.cause }}</span></td>
              <td><span class="sd-pill p-green">Analysed</span></td>
              <td><span class="sd-pill" :class="resultPill(r.result)">{{ r.result }}</span></td>
              <td><button class="sd-btn sd-btn-sec" @click="openTrail(r)">Trail</button></td>
            </tr>
            <tr v-if="!resolved.length"><td colspan="10" class="empty">No resolved retests yet.</td></tr>
          </template>

          <!-- STILL UNFIT -->
          <template v-if="!filters.status || filters.status === 'still_unfit'">
            <tr class="group-h"><td colspan="10">STILL UNFIT — FURTHER ACTION REQUIRED</td></tr>
            <tr v-for="r in stillUnfit" :key="'u' + r.id" style="background:#fff5f5">
              <td class="sid red">{{ r.retest_slug }}</td>
              <td><RouterLink :to="`/se/isr?q=${r.original_slug}`" style="color:#1d4ed8;text-decoration:none">{{ r.original_slug }}</RouterLink></td>
              <td><b>{{ r.wss_name }}</b></td>
              <td>{{ r.district }}</td>
              <td><span class="sd-pill p-cyan">{{ r.stage }}</span></td>
              <td>{{ fmtDate(r.collection_date) }}</td>
              <td><span class="sd-pill p-red">{{ r.cause }}</span></td>
              <td><span class="sd-pill p-green">Analysed</span></td>
              <td><span class="sd-pill p-red">Still Unfit</span></td>
              <td>
                <button class="sd-btn sd-btn-sec">Trail</button>
                <button class="sd-btn sd-btn-pri" style="margin-left:4px" @click="openLog(r)">Log R{{ r.round + 1 }}</button>
              </td>
            </tr>
            <tr v-if="!stillUnfit.length"><td colspan="10" class="empty">No still-unfit retests.</td></tr>
          </template>
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
