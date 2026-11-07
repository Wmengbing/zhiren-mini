<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { api, API_BASE } from './api'
import { labels, resources } from './resources'

const token = ref(sessionStorage.getItem('admin_token') || '')
const loginForm = reactive({ username: '', password: '' })
const current = ref('dashboard')
const loading = ref(false)
const saving = ref(false)
const rows = ref([])
const total = ref(0)
const page = ref(1)
const dashboard = ref({})
const categories = ref([])
const editor = ref(false)
const editingId = ref(null)
const form = reactive({})
const filters = reactive({ keyword: '', status: '', category_id: '' })
let loadVersion = 0
const securityOpen = ref(false)
const credentials = reactive({ current_password: '', new_password: '', confirm: '' })
const gallery = ref([])
const videos = ref([])
const isVideoSetting = computed(() => current.value === 'settings' && rows.value.find(row => row.id === editingId.value)?.key === 'production_videos')
const activeConfig = computed(() => resources[current.value])
const pageCount = computed(() => Math.max(1, Math.ceil(total.value / 10)))

function resetForm(item = null) {
  Object.keys(form).forEach(key => delete form[key])
  for (const field of activeConfig.value.fields) {
    form[field.key] = item?.[field.key] ?? (field.type === 'switch' ? 1 : field.type === 'number' || field.type === 'category' ? 0 : '')
  }
  if (current.value === 'articles' && !item) {
    const now = new Date()
    form.published_at = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 19).replace('T', ' ')
  }
}

async function login() {
  if (!loginForm.username || !loginForm.password) return ElMessage.warning('请输入账号和密码')
  try {
    const data = await api('/admin/login', { method: 'POST', body: JSON.stringify(loginForm) })
    sessionStorage.setItem('admin_token', data.token)
    token.value = data.token
    loginForm.password = ''
    await select('dashboard')
  } catch (error) { ElMessage.error(error.message) }
}

async function logout() {
  try { await api('/admin/logout', { method: 'POST' }) } catch { /* local logout still proceeds */ }
  sessionStorage.removeItem('admin_token')
  token.value = ''
  rows.value = []
  loadVersion++
}

async function select(key) {
  current.value = key
  rows.value = []
  total.value = 0
  page.value = 1
  editor.value = false
  Object.assign(filters, { keyword: '', status: '', category_id: '' })
  await load()
}

async function load() {
  const version = ++loadVersion
  const resource = current.value
  loading.value = true
  try {
    if (resource === 'dashboard') {
      const data = await api('/admin/dashboard')
      if (version === loadVersion) dashboard.value = data
    } else {
      const query = new URLSearchParams({ page: String(page.value), page_size: '10', ...filters })
      const [data, categoryData] = await Promise.all([
        api(`/admin/${resource}?${query}`),
        resource === 'products' ? api('/admin/categories?page_size=50') : Promise.resolve(null),
      ])
      if (version !== loadVersion) return
      rows.value = data.items
      total.value = data.total
      if (categoryData) categories.value = categoryData.items
    }
  } catch (error) { if (version === loadVersion) ElMessage.error(error.message) }
  finally { if (version === loadVersion) loading.value = false }
}

function search() { page.value = 1; load() }

function parseList(value) {
  try { const result = JSON.parse(value || '[]'); return Array.isArray(result) ? result : [] } catch { return [] }
}

function openEditor(item = null) {
  editingId.value = item?.id ?? null
  resetForm(item)
  gallery.value = parseList(item?.gallery_json)
  videos.value = item?.key === 'production_videos' ? parseList(item.value) : []
  editor.value = true
}

async function save() {
  for (const field of activeConfig.value.fields) {
    if (field.required && !form[field.key]) return ElMessage.warning(`请填写${field.label}`)
  }
  saving.value = true
  try {
    if (current.value === 'products') form.gallery_json = JSON.stringify(gallery.value.filter(Boolean))
    if (isVideoSetting.value) form.value = JSON.stringify(videos.value)
    const path = `/admin/${current.value}${editingId.value ? `/${editingId.value}` : ''}`
    await api(path, { method: editingId.value ? 'PUT' : 'POST', body: JSON.stringify(form) })
    ElMessage.success('保存成功')
    editor.value = false
    await load()
  } catch (error) { ElMessage.error(error.message) }
  finally { saving.value = false }
}

async function remove(item) {
  try {
    await ElMessageBox.confirm(`确定删除“${item.title || item.name}”？`, '删除确认', { type: 'warning' })
    await api(`/admin/${current.value}/${item.id}`, { method: 'DELETE' })
    ElMessage.success('删除成功')
    if (rows.value.length === 1 && page.value > 1) page.value--
    await load()
  } catch (error) { if (error !== 'cancel') ElMessage.error(error.message || '删除失败') }
}

async function upload(field, event) {
  const file = event.target.files?.[0]
  if (!file) return
  const body = new FormData()
  body.append('file', file)
  try {
    const result = await api('/admin/upload/image', { method: 'POST', body })
    if (field === 'gallery_json') gallery.value.push(result.url)
    else form[field] = result.url
    ElMessage.success('上传成功')
  } catch (error) { ElMessage.error(error.message) }
  event.target.value = ''
}

function categoryName(id) { return categories.value.find(item => Number(item.id) === Number(id))?.name || `#${id}` }
function cellValue(row, key) {
  if (key === 'category_id') return categoryName(row[key])
  if (key === 'status') return current.value === 'inquiries' ? ['待处理', '跟进中', '已完成'][row[key]] : (row[key] === 1 ? '启用' : '停用')
  if (key === 'is_featured') return row[key] ? '是' : '否'
  return row[key] ?? '—'
}
async function changePassword() {
  if (credentials.new_password !== credentials.confirm) return ElMessage.warning('两次新密码不一致')
  saving.value = true
  try {
    await api('/admin/security/password', { method: 'POST', body: JSON.stringify(credentials) })
    sessionStorage.removeItem('admin_token'); token.value = ''; loadVersion++
    securityOpen.value = false
    Object.assign(credentials, { current_password: '', new_password: '', confirm: '' })
    ElMessage.success('密码已修改，请重新登录')
  } catch (error) { ElMessage.error(error.message) }
  finally { saving.value = false }
}
async function revokeSessions() {
  try {
    await ElMessageBox.confirm('将注销包括当前设备在内的所有登录，是否继续？', '注销所有登录')
    await api('/admin/security/revoke-sessions', { method: 'POST' })
    sessionStorage.removeItem('admin_token'); token.value = ''; loadVersion++
  } catch (error) { if (error !== 'cancel' && error !== 'close') ElMessage.error(error.message) }
}
function imageSrc(path) { return path?.startsWith('/') ? `${API_BASE}${path}` : path }

window.addEventListener('admin-unauthorized', () => { token.value = ''; ElMessage.warning('登录已过期，请重新登录') })
onMounted(() => { if (token.value) load() })
</script>

<template>
  <div v-if="!token" class="login-page">
    <div class="login-card">
      <div class="brand-mark">ZR</div>
      <p class="eyebrow">JIANGXI ZHIREN</p>
      <h1>内容管理后台</h1>
      <p class="muted">江西志人不锈钢制品有限公司</p>
      <el-form @submit.prevent="login" class="login-form">
        <el-form-item><el-input v-model="loginForm.username" placeholder="管理员账号" size="large" autocomplete="username" /></el-form-item>
        <el-form-item><el-input v-model="loginForm.password" placeholder="登录密码" size="large" type="password" show-password autocomplete="current-password" /></el-form-item>
        <el-button type="primary" size="large" native-type="submit" class="full">登录后台</el-button>
      </el-form>
    </div>
  </div>
  <div v-else class="app-shell">
    <aside class="sidebar">
      <div class="sidebar-brand"><span class="brand-mark small">ZR</span><span><strong>志人制造</strong><small>CONTENT STUDIO</small></span></div>
      <div class="nav-label">工作台</div>
      <button :class="['nav-item', { active: current === 'dashboard' }]" @click="select('dashboard')">概览</button>
      <div class="nav-label">内容管理</div>
      <button v-for="(config, key) in resources" :key="key" :class="['nav-item', { active: current === key }]" @click="select(key)">{{ config.title }}</button>
      <button class="nav-item logout" @click="logout">退出登录</button>
      <button class="nav-item" @click="securityOpen = true">修改密码</button>
      <button class="nav-item" @click="revokeSessions">注销所有登录</button>
    </aside>
    <main class="main-area">
      <header class="topbar"><div><span class="eyebrow">ZHIREN / ADMIN</span><h1>{{ current === 'dashboard' ? '数据概览' : activeConfig.title }}</h1></div><span class="admin-pill">管理员</span></header>
      <section v-if="current === 'dashboard'" class="dashboard">
        <div class="welcome-card"><span class="eyebrow light">WELCOME BACK</span><h2>每一处细节，都是企业形象。</h2><p>在左侧菜单维护小程序内容。建议先完善公司联系方式、上传产品图片，再发布正式内容。</p></div>
        <div class="stats-grid"><button v-for="(value, key) in dashboard" :key="key" class="stat-card" @click="select(key === 'new_inquiries' ? 'inquiries' : key)"><span>{{ { products: '产品总数', categories: '产品分类', articles: '新闻资讯', new_inquiries: '待处理咨询' }[key] }}</span><strong>{{ value }}</strong><small>查看详情 →</small></button></div>
      </section>
      <section v-else class="content-card" v-loading="loading">
        <div class="section-heading"><div><h2>{{ activeConfig.title }}</h2><p>共 {{ total }} 条记录</p></div><el-button v-if="!activeConfig.updateOnly" type="primary" @click="openEditor()">＋ 新增{{ activeConfig.title }}</el-button></div>
        <el-form inline class="list-filters" @submit.prevent="search">
          <el-form-item><el-input v-model="filters.keyword" placeholder="搜索名称或内容" clearable @clear="search" /></el-form-item>
          <el-form-item v-if="current !== 'settings'"><el-select v-model="filters.status" placeholder="全部状态" clearable style="width:130px" @change="search"><el-option :value="'0'" :label="current === 'inquiries' ? '待处理' : '停用'" /><el-option :value="'1'" :label="current === 'inquiries' ? '跟进中' : '启用'" /><el-option v-if="current === 'inquiries'" value="2" label="已完成" /></el-select></el-form-item>
          <el-form-item v-if="current === 'products'"><el-select v-model="filters.category_id" placeholder="全部分类" clearable style="width:170px" @change="search"><el-option v-for="category in categories" :key="category.id" :value="String(category.id)" :label="category.name" /></el-select></el-form-item>
          <el-form-item><el-button native-type="submit" type="primary">搜索</el-button></el-form-item>
        </el-form>
        <el-table :data="rows" stripe empty-text="暂无内容" class="data-table">
          <el-table-column prop="id" label="ID" width="70" />
          <el-table-column v-for="key in activeConfig.columns" :key="key" :label="labels[key] || key" min-width="130" show-overflow-tooltip>
            <template #default="scope"><img v-if="key === 'image_url' && scope.row[key]" :src="imageSrc(scope.row[key])" class="table-image" alt="" /><span v-else>{{ cellValue(scope.row, key) }}</span></template>
          </el-table-column>
          <el-table-column label="操作" width="150" fixed="right"><template #default="scope"><el-button link type="primary" @click="openEditor(scope.row)">编辑</el-button><el-button v-if="!activeConfig.updateOnly" link type="danger" @click="remove(scope.row)">删除</el-button></template></el-table-column>
        </el-table>
        <div class="pagination"><el-pagination v-model:current-page="page" :page-count="pageCount" layout="prev, pager, next" @current-change="load" /></div>
      </section>
    </main>
    <el-drawer v-model="editor" :title="editingId ? '编辑内容' : '新增内容'" size="min(560px, 100%)" destroy-on-close>
      <el-form label-position="top" @submit.prevent="save" class="edit-form">
        <el-form-item v-for="field in activeConfig.fields" :key="field.key" :label="field.label" :required="field.required">
          <div v-if="field.key === 'value' && isVideoSetting" class="media-editor">
            <div v-for="(video, index) in videos" :key="index" class="media-entry"><el-input v-model="video.title" placeholder="视频标题" /><el-input v-model="video.url" placeholder="视频 HTTPS 地址" /><el-input v-model="video.poster" placeholder="封面图片地址（可选）" /><el-button type="danger" link @click="videos.splice(index, 1)">删除该视频</el-button></div>
            <el-button :disabled="videos.length >= 30" @click="videos.push({ title: '', url: '', poster: '' })">添加视频</el-button>
          </div>
          <div v-else-if="field.type === 'gallery'" class="media-editor">
            <div v-for="(url, index) in gallery" :key="index" class="media-entry"><img v-if="url" :src="imageSrc(url)" class="image-preview" alt="产品图" /><el-input v-model="gallery[index]" placeholder="图片地址" /><el-button link type="danger" @click="gallery.splice(index, 1)">删除</el-button><el-button v-if="index > 0" link @click="[gallery[index - 1], gallery[index]] = [gallery[index], gallery[index - 1]]">上移</el-button></div>
            <el-button :disabled="gallery.length >= 20" @click="gallery.push('')">添加图片地址</el-button><label v-if="gallery.length < 20" class="upload-button">上传图片<input type="file" accept="image/jpeg,image/png,image/webp" hidden @change="upload('gallery_json', $event)" /></label>
          </div>
          <el-switch v-else-if="field.type === 'switch'" v-model="form[field.key]" :active-value="1" :inactive-value="0" />
          <el-input-number v-else-if="field.type === 'number'" v-model="form[field.key]" :min="0" controls-position="right" />
          <el-select v-else-if="field.type === 'select'" v-model="form[field.key]" class="full"><el-option v-for="option in field.options" :key="option.value" :value="option.value" :label="option.label" /></el-select>
          <el-select v-else-if="field.type === 'category'" v-model="form[field.key]" class="full" placeholder="选择分类"><el-option v-for="category in categories" :key="category.id" :value="category.id" :label="category.name" /></el-select>
          <el-date-picker v-else-if="field.type === 'datetime'" v-model="form[field.key]" type="datetime" value-format="YYYY-MM-DD HH:mm:ss" class="full" />
          <div v-else-if="field.type === 'image'" class="image-field"><el-input v-model="form[field.key]" placeholder="图片 URL 或上传文件" /><label class="upload-button">上传图片<input type="file" accept="image/jpeg,image/png,image/webp" hidden @change="upload(field.key, $event)" /></label><img v-if="form[field.key]" :src="imageSrc(form[field.key])" class="image-preview" alt="预览" /></div>
          <el-input v-else-if="field.type === 'textarea'" v-model="form[field.key]" type="textarea" :rows="field.key === 'content' ? 8 : 4" />
          <el-input v-else v-model="form[field.key]" />
        </el-form-item>
        <div class="drawer-actions"><el-button @click="editor = false">取消</el-button><el-button type="primary" native-type="submit" :loading="saving">保存</el-button></div>
      </el-form>
    </el-drawer>
    <el-dialog v-model="securityOpen" title="修改管理员密码" width="min(440px, 95%)" @closed="Object.assign(credentials, { current_password: '', new_password: '', confirm: '' })">
      <el-form label-position="top" @submit.prevent="changePassword"><el-form-item label="当前密码"><el-input v-model="credentials.current_password" type="password" show-password autocomplete="current-password" /></el-form-item><el-form-item label="新密码（12-72 字节）"><el-input v-model="credentials.new_password" type="password" show-password autocomplete="new-password" /></el-form-item><el-form-item label="确认新密码"><el-input v-model="credentials.confirm" type="password" show-password autocomplete="new-password" /></el-form-item><el-button type="primary" native-type="submit" :loading="saving">保存并重新登录</el-button></el-form>
    </el-dialog>
  </div>
</template>
