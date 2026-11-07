const { request, toastError } = require('../../utils/api')
Page({
  data: { item: null },
  onLoad(options) { this.id = Number(options.id); this.load() },
  async load() {
    try { const item = await request(`/articles/${this.id}`); this.setData({ item }); wx.setNavigationBarTitle({ title: item.title }) }
    catch (error) { toastError(error) }
  },
  copySource() {
    if (this.data.item?.source_url) wx.setClipboardData({ data: this.data.item.source_url })
  },
  onShareAppMessage() { return { title: this.data.item?.title || '志人资讯', path: `/pages/news-detail/index?id=${this.id}` } },
})
