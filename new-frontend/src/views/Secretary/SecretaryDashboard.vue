<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { secretaryService } from '../../services/secretaryService.js'
import SecSkelRow from './SecSkelRow.vue'

const router = useRouter()
const loading = ref(true)
const data    = ref(null)

async function load() {
  loading.value = true
  try { data.value = await secretaryService.dashboard() }
  catch { data.value = null } finally { loading.value = false }
}
onMounted(load)

const row1  = computed(() => data.value?.row1 || {})
const row2  = computed(() => data.value?.row2 || {})
const summary = computed(() => data.value?.ce_summary || { rows: [], totals: {} })
const fate  = computed(() => data.value?.fate_decisions || [])
const cePerf = computed(() => data.value?.ce_performance || [])
const notifs = computed(() => data.value?.notifications || [])
const scope = computed(() => data.value?.scope || { ces: [] })

function ragBadge(rag) {
  rag = (rag || '').toLowerCase()
  if (rag === 'high') return 'high'
  if (rag === 'med' || rag === 'medium') return 'med'
  return 'low'
}

</script>

<template>
  <div class="sd">
    <div class="sd-scope">
      <span class="pin">📍</span>
      <b>Scope:</b>
      <b>Secretary PHED KP</b>
      <span class="sep">—</span>
      <span>Province-wide view across all Chief Engineers:</span>
      <template v-for="(c, i) in scope.ces" :key="i">
        <span class="lbl">{{ c }}</span>
        <span v-if="i < scope.ces.length - 1" class="sep">·</span>
      </template>
    </div>

    <!-- Banner -->
    <div v-if="loading" class="sd-banner">
      <span class="sd-skel" style="width:60%;height:14px"></span>
    </div>
    <div v-else-if="row2.fate_decisions_pending > 0" class="sd-banner rose">
      ⚖️
      <span class="lab">{{ row2.fate_decisions_pending }} WSS Fate Decisions pending your approval</span>
      — persistent chemical contamination after R2, escalated by CE. Immediate decision required.
      <RouterLink to="/secretary/fate-decisions" class="view-link">View →</RouterLink>
    </div>
    <div v-else class="sd-banner ok">
      ✅ <span class="lab">No fate decisions pending.</span> All cases handled by CE or below.
    </div>

    <!-- Row 1 -->
    <div class="row-h">Row 1 — Water Supply Schemes (Province-wide)</div>
    <div class="sd-cards cards-5">
      <div class="c">
        <div class="lbl">FUNCTIONAL WSS</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ row1.functional_wss?.total?.toLocaleString() ?? 0 }}</div>
        <div class="sub" v-if="!loading">🟡 Solar: {{ row1.functional_wss?.solar ?? 0 }} &nbsp; ⚡ Non-Solar: {{ row1.functional_wss?.non_solar ?? 0 }}</div>
        <RouterLink to="/secretary/wss-register" class="link">→ WSS Register</RouterLink>
      </div>
      <div class="c">
        <div class="lbl">TESTED WSS</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ row1.tested_wss?.toLocaleString() ?? 0 }}</div>
        <div class="sub" v-if="!loading">✅ Fit: {{ row1.fit_samples ?? 0 }} &nbsp; ❌ Unfit: {{ row1.unfit_samples ?? 0 }}</div>
        <div class="sub" v-if="!loading && row1.functional_wss?.total > 0">{{ ((row1.tested_wss / row1.functional_wss.total) * 100).toFixed(1) }}% of Functional WSS</div>
        <RouterLink to="/secretary/wss-register" class="link">→ WSS Register</RouterLink>
      </div>
      <div class="c">
        <div class="lbl">TESTED SAMPLES</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ row1.tested_samples?.toLocaleString() ?? 0 }}</div>
        <div class="sub" v-if="!loading">✅ Fit: {{ row1.fit_samples ?? 0 }} &nbsp; ❌ Unfit: {{ row1.unfit_samples ?? 0 }} &nbsp; ⏳ Other: {{ Math.max(0, (row1.tested_samples ?? 0) - (row1.fit_samples ?? 0) - (row1.unfit_samples ?? 0)) }}</div>
        <RouterLink to="/secretary/gar" class="link">→ GAR Report</RouterLink>
      </div>
      <div class="c c-red">
        <div class="lbl">UNFIT SAMPLES</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ row1.unfit_samples?.toLocaleString() ?? 0 }}</div>
        <div class="sub" v-if="!loading && row1.tested_samples > 0">{{ ((row1.unfit_samples / row1.tested_samples) * 100).toFixed(1) }}% of tested</div>
        <a class="link" @click="router.push('/secretary/fate-decisions')">→ Unfit Trail</a>
      </div>
      <div class="c c-amber">
        <div class="lbl">UNFIT FOLLOW-UP</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ row1.unfit_followup?.total ?? 0 }}</div>
        <div class="sub" v-if="!loading">✅ Fit: {{ row1.unfit_followup?.fit ?? 0 }} &nbsp; ❌ Unfit: {{ row1.unfit_followup?.still_unfit ?? 0 }} &nbsp; ⏳: {{ row1.unfit_followup?.closed ?? 0 }}</div>
        <div class="sub" v-if="!loading">{{ row1.unfit_followup?.rate_percent ?? 0 }}% follow-up rate</div>
        <a class="link" @click="router.push('/secretary/fate-decisions')">→ Unfit Trail</a>
      </div>
    </div>

    <!-- Row 2 -->
    <div class="row-h">Row 2 — Escalations &amp; Compliance</div>
    <div class="sd-cards cards-5">
      <div class="c c-rose">
        <div class="lbl">FATE DECISIONS PENDING</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ row2.fate_decisions_pending ?? 0 }}</div>
        <div class="sub" v-if="!loading">Secretary approval required</div>
      </div>
      <div class="c c-red">
        <div class="lbl">PERSISTENT UNFIT WSS</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ row2.persistent_unfit_wss ?? 0 }}</div>
        <div class="sub" v-if="!loading">Chemical contamination after R2</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">CE ESCALATED (ACTIVE)</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ row2.ce_escalated_active ?? 0 }}</div>
        <div class="sub" v-if="!loading">Zero XEN action &gt;20 days</div>
      </div>
      <div class="c">
        <div class="lbl">15% MONTHLY COVERAGE</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ row2.monthly_coverage?.labs_on_target ?? 0 }}/{{ row2.monthly_coverage?.total_labs ?? 0 }}</div>
        <div class="sub" v-if="!loading">Labs on target province-wide</div>
      </div>
      <div class="c c-green">
        <div class="lbl">RESOLVED THIS YEAR</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ row2.resolved_this_year ?? 0 }}</div>
        <div class="sub" v-if="!loading">Retest confirmed fit</div>
      </div>
    </div>

    <!-- Two-column: CE summary + side -->
    <div style="display:grid;grid-template-columns:1fr 320px;gap:14px;align-items:start">
      <div class="sd-tbl-wrap">
        <div class="sd-tbl-head">
          CE-wise WQ Summary — {{ new Date().toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }) }}
        </div>
        <table class="sd-tbl">
          <thead>
            <tr>
              <th>Chief Engineer</th>
              <th>Circles</th>
              <th>Districts</th>
              <th>Tested</th>
              <th>Fit</th>
              <th>Unfit</th>
              <th>% Fit</th>
              <th>Fit vs Unfit</th>
              <th>CE Esc.</th>
              <th>Fate Dec.</th>
              <th>RAG</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="loading">
              <SecSkelRow v-for="n in 4" :key="'cs' + n" :cols="[120, 150, 50, 50, 40, 40, 50, 100, 40, 40, 50]" />
            </template>
            <template v-else>
              <tr v-for="r in summary.rows" :key="r.region_id">
                <td>
                  <RouterLink :to="`/secretary/ce/${r.region_id}`" style="color:#1d4ed8;font-weight:700;text-decoration:none">{{ r.ce }}</RouterLink>
                  <div style="font-size:10.5px;color:#64748b;margin-top:2px">{{ r.ce_name }}</div>
                </td>
                <td style="font-size:11px;color:#64748b">{{ (r.circles || []).join(' · ') }}</td>
                <td>{{ r.districts_count }} districts</td>
                <td>{{ r.tested.toLocaleString() }}</td>
                <td style="color:#16a34a;font-weight:600">{{ r.fit }}</td>
                <td style="color:#dc2626;font-weight:700">{{ r.unfit }}</td>
                <td>{{ r.pct_fit }}%</td>
                <td>
                  <span class="sd-fvu-bar" :class="r.pct_fit >= 90 ? 'good' : ''">
                    <span class="fit" :style="`width:${r.pct_fit}%`">{{ r.pct_fit }}%</span>
                  </span>
                </td>
                <td>{{ r.ce_escalated || '—' }}</td>
                <td>{{ r.fate_decisions || '—' }}</td>
                <td><span class="sd-rag" :class="ragBadge(r.rag)"><span class="dot"></span> {{ r.rag }}</span></td>
              </tr>
              <tr v-if="summary.rows.length === 0">
                <td colspan="11" class="empty">No CE data available.</td>
              </tr>
              <tr class="total-r" v-if="summary.rows.length > 0">
                <td colspan="3">PROVINCE TOTAL</td>
                <td>{{ summary.totals?.tested?.toLocaleString() }}</td>
                <td>{{ summary.totals?.fit }}</td>
                <td>{{ summary.totals?.unfit }}</td>
                <td>{{ summary.totals?.pct_fit }}%</td>
                <td>—</td>
                <td>{{ summary.totals?.ce_escalated || '—' }}</td>
                <td>{{ summary.totals?.fate_decisions || '—' }}</td>
                <td>—</td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <div style="display:flex;flex-direction:column;gap:12px">
        <div class="sd-side-panel h-navy">
          <div class="ps-h">CE Performance — {{ new Date().toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }) }}</div>
          <template v-if="loading">
            <div class="ps-row" v-for="n in 4" :key="'pp' + n">
              <span class="sd-skel" style="width:80%;height:13px"></span>
              <div style="margin-top:4px"><span class="sd-skel" style="width:60%;height:11px"></span></div>
            </div>
          </template>
          <template v-else>
            <div class="ps-row" v-for="p in cePerf" :key="p.region_id">
              <div class="nm">
                {{ p.ce }}
                <span class="badge" :class="p.rag === 'High' ? '' : p.rag === 'Med' ? 'b-amber' : 'b-green'">{{ p.rag }}</span>
              </div>
              <div class="meta">{{ p.sub }}</div>
            </div>
          </template>
        </div>

        <div class="sd-side-panel">
          <div class="ps-h">Secretary Notifications</div>
          <template v-if="loading">
            <div class="ps-row" v-for="n in 3" :key="'sn' + n">
              <span class="sd-skel" style="width:80%;height:13px"></span>
            </div>
          </template>
          <template v-else>
            <div class="ps-row" v-for="(n, i) in notifs" :key="i">
              <div class="nm">
                {{ n.label }}
                <span class="badge" :class="n.type === 'action' ? '' : n.type === 'alert' ? 'b-amber' : 'b-info'">{{ n.badge }}</span>
              </div>
              <div class="meta">{{ n.title }}</div>
              <div class="meta">{{ n.meta }}</div>
            </div>
            <div v-if="!notifs.length" class="ps-row" style="color:#94a3b8;font-style:italic">No notifications.</div>
          </template>
        </div>
      </div>
    </div>

    <!-- Fate Decisions table -->
    <div class="sd-tbl-wrap head-rose" style="margin-top:14px">
      <div class="sd-tbl-head" style="background:#9d174d">
        ⚖ WSS Fate Decisions — Pending Secretary Approval
        <RouterLink to="/secretary/fate-decisions" style="color:#fff;font-weight:600;font-size:11px;text-decoration:underline">View All →</RouterLink>
      </div>
      <table class="sd-tbl">
        <thead>
          <tr><th>Sample ID</th><th>WSS Name</th><th>District</th><th>CE</th><th>Stage</th><th>R2 Remarks</th></tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <SecSkelRow v-for="n in 3" :key="'fd' + n" :cols="[100, 160, 80, 110, 70, 200]" />
          </template>
          <template v-else>
            <tr v-for="r in fate.slice(0, 5)" :key="r.id" style="background:#fdf2f8">
              <td class="sid">{{ r.slug }}</td>
              <td><b>{{ r.wss_name }}</b></td>
              <td>{{ r.district }}</td>
              <td><span class="sd-pill p-blue">{{ r.ce }}</span></td>
              <td><span class="sd-pill p-red">{{ r.stage }}</span></td>
              <td style="font-size:11px;color:#475569">{{ r.r2 }}</td>
            </tr>
            <tr v-if="!fate.length"><td colspan="6" class="empty">No fate decisions pending.</td></tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use './secretary-shared.scss' as *;

@media (max-width: 1200px) {
  .sd > div[style*="grid-template-columns"] {
    grid-template-columns: 1fr !important;
  }
}
</style>
