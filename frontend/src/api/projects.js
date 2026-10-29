import { request } from './client'

export function listProjects(token, { page = 1 } = {}) {
  return request(`/projects?page=${page}`, { token })
}

export function createProject(token, payload) {
  return request('/projects', { method: 'POST', body: payload, token })
}
