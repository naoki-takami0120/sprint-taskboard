import { request } from './client'

export function listTasks(token, projectId, { status, perPage } = {}) {
  const params = new URLSearchParams()
  if (status) params.set('status', status)
  if (perPage) params.set('per_page', perPage)

  const query = params.toString()
  return request(`/projects/${projectId}/tasks${query ? `?${query}` : ''}`, { token })
}
