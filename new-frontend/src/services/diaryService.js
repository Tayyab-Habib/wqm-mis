import { api } from './api.js'

export const diaryService = {
  // Diary registers (enum: 'diary' or 'dispatch')
  getDiaries: () => api.get('/diary-dispatch/diary/registers'),
  getDiary: (id) => api.get(`/diary-dispatch/diary/registers/${id}`),
  createDiary: (data) => api.post('/diary-dispatch/diary/registers', data),
  updateDiary: (id, data) => api.put(`/diary-dispatch/diary/registers/${id}`, data),
  deleteDiary: (id) => api.delete(`/diary-dispatch/diary/registers/${id}`),

  // Dispatch registers
  getDispatches: () => api.get('/diary-dispatch/dispatch/registers'),
  getDispatch: (id) => api.get(`/diary-dispatch/dispatch/registers/${id}`),
  createDispatch: (data) => api.post('/diary-dispatch/dispatch/registers', data),
  updateDispatch: (id, data) => api.put(`/diary-dispatch/dispatch/registers/${id}`, data),
  deleteDispatch: (id) => api.delete(`/diary-dispatch/dispatch/registers/${id}`),
}
