import { request } from './client'

export function listProjects(token, { page = 1 } = {}) {
  return request(`/projects?page=${page}`, { token })
}

export function createProject(token, payload) {
  return request('/projects', { method: 'POST', body: payload, token })
}

export function getProject(token, id) {
  return request(`/projects/${id}`, { token })
}

export function listMembers(token, id) {
  return request(`/projects/${id}/members`, { token })
}
