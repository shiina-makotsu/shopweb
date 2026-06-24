# ShopWeb 运维预案

## 优先级

| 级别 | 场景 | 通知 |
|---|---|---|
| P0 | 网站崩溃、未捕获异常风暴、限流/降级大量触发、数据库不可用 | Alert Bot 立即发送 |
| P1 | Redis 集群失效、CDN 主节点异常、支付主通道不可用但有备选 | Alert Bot 可扩展 |
| P2 | 客户客服消息待处理 | Alert Bot 发送客户消息提醒 |
| P3 | 订单待确认收款、普通未读消息、低风险队列积压 | Alert Bot 发送普通提醒 |

## Redis 三主三从与 Sentinel

推荐部署为 3 master + 3 replica + 3 sentinel。应用侧通过环境变量接入：

```env
CACHE_STORE=redis
CACHE_REDIS_CONNECTION=cache
REDIS_CLIENT=phpredis
REDIS_CLUSTER=redis
REDIS_SENTINEL_MASTER=mymaster
REDIS_SENTINEL_NODES=10.0.0.11:26379,10.0.0.12:26379,10.0.0.13:26379
```

推荐 Redis 参数：

```conf
maxmemory-policy allkeys-lfu
lazyfree-lazy-eviction yes
lazyfree-lazy-expire yes
lazyfree-lazy-server-del yes
replica-read-only yes
```

`allkeys-lfu` 比 LRU 更适合商城首页、商品页、菜单和配置这类高频热点数据。若业务更强调最近访问而不是长期热点，可切换为 `allkeys-lru`。

## 缓存预热

缓存预热是必须项。系统提供：

```bash
php artisan shop:cache-prewarm
```

调度默认每 10 分钟执行一次，并使用缓存锁避免重复预热。预热内容包括首页分类、推荐商品、最新商品和关键入口 URL 列表。上线后建议在部署脚本中执行：

```bash
php artisan migrate --force
php artisan optimize
php artisan shop:cache-prewarm
```

## 数据库主从切换失败预案

1. 立即确认主库、从库、代理层或连接池状态。
2. 将站点切入降级模式：保留浏览、关闭高写入操作或提高令牌桶限制强度。
3. 若主库不可恢复，手动提升最新从库为主库，校验 binlog 位点和数据延迟。
4. 更新 `.env` 中 `DB_HOST` 或数据库代理指向，执行 `php artisan config:clear`。
5. 执行 `php artisan shop:database-health --fix` 和关键订单查询。
6. 发送 P0 恢复通知，记录切换时间、丢失窗口、补偿动作。

## CDN 节点宕机预案

1. 检查 CDN 健康状态、源站可达性、TLS 证书和回源错误。
2. 临时切换 DNS/CDN 到备用节点或直接回源。
3. 保持 `TRUSTED_PROXIES` 正确，避免登录 Cookie 和 HTTPS 判断异常。
4. 对静态资源执行缓存刷新，确认 `public/build/manifest.json` 与资源路径一致。
5. 若 CDN 错误导致请求回源暴涨，提高令牌桶限流并启用缓存预热。

## 支付通道熔断预案

1. 主支付通道失败时，前台展示备用二维码、口令红包、钱包余额和联系客服入口。
2. 后台订单继续允许人工确认收款，确认动作必须留下操作记录。
3. 支付通道恢复后抽样核对订单、付款凭证和钱包充值流水。
4. 若有用户重复提交付款，按幂等逻辑保留首个有效凭证，不重复入账。

## 第三方接口超时预案

1. 所有第三方接口必须设置连接超时和总超时。
2. 失败后使用指数退避：3 秒起步，每次翻倍，加入随机抖动，最多 10 次。
3. AI、物流、汇率、支付等接口失败时优先返回明确错误，不允许白屏或 500。
4. 对可缓存数据使用 stale-while-revalidate 思路，旧数据可短时继续展示。

## 雪崩与穿透防护

1. 令牌桶限流保护 MySQL 接入层，前台和后台使用不同容量。
2. 热点数据使用 Redis 缓存，Redis 不可用时退回数据库/文件缓存，但不进行无限重试。
3. 缓存预热和缓存锁用于避免大量请求同时回源。
4. 系统繁忙时返回 503 兜底页，提示预计 10 分钟恢复，并通过 `Retry-After` 指示指数退避。
5. P0 事件通过 Alert Bot 推送到 llbot / NapCat / OlivOS / Telegram / QQ 网关。
