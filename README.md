# 江西志人不锈钢制品有限公司 · 企业展示小程序

项目包含 ThinkPHP 6 API、Vue 3 + Element Plus 内容管理后台、微信原生小程序。当前展示内容已按公司新官网 `www.jxzrcasting.com` 更新：8 个零件分类、92 款产品、15 篇技术指南中文摘要、9 个公司资料栏目和 9 段生产视频。

Linux / 宝塔上线请按 [部署说明](deploy/baota-setup.md) 操作。Git 只传代码，数据库与上传素材使用独立迁移文件。
本地后台为 http://127.0.0.1:8000/admin/ ，线上后台为 https://zhirencasting.cc/admin/ （服务器部署后可用）。当前 phpstudy 的 80 端口已禁止直接访问项目源码目录。
首次上线使用 api/.env.production.example；已有服务器后续可执行 bash deploy/update.sh 更新代码、迁移数据库并构建后台。

## 项目结构

```text
api/          ThinkPHP 6 接口与 MySQL 建表数据
admin/        Vue 3 管理后台
miniprogram/  微信原生小程序
deploy/       Nginx 配置示例
```

## 官网资料与导入

当前资料来源为 [公司新官网](https://www.jxzrcasting.com/)，整理日期为 2026 年 11 月 11 日。按官网现行目录导入 8 个零件系列和 92 款产品；官网站点地图中另外 16 个旧产品网址返回 410，未导入。产品图片为官网部件示意图，尺寸、公差、最终材质和供货范围仍需按客户图纸确认。15 篇英文技术指南在小程序中展示中文摘要，并可复制官网原文链接。

- 新官网快照：`api/database/jxzrcasting/manifest.json`、`article-digests.php` 和 `assets/`。素材随 Git 提交，导入时复制到 `api/public/uploads/jxzrcasting/`；数据库保存 `/uploads/...` 相对路径。
- 本地重新采集：在 `api` 目录运行 `python bin/collect-jxzrcasting.py --refresh`，需要 Python 的 `requests` 与 `beautifulsoup4`。重新采集会更新快照，需重新审核后再提交；服务器部署不需要 Python 或访问源网站。
- 导入预览：`php bin/import-jxzrcasting.php`。先在宝塔或本地备份数据库，再运行 `php bin/import-jxzrcasting.php --apply`。可重复执行，按来源更新新官网记录；会覆盖这些记录在后台的后续人工修改。
- 导入时保留客户咨询及其关联的旧产品；关联的旧产品下架归档，旧官网其他产品从展示目录移除。旧官网生产视频与证书原图仍在新官网展示，因此继续使用已有本地文件。

### 旧官网历史资料

以下为旧官网 `www.zhirencasting.com` 的历史导入资料，仅供追溯。当前内容更新请使用上面的新官网脚本，不要重新运行旧导入脚本：

- 内容定义：`api/database/official-content.php`。
- 产品展示名称：`api/database/official-site/product-display-names.json`，按官网说明和图片可见结构整理；不是对图纸型号或最终用途的确认。原始官网标题仍保存在来源记录中。
- 产品与素材来源快照：`api/database/official-site/`。
- 正式图片和视频：`api/public/uploads/official-site/`，部署时需一起复制，此目录不在 Git 跟踪范围内。
- 导入前内容备份：`api/runtime/official-backups/`。
- 数据来源与产品多图关联：MySQL 表 `official_content_source`。

在 `api` 目录执行 `php bin/import-official.php --dry-run` 可检查资料和导入计划；执行 `php bin/import-official.php` 导入。重复执行会按来源更新已导入记录，保留其他手动新增记录，但会覆盖已导入记录上的后续人工修改；每次导入都会先备份。本站素材在数据库中保存为 `/uploads/...` 相对路径，公开接口按 `SITE.PUBLIC_BASE_URL` 生成完整地址；正式环境应配置 HTTPS。以后更换域名只需修改该配置，无需重新导入官网资料或批量改库。

已有数据库只需迁移一次：先备份，再执行 `php bin/normalize-media-paths.php` 预览，确认后执行 `php bin/normalize-media-paths.php --apply`。脚本只转换本机确实存在的 `/uploads/` 文件，外部图片和官网来源地址保持原样。

小程序公司介绍支持折叠查看资料、点击资质原图放大，以及切换9段生产视频；产品详情支持官网产品多图。当前本地启动入口支持视频分段读取，便于拖动播放进度。导入正式资料后，演示数据脚本默认禁止再次写入测试内容。

如只需应用整理后的产品展示名称，在 `api` 目录执行 `php bin/apply-product-display-names.php`。该命令先备份原名称，只修改名称与命名说明，保留分类、图片、参数及其他内容，并跳过已手动改成其他名称的产品。在后台修改为公司确认的正式品名后，详情页会自动隐藏原结构描述说明。

## 运行要求

- PHP 8.1+，开启 `pdo_mysql`、`mbstring`、`fileinfo`、`curl`、`openssl` 扩展
- Composer 2
- MySQL 5.7+ 或 8.x
- Node.js 18+ 与 npm（管理后台构建）
- 微信开发者工具与企业小程序 AppID（真机和发布时）

## 1. 建库与 API

先创建专用 MySQL 用户和 UTF-8（`utf8mb4`）数据库，选中该数据库后导入 [`api/database/schema.sql`](api/database/schema.sql)。SQL 中含公司介绍与产品分类初始数据，建议仅在新库执行一次。

在 `api` 目录执行：

```bash
composer install
cp .env.example .env
# 编辑 .env 中的数据库地址、账号、密码、PUBLIC_BASE_URL
php bin/migrate.php
php bin/create-admin.php admin
php -S 127.0.0.1:8000 -t public public/index.php
```

Windows PowerShell 可使用 `Copy-Item .env.example .env`；导入 SQL 可在 MySQL 客户端执行 `USE 你的数据库名;`，再执行 `source C:/完整路径/api/database/schema.sql`。

在本机 Windows 环境中，项目根目录可运行 `.\install-api.cmd` 安装 PHP 依赖。脚本会为 Composer 临时启用 PHP `zip` 扩展，避免压缩包下载失败后回退到 Git。

本地调试可在项目根目录运行 `.\start-local.cmd`，然后访问 `http://127.0.0.1:8000/admin/` 使用已编译的管理后台，API 仍位于同一端口的 `/api/v1/`。该命令仅用于本机开发。

访问 `http://127.0.0.1:8000/api/v1/health`，应返回 `{"code":0,"data":{"status":"ok"}}`。生产环境的 Web 根目录必须指向 `api/public`，并把 `APP_DEBUG` 设为 `false`。

## 2. 管理后台

在 `admin` 目录执行：

```bash
npm install
npm run dev
```

浏览器打开 `http://127.0.0.1:5173`，用刚创建的管理员账号登录。开发服务器自动将 `/api` 代理到 `http://127.0.0.1:8000`。如 API 地址不同，修改 `admin/vite.config.js` 的代理目标。

发布时运行 `npm run build`，将 `admin/dist` 放在与 API 同域名的 `/admin/` 路径；可参考 [`deploy/nginx.conf.example`](deploy/nginx.conf.example)。后台功能包括轮播、公司介绍、产品分类、产品、资讯、站点配置、客户咨询和图片上传。

## 3. 小程序

用微信开发者工具导入 `miniprogram` 文件夹，核对 `project.config.json` 中的企业小程序 AppID。

小程序体验版和正式版默认连接 `https://zhirencasting.cc`，开发版默认连接 `http://127.0.0.1:8000`。需要覆盖时，复制 `miniprogram/config.local.example.js` 为 `miniprogram/config.local.js`，分别设置 developmentApiBase 和 productionApiBase。本机覆盖配置不进入 Git。发布前在微信小程序后台配置正式域名并开启合法域名校验。

小程序入口：主页、产品中心、资讯、联系；另有公司介绍、产品详情、资讯详情和合作咨询页。产品与资讯未录入时显示空状态。

## 图片存储

开发环境默认为本地上传，文件保存在 `api/public/uploads`。正式环境可将 `.env` 中的 `SITE.UPLOAD_DRIVER` 改为 `cos`，填写腾讯云 COS 的 SecretId、SecretKey、Region、Bucket、可选 Base URL。服务端通过 COS PHP SDK 上传，密钥不会发到小程序。COS 存储桶需要允许图片通过配置的域名读取。

## 接口约定

公开接口使用 `/api/v1` 前缀：`home`、`company`、`product/categories`、`products`、`products/:id`、`articles`、`articles/:id`、`contact`、`inquiries`。管理接口使用 `/api/v1/admin` 前缀，登录后请求携带 `Authorization: Bearer <token>`。统一返回 `code`、`message`、`data`；列表返回 `items`、`total`、`page`、`page_size`。

## 发布前检查

1. 在后台核对已从官网导入的联系方式、Logo、轮播、产品图片和公司介绍，补充官网未公开的工作时间及具体产品参数。
2. 配置正式 MySQL 账号、HTTPS 域名、小程序 AppID 与微信合法域名；如启用 COS，配置密钥与读权限。
3. 设置 `APP_DEBUG=false`，确保 `api/.env`、`api/vendor`、`api/runtime` 不在 Web 可访问目录中。
4. 使用真实设备测试产品浏览、图片加载、电话/地图与咨询提交。
5. 执行 `composer audit` 和 `npm audit`，评估第三方依赖公告。当前依赖审计报告 ThinkPHP 6.0.16 异常页 XSS 公告和 Flysystem 路径规范化公告；本项目使用专用 JSON 异常处理器，且未向 Flysystem 传入用户路径，正式上线仍需复核。
6. 在微信小程序平台完善隐私保护指引，说明咨询表单收集称呼、手机号和需求的用途。

当前版本的正文和规格字段为纯文本多行内容；后续如需图文混排，可升级编辑器与小程序详情渲染。第一版未包含微信登录、商城支付、产品收藏和在线客服。
