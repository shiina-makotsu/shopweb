# ShopWeb 部署说明

## 服务器要求

- PHP 8.3+，启用 `pdo_mysql`、`openssl`、`fileinfo`、`mbstring`、`intl`、`zip`、`gd`。
- MySQL 8+ 或 MariaDB 10.6+。
- Web Server: Nginx、Apache 或 Windows IIS。
- Web 根目录必须指向解压目录下的 `public`，不要把 Laravel 根目录暴露为站点根目录。
- `.env`、`storage`、`bootstrap/cache`、`public/uploads` 必须可写。

## 解压安装

1. 上传对应平台包到服务器并解压：
   - Windows: `dist/shopweb-v{version}-windows.zip`
   - Linux: `dist/shopweb-v{version}-linux.zip`
2. 将站点根目录设置为 `{解压目录}/public`。
3. 访问站点地址，未安装时会自动进入 `/install`。
4. 在安装向导中填写 MySQL/MariaDB、站点信息和管理员账号。
5. 安装完成后系统会写入 `.env`、执行迁移、创建管理员、优化缓存，并创建 `storage/app/install.lock`。
6. 安装后 `/install` 会返回 404。需要重新安装时，删除 `storage/app/install.lock` 并在环境变量中显式设置 `SHOP_INSTALLER_ENABLED=true`。

## Web Server

Apache 使用包内 `public/.htaccess`。

Windows IIS 使用包内 `public/web.config`，服务器需要启用 URL Rewrite。

Nginx 可参考 `docs/nginx.conf.example`，核心配置是：

```nginx
root /var/www/shopweb/public;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

## CLI 备用安装

网页安装不可用时，可在解压目录运行：

```bash
php artisan shop:install \
  --db-host=127.0.0.1 \
  --db-port=3306 \
  --db-database=shopweb \
  --db-username=shopweb \
  --db-password=secret \
  --app-url=https://example.com \
  --site-name=ShopWeb \
  --admin-email=admin@example.com \
  --admin-password=change-this-password
```

## 一键启动脚本

发布包根目录内置临时启动脚本，适合本地验证和小规模测试：

Windows:

```bat
start-windows.bat
```

Linux:

```bash
chmod +x start-linux.sh
./start-linux.sh
```

脚本会：

- 检查 `php` 是否可用。
- 缺少 `.env` 时从 `.env.example` 复制。
- 创建 `storage`、`bootstrap/cache`、`public/uploads` 等必要运行目录。
- 启动 `php artisan serve --host=127.0.0.1 --port=8000`。

启动后访问：

- 首页：`http://127.0.0.1:8000`
- 首次安装：`http://127.0.0.1:8000/install`

这些脚本不是生产 Web Server 替代品。生产环境仍应使用 Nginx、Apache 或 IIS，并把 Web 根目录指向 `public`。

## 本地构建发布包

发布包包含 `vendor/` 和 `public/build/`，服务器无需 Composer/npm 构建。

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php scripts/build-release.php 1.0.0
```

当前工作区使用项目内便携 PHP 时：

```powershell
.\.tools\php\php.exe scripts\build-release.php 1.0.0
```

发布 ZIP 不包含 `.env`、`.git`、`node_modules`、本地 SQLite 数据库、本地日志、会话、测试缓存和开发截图。

## Upload troubleshooting

If Filament or Livewire shows an error like `failed to upload`, check the upload
limits and writable directories first.

```bash
cd /var/www/shopweb
sudo mkdir -p storage/app/private/livewire-tmp public/uploads
sudo chown -R www-data:www-data storage bootstrap/cache public/uploads
sudo chmod -R u+rwX,g+rwX storage bootstrap/cache public/uploads
```

For Nginx, make sure the site config contains:

```nginx
client_max_body_size 64M;
```

Then reload Nginx:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

For PHP-FPM, make sure `upload_max_filesize` and `post_max_size` are not smaller
than the files you upload. Example for PHP 8.4:

```bash
sudo nano /etc/php/8.4/fpm/php.ini
sudo systemctl restart php8.4-fpm
```

Recommended values:

```ini
upload_max_filesize = 64M
post_max_size = 64M
max_file_uploads = 20
```

After changing permissions or PHP/Nginx settings, clear Laravel caches:

```bash
php artisan optimize:clear
```

## Scheduled tasks

ShopWeb uses Laravel Scheduler for recurring maintenance. Production servers
should run the scheduler every minute; otherwise daily currency snapshots,
gold-price snapshots, recurring flash-sale session generation, and AI recycle
bin pruning will not run automatically.

Linux cron example:

```cron
* * * * * cd /var/www/shopweb && php artisan schedule:run >> /dev/null 2>&1
```

Current scheduled commands:

- `shop:currency-refresh`: refreshes international exchange rates and gold
  price once per day.
- `shop:flash-sale-sync`: generates upcoming flash-sale sessions from active
  one-time or recurring flash-sale campaigns.
- `shop:ai-trash-prune`: deletes expired AI gallery/chat items from recycle
  bins.

AI provider requests are sent from the Laravel server. On Windows or some
minimal Linux hosts, PHP/cURL may not have a CA bundle configured and can fail
with `cURL error 60`. ShopWeb enables native OS CA verification by default:

```ini
AI_HTTP_VERIFY_SSL=true
AI_HTTP_USE_NATIVE_CA=true
AI_HTTP_CA_BUNDLE=
AI_RESPONSES_IMAGE_MODEL=gpt-5.5
```

If your host does not expose native CA certificates, download a trusted
`cacert.pem` file and set `AI_HTTP_CA_BUNDLE=/absolute/path/to/cacert.pem` or a
project-relative path. Do not set `AI_HTTP_VERIFY_SSL=false` in production
unless you fully understand the risk to API keys.

The same HTTP certificate settings are also used by currency and gold snapshot
refreshes. If the currency page can update exchange rates but gold stays empty,
check PHP/cURL CA configuration first and run:

```bash
php artisan shop:currency-refresh --force
```

`AI_RESPONSES_IMAGE_MODEL` is only used when image generation falls back to the
Responses image tool. Keep Image API models such as `gpt-image-2` on the image
endpoint, and use a tool-capable model for the Responses endpoint.

## Domain and proxy troubleshooting

After pulling an update on an existing server, run migrations and clear caches:

```bash
php artisan migrate --force
php artisan optimize:clear
```

When the site is behind Cloudflare, another CDN, or a reverse proxy, configure
the proxy headers so Laravel can detect the original host and HTTPS scheme.
For a single-server deployment, `TRUSTED_PROXIES=*` is usually the simplest
setting. If you know the exact proxy IP ranges, you can set those instead.

For HTTPS domains, use:

```ini
SESSION_LIFETIME=43200
AUTH_REMEMBER_LIFETIME=43200
SESSION_SECURE_COOKIE=true
```

`SESSION_LIFETIME=43200` keeps normal web sessions for 30 days, and
`AUTH_REMEMBER_LIFETIME=43200` keeps the "remember me" cookie on the same
30-day window. Together they prevent active users and admins from being
redirected back to login after the old 120-minute default.

Leave `SESSION_DOMAIN` empty unless you need to share login state across
multiple subdomains. A wrong `SESSION_DOMAIN` can make `/admin/login` appear to
submit successfully while the browser never keeps the session cookie.

The application rewrites same-site absolute URLs to relative paths, including
Filament and Livewire update endpoints such as `/livewire-*/update`. If admin
buttons or save actions still look unresponsive on a domain, open the browser
Network panel and check whether the `/livewire-*/update` request returns 200,
302 back to login, 419, or is blocked by mixed-content/CORS rules. After
changing `.env` or proxy settings, always run:

```bash
php artisan optimize:clear
```
