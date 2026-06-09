# ShopWeb

一个简易商店系统。轻量自研版商城，基于 Laravel、Livewire 和 Filament 构建。

## 功能概览

- 前台：注册登录、商品浏览、购物车、结算下单、付款凭证上传、订单查询、物流查询、用户中心、客服会话、售后需求、公告、评论、搜索、论坛。
- 后台：商品/SKU/库存、订单/支付审核、采购、财务成本和利润、优惠码/折扣、前台用户、后台用户、内容页面、媒体库/资源库、论坛版块、站点设置、报告、缓存、安装向导。
- 交易：支持预售、概念筹款、进货中、现货、售罄状态；人工付款确认；国内/国际物流可见性控制。

## 本机运行

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

后台地址：`/admin`

## 测试

```bash
php artisan test
npm run build
```

## 部署

生产环境 Web 根目录必须指向 `public`。首次访问未安装项目时会进入 `/install` 网页安装向导。

## Releases 包一键启动

Releases 会提供两个预构建包：

- `shopweb-v{version}-windows.zip`
- `shopweb-v{version}-linux.zip`

两个包都内置 `vendor/` 和 `public/build/`，服务器无需 Composer/npm 构建。解压后仍需要 PHP 8.3+、MySQL/MariaDB 和对应 PHP 扩展。

Windows 本地验证：

```bat
start-windows.bat
```

Linux 本地验证：

```bash
chmod +x start-linux.sh
./start-linux.sh
```

脚本会创建必要运行目录，在缺少 `.env` 时从 `.env.example` 复制一份，然后启动 `http://127.0.0.1:8000`。首次安装请打开 `http://127.0.0.1:8000/install`。

这些脚本适合本地验证或临时启动。生产部署仍建议使用 Nginx/Apache/IIS，并把 Web 根目录指向 `public`。

更多细节见 [DEPLOY.md](DEPLOY.md) 和 [docs/requirements.md](docs/requirements.md)。
