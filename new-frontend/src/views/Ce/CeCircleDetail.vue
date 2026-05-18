<script setup>
import { ref, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { ceService } from '../../services/ceService.js'
import CeSkelRow from './CeSkelRow.vue'

const route = useRoute()
const loading = ref(true)
const data    = ref(null)

async function load() {
  loading.value = true
  try { data.value = await ceService.circleDetail(route.params.id) }
  catch { data.value = null } finally { loading.value = false }
}
onMounted(load)
watch(() => route.params.id, load)

const fmtDate = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }) }
  catch { return d }
}

function stageClass(stage) {
  if (stage === 'CE Escalated')  return 'p-violet'
  if (stage === 'SE Escalated')  return 'p-rose'
  if (stage === 'Action Taken')  return 'p-amber'
  if (stage === 'No Action')     return 'p-red'
  return 'p-grey'
}
</script>

<template>
  <div class="cd">
    <div class="cd-scope">
      <span class="pin">📍</span>
      <b>{{ data?.circle?.label || 'SE Circle' }}</b>
      <span class="sep">—</span>
      <span>Engr. {{ data?.circle?.se_name || '—' }} · {{ (data?.circle?.districts || []).join(', ') }} districts. CE read-only oversight view.</span>
    </div>

    <div class="cd-cards cards-4">
      <div class="c c-red">
        <div class="lbl">UNFIT — NO ACTION</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.no_action ?? 0 }}</div>
        <div class="sub" v-if="!loading">XEN yet to respond</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">SE ESCALATED</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.se_escalated ?? 0 }}</div>
        <div class="sub" v-if="!loading">No XEN action &gt;10 days</div>
      </div>
      <div class="c c-rose">
        <div class="lbl">CE ESCALATED</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.ce_escalated ?? 0 }}</div>
        <div class="sub" v-if="!loading">Immediate intervention req.</div>
      </div>
      <div class="c c-green">
        <div class="lbl">RESOLVED THIS YEAR</div>
        <div v-if="loading" class="cd-skel cd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.resolved_this_year ?? 0 }}</div>
        <div class="sub" v-if="!loading">Retest confirmed fit</div>
      </div>
    </div>

    <div class="cd-tbl-wrap">
      <div class="cd-tbl-head">Unfit Samples — {{ data?.circle?.label || '—' }}</div>
      <table class="cd-tbl">
        <thead>
          <tr>
            <th>Sample ID</th><th>WSS</th><th>District</th><th>Date</th><th>Cause</th><th>Parameter</th><th>Status</th><th>Stage</th><th>CE Action</th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <CeSkelRow v-for="n in 4" :key="'cd' + n" :cols="[100, 160, 90, 80, 80, 100, 80, 40, 80]" />
          </template>
          <template v-else>
            <template v-for="grp in (data?.samples || [])" :key="grp.district">
              <tr class="group-h">
                <td colspan="9">{{ grp.district.toUpperCase() }}</td>
              </tr>
              <tr v-for="r in grp.rows" :key="r.id">
                <td class="sid" :class="{ red: r.stage === 'CE Escalated' || r.stage === 'No Action' }">{{ r.slug }}</td>
                <td><b>{{ r.wss_name }}</b></td>
                <td>{{ r.district }}</td>
                <td>{{ fmtDate(r.sampled_at) }}</td>
                <td>{{ r.cause }}</td>
                <td><span style="color:#dc2626;font-weight:700">{{ r.parameter }}</span></td>
                <td><span class="cd-pill" :class="stageClass(r.stage)">{{ r.stage }}</span></td>
                <td>{{ r.round > 0 ? 'R' + r.round : '—' }}</td>
                <td>
                  <button v-if="r.stage === 'CE Escalated'" class="cd-btn cd-btn-warn">⚡ Intervene</button>
                  <button v-else class="cd-btn cd-btn-sec">Trail</button>
                </td>
              </tr>
            </template>
            <tr v-if="!(data?.samples || []).length">
              <td colspan="9" class="empty">No unfit samples in this circle.</td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped lang="scss">
@use './ce-shared.scss' as *;
</style>
