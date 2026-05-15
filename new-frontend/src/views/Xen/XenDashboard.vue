<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { xenService } from '../../services/xenService.js'
import SkelRow from './SkelRow.vue'
import XenTrailModal from './XenTrailModal.vue'
import XenLogActionModal from './XenLogActionModal.vue'

const router = useRouter()

const loading = ref(true)
const error   = ref('')
const data    = ref({
  stats: { unfit_no_action: 0, retests_pending: 0, sla_breached: 0, resolved: 0 },
  unfit_samples: [],
  retest_samples: [],
  sla_breached: [],
  notifications: [],
  user_info: {},
})

const overdue = ref([])

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [dash, overdueResp] = await Promise.all([
      xenService.dashboard().catch(e => { throw e }),
      xenService.overdueWss().catch(() => ({ rows: [] })),
    ])
    data.value = dash
    overdue.value = overdueResp.rows || []
  } catch (e) {
    error.value = e?.response?.data?.message || 'Failed to load XEN dashboard. Verify backend (DB) is reachable.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

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

const unfitTop = computed(() => (data.value.unfit_samples || []).slice(0, 6))
const retestTop = computed(() => (data.value.retest_samples || []).slice(0, 6))
const notifTop  = computed(() => (data.value.notifications || []).slice(0, 5))

function statusClass(status) {
  const s = (status || '').toLowerCase()
  if (s === 'no_action' || s === 'no action') return 'st-red'
  if (s === 'action_taken' || s === 'action taken') return 'st-amber'
  if (s === 'resolved') return 'st-green'
  return 'st-grey'
}
function statusLabel(status) {
  const s = (status || '').toLowerCase()
  if (s === 'no_action' || s === 'no action') return 'No Action'
  if (s === 'action_taken' || s === 'action taken') return 'Action Taken'
  if (s === 'resolved') return 'Resolved'
  return status || '—'
}
function rowOpen(s) {
  // navigate to ISR by water_sample_id
  router.push(`/xen/isr/${s.id || s.water_sample_id}`)
}

// ── Trail modal ──────────────────────────────────────────────
const showTrailModal = ref(false)
const trailSampleId  = ref(null)
function openTrail(s) {
  trailSampleId.value = s.id || s.water_sample_id
  showTrailModal.value = true
}

// ── Log Action modal ─────────────────────────────────────────
const showLogModal = ref(false)
const logSample    = ref({ id: null, slug: '', wss: '' })
function openLog(s) {
  logSample.value = {
    id:   s.id || s.water_sample_id,
    slug: s.slug || '',
    wss:  s.water_scheme_name || s.sample_name || '',
  }
  showLogModal.value = true
}
function onActionSaved() {
  // Refresh the dashboard so status pills / counters update
  load()
}
</script>

<template>
  <div class="xd">
    <!-- ── Scope banner ───────────────────────────── -->
    <div class="xd-scope">
      <span class="pin">📍</span>
      <span class="t1">Scope: XEN {{ data.user_info?.division || '—' }}</span>
      <span class="sep">—</span>
      <span class="t2">{{ data.user_info?.district }} ({{ data.user_info?.division }}). All data scoped to your division only.</span>
    </div>

    <!-- ── Identity bar ───────────────────────────── -->
    <div class="xd-id-bar">
      <div class="kv"><b>PHE Division:</b> {{ data.user_info?.division || '—' }}</div>
      <div class="kv"><b>District:</b> {{ data.user_info?.district || '—' }}</div>
      <div class="kv"><b>Sub-Area:</b> {{ data.user_info?.division || '—' }}</div>
      <div class="kv"><b>XEN:</b> {{ data.user_info?.name || '—' }}</div>
      <div class="kv"><b>Phone:</b> {{ data.user_info?.phone || '—' }}</div>
      <RouterLink to="/xen/unfit-trail" class="btn-cta">📋 Unfit Trail →</RouterLink>
    </div>

    <div v-if="error" class="xd-err">{{ error }}</div>

    <!-- ── Stat cards ─────────────────────────────── -->
    <div class="xd-cards">
      <div class="c c-red">
        <div class="lbl">UNFIT — NO ACTION</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ data.stats?.unfit_no_action ?? 0 }}</div>
        <div v-if="loading" class="skel sub-skel"></div>
        <div v-else class="sub">Immediate action needed</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">RETESTS PENDING</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ data.stats?.retests_pending ?? 0 }}</div>
        <div v-if="loading" class="skel sub-skel"></div>
        <div v-else class="sub">Awaiting analysis</div>
      </div>
      <div class="c c-orange">
        <div class="lbl">OVERDUE WSS</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ overdue.length || (data.stats?.sla_breached ?? 0) }}</div>
        <div v-if="loading" class="skel sub-skel"></div>
        <div v-else class="sub">Past scheduled retest</div>
      </div>
      <div class="c c-green">
        <div class="lbl">RESOLVED THIS YEAR</div>
        <div v-if="loading" class="skel val-skel"></div>
        <div v-else class="val">{{ data.stats?.resolved ?? 0 }}</div>
        <div v-if="loading" class="skel sub-skel"></div>
        <div v-else class="sub">Retest confirmed fit</div>
      </div>
    </div>

    <div class="xd-grid">
      <!-- Unfit table -->
      <div class="panel">
        <div class="panel-h panel-h-red">
          <span>⚠️ Unfit Samples — {{ data.user_info?.division || 'Division' }}</span>
          <RouterLink to="/xen/unfit-trail" class="link">View Full Trail →</RouterLink>
        </div>
        <table class="tbl">
          <thead>
            <tr>
              <th>Sample ID</th><th>WSS / Point</th><th>Date</th><th>Cause</th><th>Status</th><th>Action</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="loading">
              <SkelRow v-for="n in 5" :key="'su' + n" :cols="[90, 160, 90, 80, 90, 120]" />
            </template>
            <template v-else>
              <tr v-for="s in unfitTop" :key="s.id" :class="{ 'is-no-action': statusClass(s.status) === 'st-red' }">
                <td class="sid" :class="{ red: statusClass(s.status) === 'st-red' }">{{ s.slug }}</td>
                <td>{{ s.water_scheme_name }}</td>
                <td>{{ fmtDate(s.analyzed_at) }}</td>
                <td><span class="pill pill-red">{{ s.cause || 'Biological' }}</span></td>
                <td><span class="pill" :class="statusClass(s.status)">{{ statusLabel(s.status) }}</span></td>
                <td>
                  <button v-if="statusClass(s.status) === 'st-red'" class="btn btn-pri" @click="openLog(s)">▶ Log</button>
                  <button class="btn btn-sec" @click="openTrail(s)">👁 Trail</button>
                </td>
              </tr>
              <tr v-if="unfitTop.length === 0">
                <td colspan="6" class="empty">No unfit samples in your division.</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Right column -->
      <div class="right-col">
        <div class="panel panel-overdue">
          <div class="panel-h panel-h-orange">⚠ Overdue WSS</div>
          <div class="ov-list">
            <template v-if="loading">
              <div v-for="n in 3" :key="'ov' + n" class="ov-row">
                <div class="ov-name"><span class="skel" style="width: 140px; height: 13px"></span></div>
                <div class="ov-due">
                  <div><span class="skel" style="width: 70px; height: 11px"></span></div>
                  <div class="ov-days"><span class="skel" style="width: 60px; height: 10px; margin-top: 4px"></span></div>
                </div>
              </div>
            </template>
            <template v-else>
              <div v-for="w in overdue.slice(0, 5)" :key="w.id" class="ov-row">
                <div class="ov-name">{{ w.name }}</div>
                <div class="ov-due">
                  <div>{{ fmtDate(w.due_at) }}</div>
                  <div class="ov-days">{{ Math.round(w.overdue_days || 0) }}d overdue</div>
                </div>
              </div>
              <div v-if="overdue.length === 0" class="empty">No overdue WSS.</div>
            </template>
          </div>
        </div>

        <div class="panel">
          <div class="panel-h panel-h-navy">🔔 My Notifications</div>
          <div class="nf-list">
            <template v-if="loading">
              <div v-for="n in 3" :key="'nf' + n" class="nf-row">
                <div class="nf-id"><span class="skel" style="width: 110px; height: 12px"></span></div>
                <div class="nf-date"><span class="skel" style="width: 130px; height: 10px; margin-top: 4px"></span></div>
                <span class="skel pill"></span>
              </div>
            </template>
            <template v-else>
              <div v-for="n in notifTop" :key="n.id" class="nf-row">
                <div class="nf-id">{{ n.data?.sample_slug || ('Sample #' + n.data?.sample_id) }}</div>
                <div class="nf-date">{{ fmtDateTime(n.created_at) }}</div>
                <span class="nf-pill" :class="'st-' + (n.data?.severity || 'grey')">
                  {{ n.data?.type === 'SAMPLE_UNFIT' ? 'Unfit' : (n.data?.type === 'RETEST_REQUESTED' ? 'Retest' : 'Update') }}
                </span>
              </div>
              <div v-if="notifTop.length === 0" class="empty">No notifications.</div>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- Retest samples -->
    <div class="panel" style="margin-top: 16px">
      <div class="panel-h panel-h-navy">
        <span>🧪 Retest Samples — {{ data.user_info?.division || 'Division' }}</span>
        <RouterLink to="/xen/retest-samples" class="link">View All →</RouterLink>
      </div>
      <table class="tbl">
        <thead>
          <tr>
            <th>Retest ID</th><th>Original</th><th>WSS</th><th>Stage</th><th>Date</th><th>Status</th><th>Trail</th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <SkelRow v-for="n in 5" :key="'rt' + n" :cols="[90, 90, 160, 50, 90, 110, 80]" />
          </template>
          <template v-else>
            <tr v-for="r in retestTop" :key="r.id">
              <td class="sid">{{ r.slug || 'R' + r.id }}</td>
              <td><RouterLink :to="`/xen/isr/${r.water_sample_id}`" class="link">{{ r.slug }}</RouterLink></td>
              <td>{{ r.water_scheme_name }}</td>
              <td><span class="pill st-blue">R{{ r.current_round }}</span></td>
              <td>{{ fmtDate(r.analyzed_at) }}</td>
              <td><span class="pill" :class="r.status_badge || 'st-grey'">{{ r.status_label || '—' }}</span></td>
              <td><button class="btn btn-sec" @click="openTrail({ id: r.water_sample_id })">👁 Trail</button></td>
            </tr>
            <tr v-if="retestTop.length === 0">
              <td colspan="7" class="empty">No retest samples in your division.</td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- ── Trail + Log Action modals ─────────────────────────────── -->
    <XenTrailModal
      v-model="showTrailModal"
      :sample-id="trailSampleId"
      @saved="onActionSaved"
    />
    <XenLogActionModal
      v-model="showLogModal"
      :sample-id="logSample.id"
      :sample-slug="logSample.slug"
      :wss-name="logSample.wss"
      @saved="onActionSaved"
    />
  </div>
</template>

<style scoped lang="scss">
@use './xen-shared.scss' as *;
</style>
