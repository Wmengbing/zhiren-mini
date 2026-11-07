const { request, toastError } = require('../../utils/api')
Page({
  data: { productId: 0, productName: '', name: '', phone: '', message: '', consent: false, submitting: false },
  onLoad(options) { this.setData({ productId: Number(options.product_id || 0), productName: decodeURIComponent(options.product_name || '') }) },
  nameInput(event) { this.setData({ name: event.detail.value }) },
  phoneInput(event) { this.setData({ phone: event.detail.value }) },
  messageInput(event) { this.setData({ message: event.detail.value }) },
  consentChange(event) { this.setData({ consent: event.detail.value.length > 0 }) },
  showPrivacy() {
    wx.showModal({
      title: '联系信息使用说明',
      content: '江西志人不锈钢制品有限公司将收集您填写的称呼、手机号及需求，用于业务联系与咨询跟进。信息保存在公司后台，由获授权人员处理。您可通过“联系我们”中的电话或邮箱申请查询、更正或删除。本说明版本：2026-11-07。',
      showCancel: false,
      confirmText: '我已阅读',
    })
  },
  async submit() {
    const { name, phone, message, productId, consent, submitting } = this.data
    if (submitting) return
    if (name.trim().length < 2) return wx.showToast({ title: '请填写称呼', icon: 'none' })
    if (!/^1[3-9]\d{9}$/.test(phone)) return wx.showToast({ title: '请填写有效手机号', icon: 'none' })
    if (message.trim().length < 5) return wx.showToast({ title: '请填写至少 5 个字的需求', icon: 'none' })
    if (!consent) return wx.showToast({ title: '请先同意提交联系信息', icon: 'none' })
    this.setData({ submitting: true })
    try {
      await request('/inquiries', { method: 'POST', data: { name, phone, message, product_id: productId, consent: true, consent_version: '2026-11-07' } })
      wx.showToast({ title: '提交成功', icon: 'success' })
      setTimeout(() => wx.navigateBack({ delta: 1 }), 1200)
    } catch (error) { toastError(error) }
    finally { this.setData({ submitting: false }) }
  },
})
