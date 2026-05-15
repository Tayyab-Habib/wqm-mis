<script setup>
import { ref, computed, onMounted } from 'vue'
import { diaryService } from '../../../services/diaryService.js'

const loading   = ref(false)
const errorMsg  = ref('')
const activeTab = ref(0)
const diaryEntries = ref([])
const dispatches   = ref([])

// ── Toast (matches the pattern in Topbar.vue / UsersHR.vue) ───────────
const toast = ref({ show: false, message: '', type: 'success' })
let toastTimer = null
function showToast(message, type = 'success') {
  clearTimeout(toastTimer)
  toast.value = { show: true, message, type }
  toastTimer = setTimeout(() => { toast.value.show = false }, 4000)
}

function mapDiary(d) {
  return {
    id:            d.id,
    diaryNo:       d.id ? `DR/${new Date(d.created_at||Date.now()).getFullYear().toString().slice(-2)}/CLB/${String(d.id).padStart(4,'0')}` : '',
    date:          d.created_at ? d.created_at.split(' ')[0] : '',
    from:          d.from_sender || d.person_name || '',
    subject:       d.subject || '',
    referenceNo:   d.reference_no || '',
    category:      d.category || '',
    priority:      d.priority || 'Routine',
    addressedTo:   d.addressed_to || '',
    due:           d.action_due_date || '',
    status:        d.action_status || 'Pending',
    remarks:       d.remarks || '',
    attachment:    d.attachment || null,
  }
}

function mapDispatch(d) {
  return {
    id:              d.id,
    dispatchNo:      d.id ? `DSP/${new Date(d.created_at||Date.now()).getFullYear().toString().slice(-2)}/CLB/${String(d.id).padStart(4,'0')}` : '',
    date:            d.created_at ? d.created_at.split(' ')[0] : '',
    to:              d.to_recipient || d.person_name || '',
    subject:         d.subject || '',
    referenceNo:     d.reference_no || '',
    referenceDiaryNo: d.reference_diary_no || '',
    category:        d.category || '',
    priority:        d.priority || 'Routine',
    mode:            d.mode_of_dispatch || '',
    by:              d.dispatched_by || '',
    remarks:         d.remarks || '',
    status:          d.action_status || 'Draft',
    attachment:      d.attachment || null,
  }
}

async function loadData() {
  loading.value = true
  errorMsg.value = ''
  try {
    const [dRes, dispRes] = await Promise.all([
      diaryService.getDiaries(),
      diaryService.getDispatches(),
    ])
    const dData    = dRes.data?.data    || dRes.data    || []
    const dispData = dispRes.data?.data || dispRes.data || []
    diaryEntries.value = Array.isArray(dData)    ? dData.map(mapDiary)    : []
    dispatches.value   = Array.isArray(dispData) ? dispData.map(mapDispatch) : []
  } catch (e) {
    errorMsg.value = 'Failed to load diary/dispatch data'
    console.error('Diary load error:', e)
  } finally {
    loading.value = false
  }
}

const showDiaryModal = ref(false)
const diaryForm = ref({
  from_sender: '', reference_no: '', date_on_letter: new Date().toISOString().split('T')[0],
  subject: '', category: '', priority: 'Routine', addressed_to: '',
  action_required: false, action_due_date: '', remarks: '',
  attachment: null, attachment_name: '',
})
function onDiaryFile(e) { diaryForm.value.attachment = e.target.files[0] || null }

async function saveDiary() {
  if (!diaryForm.value.subject) { showToast('⚠️ Subject is required.', 'error'); return }
  try {
    const fd = new FormData()
    const fields = ['from_sender','reference_no','date_on_letter','subject','category',
                    'priority','addressed_to','action_required','action_due_date','remarks','attachment_name']
    fields.forEach(f => { if (diaryForm.value[f] !== '' && diaryForm.value[f] !== null && diaryForm.value[f] !== undefined) fd.append(f, diaryForm.value[f]) })
    // action_required must be 1/0 for FormData boolean validation
    fd.set('action_required', diaryForm.value.action_required ? '1' : '0')
    if (diaryForm.value.attachment) fd.append('attachment', diaryForm.value.attachment)
    await diaryService.createDiary(fd)
    await loadData()
    showDiaryModal.value = false
    showToast('✅ Diary entry saved successfully', 'success')
  } catch (e) {
    showToast('❌ Failed to save: ' + (e?.response?.data?.message || e?.message || 'Error'), 'error')
  }
}

const showDispatchModal = ref(false)
const dispForm = ref({
  to_recipient: '', reference_no: '', reference_diary_no: '',
  date_on_letter: new Date().toISOString().split('T')[0],
  subject: '', category: '', priority: 'Routine',
  mode_of_dispatch: '', dispatch_reference_no: '',
  prepared_by: '', dispatched_by: '', remarks: '',
  attachment: null, attachment_name: '',
})
function onDispatchFile(e) { dispForm.value.attachment = e.target.files[0] || null }

async function saveDispatch() {
  if (!dispForm.value.subject || !dispForm.value.to_recipient) { showToast('⚠️ Subject and To are required.', 'error'); return }
  try {
    const fd = new FormData()
    const fields = ['to_recipient','reference_no','reference_diary_no','date_on_letter','subject',
                    'category','priority','mode_of_dispatch','dispatch_reference_no',
                    'prepared_by','dispatched_by','remarks','attachment_name']
    fields.forEach(f => { if (dispForm.value[f] !== '' && dispForm.value[f] !== null && dispForm.value[f] !== undefined) fd.append(f, dispForm.value[f]) })
    if (dispForm.value.attachment) fd.append('attachment', dispForm.value.attachment)
    await diaryService.createDispatch(fd)
    await loadData()
    showDispatchModal.value = false
    showToast('✅ Dispatch saved successfully', 'success')
  } catch (e) {
    showToast('❌ Failed to save: ' + (e?.response?.data?.message || e?.message || 'Error'), 'error')
  }
}

async function markDone(id, type) {
  try {
    if (type === 'diary') await diaryService.updateDiary(id, { action_status: 'Completed' })
    else await diaryService.updateDispatch(id, { action_status: 'Sent' })
    await loadData()
    showToast(type === 'diary' ? '✅ Marked as completed' : '✅ Marked as sent', 'success')
  } catch (e) {
    console.error('Mark done error:', e)
    const errs = e?.response?.data?.errors
    const msg = errs
      ? Object.values(errs).flat().join(' | ')
      : (e?.response?.data?.message || e?.message || 'Unknown error')
    showToast('❌ Failed to mark as done: ' + msg, 'error')
  }
}

const diarySearch  = ref('')
const diaryPri     = ref('')
const diaryStatus  = ref('')

const filteredDiary = computed(() => diaryEntries.value.filter(d => {
  const q = diarySearch.value.toLowerCase()
  const matchSearch = !q || d.subject.toLowerCase().includes(q) || d.from.toLowerCase().includes(q)
  const matchPri    = !diaryPri.value    || d.priority === diaryPri.value
  const matchStatus = !diaryStatus.value || d.status   === diaryStatus.value
  return matchSearch && matchPri && matchStatus
}))

const filteredDisp = computed(() => dispatches.value.filter(d => {
  const q = diarySearch.value.toLowerCase()
  return !q || d.subject.toLowerCase().includes(q) || d.to.toLowerCase().includes(q)
}))

const pendingActions = computed(() => {
  const inward  = diaryEntries.value.filter(d => d.status !== 'Completed').map(d => ({ ...d, entryType:'Inward' }))
  const outward = dispatches.value.filter(d => d.status === 'Draft').map(d => ({ ...d, entryType:'Outward' }))
  return [...inward, ...outward].sort((a, b) => (a.due || '').localeCompare(b.due || ''))
})

function priClass(p) { return p === 'Immediate' ? 'r-red' : p === 'Urgent' ? 'r-amber' : 'r-grey' }
function statusClass(s) { return s === 'Completed' || s === 'Sent' ? 'r-green' : s === 'In Progress' ? 'r-amber' : s === 'Draft' ? 'r-grey' : 'r-red' }

onMounted(loadData)
</script>

<template>
  <div>
    <!-- ── Toast notification ── -->
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

    <div class="tabs" style="margin-bottom:0">
      <div v-for="(t, i) in ['Diary (Inward)', 'Dispatch (Outward)', 'Pending Actions']" :key="i"
           class="tab" :class="{ active: activeTab === i }" @click="activeTab = i">{{ t }}</div>
    </div>

    <div v-if="errorMsg" style="background:#fee2e2;border:1px solid #fca5a5;border-radius:5px;padding:9px 14px;margin:10px 0;font-size:12.5px;color:#991b1b">{{ errorMsg }}</div>

    <!-- DIARY (INWARD) -->
    <div v-if="activeTab === 0">
      <div class="toolbar">
        <input type="text" v-model="diarySearch" placeholder="Search subject, sender...">
        <select v-model="diaryPri" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
          <option value="">All Priority</option>
          <option value="Immediate">Immediate</option>
          <option value="Urgent">Urgent</option>
          <option value="Routine">Routine</option>
        </select>
        <select v-model="diaryStatus" style="border:1px solid var(--input-border);border-radius:4px;padding:6px 8px;font-size:12px;font-family:inherit">
          <option value="">All Status</option>
          <option value="Pending">Pending</option>
          <option value="In Progress">In Progress</option>
          <option value="Completed">Completed</option>
        </select>
        <div class="tsp"></div>
        <button class="btn btn-pri btn-sm" @click="showDiaryModal = true">+ New Diary Entry</button>
      </div>
      <div class="tbl-wrap">
        <table style="font-size:11.5px">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="color:#fff">Diary No.</th>
              <th style="color:#fff">Date Received</th>
              <th style="color:#fff">From</th>
              <th style="color:#fff">Subject</th>
              <th style="color:#fff">Ref. No.</th>
              <th style="color:#fff">Category</th>
              <th style="color:#fff">Priority</th>
              <th style="color:#fff">Action Due</th>
              <th style="color:#fff">Action Status</th>
              <th style="color:#fff">Actions</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="loading">
              <tr v-for="n in 6" :key="'sk-d-' + n" class="skel-row">
                <td><span class="skel" style="width:90px"></span></td>
                <td><span class="skel" style="width:70px"></span></td>
                <td><span class="skel" style="width:120px"></span></td>
                <td><span class="skel" style="width:180px"></span></td>
                <td><span class="skel" style="width:80px"></span></td>
                <td><span class="skel" style="width:80px"></span></td>
                <td><span class="skel pill" style="width:55px"></span></td>
                <td><span class="skel" style="width:70px"></span></td>
                <td><span class="skel pill" style="width:65px"></span></td>
                <td><span class="skel btn" style="width:55px"></span></td>
              </tr>
            </template>
            <tr v-else-if="!filteredDiary.length">
              <td colspan="10" style="text-align:center;padding:24px;color:var(--muted)">No diary entries found.</td>
            </tr>
            <tr v-else v-for="(d, i) in filteredDiary" :key="d.id"
                :class="i%2===1?'alt':''"
                :style="d.status !== 'Completed' && d.due && d.due < new Date().toISOString().split('T')[0] ? 'background:#fff3f3' : ''">
              <td class="mono" style="font-size:11px">{{ d.diaryNo }}</td>
              <td>{{ d.date }}</td>
              <td>{{ d.from }}</td>
              <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ d.subject }}</td>
              <td class="mono" style="font-size:11px">{{ d.referenceNo }}</td>
              <td style="font-size:11px">{{ d.category }}</td>
              <td><span class="rag" :class="priClass(d.priority)">{{ d.priority }}</span></td>
              <td :style="d.status !== 'Completed' && d.due && d.due < new Date().toISOString().split('T')[0] ? 'color:var(--red);font-weight:700' : ''">{{ d.due }}</td>
              <td><span class="rag" :class="statusClass(d.status)">{{ d.status }}</span></td>
              <td style="white-space:nowrap">
                <button v-if="d.status !== 'Completed'" class="btn btn-sec btn-xs" @click="markDone(d.id, 'diary')">Done</button>
                <a v-if="d.attachment" :href="d.attachment" target="_blank" class="btn btn-sec btn-xs" style="margin-left:4px">File</a>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- DISPATCH (OUTWARD) -->
    <div v-if="activeTab === 1">
      <div class="toolbar">
        <input type="text" v-model="diarySearch" placeholder="Search subject, recipient...">
        <div class="tsp"></div>
        <button class="btn btn-pri btn-sm" @click="showDispatchModal = true">+ New Dispatch</button>
      </div>
      <div class="tbl-wrap">
        <table style="font-size:11.5px">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="color:#fff">Dispatch No.</th>
              <th style="color:#fff">Date</th>
              <th style="color:#fff">To</th>
              <th style="color:#fff">Subject</th>
              <th style="color:#fff">Ref. Diary No.</th>
              <th style="color:#fff">Category</th>
              <th style="color:#fff">Mode</th>
              <th style="color:#fff">Dispatched By</th>
              <th style="color:#fff">Status</th>
              <th style="color:#fff">File</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="loading">
              <tr v-for="n in 6" :key="'sk-x-' + n" class="skel-row">
                <td><span class="skel" style="width:90px"></span></td>
                <td><span class="skel" style="width:70px"></span></td>
                <td><span class="skel" style="width:120px"></span></td>
                <td><span class="skel" style="width:180px"></span></td>
                <td><span class="skel" style="width:90px"></span></td>
                <td><span class="skel" style="width:80px"></span></td>
                <td><span class="skel" style="width:70px"></span></td>
                <td><span class="skel" style="width:90px"></span></td>
                <td><span class="skel pill" style="width:55px"></span></td>
                <td><span class="skel btn" style="width:40px"></span></td>
              </tr>
            </template>
            <tr v-else-if="!filteredDisp.length">
              <td colspan="10" style="text-align:center;padding:24px;color:var(--muted)">No dispatches found.</td>
            </tr>
            <tr v-else v-for="(d, i) in filteredDisp" :key="d.id" :class="i%2===1?'alt':''">
              <td class="mono" style="font-size:11px">{{ d.dispatchNo }}</td>
              <td>{{ d.date }}</td>
              <td>{{ d.to }}</td>
              <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ d.subject }}</td>
              <td class="mono" style="font-size:11px">{{ d.referenceDiaryNo }}</td>
              <td style="font-size:11px">{{ d.category }}</td>
              <td style="font-size:11px">{{ d.mode }}</td>
              <td>{{ d.by }}</td>
              <td><span class="rag" :class="statusClass(d.status)">{{ d.status }}</span></td>
              <td>
                <a v-if="d.attachment" :href="d.attachment" target="_blank" class="btn btn-sec btn-xs" style="font-size:10px">File</a>
                <span v-else style="color:var(--muted);font-size:11px">-</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- PENDING ACTIONS -->
    <div v-if="activeTab === 2">
      <div v-if="loading" class="abar amber" style="padding:9px 13px">
        <span class="skel" style="width:220px;height:13px"></span>
      </div>
      <div v-else-if="pendingActions.length === 0" class="abar green">No pending actions.</div>
      <div v-else class="abar amber">{{ pendingActions.length }} pending action(s) require attention. Overdue entries highlighted red.</div>
      <div class="tbl-wrap">
        <table style="font-size:11.5px">
          <thead>
            <tr style="background:var(--navy);color:#fff">
              <th style="color:#fff">Ref. No.</th>
              <th style="color:#fff">Type</th>
              <th style="color:#fff">Date</th>
              <th style="color:#fff">From / To</th>
              <th style="color:#fff">Subject</th>
              <th style="color:#fff">Priority</th>
              <th style="color:#fff">Due Date</th>
              <th style="color:#fff">Status</th>
              <th style="color:#fff">Actions</th>
            </tr>
          </thead>
          <tbody>
            <template v-if="loading">
              <tr v-for="n in 5" :key="'sk-p-' + n" class="skel-row">
                <td><span class="skel" style="width:90px"></span></td>
                <td><span class="skel pill" style="width:55px"></span></td>
                <td><span class="skel" style="width:70px"></span></td>
                <td><span class="skel" style="width:110px"></span></td>
                <td><span class="skel" style="width:170px"></span></td>
                <td><span class="skel pill" style="width:55px"></span></td>
                <td><span class="skel" style="width:70px"></span></td>
                <td><span class="skel pill" style="width:65px"></span></td>
                <td><span class="skel btn" style="width:55px"></span></td>
              </tr>
            </template>
            <tr v-else-if="!pendingActions.length">
              <td colspan="9" style="text-align:center;padding:24px;color:var(--muted)">No pending actions.</td>
            </tr>
            <tr v-else v-for="(d, i) in pendingActions" :key="d.id"
                :class="i%2===1?'alt':''"
                :style="d.due && d.due < new Date().toISOString().split('T')[0] ? 'background:#fff3f3' : ''">
              <td class="mono" style="font-size:11px">{{ d.diaryNo || d.dispatchNo }}</td>
              <td>
                <span v-if="d.entryType==='Inward'" style="font-size:10px;background:#e0f0ff;color:var(--blue);padding:2px 7px;border-radius:4px;font-weight:600">Inward</span>
                <span v-else style="font-size:10px;background:#e8f5e9;color:var(--green);padding:2px 7px;border-radius:4px;font-weight:600">Outward</span>
              </td>
              <td>{{ d.date }}</td>
              <td>{{ d.from || d.to }}</td>
              <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ d.subject }}</td>
              <td><span class="rag" :class="priClass(d.priority)">{{ d.priority }}</span></td>
              <td :style="d.due && d.due < new Date().toISOString().split('T')[0] ? 'color:var(--red);font-weight:700' : ''">{{ d.due || '-' }}</td>
              <td><span class="rag" :class="statusClass(d.status)">{{ d.status }}</span></td>
              <td>
                <button class="btn btn-pri btn-xs" @click="markDone(d.id, d.entryType === 'Inward' ? 'diary' : 'dispatch')">Done</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- NEW DIARY MODAL -->
    <Teleport to="body">
      <div v-if="showDiaryModal" @click.self="showDiaryModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:3800;align-items:flex-start;justify-content:center;overflow-y:auto;padding:30px">
        <div style="background:#fff;border-radius:10px;width:100%;max-width:720px;margin:auto;box-shadow:0 8px 40px rgba(0,0,0,.28);overflow:hidden">
          <div style="background:var(--navy);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:14px;font-weight:700">New Diary Entry (Inward Correspondence)</div>
            <button @click="showDiaryModal = false" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 12px;cursor:pointer;font-size:13px">Close</button>
          </div>
          <div style="padding:22px 24px">
            <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px">
              <div class="fg2 span2"><label>From (Sender / Organisation) *</label><input type="text" v-model="diaryForm.from_sender" placeholder="e.g. UNOPS Office, DHO Peshawar"></div>
              <div class="fg2"><label>Reference No.</label><input type="text" v-model="diaryForm.reference_no" placeholder="e.g. UNOPS/2026/041"></div>
              <div class="fg2"><label>Letter Date</label><input type="date" v-model="diaryForm.date_on_letter"></div>
              <div class="fg2 span2"><label>Subject *</label><input type="text" v-model="diaryForm.subject" placeholder="Brief subject of the communication"></div>
              <div class="fg2">
                <label>Category</label>
                <select v-model="diaryForm.category">
                  <option value="">Select</option>
                  <option>Order</option><option>Circular</option><option>Notification</option>
                  <option>Report Request</option><option>Complaint</option><option>Other</option>
                </select>
              </div>
              <div class="fg2">
                <label>Priority</label>
                <select v-model="diaryForm.priority">
                  <option value="Routine">Routine</option>
                  <option value="Urgent">Urgent</option>
                  <option value="Immediate">Immediate</option>
                </select>
              </div>
              <div class="fg2"><label>Addressed To</label><input type="text" v-model="diaryForm.addressed_to" placeholder="e.g. Lab In-charge"></div>
              <div class="fg2"><label>Action Due Date</label><input type="date" v-model="diaryForm.action_due_date"></div>
              <div class="fg2" style="display:flex;align-items:center;gap:8px;padding-top:20px">
                <input type="checkbox" v-model="diaryForm.action_required" id="action_req">
                <label for="action_req" style="margin:0;font-size:12px">Action Required</label>
              </div>
              <div class="fg2">
                <label>Action Status</label>
                <select v-model="diaryForm.action_status">
                  <option value="Pending">Pending</option>
                  <option value="In Progress">In Progress</option>
                  <option value="Completed">Completed</option>
                </select>
              </div>
              <div class="fg2 span2"><label>Remarks</label><textarea v-model="diaryForm.remarks" rows="2" placeholder="Any remarks"></textarea></div>
              <div class="fg2"><label>Attachment (PDF/JPG/DOC)</label><input type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" @change="onDiaryFile" style="font-size:12px;padding:5px 8px;border:1px solid var(--border);border-radius:4px;width:100%;box-sizing:border-box"></div>
              <div class="fg2"><label>Attachment Name</label><input type="text" v-model="diaryForm.attachment_name" placeholder="e.g. Letter_Mar26.pdf"></div>
            </div>
          </div>
          <div style="padding:14px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;background:#fafbfc">
            <button class="btn btn-sec" @click="showDiaryModal = false">Cancel</button>
            <button v-write class="btn btn-pri" @click="saveDiary">Save Diary Entry</button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- NEW DISPATCH MODAL -->
    <Teleport to="body">
      <div v-if="showDispatchModal" @click.self="showDispatchModal = false"
           style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:3900;align-items:flex-start;justify-content:center;overflow-y:auto;padding:30px">
        <div style="background:#fff;border-radius:10px;width:100%;max-width:720px;margin:auto;box-shadow:0 8px 40px rgba(0,0,0,.28);overflow:hidden">
          <div style="background:var(--navy);color:#fff;padding:14px 20px;display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:14px;font-weight:700">New Dispatch (Outward Correspondence)</div>
            <button @click="showDispatchModal = false" style="background:rgba(255,255,255,.15);border:none;color:#fff;border-radius:5px;padding:5px 12px;cursor:pointer;font-size:13px">Close</button>
          </div>
          <div style="padding:22px 24px">
            <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px">
              <div class="fg2 span2"><label>To (Recipient / Organisation) *</label><input type="text" v-model="dispForm.to_recipient" placeholder="e.g. UNOPS Office, DHO Peshawar"></div>
              <div class="fg2"><label>Reference No.</label><input type="text" v-model="dispForm.reference_no" placeholder="e.g. CLB/DSP/2026/018"></div>
              <div class="fg2"><label>In Reply to Diary No.</label><input type="text" v-model="dispForm.reference_diary_no" placeholder="e.g. DR/26/CLB/0041"></div>
              <div class="fg2"><label>Letter Date</label><input type="date" v-model="dispForm.date_on_letter"></div>
              <div class="fg2 span2"><label>Subject *</label><input type="text" v-model="dispForm.subject" placeholder="Subject of dispatch"></div>
              <div class="fg2">
                <label>Category</label>
                <select v-model="dispForm.category">
                  <option value="">Select</option>
                  <option>Report</option><option>Reply</option><option>Forwarding</option>
                  <option>Office Order</option><option>Complaint Response</option><option>Other</option>
                </select>
              </div>
              <div class="fg2">
                <label>Priority</label>
                <select v-model="dispForm.priority">
                  <option value="Routine">Routine</option>
                  <option value="Urgent">Urgent</option>
                  <option value="Immediate">Immediate</option>
                </select>
              </div>
              <div class="fg2">
                <label>Mode of Dispatch</label>
                <select v-model="dispForm.mode_of_dispatch">
                  <option value="">Select</option>
                  <option>Hand Delivery</option><option>Post</option>
                  <option>Courier</option><option>Email</option><option>Fax</option>
                </select>
              </div>
              <div class="fg2"><label>Dispatch Reference No.</label><input type="text" v-model="dispForm.dispatch_reference_no" placeholder="e.g. DSP/2026/018"></div>
              <div class="fg2"><label>Prepared By</label><input type="text" v-model="dispForm.prepared_by" placeholder="Name of preparer"></div>
              <div class="fg2"><label>Dispatched By</label><input type="text" v-model="dispForm.dispatched_by" placeholder="Name of dispatcher"></div>
              <div class="fg2 span2"><label>Remarks</label><textarea v-model="dispForm.remarks" rows="2" placeholder="Any remarks"></textarea></div>
              <div class="fg2"><label>Attachment (PDF/JPG/DOC)</label><input type="file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" @change="onDispatchFile" style="font-size:12px;padding:5px 8px;border:1px solid var(--border);border-radius:4px;width:100%;box-sizing:border-box"></div>
              <div class="fg2"><label>Attachment Name</label><input type="text" v-model="dispForm.attachment_name" placeholder="e.g. Monthly_Report_Feb26.pdf"></div>
            </div>
          </div>
          <div style="padding:14px 24px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;background:#fafbfc">
            <button class="btn btn-sec" @click="showDispatchModal = false">Cancel</button>
            <button v-write class="btn btn-pri" @click="saveDispatch">Save Dispatch</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped lang="scss">
@keyframes dd-shimmer {
  0%   { background-position: -400px 0; }
  100% { background-position:  400px 0; }
}

.skel-row {
  background: transparent !important;
  cursor: default;
  &:hover { background: transparent !important; }
}

.skel {
  display: inline-block;
  height: 12px;
  border-radius: 4px;
  background: linear-gradient(90deg, #eef1f6 0%, #f8fafc 50%, #eef1f6 100%);
  background-size: 800px 100%;
  animation: dd-shimmer 1.2s linear infinite;
  vertical-align: middle;

  &.pill { height: 16px; border-radius: 11px; }
  &.btn  { height: 22px; border-radius: 4px; }
}

// Alert bar containing a skeleton — keep its padding/height stable so the
// row count chip doesn't shift in/out as the data loads.
.abar .skel {
  height: 13px;
  background: linear-gradient(90deg, rgba(0,0,0,.08) 0%, rgba(0,0,0,.04) 50%, rgba(0,0,0,.08) 100%);
  background-size: 800px 100%;
}
</style>

<style>
/* Global so the toast in <Teleport to="body"> picks it up */
.toast-slide-enter-active,
.toast-slide-leave-active { transition: all 0.3s ease; }
.toast-slide-enter-from,
.toast-slide-leave-to     { opacity: 0; transform: translateX(60px); }
</style>