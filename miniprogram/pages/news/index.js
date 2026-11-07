const { request, toastError } = require('../../utils/api')
Page({
  data: { items: [], page: 1, hasMore: true, loading: false, error: '' },
  onLoad() { this.reload() },
  async reload() {
    this.requestId = (this.requestId || 0) + 1
    this.setData({ items: [], page: 1, hasMore: true, loading: false, error: '' })
    await this.loadMore()
  },
  async loadMore() {
    if (!this.data.hasMore || this.data.loading) return
    this.setData({ loading: true, error: '' })
    const requestId = this.requestId
    const page = this.data.page
    try {
      const result = await request(`/articles?page=${page}&page_size=10`)
      if (requestId !== this.requestId) return
      const items = this.data.items.concat(result.items)
      this.setData({ items, page: page + 1, hasMore: items.length < result.total, loading: false })
    } catch (error) {
      if (requestId !== this.requestId) return
      this.setData({ loading: false, error: error.message }); toastError(error)
    }
  },
  onReachBottom() { this.loadMore() },
  async onPullDownRefresh() { try { await this.reload() } finally { wx.stopPullDownRefresh() } },
  onUnload() { this.requestId = (this.requestId || 0) + 1 },
  open(event) { wx.navigateTo({ url: `/pages/news-detail/index?id=${event.currentTarget.dataset.id}` }) },
})
