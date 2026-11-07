const { request, toastError } = require('../../utils/api')
Page({
  data: { item: null, galleryIndex: 0 },
  onLoad(options) { this.id = Number(options.id); this.load() },
  async load() {
    try { const item = await request(`/products/${this.id}`); this.setData({ item }); wx.setNavigationBarTitle({ title: item.title }) }
    catch (error) { toastError(error) }
  },
  inquire() { wx.navigateTo({ url: `/pages/inquiry/index?product_id=${this.id}&product_name=${encodeURIComponent(this.data.item.title)}` }) },
  galleryChange(event) { this.setData({ galleryIndex: event.detail.current }) },
  previewImages() {
    const urls = this.data.item.gallery || []
    if (urls.length) wx.previewImage({ current: urls[this.data.galleryIndex] || urls[0], urls })
  },
  onShareAppMessage() { return { title: this.data.item?.title || '志人产品', path: `/pages/product-detail/index?id=${this.id}` } },
})
