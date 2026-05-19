<script setup>
import { ref, computed, onMounted } from 'vue'
import { seService } from '../../services/seService.js'
import SeSkelRow from './SeSkelRow.vue'
import SeTrailModal from './SeTrailModal.vue'
import SeLogActionModal from './SeLogActionModal.vue'

const loading = ref(true)
const data    = ref(null)

async function load() {
  loading.value = true
  try { data.value = await seService.dashboard() }
  catch { data.value = null }
  finally { loading.value = false }
}
onMounted(load)

const scope = computed(() => data.value?.scope || {})
const stats = computed(() => data.value?.stats || {})
const unfitSamples  = computed(() => data.value?.unfit_samples || [])
const retestSamples = computed(() => data.value?.retest_samples || [])
const overduePanel  = computed(() => data.value?.overdue_panel || [])
const notifications = computed(() => data.value?.notifications || [])

const fmtDate = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }) }
  catch { return d }
}
const fmtDateTime = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: '2-digit', hour: '2-digit', minute: '2-digit' }) }
  catch { return d }
}
function statusPill(s) {
  if (s === 'No Action')     return 'p-red'
  if (s === 'Action Taken')  return 'p-amber'
  if (s === 'Re-notified')   return 'p-violet'
  return 'p-grey'
}
function retestStatusPill(s) {
  if (s === 'Awaiting' || s === 'Awaiting Analysis') return 'p-amber'
  if (s === 'In Analysis') return 'p-blue'
  if (s === 'Analysed')    return 'p-green'
  return 'p-grey'
}

// ── Trail + Log Action modals ───────────────────────────────────────
const showTrailModal = ref(false)
const trailSampleId  = ref(null)
function openTrail(row) {
  trailSampleId.value = row.id || row.water_sample_id
  showTrailModal.value = true
}

const showLogModal = ref(false)
const logSample    = ref({ id: null, slug: '', wss: '' })
function openLog(row) {
  logSample.value = {
    id:   row.id || row.water_sample_id,
    slug: row.slug || '',
    wss:  row.wss_name || '',
  }
  showLogModal.value = true
}
function onActionSaved() { load() }
</script>

<template>
  <div class="sd">
    <!-- Scope bar -->
    <div class="sd-scope">
      <span class="pin">📍</span>
      <span><b>Scope:</b></span>
      <b>{{ scope.name || '—' }}</b>
      <span class="sep">Circle</span>
      <span>— {{ (scope.districts || []).join(', ') }} districts only.</span>
    </div>

    <!-- KPI cards -->
    <div class="sd-cards cards-4">
      <div class="c c-red">
        <div class="lbl">UNFIT — NO ACTION YET</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ stats.unfit_no_action ?? 0 }}</div>
        <div class="sub" v-if="!loading">Immediate action required</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">RETESTS PENDING</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ stats.retests_pending ?? 0 }}</div>
        <div class="sub" v-if="!loading">Awaiting analysis</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">OVERDUE WSS</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ stats.overdue_wss ?? 0 }}</div>
        <div class="sub" v-if="!loading">Past scheduled retest</div>
      </div>
      <div class="c c-green">
        <div class="lbl">RESOLVED THIS YEAR</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ stats.resolved_this_year ?? 0 }}</div>
        <div class="sub" v-if="!loading">Retest confirmed fit</div>
      </div>
    </div>

    <!-- Two-column: Unfit Samples table | side panels -->
    <div style="display:grid;grid-template-columns:1fr 320px;gap:14px;align-items:start">

      <!-- Left: Unfit Samples — Action Required -->
      <div class="sd-tbl-wrap">
        <div class="sd-tbl-head">
          Unfit Samples — Action Required
          <RouterLink to="/se/unfit-trail" class="view-all">View Full Trail</RouterLink>
        </div>
        <table class="sd-tbl">
          <thead>
            <tr>
              <th>Sample ID</th><th>WSS</th><th>District</th><th>Date</th>
              <th>Cause</th><th>Status</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="loading">
              <SeSkelRow v-for="n in 4" :key="'us' + n" :cols="[90, 160, 80, 80, 80, 80, 100]" />
            </template>
            <template v-else>
              <tr v-for="r in unfitSamples" :key="r.id">
                <td class="sid" :class="{ red: r.status === 'No Action' || r.status === 'Re-notified' }">{{ r.slug }}</td>
                <td><b>{{ r.wss_name }}</b></td>
                <td>{{ r.district }}</td>
                <td>{{ fmtDate(r.sampled_at) }}</td>
                <td><span class="sd-pill p-red">{{ r.cause }}</span></td>
                <td><span class="sd-pill" :class="statusPill(r.status)">{{ r.status }}</span></td>
                <td>
                  <button v-if="r.status === 'No Action'"    class="sd-btn sd-btn-pri" @click="openLog(r)">Log</button>
                  <button v-else-if="r.status === 'Action Taken' || r.status === 'Re-notified'" class="sd-btn sd-btn-pri" @click="openLog(r)">Retest</button>
                  <button class="sd-btn sd-btn-sec" style="margin-left:4px" @click="openTrail(r)">Trail</button>
                </td>
              </tr>
              <tr v-if="!unfitSamples.length">
                <td colspan="7" class="empty">No unfit samples in your circle.</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Right: side panels -->
      <div style="display:flex;flex-direction:column;gap:12px">
        <div class="sd-side-panel">
          <div class="ps-h">Overdue WSS — {{ scope.name }}</div>
          <template v-if="loading">
            <div class="ps-row" v-for="n in 3" :key="'oh' + n">
              <span class="sd-skel" style="width:80%;height:13px"></span>
              <div style="margin-top:4px"><span class="sd-skel" style="width:50%;height:11px"></span></div>
            </div>
          </template>
          <template v-else>
            <div class="ps-row" v-for="o in overduePanel" :key="o.id">
              <span class="right">{{ fmtDate(o.next_scheduled) }}<br>{{ o.days_overdue }}d overdue</span>
              <div class="nm">{{ o.wss_name }}</div>
              <div class="meta">{{ o.district }}</div>
            </div>
            <div v-if="!overduePanel.length" class="ps-row" style="color:#94a3b8;font-style:italic">No overdue WSS.</div>
          </template>
        </div>

        <div class="sd-side-panel h-navy">
          <div class="ps-h">My Notifications (7 days)</div>
          <template v-if="loading">
            <div class="ps-row" v-for="n in 3" :key="'nh' + n">
              <span class="sd-skel" style="width:80%;height:12px"></span>
            </div>
          </template>
          <template v-else>
            <div class="ps-row" v-for="n in notifications" :key="n.id">
              <div class="nm">{{ n.slug }} <span class="badge">{{ n.label }}</span></div>
              <div class="meta">{{ n.wss_name }} · {{ fmtDateTime(n.created_at) }}</div>
            </div>
            <div v-if="!notifications.length" class="ps-row" style="color:#94a3b8;font-style:italic">No recent notifications.</div>
          </template>
        </div>
      </div>
    </div>

    <!-- Retest Samples — My Area -->
    <div class="sd-tbl-wrap head-navy" style="margin-top:14px">
      <div class="sd-tbl-head">
        Retest Samples — My Area
        <RouterLink to="/se/retest-samples" class="view-all">View All</RouterLink>
      </div>
      <table class="sd-tbl">
        <thead>
          <tr><th>Retest ID</th><th>Original</th><th>WSS</th><th>Stage</th><th>Date</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <SeSkelRow v-for="n in 3" :key="'rs' + n" :cols="[100, 100, 160, 40, 80, 80, 60]" />
          </template>
          <template v-else>
            <tr v-for="r in retestSamples" :key="r.id">
              <td class="sid">{{ r.retest_slug }}</td>
              <td><RouterLink :to="`/se/isr?q=${r.original_slug}`" style="color:#1d4ed8;text-decoration:none">{{ r.original_slug }}</RouterLink></td>
              <td><b>{{ r.wss_name }}</b></td>
              <td><span class="sd-pill p-cyan">{{ r.stage }}</span></td>
              <td>{{ fmtDate(r.date) }}</td>
              <td><span class="sd-pill" :class="retestStatusPill(r.status)">{{ r.status }}</span></td>
              <td><button class="sd-btn sd-btn-sec" @click="openTrail({ id: r.water_sample_id || r.id })">Trail</button></td>
            </tr>
            <tr v-if="!retestSamples.length"><td colspan="7" class="empty">No retest samples yet.</td></tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Trail + Log modals -->
    <SeTrailModal     v-model="showTrailModal" :sample-id="trailSampleId" @saved="onActionSaved" />
    <SeLogActionModal v-model="showLogModal"   :sample-id="logSample.id" :sample-slug="logSample.slug" :wss-name="logSample.wss" @saved="onActionSaved" />
  </div>
</template>

<style scoped lang="scss">
@use './se-shared.scss' as *;
@media (max-width: 1200px) {
  .sd > div[style*="grid-template-columns"] {
    grid-template-columns: 1fr !important;
  }
}
</style>
