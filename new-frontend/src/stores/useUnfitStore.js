import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useUnfitStore = defineStore('unfit', () => {
  const unfitSamples = ref([
    { id:'26/CLB/5040', wss:'Shahi Bagh WSS',      div:'PWR-I',    district:'Peshawar', date:'09-Mar-26', cause:'E. coli',  value:'12 CFU / 0',        status:'Action Taken',      stage:'R1', result:'Pending',          rag:'r-amber' },
    { id:'26/MRD/0289', wss:'Mardan City WSS',      div:'Mardan',   district:'Mardan',   date:'07-Mar-26', cause:'Arsenic',  value:'62 µg/L / 50 µg/L', status:'No Action Yet',     stage:'—',  result:'—',                rag:'r-red'   },
    { id:'26/SWT/0411', wss:'Swat Mingora WSS',     div:'Swat',     district:'Swat',     date:'05-Mar-26', cause:'T. Coliform',value:'28 CFU / 0',       status:'Resolved',          stage:'R1', result:'Fit ✔',            rag:'r-green' },
    { id:'26/KHT/0298', wss:'Kohat Ring Road',      div:'Kohat',    district:'Kohat',    date:'01-Mar-26', cause:'E. coli',  value:'5 CFU / 0',         status:'XEN Re-notified',   stage:'R2', result:'Unfit ✘',          rag:'r-red'   },
    { id:'26/BNU/0174', wss:'Bannu Saddar WSS',     div:'Bannu',    district:'Bannu',    date:'28-Feb-26', cause:'E. coli',  value:'9 CFU / 0',         status:'XEN Action #2',     stage:'R2', result:'Pending',          rag:'r-red'   },
    { id:'26/DIK/0088', wss:'D.I. Khan Canal WSS',  div:'D.I. Khan',district:'D.I. Khan',date:'15-Feb-26', cause:'Arsenic',  value:'88 µg/L / 50 µg/L', status:'Fate Decision Req.',stage:'R3', result:'Persistently Unfit',rag:'r-red'   },
  ])

  const notifLog = ref([
    { sampleId:'26/CLB/5040', wss:'Shahi Bagh — Peshawar',    div:'PWR-I',    xen:'Engr. Tariq XEN',  type:'Initial',      at:'09-Mar-26 14:32', channel:'Dashboard + Email' },
    { sampleId:'26/MRD/0289', wss:'Mardan City — Mardan',     div:'Mardan',   xen:'Engr. Saleem XEN', type:'Initial',      at:'07-Mar-26 11:15', channel:'Dashboard + Email' },
    { sampleId:'26/SWT/0411', wss:'Swat Mingora — Swat',      div:'Swat',     xen:'Engr. Imran XEN',  type:'Initial',      at:'05-Mar-26 09:48', channel:'Dashboard + Email' },
    { sampleId:'26/KHT/0298', wss:'Kohat Ring Road — Kohat',  div:'Kohat',    xen:'Engr. Nasir XEN',  type:'Initial',      at:'01-Mar-26 08:22', channel:'Dashboard + Email' },
    { sampleId:'26/KHT/0298', wss:'Kohat Ring Road — Kohat',  div:'Kohat',    xen:'Engr. Nasir XEN',  type:'Escalation',   at:'05-Mar-26 14:31', channel:'Dashboard + Email' },
    { sampleId:'26/BNU/0174', wss:'Bannu Saddar WSS — Bannu', div:'Bannu',    xen:'Engr. Khalid XEN', type:'Initial',      at:'28-Feb-26 16:05', channel:'Dashboard + Email' },
    { sampleId:'26/DIK/0088', wss:'D.I. Khan Canal WSS',      div:'D.I. Khan',xen:'Engr. Waheed XEN', type:'2nd Escalation',at:'25-Feb-26 14:02',channel:'Dashboard + Email' },
  ])

  const summary = computed(() => ({
    total:        unfitSamples.value.length,
    noAction:     unfitSamples.value.filter(s => s.status === 'No Action Yet').length,
    actionTaken:  unfitSamples.value.filter(s => s.status === 'Action Taken').length,
    renotified:   unfitSamples.value.filter(s => s.status.includes('Re-notified') || s.status.includes('XEN Re')).length,
    fateDecision: unfitSamples.value.filter(s => s.status.includes('Fate')).length,
    resolved:     unfitSamples.value.filter(s => s.status === 'Resolved').length,
  }))

  function updateStatus(id, status, result) {
    const s = unfitSamples.value.find(s => s.id === id)
    if (s) { s.status = status; if (result) s.result = result }
  }

  return { unfitSamples, notifLog, summary, updateStatus }
})
