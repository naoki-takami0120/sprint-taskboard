import { request } from './client'

export function listTasks(token, projectId, { status, assigneeId, perPage } = {}) {
  const params = new URLSearchParams()
  if (status) params.set('status', status)
  if (assigneeId) params.set('assignee_id', assigneeId)
  if (perPage) params.set('per_page', perPage)

  const query = params.toString()
  return request(`/projects/${projectId}/tasks${query ? `?${query}` : ''}`, { token })
}

export function createTask(token, projectId, payload) {
  return request(`/projects/${projectId}/tasks`, { method: 'POST', body: payload, token })
}

export function updateTask(token, taskId, payload) {
  return request(`/tasks/${taskId}`, { method: 'PUT', body: payload, token })
}

export function deleteTask(token, taskId) {
  return request(`/tasks/${taskId}`, { method: 'DELETE', token })
}

export function changeTaskStatus(token, taskId, status) {
  return request(`/tasks/${taskId}/status`, { method: 'PATCH', body: { status }, token })
}
