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
    <!-- ── Full-page skeleton (mirrors the GAR / Individual Sample Report
         block-replacement pattern). Renders only while loading=true so the
         real labels, KPI numbers, table rows etc. never paint with empty
         data — the user sees shimmer placeholders that match the final
         layout, then a clean swap to real content. ── -->
    <template v-if="loading">
      <!-- Scope bar skeleton -->
      <div class="sd-scope">
        <span class="sd-skel" style="width:300px;height:13px"></span>
      </div>

      <!-- KPI cards skeleton -->
      <div class="sd-cards cards-4">
        <div class="c" v-for="i in 4" :key="'sk-c'+i">
          <div class="sd-skel" style="width:65%;height:11px;margin-bottom:8px;display:block"></div>
          <div class="sd-skel" style="width:80px;height:24px;margin-bottom:8px;display:block"></div>
          <div class="sd-skel" style="width:75%;height:10px;display:block"></div>
        </div>
      </div>

      <!-- Two-column row skeleton -->
      <div class="sd-two-col">
        <!-- Unfit Samples table skeleton -->
        <div class="sd-tbl-wrap">
          <div class="sd-tbl-head">
            <span class="sd-skel" style="width:220px;height:14px"></span>
          </div>
          <table class="sd-tbl">
            <thead>
              <tr>
                <th v-for="i in 7" :key="'sk-th'+i">
                  <span class="sd-skel" style="width:60px;height:11px"></span>
                </th>
              </tr>
            </thead>
            <tbody>
              <SeSkelRow v-for="n in 4" :key="'sk-us'+n" :cols="[90,160,80,80,80,80,100]" />
            </tbody>
          </table>
        </div>

        <!-- Side panels skeleton -->
        <div class="sd-side-stack">
          <div class="sd-side-panel">
            <div class="ps-h"><span class="sd-skel" style="width:140px;height:12px"></span></div>
            <div class="ps-row" v-for="n in 3" :key="'sk-oh'+n">
              <span class="sd-skel" style="width:80%;height:13px;display:block;margin-bottom:4px"></span>
              <span class="sd-skel" style="width:50%;height:11px;display:block"></span>
            </div>
          </div>
          <div class="sd-side-panel h-navy">
            <div class="ps-h"><span class="sd-skel" style="width:160px;height:12px"></span></div>
            <div class="ps-row" v-for="n in 3" :key="'sk-nh'+n">
              <span class="sd-skel" style="width:90%;height:12px"></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Retest table skeleton -->
      <div class="sd-tbl-wrap head-navy" style="margin-top:14px">
        <div class="sd-tbl-head">
          <span class="sd-skel" style="width:200px;height:14px"></span>
        </div>
        <table class="sd-tbl">
          <thead>
            <tr>
              <th v-for="i in 7" :key="'sk-rth'+i">
                <span class="sd-skel" style="width:60px;height:11px"></span>
              </th>
            </tr>
          </thead>
          <tbody>
            <SeSkelRow v-for="n in 3" :key="'sk-rs'+n" :cols="[100,100,160,40,80,80,60]" />
          </tbody>
        </table>
      </div>
    </template>

    <!-- ── Real content (rendered once data has arrived) ── -->
    <template v-else>
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
        <div class="val">{{ stats.unfit_no_action ?? 0 }}</div>
        <div class="sub">Immediate action required</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">RETESTS PENDING</div>
        <div class="val">{{ stats.retests_pending ?? 0 }}</div>
        <div class="sub">Awaiting analysis</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">OVERDUE WSS</div>
        <div class="val">{{ stats.overdue_wss ?? 0 }}</div>
        <div class="sub">Past scheduled retest</div>
      </div>
      <div class="c c-green">
        <div class="lbl">RESOLVED THIS YEAR</div>
        <div class="val">{{ stats.resolved_this_year ?? 0 }}</div>
        <div class="sub">Retest confirmed fit</div>
      </div>
    </div>

    <!-- Two-column: Unfit Samples table | side panels -->
    <div class="sd-two-col">

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
            <tr v-for="r in unfitSamples" :key="r.id">
              <td class="sid" :class="{ red: r.status === 'No Action' || r.status === 'Re-notified' }">{{ r.slug }}</td>
              <td><b>{{ r.wss_name }}</b></td>
              <td>{{ r.district }}</td>
              <td>{{ fmtDate(r.sampled_at) }}</td>
              <td><span class="sd-pill p-red">{{ r.cause }}</span></td>
              <td><span class="sd-pill" :class="statusPill(r.status)">{{ r.status }}</span></td>
              <td style="white-space:nowrap">
                <div style="display:inline-flex;gap:4px;align-items:center">
                  <button v-if="r.status === 'No Action'"    class="sd-btn sd-btn-pri" @click="openLog(r)">Log</button>
                  <button v-else-if="r.status === 'Action Taken' || r.status === 'Re-notified'" class="sd-btn sd-btn-pri" @click="openLog(r)">Retest</button>
                  <button class="sd-btn sd-btn-sec" @click="openTrail(r)">Trail</button>
                </div>
              </td>
            </tr>
            <tr v-if="!unfitSamples.length">
              <td colspan="7" class="empty">No unfit samples in your circle.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Right: side panels -->
      <div class="sd-side-stack">
        <div class="sd-side-panel">
          <div class="ps-h">Overdue WSS — {{ scope.name }}</div>
          <div class="ps-row" v-for="o in overduePanel" :key="o.id">
            <span class="right">{{ fmtDate(o.next_scheduled) }}<br>{{ o.days_overdue }}d overdue</span>
            <div class="nm">{{ o.wss_name }}</div>
            <div class="meta">{{ o.district }}</div>
          </div>
          <div v-if="!overduePanel.length" class="ps-row" style="color:#94a3b8;font-style:italic">No overdue WSS.</div>
        </div>

        <div class="sd-side-panel h-navy">
          <div class="ps-h">My Notifications (7 days)</div>
          <div class="ps-row" v-for="n in notifications" :key="n.id">
            <div class="nm">{{ n.slug }} <span class="badge">{{ n.label }}</span></div>
            <div class="meta">{{ n.wss_name }} · {{ fmtDateTime(n.created_at) }}</div>
          </div>
          <div v-if="!notifications.length" class="ps-row" style="color:#94a3b8;font-style:italic">No recent notifications.</div>
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
        </tbody>
      </table>
    </div>
    </template>

    <!-- Trail + Log modals -->
    <SeTrailModal     v-model="showTrailModal" :sample-id="trailSampleId" @saved="onActionSaved" />
    <SeLogActionModal v-model="showLogModal"   :sample-id="logSample.id" :sample-slug="logSample.slug" :wss-name="logSample.wss" @saved="onActionSaved" />
  </div>
</template>

<style scoped lang="scss">
@use './se-shared.scss' as *;

.sd-two-col {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 14px;
  align-items: start;
  // Without min-width:0 grid children refuse to shrink below their content
  // size, which lets a wide table push the right-hand panels off-screen.
  > * { min-width: 0; }
}
.sd-side-stack { display: flex; flex-direction: column; gap: 12px; min-width: 0; }

@media (max-width: 1280px) {
  .sd-two-col { grid-template-columns: 1fr; }
}
</style>
