const config = require('./config')
App({
  globalData: {
    // 开发时可用局域网地址；发布前改为已配置小程序 request 合法域名的 HTTPS 地址。
    apiBase: config.apiBase,
  },
})
