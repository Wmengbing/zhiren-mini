const { request, toastError } = require('../../utils/api')
Page({
  data: { settings: {}, heroLines: ['精密铸造', '专注品质'], banners: [], categories: [], products: [], articles: [], loading: true },
  onShow() { this.load() },
  async load() {
    try {
      const result = await request('/home')
      const heroLines = String(result.settings.hero_title || '精密铸造 · 专注品质').split(/[·|｜\n]+/).map(line => line.trim()).filter(Boolean)
      this.setData({ ...result, heroLines, loading: false })
    }
    catch (error) { this.setData({ loading: false }); toastError(error) }
  },
  goCompany() { wx.navigateTo({ url: '/pages/company/index' }) },
  goProducts(event) {
    getApp().globalData.productCategoryId = Number(event?.currentTarget?.dataset?.id || 0)
    wx.switchTab({ url: '/pages/products/index' })
  },
  goInquiry() { wx.navigateTo({ url: '/pages/inquiry/index' }) },
  goNews() { wx.switchTab({ url: '/pages/news/index' }) },
  goProduct(event) { wx.navigateTo({ url: `/pages/product-detail/index?id=${event.currentTarget.dataset.id}` }) },
  goArticle(event) { wx.navigateTo({ url: `/pages/news-detail/index?id=${event.currentTarget.dataset.id}` }) },
  bannerTap(event) {
    const item = this.data.banners[event.currentTarget.dataset.index]
    if (item.link_type === 'product' && /^\d+$/.test(item.link_value)) wx.navigateTo({ url: `/pages/product-detail/index?id=${item.link_value}` })
    if (item.link_type === 'company') this.goCompany()
  },
})
