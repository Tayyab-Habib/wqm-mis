import axios from './axios.js'

export const api = {
  get: (path, config = {}) => axios.get(`/api${path}`, config),
  post: (path, body, config = {}) => axios.post(`/api${path}`, body, config),
  put: (path, body, config = {}) => axios.put(`/api${path}`, body, config),
  patch: (path, body, config = {}) => axios.patch(`/api${path}`, body, config),
  delete: (path, config = {}) => axios.delete(`/api${path}`, config),
}
