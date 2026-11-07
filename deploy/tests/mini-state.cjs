const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')
const vm = require('node:vm')
const root = path.resolve(__dirname, '../..')
const flush = () => new Promise(resolve => setImmediate(resolve))

function pageFixture(name) {
  let page
  const calls = []
  const app = { globalData: {} }
  const sandbox = {
    getApp: () => app,
    wx: { stopPullDownRefresh() {}, navigateTo() {} },
    require: () => ({
      request: url => new Promise((resolve, reject) => calls.push({ url, resolve, reject })),
      toastError() {},
    }),
    Page: definition => { page = definition },
  }
  vm.runInNewContext(fs.readFileSync(path.join(root, 'miniprogram/pages', name, 'index.js'), 'utf8'), sandbox)
  page.data = JSON.parse(JSON.stringify(page.data))
  page.setData = values => Object.assign(page.data, values)
  return { page, calls, app }
}

async function main() {
  const products = pageFixture('products')
  products.page.onShow()
  products.calls.find(call => call.url === '/product/categories').resolve([])
  const first = products.calls.find(call => call.url.startsWith('/products?'))
  first.resolve({ items: [{ id: 1 }], total: 30 })
  await flush()
  assert.equal(products.page.data.page, 2)
  products.page.onShow()
  assert.equal(products.calls.filter(call => call.url.startsWith('/products?')).length, 1, 'returning preserves the product list')
  products.page.loadMore()
  const stale = products.calls.at(-1)
  products.app.globalData.productCategoryId = 2
  products.page.onShow()
  const latest = products.calls.at(-1)
  latest.resolve({ items: [{ id: 20 }], total: 1 })
  await flush()
  stale.resolve({ items: [{ id: 2 }], total: 30 })
  await flush()
  assert.equal(products.page.data.items[0].id, 20, 'stale products do not overwrite the selected category')
  assert.equal(products.page.data.selected, 2)

  const news = pageFixture('news')
  news.page.onLoad()
  const oldNews = news.calls[0]
  news.page.reload()
  news.calls[1].resolve({ items: [{ id: 10 }], total: 2 })
  await flush()
  oldNews.resolve({ items: [{ id: 1 }], total: 100 })
  await flush()
  assert.equal(news.page.data.items[0].id, 10)
  assert.equal(news.page.data.page, 2)
  news.page.loadMore()
  news.calls.at(-1).reject(new Error('timeout'))
  await flush()
  assert.equal(news.page.data.error, 'timeout')
  assert.equal(news.page.data.page, 2, 'failed requests retain the page to retry')

  const configCode = fs.readFileSync(path.join(root, 'miniprogram/config.js'), 'utf8')
  for (const version of ['release', 'trial', 'develop']) {
    const sandbox = { module: { exports: {} }, wx: { getAccountInfoSync: () => ({ miniProgram: { envVersion: version } }) }, require: () => { throw new Error('no local config') } }
    vm.runInNewContext(configCode, sandbox)
    assert.equal(sandbox.module.exports.apiBase, version === 'develop' ? 'http://127.0.0.1:8000' : 'https://zhirencasting.cc')
  }
  for (const override of ['http://127.0.0.1:8000', 'https://localhost', 'https://api.example.test/']) {
    const sandbox = { module: { exports: {} }, wx: { getAccountInfoSync: () => ({ miniProgram: { envVersion: 'release' } }) }, require: () => ({ productionApiBase: override }) }
    vm.runInNewContext(configCode, sandbox)
    assert.equal(sandbox.module.exports.apiBase, override === 'https://api.example.test/' ? 'https://api.example.test' : '')
  }
  let requestOptions
  const apiSandbox = { module: { exports: {} }, getApp: () => ({ globalData: { apiBase: 'https://example.test' } }), wx: { request: options => { requestOptions = options } } }
  vm.runInNewContext(fs.readFileSync(path.join(root, 'miniprogram/utils/api.js'), 'utf8'), apiSandbox)
  const response = apiSandbox.module.exports.request('/health')
  assert.equal(requestOptions.timeout, 15000)
  requestOptions.success({ statusCode: 200, data: { code: 0, data: { status: 'ok' } } })
  assert.equal((await response).status, 'ok')

  const appConfig = JSON.parse(fs.readFileSync(path.join(root, 'miniprogram/app.json'), 'utf8'))
  for (const page of appConfig.pages) {
    for (const extension of ['js', 'json', 'wxml', 'wxss']) assert.ok(fs.existsSync(path.join(root, 'miniprogram', page + '.' + extension)), page + '.' + extension)
    JSON.parse(fs.readFileSync(path.join(root, 'miniprogram', page + '.json'), 'utf8'))
  }
  console.log('PASS: mini program list state, stale requests, retry, environment, timeout and registered pages.')
}
main().catch(error => { console.error(error); process.exitCode = 1 })
