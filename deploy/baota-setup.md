# Linux / 宝塔部署

本地后台：http://127.0.0.1:8000/admin/ 。线上后台：https://你的API域名/admin/ 。
Git 仓库：https://github.com/Wmengbing/wmb 。代码、数据库和上传素材需要分别迁移。

## 1. 拉取代码与配置

建议 PHP 8.2、MySQL 5.7 或 8.x、Node.js 22、Composer 2、Nginx。
PHP 需要 pdo_mysql、mbstring、fileinfo、curl、openssl、zlib；启用 proc_open 供 Composer 使用。
在宝塔添加 API 网站，部署目录例如 /www/wwwroot/zhiren-mini。
网站“运行目录”必须设置为 /api/public，并将项目目录加入 PHP open_basedir，保证 vendor 与 .env 可被 PHP 读取。
禁止将项目根目录设为可公开下载的站点目录。

~~~bash
git clone https://github.com/Wmengbing/wmb.git /www/wwwroot/zhiren-mini
cd /www/wwwroot/zhiren-mini
cp api/.env.example api/.env
chmod 600 api/.env
# 编辑 .env，填写服务器数据库账号、密码和 SITE.PUBLIC_BASE_URL=https://你的API域名
# APP_DEBUG 和 APP_TRACE 保持 false
cd api
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
~~~

在宝塔创建专用数据库账号，权限限于项目数据库。
API 与后台同域名：/api/v1/ 为接口，/admin/ 为后台。
HTTPS 证书由宝塔申请，参考 nginx.conf.example 设置路由、后台静态目录与安全规则。
示例中的路径、域名、证书和 PHP-FPM socket 均需替换；不要直接覆盖宝塔整份网站配置。

## 2. 导入当前真实业务数据

本地 deploy/private/ 文件不进入 Git。将数据库备份与 uploads.tar.gz 单独通过 SFTP 上传。
备份含管理员密码哈希和可能的客户信息，请放到网站运行目录之外。
首次还原请使用**空数据库**，不要先执行 schema.sql，也不要先跑 migrate.php。

~~~bash
cd /www/wwwroot/zhiren-mini/api
php bin/restore-database.php /安全目录/database.json.gz
php bin/migrate.php
cd ..
tar -xzf /安全目录/uploads.tar.gz -C api/public
chown -R www:www api/runtime api/public/uploads
chmod -R u=rwX,g=rX,o= api/runtime api/public/uploads
~~~

restore-database.php 会拒绝覆盖已有数据库；已有库请备份并另建空库恢复。
只恢复来自本项目工具的可信备份文件。会话与登录失败计数不会迁移。
schema.sql 用于从零安装；本次上线应还原实际备份，保留公司资料、116 款产品及现有编辑。
完成后替换旧的本地素材地址，不会重导官网或覆盖产品名称：

~~~bash
cd /www/wwwroot/zhiren-mini/api
php bin/backup-database.php
php bin/rebase-media.php --to=https://你的API域名
# 检查预览数量后应用：
php bin/rebase-media.php --to=https://你的API域名 --apply
~~~

从其他域名迁移时增加 --from=https://旧域名。不要再次运行官网导入脚本来迁移域名，以免覆盖后台改动。

## 3. 构建后台与小程序

~~~bash
cd /www/wwwroot/zhiren-mini/admin
npm ci --no-audit --no-fund
npm run build
~~~

Nginx 将 /admin/ 映射到 admin/dist/。修改源码后需要重新 build；日常打开后台不需要编译。
保留 composer.lock、package-lock.json，以相同依赖版本部署。
小程序在开发电脑上传发布，不在 Linux 服务器运行。
复制 miniprogram/config.local.example.js 为 miniprogram/config.local.js，
设置 productionApiBase 为正式 HTTPS 域名，开发版单独设置 developmentApiBase。
正式版和体验版不会回退到本地地址；未配置会提示。
在微信后台配置实际使用的合法域名，发布前开启开发工具“校验合法域名”，真机验证图片、视频、详情和咨询表单。

## 4. 备份和更新

在宝塔计划任务中每天执行：

~~~bash
BACKUP_DIR=/www/backup/zhiren-mini /bin/bash /www/wwwroot/zhiren-mini/deploy/backup.sh
~~~

备份目录应只允许备份管理人员读取，并定期复制到另一台机器/对象存储。脚本不自动删除旧备份，请按磁盘容量制定保留策略。
恢复演练使用单独的空数据库。
更新前先备份，再 git pull --ff-only、composer install、php bin/migrate.php、npm ci、npm run build。
如果启用 PHP OPcache，更新后在宝塔重载 PHP-FPM。
生产入口使用 Nginx 与 PHP-FPM，不运行 start-local.cmd 或 php -S。

## 5. 验收

- /api/v1/health 正常，首页、产品分页、搜索与咨询提交正常。
- /.env、/api/.env、/.git/config、/composer.json 均不可下载。
- 后台登录、修改密码、注销所有登录、产品多图及视频维护正常。
- 视频拖动播放正常，原始素材与 optimized 缩略图均已迁移。
- API 响应中的素材 URL 无 127.0.0.1 或 localhost。
- 换用独立数据库密码，并在后台修改管理员密码。

原始大视频保留。当前播放为用户展开并点击后加载；访问量增加时可转码并迁移到 COS/CDN。
