import { ref, watch, onMounted } from 'vue'
import { dropdownService } from '../services/dropdownService.js'

export function useCascadeLocation() {
  const division  = ref('')
  const district  = ref('')
  const phediv    = ref('')

  const allDivisions = ref([])
  const districts    = ref([])
  const phedivs      = ref([])
  const allDistricts = ref([])

  // Load divisions from backend
  async function loadDivisions() {
    try {
      const res = await dropdownService.getDivisions()
      allDivisions.value = Array.isArray(res) ? res : (res.data || [])
    } catch (e) {
      console.error('Failed to load divisions:', e)
    }
  }

  // Load districts from backend
  async function loadDistricts() {
    try {
      const res = await dropdownService.getDistricts()
      allDistricts.value = Array.isArray(res) ? res : (res.data || [])
    } catch (e) {
      console.error('Failed to load districts:', e)
    }
  }

  // Load PHE divisions from backend
  async function loadPhedDivisions() {
    try {
      const res = await dropdownService.getPhedDivisions()
      phedivs.value = Array.isArray(res) ? res : (res.data || [])
    } catch (e) {
      console.error('Failed to load PHE divisions:', e)
    }
  }

  // Filter districts when division changes
  watch(division, (val) => {
    district.value = ''
    phediv.value   = ''
    if (val) {
      // Filter districts by division
      districts.value = allDistricts.value.filter(d =>
        d.division_id === val || d.division?.id === val || d.division?.name === val
      )
    } else {
      districts.value = []
    }
    phedivs.value = []
  })

  watch(district, (val) => {
    phediv.value = ''
    if (val) {
      loadPhedDivisions()
    } else {
      phedivs.value = []
    }
  })

  onMounted(() => {
    loadDivisions()
    loadDistricts()
  })

  return {
    division,
    district,
    phediv,
    districts,
    phedivs,
    allDivisions,
    allDistricts,
    loadDivisions,
    loadDistricts,
  }
}
