<script setup>
import { ref, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { secretaryService } from '../../services/secretaryService.js'
import SecSkelRow from './SecSkelRow.vue'

const route = useRoute()
const loading = ref(true)
const data    = ref(null)

async function load() {
  loading.value = true
  try { data.value = await secretaryService.ceUnfit(route.params.regionId) }
  catch { data.value = null } finally { loading.value = false }
}
onMounted(load)
watch(() => route.params.regionId, load)

function statusClass(status) {
  if (status === 'CE Escalated')      return 'p-rose'
  if (status === 'SE Escalated')      return 'p-violet'
  if (status === 'Action Taken')      return 'p-amber'
  if (status === 'No Action')         return 'p-red'
  if (status === 'Persistent Unfit')  return 'p-rose'
  if (status === 'Resolved')          return 'p-green'
  return 'p-grey'
}
</script>

<template>
  <div class="sd">
    <div class="sd-scope">
      <span class="pin">📍</span>
      <b>{{ data?.region?.name || 'CE' }}</b>
      <span class="sep">—</span>
      <span>Engr. {{ data?.region?.ce_name || '—' }} · {{ (data?.region?.circles_descriptive || []).join(' · ') }}. Secretary read-only view.</span>
    </div>

    <div class="sd-cards cards-5">
      <div class="c c-red">
        <div class="lbl">TOTAL UNFIT</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.total_unfit ?? 0 }}</div>
        <div class="sub" v-if="!loading">{{ data?.stats?.pct_of_tested ?? 0 }}% of tested</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">NO ACTION YET</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.no_action ?? 0 }}</div>
        <div class="sub" v-if="!loading">XEN yet to respond</div>
      </div>
      <div class="c c-rose">
        <div class="lbl">CE ESCALATED</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.ce_escalated ?? 0 }}</div>
        <div class="sub" v-if="!loading">No action &gt;20 days</div>
      </div>
      <div class="c">
        <div class="lbl">PERSISTENT UNFIT</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.persistent ?? 0 }}</div>
        <div class="sub" v-if="!loading">Fate Decision pending</div>
      </div>
      <div class="c c-green">
        <div class="lbl">RESOLVED</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.resolved_this_year ?? 0 }}</div>
        <div class="sub" v-if="!loading">Retest confirmed fit</div>
      </div>
    </div>

    <div class="sd-tbl-wrap">
      <div class="sd-tbl-head">Unfit Samples — {{ data?.region?.name || '—' }} (grouped by SE Circle)</div>
      <table class="sd-tbl">
        <thead>
          <tr><th>Sample ID</th><th>WSS</th><th>District</th><th>SE Circle</th><th>Cause</th><th>Parameter</th><th>Status</th><th>Stage</th></tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <SecSkelRow v-for="n in 5" :key="'cu' + n" :cols="[100, 160, 80, 110, 80, 100, 80, 40]" />
          </template>
          <template v-else>
            <template v-for="grp in (data?.samples_grouped_by_circle || [])" :key="grp.circle">
              <tr class="group-h"><td colspan="8">{{ grp.circle.toUpperCase() }}</td></tr>
              <tr v-for="r in grp.rows" :key="r.id">
                <td class="sid" :class="{ red: r.status === 'CE Escalated' || r.status === 'No Action' || r.status === 'Persistent Unfit' }">{{ r.slug }}</td>
                <td><b>{{ r.wss_name }}</b></td>
                <td>{{ r.district }}</td>
                <td><span class="sd-pill p-blue">{{ r.se_circle }}</span></td>
                <td>{{ r.cause }}</td>
                <td><span style="color:#dc2626;font-weight:700">{{ r.parameter }}</span></td>
                <td><span class="sd-pill" :class="statusClass(r.status)">{{ r.status }}</span></td>
                <td>{{ r.stage }}</td>
              </tr>
            </template>
            <tr v-if="!(data?.samples_grouped_by_circle || []).length">
              <td colspan="8" class="empty">No unfit samples in this region.</td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use './secretary-shared.scss' as *;
</style>
