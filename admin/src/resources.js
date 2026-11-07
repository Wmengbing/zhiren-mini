const field = (key, label, type = 'text', extra = {}) => ({ key, label, type, ...extra })
const common = [field('sort', '排序', 'number'), field('status', '状态', 'switch')]
export const resources = {
  banners: {
    title: '首页轮播',
    columns: ['title', 'image_url', 'status'],
    fields: [field('title', '标题', 'text', { required: true }), field('image_url', '轮播图片', 'image', { required: true }), field('link_type', '跳转类型', 'select', { options: [{ value: 'none', label: '不跳转' }, { value: 'product', label: '产品详情' }, { value: 'company', label: '公司介绍' }] }), field('link_value', '跳转值（产品 ID）'), ...common],
  },
  'company-pages': {
    title: '公司介绍',
    columns: ['title', 'slug', 'status'],
    fields: [field('title', '标题', 'text', { required: true }), field('slug', '页面标识', 'text', { required: true }), field('summary', '摘要', 'textarea'), field('content', '正文', 'textarea'), field('image_url', '配图', 'image'), ...common],
  },
  categories: {
    title: '产品分类',
    columns: ['name', 'sort', 'status'],
    fields: [field('name', '分类名称', 'text', { required: true }), field('description', '分类介绍', 'textarea'), field('image_url', '分类图片', 'image'), ...common],
  },
  products: {
    title: '产品管理',
    columns: ['title', 'category_id', 'is_featured', 'status'],
    fields: [field('category_id', '产品分类', 'category', { required: true }), field('title', '产品名称', 'text', { required: true }), field('subtitle', '副标题'), field('cover_url', '封面图片', 'image'), field('gallery_json', '产品多图（可调整顺序）', 'gallery'), field('summary', '产品简介', 'textarea'), field('features', '产品特点', 'textarea'), field('specifications', '规格参数（每行一项）', 'textarea'), field('application', '应用领域', 'textarea'), field('sort', '排序', 'number'), field('is_featured', '首页推荐', 'switch'), field('status', '上架状态', 'switch')],
  },
  articles: {
    title: '新闻资讯',
    columns: ['title', 'published_at', 'status'],
    fields: [field('title', '标题', 'text', { required: true }), field('summary', '摘要', 'textarea'), field('cover_url', '封面图片', 'image'), field('content', '正文', 'textarea'), field('published_at', '发布时间', 'datetime'), ...common],
  },
  settings: {
    title: '站点配置',
    columns: ['label', 'value'],
    fields: [field('value', '配置内容', 'textarea')],
    updateOnly: true,
  },
  inquiries: {
    title: '客户咨询',
    columns: ['name', 'phone', 'message', 'status', 'created_at'],
    fields: [field('status', '处理状态', 'select', { options: [{ value: 0, label: '待处理' }, { value: 1, label: '跟进中' }, { value: 2, label: '已完成' }] }), field('admin_note', '处理备注', 'textarea')],
    updateOnly: true,
  },
}

export const labels = {
  title: '标题', name: '姓名 / 名称', image_url: '图片', slug: '标识', sort: '排序', status: '状态',
  category_id: '分类', is_featured: '推荐', published_at: '发布时间', label: '配置项', value: '内容',
  phone: '电话', message: '咨询内容', created_at: '提交时间',
}
