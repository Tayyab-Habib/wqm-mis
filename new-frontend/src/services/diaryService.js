import { api } from './api.js'

export const diaryService = {
  // Diary registers (enum: 'diary' or 'dispatch')
  getDiaries: () => api.get('/diary-dispatch/diary/registers'),
  getDiary: (id) => api.get(`/diary-dispatch/diary/registers/${id}`),
  createDiary: (data) => api.post('/diary-dispatch/diary/registers', data, { headers: { 'Content-Type': 'multipart/form-data' } }),
  updateDiary: (id, data) => api.post(`/diary-dispatch/diary/registers/${id}`, { ...data, _method: 'PUT' }),
  deleteDiary: (id) => api.delete(`/diary-dispatch/diary/registers/${id}`),

  // Dispatch registers
  getDispatches: () => api.get('/diary-dispatch/dispatch/registers'),
  getDispatch: (id) => api.get(`/diary-dispatch/dispatch/registers/${id}`),
  createDispatch: (data) => api.post('/diary-dispatch/dispatch/registers', data, { headers: { 'Content-Type': 'multipart/form-data' } }),
  updateDispatch: (id, data) => api.post(`/diary-dispatch/dispatch/registers/${id}`, { ...data, _method: 'PUT' }),
  deleteDispatch: (id) => api.delete(`/diary-dispatch/dispatch/registers/${id}`),
}
