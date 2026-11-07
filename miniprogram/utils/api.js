function request(path, options = {}) {
  return new Promise((resolve, reject) => {
    const base = getApp().globalData.apiBase
    if (!base) return reject(new Error('请配置正式服务域名'))
    wx.request({
      url: `${getApp().globalData.apiBase}/api/v1${path}`,
      method: options.method || 'GET',
      data: options.data || {},
      header: { 'content-type': 'application/json' },
      timeout: 15000,
      success(res) {
        if (res.statusCode >= 200 && res.statusCode < 300 && res.data && res.data.code === 0) {
          resolve(res.data.data)
        } else {
          reject(new Error(res.data?.message || `请求失败（${res.statusCode}）`))
        }
      },
      fail(error) { reject(new Error(error.errMsg?.includes('timeout') ? '请求超时，请重试' : '网络连接失败，请稍后重试')) },
    })
  })
}

function toastError(error) { wx.showToast({ title: error.message || '加载失败', icon: 'none' }) }
module.exports = { request, toastError }
