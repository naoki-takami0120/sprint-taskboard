const BASE = '/api'

let unauthorizedHandler = null

export function setUnauthorizedHandler(handler) {
  unauthorizedHandler = handler
}

export class ApiError extends Error {
  constructor(status, body) {
    super(body?.message ?? `HTTP ${status}`)
    this.status = status
    this.body = body
  }
}

export async function request(
  path,
  { method = 'GET', body, token, handleUnauthorized = true } = {},
) {
  const headers = { Accept: 'application/json' }
  const options = { method, headers }

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    options.body = JSON.stringify(body)
  }
  if (token) headers.Authorization = `Bearer ${token}`

  let response
  try {
    response = await fetch(`${BASE}${path}`, options)
  } catch {
    throw new ApiError(0, { message: 'Network error. Check your connection and try again.' })
  }

  const data = response.status === 204 ? null : await response.json().catch(() => null)

  if (!response.ok) {
    if (response.status === 401 && token && handleUnauthorized) {
      unauthorizedHandler?.()
    }
    throw new ApiError(response.status, data)
  }

  return data
}
