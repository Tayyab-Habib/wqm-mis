import { defineStore } from 'pinia'
import { reactive } from 'vue'

export const useFilterStore = defineStore('filters', () => {
  const global = reactive({
    client: 'PHE',
    from: '2026-03-01',
    to: '2026-03-31',
    allTime: false,
    region: '',
    division: '',
    circle: '',
    lab: '',
    district: '',
    phediv: '',
  })

  function reset() {
    Object.assign(global, {
      client: 'PHE', from: '2026-03-01', to: '2026-03-31',
      allTime: false, region: '', division: '', circle: '',
      lab: '', district: '', phediv: '',
    })
  }

  return { global, reset }
})
