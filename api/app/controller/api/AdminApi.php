<?php
namespace app\controller\api;

use app\support\MediaUrl;
use think\facade\Db;
use think\Response;

class AdminApi extends ApiBase
{
    private const RESOURCES = [
        'banners' => ['table' => 'banner', 'fields' => ['title', 'image_url', 'link_type', 'link_value', 'sort', 'status']],
        'company-pages' => ['table' => 'company_page', 'fields' => ['title', 'slug', 'summary', 'content', 'image_url', 'sort', 'status']],
        'categories' => ['table' => 'product_category', 'fields' => ['name', 'description', 'image_url', 'sort', 'status']],
        'products' => ['table' => 'product', 'fields' => ['category_id', 'title', 'subtitle', 'cover_url', 'gallery_json', 'summary', 'features', 'specifications', 'application', 'sort', 'is_featured', 'status']],
        'articles' => ['table' => 'article', 'fields' => ['title', 'summary', 'cover_url', 'content', 'published_at', 'sort', 'status']],
        'settings' => ['table' => 'site_setting', 'fields' => ['value']],
        'inquiries' => ['table' => 'inquiry', 'fields' => ['status', 'admin_note']],
    ];

    private function admin(): ?array
    {
        $header = (string) $this->request->header('Authorization', '');
        if (!preg_match('/^Bearer ([a-f0-9]{64})$/i', $header, $matches)) return null;
        $tokenHash = hash('sha256', strtolower($matches[1]));
        $session = Db::name('admin_session')->where('token_hash', $tokenHash)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))->find();
        if (!$session) return null;
        return Db::name('admin')->where('id', $session['admin_id'])->where('status', 1)->find() ?: null;
    }

    private function resource(string $resource): ?array
    {
        return self::RESOURCES[$resource] ?? null;
    }

    private function authError(): Response
    {
        return $this->fail('请先登录管理后台', 401, 401);
    }

    public function login(): Response
    {
        $input = $this->input();
        if (!is_string($input['username'] ?? null) || !is_string($input['password'] ?? null)) return $this->fail('账号或密码格式不正确');
        $username = trim((string) ($input['username'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        if ($username === '' || $password === '') return $this->fail('请输入账号和密码');
        if (strlen($username) > 100 || strlen($password) > 1024) return $this->fail('账号或密码格式不正确');
        // Reserve attempts atomically, so concurrent requests cannot bypass the limit.
        $pairBucket = hash('sha256', 'pair:' . $this->request->ip() . ':' . strtolower($username));
        foreach ([hash('sha256', 'ip:' . $this->request->ip()) => 40, $pairBucket => 5] as $bucket => $limit) {
            $table = (string) env('DATABASE.PREFIX', '') . 'admin_login_limit';
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) throw new \RuntimeException('Invalid table name');
            $now = time();
            Db::execute("INSERT INTO " . $table . " (bucket,attempts,reset_at) VALUES (?,1,?)
                ON DUPLICATE KEY UPDATE attempts=IF(reset_at<=?,1,attempts+1),reset_at=IF(reset_at<=?, ?, reset_at)",
                [$bucket, $now + 900, $now, $now, $now + 900]);
            if ((int) Db::name('admin_login_limit')->where('bucket', $bucket)->value('attempts') > $limit) {
                return $this->fail('登录尝试过多，请 15 分钟后重试', 429, 429);
            }
        }
        return Db::transaction(function () use ($username, $password, $pairBucket): Response {
            $admin = Db::name('admin')->where('username', $username)->where('status', 1)->lock(true)->find();
            if (!$admin || !password_verify($password, $admin['password_hash'])) {
                return $this->fail('账号或密码错误', 401, 401);
            }
            Db::name('admin_login_limit')->where('bucket', $pairBucket)->delete();
            Db::name('admin_login_limit')->where('reset_at', '<', time() - 86400)->delete();
            Db::name('admin_session')->where('expires_at', '<', date('Y-m-d H:i:s'))->delete();
            $token = bin2hex(random_bytes(32));
            Db::name('admin_session')->insert([
                'admin_id' => $admin['id'], 'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + 7 * 86400),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            Db::name('admin')->where('id', $admin['id'])->update(['last_login_at' => date('Y-m-d H:i:s')]);
            return $this->ok(['token' => $token, 'username' => $admin['username'], 'expires_in' => 7 * 86400]);
        });
    }

    public function logout(): Response
    {
        if (!$this->admin()) return $this->authError();
        $header = (string) $this->request->header('Authorization', '');
        Db::name('admin_session')->where('token_hash', hash('sha256', strtolower(substr($header, 7))))->delete();
        return $this->ok(null);
    }

    public function me(): Response
    {
        $admin = $this->admin();
        return $admin ? $this->ok(['id' => $admin['id'], 'username' => $admin['username']]) : $this->authError();
    }

    public function password(): Response
    {
        $admin = $this->admin();
        if (!$admin) return $this->authError();
        $input = $this->input();
        if (!is_string($input['current_password'] ?? null) || !is_string($input['new_password'] ?? null)) return $this->fail('密码格式不正确');
        $old = (string) ($input['current_password'] ?? '');
        $new = (string) ($input['new_password'] ?? '');
        if (!password_verify($old, $admin['password_hash'])) return $this->fail('当前密码不正确');
        if (strlen($new) < 12 || strlen($new) > 72) return $this->fail('新密码需为 12-72 字节');
        if ($old === $new) return $this->fail('新密码不能与原密码相同');
        $changed = Db::transaction(function () use ($admin, $old, $new): bool {
            // Lock the administrator while changing credentials.
            $current = Db::name('admin')->where('id', $admin['id'])->lock(true)->find();
            if (!$current || !password_verify($old, $current['password_hash'])) return false;
            Db::name('admin')->where('id', $admin['id'])->update(['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
            Db::name('admin_session')->where('admin_id', $admin['id'])->delete();
            return true;
        });
        if (!$changed) return $this->fail('当前密码已变更，请重新登录', 401, 401);
        return $this->ok(null, '密码已修改，请重新登录');
    }

    public function revokeSessions(): Response
    {
        $admin = $this->admin();
        if (!$admin) return $this->authError();
        Db::name('admin_session')->where('admin_id', $admin['id'])->delete();
        return $this->ok(null, '所有登录已注销');
    }

    public function dashboard(): Response
    {
        if (!$this->admin()) return $this->authError();
        return $this->ok([
            'products' => Db::name('product')->count(),
            'categories' => Db::name('product_category')->count(),
            'articles' => Db::name('article')->count(),
            'new_inquiries' => Db::name('inquiry')->where('status', 0)->count(),
        ]);
    }

    public function index(string $resource): Response
    {
        if (!$this->admin()) return $this->authError();
        $config = $this->resource($resource);
        if (!$config) return $this->fail('资源不存在', 404);
        [$page, $size] = $this->page();
        $query = Db::name($config['table']);
        $keyword = trim((string) $this->request->get('keyword', ''));
        $searchFields = ['products' => 'title|subtitle|summary|application', 'articles' => 'title|summary',
            'categories' => 'name|description', 'company-pages' => 'title|summary',
            'banners' => 'title', 'settings' => 'label|key', 'inquiries' => 'name|phone|message'];
        if ($keyword !== '') $query->whereLike($searchFields[$resource], '%' . addcslashes(mb_substr($keyword, 0, 50), '%_\\') . '%');
        $status = (string) $this->request->get('status', '');
        if ($status !== '' && $resource !== 'settings') {
            if (!in_array($status, $resource === 'inquiries' ? ['0','1','2'] : ['0','1'], true)) return $this->fail('状态不正确');
            $query->where('status', (int) $status);
        }
        if ($resource === 'products' && (int) $this->request->get('category_id', 0) > 0) $query->where('category_id', (int) $this->request->get('category_id'));
        $total = (clone $query)->count();
        $order = $resource === 'settings' ? 'id asc' : 'id desc';
        $items = $query->order($order)->page($page, $size)->select()->toArray();
        if ($resource === 'products') {
            $sourceRows = Db::name('official_content_source')->where('resource', 'product')->whereIn('record_id', array_column($items, 'id') ?: [0])->select()->toArray();
            $galleries = [];
            foreach ($sourceRows as $row) $galleries[$row['record_id']] = (json_decode($row['metadata'], true) ?: [])['gallery'] ?? [];
            foreach ($items as &$item) {
                if ($item['gallery_json'] === null) $item['gallery_json'] = json_encode($galleries[$item['id']] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            unset($item);
        }
        return $this->ok(['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $size]);
    }

    public function show(string $resource, int $id): Response
    {
        if (!$this->admin()) return $this->authError();
        $config = $this->resource($resource);
        if (!$config) return $this->fail('资源不存在', 404);
        $item = Db::name($config['table'])->where('id', $id)->find();
        return $item ? $this->ok($item) : $this->fail('记录不存在', 404);
    }

    private function payload(string $resource, array $source): array
    {
        $fields = self::RESOURCES[$resource]['fields'];
        $data = array_intersect_key($source, array_flip($fields));
        foreach (['sort', 'status', 'is_featured', 'category_id'] as $integerField) {
            if (array_key_exists($integerField, $data) && is_scalar($data[$integerField])) $data[$integerField] = (int) $data[$integerField];
        }
        foreach ($data as $key => $value) {
            if (is_string($value)) $data[$key] = trim($value);
        }
        return $data;
    }

    private function normalizeMediaPayload(string $resource, array $data, ?array $record = null): array
    {
        $baseUrl = (string) env('SITE.PUBLIC_BASE_URL', $this->request->domain());
        foreach (['image_url', 'cover_url'] as $field) {
            if (isset($data[$field])) $data[$field] = MediaUrl::forStorage($data[$field], $baseUrl);
        }
        if ($resource === 'products' && isset($data['gallery_json'])) {
            $gallery = json_decode($data['gallery_json'], true);
            $data['gallery_json'] = json_encode(MediaUrl::normalize($gallery, $baseUrl), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        if ($resource === 'settings' && isset($data['value'])) {
            $key = $record['key'] ?? '';
            if (in_array($key, ['logo_url', 'hero_image_url', 'company_image_url', 'wechat_qr'], true)) {
                $data['value'] = MediaUrl::forStorage($data['value'], $baseUrl);
            } elseif ($key === 'production_videos') {
                $videos = json_decode($data['value'], true);
                $data['value'] = json_encode(MediaUrl::normalize($videos, $baseUrl), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }
        }
        return $data;
    }

    private function validationError(string $resource, array $data, bool $creating): ?string
    {
        foreach ($data as $key => $value) {
            if (!is_string($value) && !is_int($value)) return $key . '格式不正确';
        }
        if ($creating && in_array($resource, ['settings', 'inquiries'], true)) return '该资源不能新增';
        if (in_array($resource, ['products', 'articles', 'company-pages', 'banners'], true)
            && ($creating || array_key_exists('title', $data)) && empty($data['title'])) return '标题不能为空';
        if ($resource === 'categories' && ($creating || array_key_exists('name', $data)) && empty($data['name'])) return '分类名称不能为空';
        if ($resource === 'banners' && ($creating || array_key_exists('image_url', $data)) && empty($data['image_url'])) return '请上传轮播图片';
        if ($resource === 'products' && isset($data['category_id'])
            && !Db::name('product_category')->where('id', $data['category_id'])->find()) return '请选择有效的产品分类';
        if ($resource === 'products' && $creating && empty($data['category_id'])) return '请选择产品分类';
        if ($resource === 'company-pages' && ($creating || array_key_exists('slug', $data)) && empty($data['slug'])) return '页面标识不能为空';
        if ($resource === 'company-pages' && isset($data['slug']) && !preg_match('/^[a-z0-9-]{2,60}$/', $data['slug'])) return '页面标识只能包含小写字母、数字和连字符';
        if ($resource === 'inquiries' && isset($data['status']) && !in_array($data['status'], [0, 1, 2], true)) return '状态不正确';
        foreach (['status', 'is_featured'] as $flag) {
            if (isset($data[$flag]) && $resource !== 'inquiries' && !in_array($data[$flag], [0, 1], true)) return '状态不正确';
        }
        foreach ($data as $key => $value) {
            if (!is_string($value) && !is_int($value)) return $key . '格式不正确';
            $limits = ['title' => in_array($resource, ['banners', 'company-pages'], true) ? 120 : 160,
                'name' => 100, 'subtitle' => 255, 'slug' => 60, 'description' => 500,
                'image_url' => 500, 'cover_url' => 500, 'link_type' => 30, 'link_value' => 255,
                'admin_note' => 1000, 'published_at' => 19];
            if ($key === 'summary' && $resource !== 'products') $limits[$key] = 500;
            if (is_string($value) && (mb_strlen($value) > ($limits[$key] ?? 10000) || strlen($value) > 60000)) return $key . '内容过长';
            if (in_array($key, ['image_url', 'cover_url'], true) && $value !== '' && !$this->mediaUrl($value)) return '图片地址需为 HTTP(S) 地址或 /uploads/ 路径';
            if (in_array($key, ['sort', 'category_id'], true) && ($value < 0 || $value > 2147483647)) return $key . '超出允许范围';
        }
        if (isset($data['published_at'])) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $data['published_at']);
            if (!$date || $date->format('Y-m-d H:i:s') !== $data['published_at']) return '发布时间格式不正确';
        }
        if (isset($data['gallery_json'])) {
            $images = json_decode($data['gallery_json'], true);
            if (!is_array($images) || !array_is_list($images) || count($images) > 20) return '产品图片最多 20 张';
            foreach ($images as $url) if (!is_string($url) || strlen($url) > 500 || !$this->mediaUrl($url)) return '产品图片地址不正确';
        }
        if ($resource === 'banners' && isset($data['link_type']) && !in_array($data['link_type'], ['none', 'product', 'company'], true)) return '跳转类型不正确';
        return null;
    }

    private function mediaUrl(string $url): bool
    {
        return preg_match('#^/uploads/[a-zA-Z0-9/_\.\-]+$#', $url) === 1
            || (filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?: ''), ['http', 'https'], true));
    }

    private function settingError(array $record, array $data): ?string
    {
        if ($record['key'] === 'production_videos' && isset($data['value'])) {
            $videos = json_decode($data['value'], true);
            if (!is_array($videos) || !array_is_list($videos) || count($videos) > 30) return '视频列表格式不正确，最多 30 段';
            foreach ($videos as $video) {
                if (!is_array($video) || !is_string($video['title'] ?? null) || mb_strlen($video['title']) > 120
                    || !is_string($video['url'] ?? null) || !$this->mediaUrl($video['url'])
                    || !is_string($video['poster'] ?? '') || (($video['poster'] ?? '') !== '' && !$this->mediaUrl($video['poster']))) return '请填写有效的视频标题、地址和封面';
            }
        }
        return null;
    }

    public function create(string $resource): Response
    {
        if (!$this->admin()) return $this->authError();
        $config = $this->resource($resource);
        if (!$config) return $this->fail('资源不存在', 404);
        $data = $this->payload($resource, $this->input());
        if ($error = $this->validationError($resource, $data, true)) return $this->fail($error);
        $data = $this->normalizeMediaPayload($resource, $data);
        $defaults = [
            'banners' => ['image_url' => '', 'link_type' => 'none', 'link_value' => '', 'sort' => 0, 'status' => 1],
            'company-pages' => ['summary' => '', 'content' => '', 'image_url' => '', 'sort' => 0, 'status' => 1],
            'categories' => ['description' => '', 'image_url' => '', 'sort' => 0, 'status' => 1],
            'products' => ['subtitle' => '', 'cover_url' => '', 'summary' => '', 'features' => '', 'specifications' => '', 'application' => '', 'sort' => 0, 'is_featured' => 0, 'status' => 1],
            'articles' => ['summary' => '', 'cover_url' => '', 'content' => '', 'sort' => 0, 'status' => 1],
        ];
        $data = array_merge($defaults[$resource], $data);
        if ($resource === 'articles' && empty($data['published_at'])) $data['published_at'] = date('Y-m-d H:i:s');
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;
        $id = Db::name($config['table'])->insertGetId($data);
        return $this->ok(['id' => $id], '创建成功');
    }

    public function update(string $resource, int $id): Response
    {
        if (!$this->admin()) return $this->authError();
        $config = $this->resource($resource);
        if (!$config) return $this->fail('资源不存在', 404);
        $query = Db::name($config['table'])->where('id', $id);
        $record = (clone $query)->find();
        if (!$record) return $this->fail('记录不存在', 404);
        $data = $this->payload($resource, $this->input());
        if (!$data) return $this->fail('没有可更新的字段');
        if ($error = $this->validationError($resource, $data, false)) return $this->fail($error);
        if ($resource === 'settings' && ($error = $this->settingError($record, $data))) return $this->fail($error);
        $data = $this->normalizeMediaPayload($resource, $data, $record);
        if ($resource !== 'settings' && $resource !== 'inquiries') $data['updated_at'] = date('Y-m-d H:i:s');
        $query->update($data);
        return $this->ok(null, '保存成功');
    }

    public function delete(string $resource, int $id): Response
    {
        if (!$this->admin()) return $this->authError();
        if (in_array($resource, ['settings', 'inquiries'], true)) return $this->fail('该资源不能删除');
        $config = $this->resource($resource);
        if (!$config) return $this->fail('资源不存在', 404);
        if ($resource === 'categories' && Db::name('product')->where('category_id', $id)->count()) return $this->fail('该分类仍有产品，不能删除');
        $deleted = Db::name($config['table'])->where('id', $id)->delete();
        return $deleted ? $this->ok(null, '删除成功') : $this->fail('记录不存在', 404);
    }

    public function upload(): Response
    {
        if (!$this->admin()) return $this->authError();
        $file = $this->request->file('file');
        if (!$file || !$file->isValid()) return $this->fail('请选择图片');
        $path = $file->getRealPath();
        if (filesize($path) > 5 * 1024 * 1024) return $this->fail('图片不能超过 5MB');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) return $this->fail('仅支持 JPG、PNG、WebP 图片');
        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $relativePath = 'images/' . date('Y/m') . '/' . $filename;
        if (env('SITE.UPLOAD_DRIVER', 'local') === 'cos') {
            $secretId = (string) env('COS.SECRET_ID', '');
            $secretKey = (string) env('COS.SECRET_KEY', '');
            $region = (string) env('COS.REGION', '');
            $bucket = (string) env('COS.BUCKET', '');
            if (!$secretId || !$secretKey || !$region || !$bucket) return $this->fail('COS 配置不完整', 500);
            $stream = fopen($path, 'rb');
            try {
                $client = new \Qcloud\Cos\Client([
                    'region' => $region, 'scheme' => 'https',
                    'credentials' => ['secretId' => $secretId, 'secretKey' => $secretKey],
                ]);
                $client->putObject(['Bucket' => $bucket, 'Key' => $relativePath, 'Body' => $stream, 'ContentType' => $mime]);
            } catch (\Throwable $error) {
                return $this->fail('图片上传失败，请检查 COS 配置', 500);
            } finally {
                if (is_resource($stream)) fclose($stream);
            }
            $baseUrl = (string) env('COS.BASE_URL', '');
            if (!$baseUrl) $baseUrl = 'https://' . $bucket . '.cos.' . $region . '.myqcloud.com';
            return $this->ok(['url' => rtrim($baseUrl, '/') . '/' . $relativePath]);
        }
        $directory = dirname(__DIR__, 3) . '/public/uploads/' . date('Y/m');
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) return $this->fail('图片目录创建失败', 500);
        $file->move($directory, $filename);
        return $this->ok(['url' => '/uploads/' . date('Y/m') . '/' . $filename]);
    }
}
