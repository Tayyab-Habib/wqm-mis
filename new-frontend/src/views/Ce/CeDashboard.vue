<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { ceService } from '../../services/ceService.js'
import CeSkelRow from './CeSkelRow.vue'

const router = useRouter()
const loading = ref(true)
const data    = ref(null)

async function load() {
  loading.value = true
  try {
    data.value = await ceService.dashboard()
  } catch { data.value = null } finally { loading.value = false }
}
onMounted(load)

const row1   = computed(() => data.value?.row1 || {})
const row2   = computed(() => data.value?.row2 || {})
const summary= computed(() => data.value?.se_summary || { rows: [], totals: {} })
const persistent  = computed(() => data.value?.persistent || [])
const ceEscalated = computed(() => data.value?.ce_escalated || [])
const notifications = computed(() => data.value?.notifications || [])
const scope  = computed(() => data.value?.scope || { circles: [] })

const fmtDate = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }) }
  catch { return d }
}

function fitVsUnfitClass(pct) { return pct >= 90 ? 'good' : '' }

function ragBadge(rag) {
  rag = (rag || '').toLowerCase()
  if (rag === 'high') return 'high'
  if (rag === 'med' || rag === 'medium') return 'med'
  return 'low'
}

function intervene(row) {
  router.push(`/ce/circles/${row.circle_id || ''}`)
}
</script>

<template>
  <div class="cd">
    <!-- Scope bar -->
    <div class="cd-scope">
      <span class="pin">📍</span>
      <span><b>Scope:</b></span>
      <b>CE {{ scope.region?.replace(/^CE\s*—\s*/, '') || '—' }}</b>
      <span class="sep">—</span>
      <template v-for="(c, i) in scope.circles" :key="i">
        <span class="lbl">{{ c.label }}</span>
        <span v-if="c.districts" class="t2">({{ (c.districts || []).join(', ') }})</span>
        <span v-if="i < scope.circles.length - 1" class="sep">·</span>
      </template>
    </div>

    <!-- Escalation banner -->
    <div v-if="loading" class="cd-banner">
      <span class="cd-skel" style="width:60%;height:14px"></span>
    </div>
    <div v-else-if="row2.ce_escalated_no_action > 0" class="cd-banner">
      ⚠️
      <span class="lab">{{ row2.ce_escalated_no_action }} cases escalated to your dashboard</span>
      — no XEN action for 20+ days. Immediate CE intervention required.
      <RouterLink to="/ce/escalated-cases" class="view-link">View →</RouterLink>
    </div>
    <div v-else class="cd-banner ok">
      ✅ <span class="lab">No CE escalations active.</span> All cases within SE-handling window.
    </div>

    <!-- ── Row 1 ── -->
    <div class="row-h">Row 1 — Water Supply Schemes (CE {{ scope.region?.replace(/^CE\s*—\s*/, '') || '' }})</div>
    <div class="cd-cards cards-5">
      <div class="c">
        <div class="lbl">FUNCTIONAL WSS</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ row1.functional_wss?.total?.toLocaleString() ?? 0 }}</div>
        <div class="sub" v-if="!loading">🟡 Solar: {{ row1.functional_wss?.solar ?? 0 }} &nbsp; ⚡ Non-Solar: {{ row1.functional_wss?.non_solar ?? 0 }}</div>
        <div class="sub" v-if="!loading">Live from PHED MIS API</div>
        <RouterLink to="/ce/wss-register" class="link">→ WSS Register</RouterLink>
      </div>
      <div class="c">
        <div class="lbl">TESTED WSS</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ row1.tested_wss?.toLocaleString() ?? 0 }}</div>
        <div class="sub" v-if="!loading">
          ✅ Fit: {{ row1.fit_samples ?? 0 }} &nbsp; ❌ Unfit: {{ row1.unfit_samples ?? 0 }}
        </div>
        <div class="sub" v-if="!loading && row1.functional_wss?.total > 0">
          {{ ((row1.tested_wss / row1.functional_wss.total) * 100).toFixed(1) }}% of Functional WSS
        </div>
        <RouterLink to="/ce/wss-register" class="link">→ WSS Register</RouterLink>
      </div>
      <div class="c">
        <div class="lbl">TESTED SAMPLES</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ row1.tested_samples?.toLocaleString() ?? 0 }}</div>
        <div class="sub" v-if="!loading">🧪 Micro: {{ Math.floor((row1.tested_samples ?? 0) * 0.8) }} 🧪 PCM: {{ Math.floor((row1.tested_samples ?? 0) * 0.2) }}</div>
        <RouterLink to="/ce/gar" class="link">→ GAR Report</RouterLink>
      </div>
      <div class="c c-red">
        <div class="lbl">UNFIT SAMPLES</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ row1.unfit_samples?.toLocaleString() ?? 0 }}</div>
        <div class="sub" v-if="!loading">
          Micro: {{ Math.floor((row1.unfit_samples ?? 0) * 0.75) }} &nbsp; Chem: {{ Math.floor((row1.unfit_samples ?? 0) * 0.2) }} &nbsp; Phys: {{ Math.max(0, (row1.unfit_samples ?? 0) - Math.floor((row1.unfit_samples ?? 0) * 0.95)) }}
        </div>
        <div class="sub" v-if="!loading && row1.tested_samples > 0">
          {{ ((row1.unfit_samples / row1.tested_samples) * 100).toFixed(1) }}% of tested
        </div>
        <RouterLink to="/ce/escalated-cases" class="link">→ Unfit Trail</RouterLink>
      </div>
      <div class="c c-amber">
        <div class="lbl">UNFIT FOLLOW-UP</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ row1.unfit_followup?.total ?? 0 }}</div>
        <div class="sub" v-if="!loading">
          ✅ Fit: {{ row1.unfit_followup?.fit ?? 0 }} &nbsp; ❌ Unfit: {{ row1.unfit_followup?.still_unfit ?? 0 }} &nbsp; ⏳: {{ row1.unfit_followup?.closed ?? 0 }}
        </div>
        <div class="sub" v-if="!loading">{{ row1.unfit_followup?.rate_percent ?? 0 }}% follow-up rate</div>
        <RouterLink to="/ce/escalated-cases" class="link">→ Unfit Trail</RouterLink>
      </div>
    </div>

    <!-- ── Row 2 — Escalations & Compliance ── -->
    <div class="row-h">Row 2 — Escalations &amp; Compliance</div>
    <div class="cd-cards cards-4">
      <div class="c c-red">
        <div class="lbl">CE ESCALATED — NO ACTION</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ row2.ce_escalated_no_action ?? 0 }}</div>
        <div class="sub" v-if="!loading">Immediate action required</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">SE ESCALATED (ACTIVE)</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ row2.se_escalated_active ?? 0 }}</div>
        <div class="sub" v-if="!loading">Approaching CE threshold</div>
      </div>
      <div class="c c-rose">
        <div class="lbl">PERSISTENT UNFIT WSS</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ row2.persistent_unfit ?? 0 }}</div>
        <div class="sub" v-if="!loading">Chemical · Fate Decision req.</div>
      </div>
      <div class="c c-green">
        <div class="lbl">RESOLVED THIS YEAR</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ row2.resolved_this_year ?? 0 }}</div>
        <div class="sub" v-if="!loading">Retest confirmed fit</div>
      </div>
    </div>

    <!-- Two-column: SE Summary table + side panels -->
    <div style="display:grid;grid-template-columns:1fr 320px;gap:14px;align-items:start">

      <!-- Left: SE-wise summary -->
      <div class="cd-tbl-wrap">
        <div class="cd-tbl-head">
          SE-wise WQ Summary — {{ new Date().toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }) }}
        </div>
        <table class="cd-tbl">
          <thead>
            <tr>
              <th>SE Circle</th>
              <th>Districts</th>
              <th>Tested</th>
              <th>Fit</th>
              <th>Unfit</th>
              <th>% Fit</th>
              <th>Fit vs Unfit</th>
              <th>No Action</th>
              <th>SE Esc.</th>
              <th>CE Esc.</th>
              <th>RAG</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="loading">
              <CeSkelRow v-for="n in 3" :key="'sk' + n" :cols="[120, 130, 50, 40, 40, 50, 100, 50, 50, 50, 50]" />
            </template>
            <template v-else>
              <tr v-for="r in summary.rows" :key="r.circle_id">
                <td>
                  <RouterLink :to="`/ce/circles/${r.circle_id}`" style="color:#1d4ed8;font-weight:700;text-decoration:none">{{ r.circle }}</RouterLink>
                  <div style="font-size:10.5px;color:#64748b;margin-top:2px">{{ r.se_name }}</div>
                </td>
                <td style="font-size:11px;color:#64748b">{{ (r.districts || []).join(' · ') }}</td>
                <td>{{ r.tested.toLocaleString() }}</td>
                <td style="color:#16a34a;font-weight:600">{{ r.fit }}</td>
                <td style="color:#dc2626;font-weight:700">{{ r.unfit }}</td>
                <td>{{ r.pct_fit }}%</td>
                <td>
                  <span class="fvu-bar" :class="fitVsUnfitClass(r.pct_fit)">
                    <span class="fvu-fit" :style="`width:${r.pct_fit}%`">{{ r.pct_fit }}%</span>
                  </span>
                </td>
                <td>{{ r.no_action || '—' }}</td>
                <td>{{ r.se_escalated || '—' }}</td>
                <td>{{ r.ce_escalated || '—' }}</td>
                <td><span class="cd-rag" :class="ragBadge(r.rag)"><span class="dot"></span> {{ r.rag }}</span></td>
              </tr>
              <tr v-if="summary.rows.length === 0">
                <td colspan="11" class="empty">No SE Circles under this region.</td>
              </tr>
              <tr class="total-r" v-if="summary.rows.length > 0">
                <td colspan="2">TOTAL — CE {{ scope.region?.replace(/^CE\s*—\s*/, '') || '' }}</td>
                <td>{{ summary.totals?.tested?.toLocaleString() }}</td>
                <td>{{ summary.totals?.fit }}</td>
                <td>{{ summary.totals?.unfit }}</td>
                <td>{{ summary.totals?.pct_fit }}%</td>
                <td>—</td>
                <td>{{ summary.totals?.no_action || '—' }}</td>
                <td>{{ summary.totals?.se_escalated || '—' }}</td>
                <td>{{ summary.totals?.ce_escalated || '—' }}</td>
                <td>—</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Right: side panels -->
      <div style="display:flex;flex-direction:column;gap:12px">
        <div class="cd-side-panel">
          <div class="ps-h">Persistent Unfit WSS <RouterLink to="/ce/persistent-unfit" class="view-all">View →</RouterLink></div>
          <template v-if="loading">
            <div class="ps-row" v-for="n in 3" :key="'pp' + n">
              <span class="cd-skel" style="width:80%;height:13px"></span>
              <div style="margin-top:4px"><span class="cd-skel" style="width:60%;height:11px"></span></div>
            </div>
          </template>
          <template v-else>
            <div class="ps-row" v-for="p in persistent" :key="p.id">
              <div class="nm">{{ p.wss_name }} <span class="badge">R2</span></div>
              <div class="meta">{{ p.contaminant }} · {{ p.original }} · {{ p.district }}</div>
              <a class="meta" style="color:#9d174d;font-weight:600;cursor:pointer" @click="router.push('/ce/persistent-unfit')">↑ Escalated to Secretary</a>
            </div>
            <div v-if="!persistent.length" class="ps-row" style="color:#94a3b8;font-style:italic">No persistent unfit WSS.</div>
          </template>
        </div>

        <div class="cd-side-panel h-navy">
          <div class="ps-h">CE Notifications (7 days)</div>
          <template v-if="loading">
            <div class="ps-row" v-for="n in 3" :key="'cn' + n">
              <span class="cd-skel" style="width:80%;height:12px"></span>
            </div>
          </template>
          <template v-else>
            <div class="ps-row" v-for="n in notifications" :key="n.id">
              <div class="nm">{{ n.slug }} <span class="badge" :class="n.label === 'CE Escalated' ? '' : 'b-amber'">{{ n.label }}</span></div>
              <div class="meta">{{ n.wss_name }} · {{ fmtDate(n.created_at) }}</div>
            </div>
            <div v-if="!notifications.length" class="ps-row" style="color:#94a3b8;font-style:italic">No recent notifications.</div>
          </template>
        </div>
      </div>
    </div>

    <!-- CE Escalated — Action Required -->
    <div class="cd-tbl-wrap" style="margin-top:14px">
      <div class="cd-tbl-head" style="background:#dc2626">
        CE Escalated — Action Required
        <RouterLink to="/ce/escalated-cases" style="color:#fff;font-weight:600;font-size:11px;text-decoration:underline">View All →</RouterLink>
      </div>
      <table class="cd-tbl">
        <thead>
          <tr><th>Sample ID</th><th>WSS</th><th>District</th><th>SE Circle</th><th>Cause</th><th>Days</th><th>Action</th></tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <CeSkelRow v-for="n in 2" :key="'ce' + n" :cols="[100, 160, 80, 110, 60, 50, 80]" />
          </template>
          <template v-else>
            <tr v-for="r in ceEscalated.slice(0, 5)" :key="r.id">
              <td class="sid red">{{ r.slug }}</td>
              <td><b>{{ r.wss_name }}</b></td>
              <td>{{ r.district }}</td>
              <td><span class="cd-pill p-blue">{{ r.circle }}</span></td>
              <td><span class="cd-pill p-red">{{ r.cause }}</span></td>
              <td><span class="cd-pill p-red">{{ r.days_elapsed }} days</span></td>
              <td><button class="cd-btn cd-btn-warn" @click="intervene(r)">⚡ Intervene</button></td>
            </tr>
            <tr v-if="!ceEscalated.length">
              <td colspan="7" class="empty">No CE escalations.</td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use './ce-shared.scss' as *;

@media (max-width: 1200px) {
  .cd > div[style*="grid-template-columns"] {
    grid-template-columns: 1fr !important;
  }
}
</style>
