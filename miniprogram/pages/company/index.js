const { request, toastError } = require('../../utils/api')
Page({
  data: { settings: {}, pages: [], videos: [], expandedPageId: 0, videosOpen: false, activeVideo: 0 },
  onLoad() { this.load() },
  async load() {
    try { this.setData(await request('/company')) } catch (error) { toastError(error) }
  },
  toggleSection(event) {
    const id = Number(event.currentTarget.dataset.id)
    this.setData({ expandedPageId: this.data.expandedPageId === id ? 0 : id })
  },
  previewPhoto(event) {
    const url = event.currentTarget.dataset.url
    if (url) wx.previewImage({ current: url, urls: [url] })
  },
  toggleVideos() { this.setData({ videosOpen: !this.data.videosOpen }) },
  selectVideo(event) { this.setData({ activeVideo: Number(event.currentTarget.dataset.index) }) },
})
