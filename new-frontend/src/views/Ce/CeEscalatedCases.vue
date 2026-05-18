<script setup>
import { ref, onMounted } from 'vue'
import { ceService } from '../../services/ceService.js'
import CeSkelRow from './CeSkelRow.vue'

const loading = ref(true)
const data    = ref(null)

async function load() {
  loading.value = true
  try { data.value = await ceService.escalatedCases() }
  catch { data.value = null } finally { loading.value = false }
}
onMounted(load)

const fmtDate = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }) }
  catch { return d }
}

function daysClass(days) {
  if (days <= 5)  return 'p-green'
  if (days <= 10) return 'p-amber'
  return 'p-red'
}
</script>

<template>
  <div class="cd">
    <div class="cd-banner">
      ⚠️
      <span class="lab">CE Escalated Cases</span>
      — Zero corrective action for 20+ days from initial XEN notification. Passed through XEN (Day 0) → SE (Day 10) → CE (Day 20) with no response.
    </div>

    <div class="cd-cards cards-4">
      <div class="c c-red">
        <div class="lbl">CE ESCALATED (ACTIVE)</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.ce_active ?? 0 }}</div>
        <div class="sub" v-if="!loading">Immediate action required</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">LONGEST INACTIVITY</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.longest_days ?? 0 }}d</div>
        <div class="sub" v-if="!loading">{{ data?.stats?.longest_slug ?? '—' }}</div>
      </div>
      <div class="c">
        <div class="lbl">SE ESCALATED (APPROACHING)</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.se_approaching ?? 0 }}</div>
        <div class="sub" v-if="!loading">Watch — nearing Day 20</div>
      </div>
      <div class="c c-green">
        <div class="lbl">CE RESOLVED (YTD)</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.ce_resolved_this_year ?? 0 }}</div>
        <div class="sub" v-if="!loading">Closed after CE intervention</div>
      </div>
    </div>

    <table class="cd-tbl" style="margin-bottom:18px">
      <thead>
        <tr>
          <th>Sample ID</th><th>WSS Name</th><th>District</th><th>SE Circle</th><th>Contamination</th><th>Parameter / Value</th><th>XEN Notified</th><th>SE Notified (Day 10)</th><th>CE Notified (Day 20)</th><th>Days Elapsed</th><th>CE Action</th>
        </tr>
      </thead>
      <tbody>
        <template v-if="loading">
          <CeSkelRow v-for="n in 2" :key="'es' + n" :cols="[100, 160, 80, 110, 80, 100, 90, 90, 90, 50, 80]" />
        </template>
        <template v-else>
          <tr v-for="r in (data?.escalated || [])" :key="r.id">
            <td class="sid red">{{ r.slug }}</td>
            <td><b>{{ r.wss_name }}</b></td>
            <td>{{ r.district }}</td>
            <td><span class="cd-pill p-blue">{{ r.circle }}</span></td>
            <td><span class="cd-pill p-red">{{ r.cause }}</span></td>
            <td><span style="color:#dc2626;font-weight:700">{{ r.parameter }}</span> · {{ r.value }}</td>
            <td>{{ fmtDate(r.xen_notified) }}</td>
            <td>{{ fmtDate(r.se_notified) }}</td>
            <td>{{ fmtDate(r.ce_notified) }}</td>
            <td><span class="cd-pill p-red">{{ r.days_elapsed }} days</span></td>
            <td><button class="cd-btn cd-btn-warn">⚡ Intervene</button></td>
          </tr>
          <tr v-if="!(data?.escalated || []).length">
            <td colspan="11" class="empty">No active CE escalations.</td>
          </tr>
        </template>
      </tbody>
    </table>

    <h3 style="font-size:13px;font-weight:700;color:#0f172a;margin:6px 0">SE Escalated — Approaching CE Threshold</h3>
    <div class="cd-banner" style="background:#eff6ff;border-color:#bfdbfe;border-left-color:#1d4ed8">
      <span style="color:#1d4ed8">Currently with SE. Will auto-escalate to CE if no action logged before Day 20 from initial XEN notification.</span>
    </div>

    <table class="cd-tbl">
      <thead>
        <tr>
          <th>Sample ID</th><th>WSS</th><th>District</th><th>SE Circle</th><th>Contamination</th><th>XEN Notified</th><th>SE Notified</th><th>Days Elapsed</th><th>Days to CE</th>
        </tr>
      </thead>
      <tbody>
        <template v-if="loading">
          <CeSkelRow v-for="n in 3" :key="'ap' + n" :cols="[100, 160, 80, 110, 80, 90, 90, 60, 60]" />
        </template>
        <template v-else>
          <tr v-for="r in (data?.approaching || [])" :key="r.id">
            <td class="sid">{{ r.slug }}</td>
            <td><b>{{ r.wss_name }}</b></td>
            <td>{{ r.district }}</td>
            <td><span class="cd-pill p-blue">{{ r.circle }}</span></td>
            <td><span class="cd-pill p-amber">{{ r.contaminant }}</span></td>
            <td>{{ fmtDate(r.xen_notified) }}</td>
            <td>{{ fmtDate(r.se_notified) }}</td>
            <td>{{ r.days_elapsed }} days</td>
            <td><span class="cd-pill" :class="daysClass(r.days_to_ce)">{{ r.days_to_ce }} days left</span></td>
          </tr>
          <tr v-if="!(data?.approaching || []).length">
            <td colspan="9" class="empty">No SE cases approaching CE threshold.</td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<style scoped lang="scss">
@use './ce-shared.scss' as *;
</style>
