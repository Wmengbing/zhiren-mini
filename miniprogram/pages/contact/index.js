const { request, toastError } = require('../../utils/api')
Page({
  data: { contact: {} },
  onShow() { this.load() },
  async load() { try { this.setData({ contact: await request('/contact') }) } catch (error) { toastError(error) } },
  call() { if (this.data.contact.phone) wx.makePhoneCall({ phoneNumber: this.data.contact.phone.replace(/[^\d+]/g, '') }) },
  copyContact(event) {
    const value = event.currentTarget.dataset.value
    if (value) wx.setClipboardData({ data: value })
  },
  map() {
    const { latitude, longitude, company_name, address } = this.data.contact
    if (latitude && longitude) wx.openLocation({ latitude: Number(latitude), longitude: Number(longitude), name: company_name, address })
    else if (address) wx.setClipboardData({ data: address })
  },
  inquire() { wx.navigateTo({ url: '/pages/inquiry/index' }) },
})
