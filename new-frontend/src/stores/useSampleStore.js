import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useSampleStore = defineStore('samples', () => {
  // Pending analysis queue
  const pendingQueue = ref([
    { id:'26/CLB/5042', wss:'Hayatabad WSS Ph-5',  district:'Peshawar', type:'PCM', point:'Source',          by:'Ahmad Khan',   date:'10-Mar-26', method:'MF',  isPT:false, status:'pending' },
    { id:'26/CLB/5043', wss:'Shahi Bagh WSS',       district:'Peshawar', type:'M',   point:'C/End',           by:'Bilal Akhtar', date:'10-Mar-26', method:'Kit', isPT:false, status:'pending' },
    { id:'26/CLB/P0089',wss:'Al-Noor Hospital',     district:'Peshawar', type:'PCM', point:'Source / Outlet', by:'Lab Staff',    date:'10-Mar-26', method:'MF',  isPT:false, status:'pending' },
    { id:'26/KHT/0313', wss:'Kohat Ring Road WSS',  district:'Kohat',    type:'PC',  point:'Source',          by:'Naveed Shah',  date:'09-Mar-26', method:'MF',  isPT:false, status:'pending' },
    { id:'26/MRD/0290', wss:'Mardan City WSS',      district:'Mardan',   type:'PCM', point:'C/End',           by:'Usman Ali',    date:'09-Mar-26', method:'Kit', isPT:false, status:'pending' },
    { id:'PT/26/CLB/001',wss:'PCRWR-Q1-2026',       district:'—',        type:'PT',  point:'N/A — Blind',     by:'Lab Staff',    date:'13-Mar-26', method:'MF',  isPT:true,  status:'pending' },
    { id:'PT/26/CLB/002',wss:'PCRWR-Q1-2026',       district:'—',        type:'PT',  point:'N/A — Blind',     by:'Lab Staff',    date:'13-Mar-26', method:'MF',  isPT:true,  status:'pending' },
  ])

  const pendingCount = computed(() => pendingQueue.value.filter(s => s.status === 'pending').length)

  function markAnalysed(id, qcStatus) {
    const s = pendingQueue.value.find(s => s.id === id)
    if (s) s.status = qcStatus
  }

  function addRetestSample(original) {
    const retestId = original.id + '-R1'
    pendingQueue.value.unshift({
      ...original,
      id: retestId,
      status: 'pending',
      isRetest: true,
      originalId: original.id,
    })
  }

  return { pendingQueue, pendingCount, markAnalysed, addRetestSample }
})
