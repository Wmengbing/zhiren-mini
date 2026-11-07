export const API_BASE = import.meta.env.VITE_API_BASE || ''

export async function api(path, options = {}) {
  const token = sessionStorage.getItem('admin_token')
  const headers = { ...(options.headers || {}) }
  if (token) headers.Authorization = `Bearer ${token}`
  if (options.body && !(options.body instanceof FormData)) headers['Content-Type'] = 'application/json'
  const response = await fetch(`${API_BASE}/api/v1${path}`, { ...options, headers, signal: options.signal || AbortSignal.timeout(20000) })
  let result
  try { result = await response.json() } catch { throw new Error('服务器响应格式错误') }
  if (!response.ok || result.code !== 0) {
    if (response.status === 401 && path !== '/admin/login') {
      sessionStorage.removeItem('admin_token')
      window.dispatchEvent(new Event('admin-unauthorized'))
    }
    throw new Error(result.message || '请求失败')
  }
  return result.data
}
