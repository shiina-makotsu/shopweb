# ShopWeb

一个简易商店系统。轻量自研版商城，基于 Laravel、Livewire 和 Filament 构建。

## 功能概览

- 前台：注册登录、商品浏览、商品标签页、购物车、结算下单、付款凭证上传、订单查询、物流查询、用户中心、客服会话、售后需求、公告、评论、搜索、论坛。
- 后台：商品/SKU/库存、订单/支付审核、采购、财务成本和利润、优惠码/折扣、前台用户、后台用户、内容页面、前台菜单、媒体库/资源库、论坛版块、站点设置、报告、缓存、安装向导。
- 交易：支持预售、概念筹款、进货中、现货、售罄状态；人工付款确认；国内/国际物流可见性控制。
- 内容与导航：支持自定义页面模板、404 页面、关于我们占位模板、传统 Markdown 编辑模式和交互式区块编辑模式；自定义页面发布时可选择同步添加到顶部导航或首页商店信息；前台菜单支持无页面上级菜单、二级菜单、树状层级展示和拖拽排序；默认提供“首页 > 标签”二级菜单，桌面端悬停显示二级浮窗，移动端以侧边栏菜单展示。
- AI 与社交：支持 AI 生图/聊天入口、前台用户 AI 配额记录、后台用户 AI 免配额调用并纳入总用量记录、论坛发帖模板、私聊和客服会话。

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

关于我们页面可参考 [docs/about-us.md](docs/about-us.md)，该文件是一份可复制粘贴的示例文案；项目内置的“关于我们模板”只提供占位结构，避免 clone 后默认使用示例站点介绍。
