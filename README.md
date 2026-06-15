# ShopWeb

ShopWeb 是一个轻量级自托管商城系统，基于 Laravel、Filament、Livewire 和 Vite 构建。它把商品交易、内容页面、论坛社交、客服会话、AI 生图/聊天、仓库采购和后台运营管理放在同一个项目里，适合小型站点或私有化商城继续二次开发。

## 技术栈

- 后端：PHP 8.3+、Laravel 13、Livewire 4
- 后台：Filament 5
- 前端构建：Vite、Tailwind CSS
- 测试：Pest / PHPUnit
- 数据库：开发环境可用 SQLite，生产环境建议 MySQL 或 MariaDB
- License：GPL-3.0-or-later

## 快速开始

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

常用入口：

- 前台首页：`http://127.0.0.1:8000`
- 后台面板：`http://127.0.0.1:8000/admin`
- 安装向导：`http://127.0.0.1:8000/install`

本地 seed 会创建一个后台管理员账号：

- 邮箱：`admin@example.com`
- 密码：`password`

生产环境部署后请立即修改默认密码。

## 项目目录

| 目录/文件 | 作用 |
| --- | --- |
| `app/Models` | 业务模型：商品、订单、用户、优惠码、页面、论坛、客服、AI 用量、仓库库存等 |
| `app/Http/Controllers` | 前台页面和接口控制器：商品、购物车、订单、客服、论坛、AI、自定义页面等 |
| `app/Filament` | 后台管理面板资源、页面和权限入口 |
| `app/Services` | 业务服务，例如购物车、日志、论坛活动、成本与库存相关处理 |
| `app/Support` | 项目内公共辅助类：Markdown、安全 URL、金额、页面模板、菜单同步、相对路径处理等 |
| `database/migrations` | 数据库结构变更 |
| `database/seeders` | 初始化数据，例如站点配置、后台管理员、演示商品、默认菜单 |
| `resources/views` | Blade 前台页面、后台自定义视图、邮件模板和组件 |
| `resources/css` / `resources/js` | 前端样式和脚本入口 |
| `routes/web.php` | 前台、用户、客服、论坛、AI、订单和后台导出等 Web 路由 |
| `tests/Feature` | 主要功能回归测试 |
| `docs` | 需求说明、部署示例、关于我们示例文案 |
| `scripts/build-release.php` | Release 包构建脚本 |
| `DEPLOY.md` | 生产部署说明 |

## 功能模块

### 前台商城

- 商品首页、商品列表、分类、标签页、搜索页和商品详情页。
- SKU 支持规格名、规格值、独立价格、库存、图片和商品图集回退。
- 商品状态支持现货、预售、进货中、概念商品、售罄和秒杀。
- 商品卡和详情页显示价格范围；多 SKU 价格显示为最低价到最高价。
- 预售商品和线上交付商品可不受库存限制下单；线上交付不会生成付款扣库存流水，也不会进入低库存提醒；秒杀商品走独立抢购流程，不参与优惠体系。
- 商品评论、收藏、想买、购买意愿投票和价格区间投票。

### 购物车、订单和支付

- 购物车、立即购买、结算、地址管理和订单查询。
- 支持手动支付凭证上传、后台付款审核、订单隐私显示和用户确认收货。
- 优惠码支持全局、指定商品、多商品范围、用户优惠码包、后台直接发放和客服补偿自动发放。
- 每个购物车 SKU 最多享受一个优惠码，多个商品可分别使用不同优惠码。
- 支持线上交付、物流交付、物流单号显示和数字附件下载。
- 售后需求、退款审核、审批菜单、订单联系客服和业务时间线。

### 采购、仓库和财务

- 采购单、进货中商品、预售用户分配和采购入库。
- 仓库、库存、库存流水、仓库出库、物流承运商和省份运费规则。
- 成本录入、利润统计、销售报表、分类报表和 CSV 导出；成本条目支持选择货币、计量单位和折算汇率；利润概览支持用结果名、变量和运算符组合自定义利润公式。

### 内容页面和导航

- 自定义页面支持传统 Markdown 编辑和交互式区块编辑。
- 页面模板包括默认、文章、404、菜单、友情链接、搜索、资源发布和关于我们。
- 文章模板会按 Markdown 标题自动生成页面目录，支持阅读量、评论开关和赞赏码。
- 文章列表页 `/articles` 支持按最新或阅读量排序，并可切换正序/倒序。
- 区块模块支持标题、段落、引用、图片、按钮、提示、双栏、头图、卡片、菜单、友情链接、搜索、商品、文章、资源和分隔线。
- 前台菜单可在后台管理为目录树，支持拖拽排序、上下级调整、无页面上级菜单、二级浮窗、移动端侧边栏和链接提示文本。
- 自定义页面发布时可选择同步加入顶部导航或首页商店信息菜单。
- 关于我们示例文案见 [docs/about-us.md](docs/about-us.md)，内置关于我们模板只提供占位结构，避免 clone 后默认使用站点专属介绍。

### 论坛、社交和客服

- 论坛版块、帖子、回复、置顶、点赞、分享、搜索、排序和版主管理。
- 发帖模板支持交友、相亲、合租、招租、找租、资源发布等结构化内容。
- 用户公开主页、头像、个人介绍、私聊入口和用户中心聊天。
- 私聊支持多会话、图片/文件、表情包、历史搜索、引用回复和删除消息。
- 客服会话支持用户/客服自动收发、未读和最新分页、接待状态、附件、表情包和聊天记录搜索，并按权限提供优惠码/退款申请或直接处理入口。
- 客服工单支持游客提交和后台处理。

### AI 生图和聊天

- 前台 AI 页面包含画廊和 Chat 两种模式。
- 生图支持自定义 API URL/Key、后台默认配置、模型获取、参考图、提示词、多图生成、尺寸、质量、格式、透明背景、流式预览和任务详情。
- Chat 支持会话侧边栏、新增/删除会话、自动命名、附件、模型选择、推理强度和联网搜索开关。
- Image API 默认使用 `gpt-image-2`；当兼容网关需要走 Responses 图像工具时，可用 `AI_RESPONSES_IMAGE_MODEL` 指定承载 `image_generation` 工具的主线模型。
- AI 出站请求由 Laravel 后端代理发送，支持 `AI_HTTP_VERIFY_SSL`、`AI_HTTP_USE_NATIVE_CA` 和 `AI_HTTP_CA_BUNDLE` 配置 PHP/cURL 证书校验，避免 Windows 或精简服务器上出现 `cURL error 60`。
- 后台可配置默认生图/聊天 URL 和 Key，也可为单个用户配置专属 URL、Key 和 token 上限。
- 用户中心可查看 AI 余额、用量、柱状图和调用记录。
- 后台用户调用 AI 不受普通用户配额限制，但仍记录到总用量。

### 后台运营

- 商品、SKU、分类、标签、制造商、供应商、属性、交付状态、售罄状态和数量单位。
- 订单、支付审核、优惠码、用户优惠码、审批、售后、采购、仓库、库存、成本和报表。
- 前台用户、后台用户、权限角色、登录日志和后台活动日志。
- 内容页面、页面评论、公告、前台菜单、友情链接、媒体库和资源库。
- 论坛版块、帖子、评论、活动日志和版主管理。
- 站点设置、安装向导、缓存管理、备份下载、系统信息和默认外观。

## 主要路由

| 路径 | 说明 |
| --- | --- |
| `/` | 前台首页 |
| `/products` | 商品列表 |
| `/products/{slug}` | 商品详情 |
| `/tags` / `/tags/{slug}` | 商品标签索引和标签商品页 |
| `/search` | 综合搜索 |
| `/friend-links` | 友情链接 |
| `/articles` | 文章列表 |
| `/p/{slug}` | 自定义页面 |
| `/forum` | 论坛首页 |
| `/support` | 客服会话 |
| `/support/demands` | 客服工单 |
| `/cart` | 购物车 |
| `/checkout` | 结算 |
| `/orders` | 我的订单 |
| `/user` | 用户中心 |
| `/ai-image` | AI 生图/聊天 |
| `/admin` | 后台面板 |
| `/install` | 安装向导 |

## 开发和测试

运行后端测试：

```bash
php artisan test
```

Windows 本地如使用仓库内嵌 PHP，可执行：

```powershell
.\.tools\php\php.exe artisan test
```

构建前端资源：

```bash
npm run build
```

开发模式：

```bash
npm run dev
php artisan serve
```

当前测试主要集中在 `tests/Feature`，覆盖后台访问、商城交易、优惠码、采购仓库、论坛客服、AI、页面模板、相对 URL 和部署包规则。

## 部署要点

- 生产环境 Web 根目录必须指向 `public`。
- 首次部署可访问 `/install` 完成安装向导。
- 建议使用 MySQL/MariaDB，并配置稳定的队列、缓存和文件存储策略。
- 需要确保 `storage`、`bootstrap/cache` 和上传目录可写。
- 域名、反向代理、Cloudflare 等环境请检查 `APP_URL`、代理头和 HTTPS 设置。
- 如果 AI 模型列表或生图/聊天请求出现 `cURL error 60`，请优先保持 `AI_HTTP_USE_NATIVE_CA=true`，或配置 `AI_HTTP_CA_BUNDLE=/path/to/cacert.pem`，不要在生产环境随意关闭 SSL 校验。
- 详细部署说明见 [DEPLOY.md](DEPLOY.md)。

## Release 包

Release 构建脚本会生成预构建包：

- `shopweb-v{version}-windows.zip`
- `shopweb-v{version}-linux.zip`

两个包都会包含 `vendor/` 和 `public/build/`，目标服务器通常不需要再运行 Composer 或 npm 构建。解压后仍需要 PHP 8.3+、数据库和对应 PHP 扩展。

Release 包本地验证入口：

```bat
start-windows.bat
```

```bash
chmod +x start-linux.sh
./start-linux.sh
```

这些脚本适合本地验证或临时启动；生产环境仍建议使用 Nginx、Apache 或 IIS，并将 Web 根目录指向 `public`。

## 文档索引

- [DEPLOY.md](DEPLOY.md)：部署、服务器配置和 Release 包说明。
- [docs/requirements.md](docs/requirements.md)：功能需求、已实现状态和后续规划。
- [docs/about-us.md](docs/about-us.md)：关于我们页面示例 Markdown。
- [docs/nginx.conf.example](docs/nginx.conf.example)：Nginx 配置示例。
