# 志人小程序上线检查清单

项目目录：`/www/wwwroot/default/zhiren-mini`

目标域名：`https://zhirencasting.cc`

状态记录：2026-11-10，依据服务器终端输出；公网访问尚未验收。

## 已完成

- [x] 服务器已完成 Git 初次同步，`deploy/update.sh` 存在，Git 工作区无未提交改动。
- [x] PHP 依赖安装完成，数据库迁移执行成功。
- [x] 管理后台 `npm run build` 成功，`admin/dist/index.html` 存在。Vite 的资源体积提示不影响构建。
- [x] 数据库包含 7 个产品分类、116 个产品、7 篇文章。
- [x] `api/public/uploads` 约 411 MB；116 个产品封面地址均对应服务器上的文件。
- [x] 服务器本机 API 健康检查返回 `code: 0`，产品列表返回 `data.total: 116`。
- [x] 262 条素材相关记录已从本地地址改为 `https://zhirencasting.cc`；`api/.env` 的 `SITE.PUBLIC_BASE_URL` 已确认是 HTTPS 地址。

## 备案期间可完成

- [ ] 更新本次代码后运行 `php bin/normalize-media-paths.php` 预览；在宝塔完成数据库备份，再用 `--apply` 将本站素材地址一次性改为 `/uploads/...`。
- [ ] 确认宝塔站点运行目录是 `api/public`，项目根目录不能作为网站公开目录。
- [ ] 确认 PHP-FPM 能读取项目内的 `api/vendor` 与 `api/.env`，并能写入 `api/runtime` 和 `api/public/uploads`。
- [ ] 按 [Nginx 示例](nginx.conf.example)配置 `/api/v1/` 路由和 `/admin/` 静态文件映射；示例中的 PHP-FPM socket 是 PHP 8.2 路径，服务器使用 PHP 8.3 时必须按宝塔实际路径调整。不要直接覆盖宝塔整份站点配置。
- [ ] 从服务器本机检查 Nginx 路由、后台首页和静态资源，并确认 `.env`、`.git` 与上传目录中的脚本文件不能通过 HTTP 下载或执行。
- [ ] 核对管理员账号可以登录，并在上线前修改为仅管理员掌握的密码。
- [ ] 在宝塔设置数据库和素材备份、保留周期及异地副本。此项由站点管理员自行完成；本清单不执行备份操作。

## 备案完成后

- [ ] 解析域名到服务器，绑定宝塔站点，申请 HTTPS 证书并启用 HTTPS。
- [ ] 用正式域名检查 `/api/v1/health`、`/api/v1/products?page=1&page_size=1` 和 `/admin/`。
- [ ] 检查首页、产品列表与详情、图片、视频、资讯、联系信息和咨询提交；API 素材地址应为 HTTPS，不能出现 `127.0.0.1`。
- [ ] 在微信小程序后台配置正式域名，用真机验证首页、产品、图片、视频和咨询表单，然后上传体验版与正式版。

## 后续代码更新

本地提交并推送到 `main` 后，在服务器执行：

```bash
cd /www/wwwroot/default/zhiren-mini
git status --short
bash deploy/update.sh
```

`git status --short` 应为空。更新脚本会拉取代码、安装锁定依赖、执行迁移并重新构建后台；需要时在宝塔重载 PHP-FPM。`api/.env`、上传素材、`api/vendor` 和 `admin/dist` 不进入 Git。详细操作见 [宝塔部署说明](baota-setup.md)。
