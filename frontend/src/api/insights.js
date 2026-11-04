import { request } from './client'

export function getRanking(token, projectId) {
  return request(`/projects/${projectId}/ranking`, { token })
}

export function listSprints(token, projectId) {
  return request(`/projects/${projectId}/sprints`, { token })
}

export function getBurndown(token, sprintId) {
  return request(`/sprints/${sprintId}/burndown`, { token })
}
