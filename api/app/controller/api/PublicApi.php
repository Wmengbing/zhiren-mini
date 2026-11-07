<?php
namespace app\controller\api;

use think\facade\Db;
use think\Response;

class PublicApi extends ApiBase
{
    public function health(): Response
    {
        return $this->ok(['status' => 'ok']);
    }

    private function settings(): array
    {
        return Db::name('site_setting')->column('value', 'key');
    }

    private function preview(string $url, string $variant = 'card'): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        if (!str_starts_with($path, '/uploads/')) return $url;
        $relative = '/uploads/optimized/' . $variant . '-' . substr(hash('sha256', $path), 0, 24) . '.jpg';
        if (!is_file(dirname(__DIR__, 3) . '/public' . $relative)) return $url;
        return rtrim((string) env('SITE.PUBLIC_BASE_URL', $this->request->domain()), '/') . $relative;
    }

    private function cards(array $items): array
    {
        foreach ($items as &$item) $item['thumbnail_url'] = $this->preview($item['cover_url'] ?? '');
        return $items;
    }

    private function publishedProducts()
    {
        return Db::name('product')->where('status', 1)
            ->whereIn('category_id', Db::name('product_category')->where('status', 1)->column('id') ?: [0]);
    }

    private const PRODUCT_CARD = 'id,category_id,title,subtitle,cover_url,summary';
    private const ARTICLE_CARD = 'id,title,summary,cover_url,published_at';

    private function source(string $resource, int $id): array
    {
        $source = Db::name('official_content_source')->where('resource', $resource)->where('record_id', $id)->find();
        if (!$source) return [];
        return ['url' => $source['source_url'], 'metadata' => json_decode($source['metadata'], true) ?: []];
    }

    public function home(): Response
    {
        $settings = $this->settings();
        unset($settings['production_videos']);
        $settings['hero_image_mobile_url'] = $this->preview($settings['hero_image_url'] ?? '', 'hero');
        $banners = Db::name('banner')->where('status', 1)->order('sort desc,id desc')->select()->toArray();
        foreach ($banners as &$banner) $banner['image_url'] = $this->preview($banner['image_url'], 'hero');
        unset($banner);
        return $this->ok([
            'settings' => $settings,
            'banners' => $banners,
            'categories' => Db::name('product_category')->where('status', 1)->order('sort desc,id asc')->select()->toArray(),
            'products' => $this->cards($this->publishedProducts()->field(self::PRODUCT_CARD)->where('is_featured', 1)->order('sort desc,id desc')->limit(6)->select()->toArray()),
            'articles' => $this->cards(Db::name('article')->field(self::ARTICLE_CARD)->where('status', 1)->where('published_at', '<=', date('Y-m-d H:i:s'))->order('published_at desc,id desc')->limit(3)->select()->toArray()),
        ]);
    }

    public function company(): Response
    {
        $settings = $this->settings();
        $videos = json_decode($settings['production_videos'] ?? '[]', true);
        unset($settings['production_videos']);
        $settings['company_image_mobile_url'] = $this->preview($settings['company_image_url'] ?? '', 'hero');
        return $this->ok([
            'settings' => $settings,
            'pages' => Db::name('company_page')->where('status', 1)->order('sort desc,id asc')->select()->toArray(),
            'videos' => is_array($videos) ? $videos : [],
        ]);
    }

    public function categories(): Response
    {
        return $this->ok(Db::name('product_category')->where('status', 1)->order('sort desc,id asc')->select()->toArray());
    }

    public function products(): Response
    {
        [$page, $size] = $this->page();
        $query = $this->publishedProducts();
        $categoryId = max(0, (int) $this->request->get('category_id', 0));
        $keyword = trim((string) $this->request->get('keyword', ''));
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }
        if ($keyword !== '') {
            $query->whereLike('title|subtitle|summary|features|application', '%' . addcslashes(mb_substr($keyword, 0, 50), '%_\\') . '%');
        }
        $total = (clone $query)->count();
        $items = $query->field(self::PRODUCT_CARD)->order('sort desc,id desc')->page($page, $size)->select()->toArray();
        return $this->ok(['items' => $this->cards($items), 'total' => $total, 'page' => $page, 'page_size' => $size]);
    }

    public function product(int $id): Response
    {
        $item = $this->publishedProducts()->where('id', $id)->find();
        if (!$item) return $this->fail('产品不存在', 404);
        $item['category'] = Db::name('product_category')->where('id', $item['category_id'])->value('name') ?: '';
        $source = $this->source('product', $id);
        $gallery = $item['gallery_json'] !== null ? (json_decode($item['gallery_json'], true) ?: []) : ($source['metadata']['gallery'] ?? []);
        $item['gallery'] = array_values(array_unique(array_filter(array_merge([$item['cover_url']], $gallery))));
        unset($item['gallery_json']);
        $item['source_url'] = $source['url'] ?? '';
        $item['name_note'] = ($source['metadata']['display_name'] ?? '') === $item['title'] ? ($source['metadata']['display_name_note'] ?? '') : '';
        return $this->ok($item);
    }

    public function articles(): Response
    {
        [$page, $size] = $this->page();
        $query = Db::name('article')->where('status', 1)->where('published_at', '<=', date('Y-m-d H:i:s'));
        $total = (clone $query)->count();
        $items = $query->field(self::ARTICLE_CARD)->order('published_at desc,id desc')->page($page, $size)->select()->toArray();
        return $this->ok(['items' => $this->cards($items), 'total' => $total, 'page' => $page, 'page_size' => $size]);
    }

    public function article(int $id): Response
    {
        $item = Db::name('article')->where('id', $id)->where('status', 1)->where('published_at', '<=', date('Y-m-d H:i:s'))->find();
        if ($item) $item['source_url'] = $this->source('article', $id)['url'] ?? '';
        return $item ? $this->ok($item) : $this->fail('文章不存在', 404);
    }

    public function contact(): Response
    {
        $settings = $this->settings();
        return $this->ok(array_intersect_key($settings, array_flip([
            'company_name', 'phone', 'email', 'alternate_email', 'contact_person', 'whatsapp', 'website', 'address', 'registered_address', 'work_hours', 'latitude', 'longitude', 'wechat_qr',
        ])));
    }

    public function inquiry(): Response
    {
        $data = $this->input();
        if (($data['consent'] ?? false) !== true || ($data['consent_version'] ?? '') !== '2026-11-07') return $this->fail('请阅读并同意联系信息使用说明');
        foreach (['name', 'phone', 'message'] as $field) {
            if (!is_string($data[$field] ?? null)) return $this->fail('咨询信息格式不正确');
        }
        if (isset($data['product_id']) && !is_scalar($data['product_id'])) return $this->fail('产品编号格式不正确');
        $name = trim((string) ($data['name'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        $productId = max(0, (int) ($data['product_id'] ?? 0));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 50) return $this->fail('姓名需为 2-50 个字符');
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) return $this->fail('请输入有效的中国大陆手机号');
        if (mb_strlen($message) < 5 || mb_strlen($message) > 1000) return $this->fail('咨询内容需为 5-1000 个字符');
        if ($productId && !Db::name('product')->where('id', $productId)->where('status', 1)->find()) return $this->fail('产品不存在');
        $ip = (string) $this->request->ip();
        $recent = Db::name('inquiry')->where('ip_hash', hash('sha256', $ip))->where('created_at', '>', date('Y-m-d H:i:s', time() - 60))->count();
        if ($recent >= 3) return $this->fail('提交过于频繁，请稍后再试', 429);
        Db::name('inquiry')->insert([
            'name' => $name, 'phone' => $phone, 'message' => $message,
            'product_id' => $productId ?: null, 'status' => 0,
            'ip_hash' => hash('sha256', $ip), 'created_at' => date('Y-m-d H:i:s'),
            'consent_version' => '2026-11-07', 'consented_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->ok(null, '提交成功，我们会尽快联系您');
    }
}
