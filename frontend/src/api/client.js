const BASE = '/api'

export class ApiError extends Error {
  constructor(status, body) {
    super(body?.message ?? `HTTP ${status}`)
    this.status = status
    this.body = body
  }
}

export async function request(path, { method = 'GET', body, token } = {}) {
  const headers = { Accept: 'application/json' }
  const options = { method, headers }

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    options.body = JSON.stringify(body)
  }
  if (token) headers.Authorization = `Bearer ${token}`

  const response = await fetch(`${BASE}${path}`, options)

  const data = response.status === 204 ? null : await response.json()

  if (!response.ok) throw new ApiError(response.status, data)

  return data
}
