<script setup>
import { ref, onMounted } from 'vue'
import { secretaryService } from '../../services/secretaryService.js'
import SecSkelRow from './SecSkelRow.vue'

const loading = ref(true)
const data    = ref(null)

async function load() {
  loading.value = true
  try { data.value = await secretaryService.fateDecisions() }
  catch { data.value = null } finally { loading.value = false }
}
onMounted(load)

// ── Toast ─────────────────────────────────────────────────────────
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

// ── Fate Decision modal ──────────────────────────────────────────
// The Secretary is the approving authority. The three options match the
// in-spec workflow: continue monitoring, public advisory, or decommission.
const showFateModal = ref(false)
const fateTarget    = ref(null)
const fateDecision  = ref('')
const fateForm      = ref({ authorisedBy: '', date: new Date().toISOString().split('T')[0], remarks: '', docRef: '' })
const fateLoading   = ref(false)

function openFate(row) {
  fateTarget.value   = row
  fateDecision.value = ''
  fateForm.value     = { authorisedBy: '', date: new Date().toISOString().split('T')[0], remarks: '', docRef: '' }
  showFateModal.value = true
}
async function submitFate() {
  if (!fateDecision.value)      { showToast('⚠️ Please select a decision.', 'error'); return }
  if (!fateForm.value.remarks)  { showToast('⚠️ Remarks are required — this becomes part of the official record.', 'error'); return }
  fateLoading.value = true
  try {
    await secretaryService.recordFate(fateTarget.value.id, {
      decision:      fateDecision.value,
      authorised_by: fateForm.value.authorisedBy || null,
      decision_date: fateForm.value.date || null,
      remarks:       fateForm.value.remarks,
      doc_ref:       fateForm.value.docRef || null,
    })
    showToast(`✅ Fate decision recorded for ${fateTarget.value.slug || fateTarget.value.wss_name}.`, 'success')
    showFateModal.value = false
    await load()
  } catch (e) {
    const msg = e?.response?.data?.message || e?.message || 'Failed to record decision'
    showToast('❌ ' + msg, 'error')
  } finally {
    fateLoading.value = false
  }
}

const fmtDate = (d) => {
  if (!d) return '—'
  try { return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: '2-digit' }) }
  catch { return d }
}

function statusClass(s) {
  if (s === 'Implemented')      return 'p-green'
  if (s === 'Advisory Issued')  return 'p-amber'
  if (s === 'Monitoring Active')return 'p-blue'
  return 'p-grey'
}
function decisionClass(d) {
  if (d === 'Decommissioned')    return 'p-rose'
  if (d === 'Public Advisory')   return 'p-amber'
  if (d === 'Continue Monitoring')return 'p-blue'
  return 'p-grey'
}
</script>

<template>
  <!-- ── Toast notification (above the modal — z-index 9999) ── -->
  <Teleport to="body">
    <Transition name="toast-slide">
      <div v-if="toast.show"
           :style="`position:fixed;top:22px;right:24px;z-index:9999;min-width:300px;max-width:460px;
                    background:${toast.type === 'success' ? '#065f46' : '#991b1b'};
                    color:#fff;border-radius:8px;padding:14px 18px;
                    box-shadow:0 6px 32px rgba(0,0,0,.28);font-size:13px;display:flex;align-items:flex-start;gap:10px`">
        <span style="flex:1;line-height:1.5">{{ toast.message }}</span>
        <button @click="toast.show = false"
                style="background:rgba(255,255,255,.2);border:none;color:#fff;border-radius:4px;
                       padding:2px 8px;cursor:pointer;font-size:13px;margin-left:4px">✕</button>
      </div>
    </Transition>
  </Teleport>

  <div class="sd">
    <div class="sd-banner rose">
      ⚖️
      <span class="lab">WSS Fate Decisions — Pending Secretary Approval.</span>
      &nbsp; These WSS have failed chemical retest R2 and have been escalated by the respective CE. As Secretary, you are the approving authority for the Fate Decision.
    </div>

    <div class="sd-cards cards-4">
      <div class="c c-rose">
        <div class="lbl">DECISIONS PENDING</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.pending ?? 0 }}</div>
        <div class="sub" v-if="!loading">Secretary approval required</div>
      </div>
      <div class="c c-amber">
        <div class="lbl">DECISIONS ISSUED (YTD)</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.issued_ytd ?? 0 }}</div>
        <div class="sub" v-if="!loading">Approved by Secretary</div>
      </div>
      <div class="c">
        <div class="lbl">DECOMMISSIONED</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.decommissioned ?? 0 }}</div>
        <div class="sub" v-if="!loading">WSS taken out of service</div>
      </div>
      <div class="c c-green">
        <div class="lbl">PUBLIC ADVISORY ISSUED</div>
        <div v-if="loading" class="sd-skel sd-val-skel"></div>
        <div v-else class="val">{{ data?.stats?.public_advisory ?? 0 }}</div>
        <div class="sub" v-if="!loading">Community warned</div>
      </div>
    </div>

    <table class="sd-tbl" style="margin-bottom:18px">
      <thead>
        <tr>
          <th>Sample ID</th>
          <th>WSS Name</th>
          <th>District</th>
          <th>CE</th>
          <th>R0 Remarks</th>
          <th>R1 Remarks</th>
          <th>R2 Remarks</th>
          <th>Stage</th>
          <th>Transferred</th>
          <th>Decision</th>
        </tr>
      </thead>
      <tbody>
        <template v-if="loading">
          <SecSkelRow v-for="n in 3" :key="'fp' + n" :cols="[100, 160, 80, 110, 140, 140, 140, 60, 100, 110]" />
        </template>
        <template v-else>
          <tr v-for="r in (data?.pending || [])" :key="r.id" style="background:#fdf2f8">
            <td class="sid">{{ r.slug }}</td>
            <td><b>{{ r.wss_name }}</b></td>
            <td>{{ r.district }}</td>
            <td><span class="sd-pill p-blue">{{ r.ce }}</span></td>
            <td style="font-size:11px;color:#475569">{{ r.original }}</td>
            <td style="font-size:11px;color:#475569">{{ r.r1 }}</td>
            <td style="font-size:11px;color:#475569">{{ r.r2 }}</td>
            <td><span class="sd-pill p-red">{{ r.stage }}</span></td>
            <td>
              <div v-if="r.transferred_at">
                <span class="sd-pill p-violet" :title="r.transferred_remarks || ''">📨 XEN handed off</span>
                <div style="font-size:10.5px;color:#64748b;margin-top:3px">
                  {{ r.transferred_by_name || 'XEN' }} · {{ fmtDate(r.transferred_at) }}
                </div>
                <div v-if="r.transferred_remarks" style="font-size:10.5px;color:#475569;margin-top:2px;font-style:italic;max-width:220px">
                  "{{ r.transferred_remarks }}"
                </div>
              </div>
              <span v-else class="sd-pill p-grey" style="font-size:10px">Auto-flagged</span>
            </td>
            <td>
              <button class="sd-btn"
                      style="background:#9d174d;color:#fff;border:none;border-radius:4px;padding:6px 12px;font-size:11.5px;font-weight:700;cursor:pointer;white-space:nowrap"
                      @click="openFate(r)">
                ⚖ Decide Fate
              </button>
            </td>
          </tr>
          <tr v-if="!(data?.pending || []).length">
            <td colspan="10" class="empty">No pending fate decisions.</td>
          </tr>
        </template>
      </tbody>
    </table>

    <h3 style="font-size:13px;font-weight:700;color:#0f172a;margin:6px 0">Past Fate Decisions — Issued by Secretary</h3>
    <table class="sd-tbl">
      <thead>
        <tr><th>WSS Name</th><th>District</th><th>CE</th><th>Decision</th><th>Date</th><th>Status</th></tr>
      </thead>
      <tbody>
        <template v-if="loading">
          <SecSkelRow v-for="n in 4" :key="'pa' + n" :cols="[160, 80, 110, 110, 90, 90]" />
        </template>
        <template v-else>
          <tr v-for="r in (data?.past || [])" :key="r.id">
            <td><b>{{ r.wss_name }}</b></td>
            <td>{{ r.district }}</td>
            <td>{{ r.ce }}</td>
            <td><span class="sd-pill" :class="decisionClass(r.decision)">{{ r.decision }}</span></td>
            <td>{{ fmtDate(r.date) }}</td>
            <td><span class="sd-pill" :class="statusClass(r.status)">{{ r.status }}</span></td>
          </tr>
          <tr v-if="!(data?.past || []).length">
            <td colspan="6" class="empty">No past fate decisions yet.</td>
          </tr>
        </template>
      </tbody>
    </table>

    <!-- ── FATE DECISION MODAL ─────────────────────────────────────── -->
    <Teleport to="body">
      <div v-if="showFateModal" @click.self="showFateModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:3400;align-items:flex-start;justify-content:center;overflow-y:auto;padding:30px">
        <div style="background:#fff;border-radius:10px;width:100%;max-width:620px;margin:auto;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,.28);font-family:'DM Sans',sans-serif">
          <div style="background:#9d174d;color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between">
            <div>
              <div style="font-size:14px;font-weight:700">⚖ WSS Fate Decision — Secretary Approval</div>
              <div style="font-size:11px;opacity:.8;margin-top:2px">
                {{ fateTarget?.slug }} · {{ fateTarget?.wss_name }} · {{ fateTarget?.district }} ({{ fateTarget?.ce }})
              </div>
            </div>
            <button @click="showFateModal = false" :disabled="fateLoading"
                    style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 12px;cursor:pointer">✕</button>
          </div>
          <div style="padding:20px 24px">
            <div style="background:#fff5f5;border:1px solid #fca5a5;border-radius:6px;padding:10px 14px;margin-bottom:14px;font-size:11.5px;line-height:1.55">
              <b style="color:#991b1b">⚠ Persistently Unfit:</b>
              Chemical contamination after R2. Stage: <b>{{ fateTarget?.stage }}</b>.
              <span v-if="fateTarget?.transferred_remarks">XEN note: <i>"{{ fateTarget?.transferred_remarks }}"</i></span>
            </div>

            <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;margin-bottom:8px">Select Decision</div>
            <label v-for="opt in [
              { val:'monitor',      title:'🔄 Continue Monitoring',       desc:'Keep WSS operational. Schedule additional retests.', color:'#1d4ed8' },
              { val:'advisory',     title:'⚠ Issue Public Advisory',      desc:'WSS remains operational but public advised against drinking.', color:'#b45309' },
              { val:'decommission', title:'🚫 Decommission / Abandon WSS', desc:'WSS taken out of service permanently. Requires formal approval.', color:'#9d174d' },
            ]" :key="opt.val"
              style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:2px solid #e2e8f0;border-radius:6px;cursor:pointer;margin-bottom:8px"
              :style="fateDecision === opt.val ? `border-color:${opt.color};background:${opt.color}10` : ''"
              @click="fateDecision = opt.val">
              <input type="radio" :value="opt.val" v-model="fateDecision" style="margin-top:2px">
              <div>
                <div style="font-size:12.5px;font-weight:700" :style="{ color: opt.color }">{{ opt.title }}</div>
                <div style="font-size:11px;color:#64748b;margin-top:2px">{{ opt.desc }}</div>
              </div>
            </label>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px">
              <div style="display:flex;flex-direction:column;gap:4px">
                <label style="font-size:11px;font-weight:600;color:#475569">Authorising Officer</label>
                <input type="text" v-model="fateForm.authorisedBy" placeholder="Name / Designation"
                       style="border:1px solid #cbd5e1;border-radius:4px;padding:7px 10px;font-size:12.5px;font-family:inherit">
              </div>
              <div style="display:flex;flex-direction:column;gap:4px">
                <label style="font-size:11px;font-weight:600;color:#475569">Decision Date</label>
                <input type="date" v-model="fateForm.date"
                       style="border:1px solid #cbd5e1;border-radius:4px;padding:7px 10px;font-size:12.5px;font-family:inherit">
              </div>
              <div style="grid-column:1/-1;display:flex;flex-direction:column;gap:4px">
                <label style="font-size:11px;font-weight:600;color:#475569">Remarks / Justification <em style="color:#dc2626;font-style:normal">*</em></label>
                <textarea v-model="fateForm.remarks" rows="3"
                          placeholder="State the basis for this decision (lab data, field notes, policy reference)…"
                          style="border:1px solid #cbd5e1;border-radius:4px;padding:7px 10px;font-size:12.5px;font-family:inherit;resize:vertical"></textarea>
              </div>
              <div style="grid-column:1/-1;display:flex;flex-direction:column;gap:4px">
                <label style="font-size:11px;font-weight:600;color:#475569">Document Reference (optional)</label>
                <input type="text" v-model="fateForm.docRef" placeholder="e.g. PHED Notification No., field inspection report ref"
                       style="border:1px solid #cbd5e1;border-radius:4px;padding:7px 10px;font-size:12.5px;font-family:inherit">
              </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:18px">
              <button @click="showFateModal = false" :disabled="fateLoading"
                      style="background:#fff;color:#334155;border:1px solid #cbd5e1;border-radius:5px;padding:8px 14px;font-size:12.5px;font-weight:600;cursor:pointer">
                Cancel
              </button>
              <button @click="submitFate" :disabled="fateLoading"
                      style="background:#9d174d;color:#fff;border:none;border-radius:5px;padding:8px 16px;font-size:12.5px;font-weight:700;cursor:pointer">
                {{ fateLoading ? '⏳ Recording…' : '⚖ Record Decision' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped lang="scss">
@use './secretary-shared.scss' as *;
</style>

<style>
/* Global so the toast in <Teleport to="body"> picks up the transition */
.toast-slide-enter-active,
.toast-slide-leave-active { transition: all 0.3s ease; }
.toast-slide-enter-from,
.toast-slide-leave-to     { opacity: 0; transform: translateX(60px); }
</style>
