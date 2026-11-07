# 本次优化验证（2026-11-07）

- 封堵本机 phpstudy 80 端口对 /zhiren-mini/ 的直接访问；.env、composer.json 和路径大小写变体实测为 404。
- 项目专用数据库账户密码已轮换，并同步更新本机 api/.env。
- 30 项 API 集成断言通过，覆盖鉴权、登录限流、修改密码与会话撤销、筛选、多图、字段长度、日期、分类停用和咨询同意校验。
- 小程序列表保留、旧请求隔离、刷新、失败重试、环境选择、超时和页面文件完整性检查通过。
- 微信原生编译器检查：8 个 WXML 页面、9 个 WXSS 文件通过。
- 管理后台生产构建通过。构建产物保留本机 admin/dist，不进入 Git。
- 单独临时数据库中验证备份还原和域名替换成功，116 个产品的名称与分类完全一致。该临时库已删除，业务库未做域名替换。
- 首页图片从 1,810,414 字节降为移动端版本 314,073 字节，原图保留。
- 首页 JSON 约 8 KB，列表只返回展示需要的字段；原有视频 Range 分段请求仍正常。
- Git 排除 .env、数据库备份、运行缓存、依赖目录、上传素材及本机小程序配置。上线素材在 deploy/private/ 单独迁移。

## 本地回归命令

~~~bash
node deploy/tests/mini-state.cjs
cd admin
npm ci --no-audit --no-fund
npm run build
cd ../api
php bin/migrate.php
php tests/smoke.php
~~~

smoke.php 只允许连接 localhost/127.0.0.1，创建临时管理员、分类、产品和文章并在 finally 中清理，同时重置用于测试的回环地址登录限流计数。仅在开发或专用集成环境执行。
备份不含有效登录会话；restore-database.php 拒绝覆盖非空数据库。

## 仍需复核

依赖锁定在现有 ThinkPHP 6.0.16 兼容版本，Composer 审计仍有两项告警，不能将本次优化视为所有依赖风险已清零：

- [ThinkPHP 异常模板 XSS 告警](https://github.com/advisories/GHSA-969f-v7jv-pgj3)：应用已使用 JSON 异常处理且关闭 debug，当前接口不渲染框架 HTML 异常模板；仍需持续关注上游修复。
- [Flysystem 路径规范化告警](https://github.com/advisories/GHSA-cxf4-7mrp-vvpr)：当前上传使用随机文件名和 MIME 白名单，不向该组件传入用户路径。

npm audit 本次报告为 0 项。上述结果是本次扫描与实际使用路径检查，不能替代上线环境的复核。
没有在真实 Linux 服务器或微信真机上验收；需要正式域名和服务器环境后完成。
