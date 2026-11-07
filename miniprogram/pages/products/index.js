const { request, toastError } = require('../../utils/api')
Page({
  data: { categories: [], selected: 0, keyword: '', items: [], total: 0, page: 1, hasMore: true, loading: false, error: '' },
  onShow() {
    const pending = getApp().globalData.productCategoryId
    if (pending !== undefined) {
      const changed = Number(pending) !== this.data.selected || this.data.keyword !== ''
      this.setData({ selected: Number(pending), keyword: '' })
      delete getApp().globalData.productCategoryId
      if (changed && this.initialized) this.reload()
    }
    if (!this.initialized) {
      this.initialized = true
      this.loadCategories()
      this.reload()
    }
  },
  async loadCategories() { try { this.setData({ categories: await request('/product/categories') }) } catch (error) { toastError(error) } },
  selectCategory(event) { this.setData({ selected: Number(event.currentTarget.dataset.id) }); this.reload() },
  onKeyword(event) { this.setData({ keyword: event.detail.value }) },
  search() { this.reload() },
  async reload() {
    this.requestId = (this.requestId || 0) + 1
    this.setData({ items: [], total: 0, page: 1, hasMore: true, loading: false, error: '' })
    await this.loadMore()
  },
  async loadMore() {
    if (!this.data.hasMore || this.data.loading) return
    this.setData({ loading: true, error: '' })
    const requestId = this.requestId
    const { page, selected, keyword, items } = this.data
    try {
      const query = `?page=${page}&page_size=10&category_id=${selected}&keyword=${encodeURIComponent(keyword)}`
      const result = await request(`/products${query}`)
      if (requestId !== this.requestId) return
      const next = items.concat(result.items)
      this.setData({ items: next, total: result.total, page: page + 1, hasMore: next.length < result.total, loading: false })
    } catch (error) {
      if (requestId !== this.requestId) return
      this.setData({ loading: false, error: error.message })
      toastError(error)
    }
  },
  onReachBottom() { this.loadMore() },
  async onPullDownRefresh() { try { await Promise.all([this.loadCategories(), this.reload()]) } finally { wx.stopPullDownRefresh() } },
  onUnload() { this.requestId = (this.requestId || 0) + 1 },
  goDetail(event) { wx.navigateTo({ url: `/pages/product-detail/index?id=${event.currentTarget.dataset.id}` }) },
})
