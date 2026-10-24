# Telegram 机器人

## 部署与配置

1. 在 Telegram 官方 `@BotFather` 创建机器人（`/newbot`），获取 Token。Token 等同于机器人密码，不应出现在截图、日志、工单或公开仓库中。
2. 部署站点到公网可访问的 HTTPS 域名，配置 `APP_URL=https://你的站点域名`，确保 TLS 证书有效、反向代理转发协议正确，且服务器可访问 Telegram Bot API。Webhook 地址为 `https://你的站点域名/telegram/webhook`。修改环境配置后按部署流程刷新配置缓存。
3. 执行 `php artisan migrate`。Windows 本地 PHP 路径为 `.tools/php/php.exe`。
4. 超级管理员进入后台“客服 > Telegram 机器人”，填写 BotFather Token，开启机器人并保存。Token 输入框每次加载均为空；留空保存保留原 Token，填写新值才替换。关闭开关不会删除 Token，也不表示已向 Telegram 注销 Webhook。
5. 点击“保存并注册 Webhook”。系统先保存配置，再通过 `getMe` 验证 Token、通过 `setWebhook` 注册本站的公开 HTTPS Webhook。成功后检查只读的机器人用户名和注册时间。普通“保存设置”不调用 Telegram API。非空 Token 必须符合 BotFather 的“数字:密钥”格式，不要粘贴完整 API URL。
6. 对应人员先使用自己的 Telegram 账号私聊机器人，发送 `/start`。机器人会返回“你的 Telegram Chat ID：数字”；未绑定后台账号也可以查询自己的私聊 Chat ID，但不会因此获得后台身份或客服权限。
7. 在后台“用户 > 后台用户”中，将机器人返回的数字填写到该账号的“Telegram Chat ID（私聊）”，按需启用 Telegram 通知，设置客服窗口上限（默认 1，范围 1–65535）。Chat ID 必须是正整数字符串，最长 32 位且全站唯一，不接受 `@username`、`t.me` 链接或群聊 ID。
8. 保存后，对应人员再次私聊机器人发送 `/start`。机器人会返回 Chat ID 和绑定成功提示。服务必须核对真实发送者和预配置的 ID，仅在私聊验证成功后激活通知目标。更换或移除预配置 ID 会清空旧验证结果、用户名和默认会话，需要重新 `/start`。表单兼容使用 `telegram_user_id` 保存预配置 ID，`telegram_chat_id` 仍只能由机器人验证后写入，不提供直接编辑入口。

Token 与待发送消息加密存储，保存后不回显 Token；表单校验失败后也需要重新输入本次 Token。妥善备份并保护 `APP_KEY`，否则无法解密已有配置和待发送消息。实际替换 Token 时，系统会在同一事务内更换 Webhook 验证密钥，清空旧机器人的消息回复映射、更新去重记录、用户名和注册时间，避免新旧机器人消息编号冲突；财务流水和客服会话、消息历史均保留。更换后需要重新注册 Webhook，旧 Telegram 消息的回复映射不再有效。留空或再次保存相同 Token 不会重置已有密钥、回复映射或去重记录；关闭开关并留空 Token 保存也会保留已有凭证和注册信息。

Webhook 验证密钥由系统自动管理，无需手工填写。反向代理必须透传 `X-Telegram-Bot-Api-Secret-Token` 请求头；不要通过删除密钥验证来解决 403。

## Telegram 出站代理

如果服务器无法直连 Telegram，可在服务器的私有 `.env` 中配置 `TELEGRAM_PROXY_URL`，例如 `http://proxy.internal:7890` 或 `socks5h://proxy.internal:7890`。该配置只作用于 Telegram Bot API 与客服附件下载，不改变其它 HTTP 请求；留空沿用默认网络配置，始终保留 HTTPS 证书验证。修改后刷新 Laravel 配置缓存，再点击“保存并注册 Webhook”。代理地址若包含认证信息，不得提交到 Git 或发到公开聊天中。

`127.0.0.1` 只代表当前运行环境：Windows 本机的 `127.0.0.1:7890` 不能直接供远程 Debian 使用；Docker 内的该地址又只代表 PHP 容器，不代表 Debian 宿主机。生产环境应使用服务器可持续访问的代理，不依赖个人电脑始终在线，也不要公开无认证的代理端口。

若代理运行在 Docker 宿主机，可为 PHP 服务配置 `host.docker.internal:host-gateway` 主机映射，再使用 `http://host.docker.internal:7890`。宿主机代理必须监听容器可达的内部接口，并用防火墙限制为容器网络访问；只监听宿主机 `127.0.0.1` 的代理无法直接被容器访问。以上配置只解决服务器向 Telegram 发送请求，公网 HTTPS Webhook 仍须能接收 Telegram 的入站请求。

## 通知与客服命令

所有后台面板用户在完成绑定且启用个人通知后都可以接收订单通知。只有超级管理员可以修改机器人配置；接单、列出/切换客服会话和发送客服消息另需客服权限。能登录后台或接收订单通知，不代表能够接待客户。

订单提醒包括下单、取消订单和退款入钱包，并显示订单号、站内用户 ID 和金额。客服消息支持文字、图片和文件双向传递，单个上传附件限 10MB，文件保存在私有存储。客户关闭会话时，Telegram 接待同步结束。

| 操作 | 使用说明 |
| --- | --- |
| `/start` | 返回发送者自己的私聊 Chat ID；若匹配后台预配置 ID，则完成验证并显示绑定成功。未绑定账号只能查询自己的 ID，不得自行取得后台身份。 |
| `/chat public_id` | 使用站内客户的公开 `public_id` 发起或接入客服会话，不使用数据库用户主键替代。 |
| inline“接单”按钮 | 从待接待通知领取指定会话；已经被他人领取或超过本人窗口上限时不能再次领取。 |
| `/sessions` | 列出该客服当前可操作的会话及其 SESSION_ID，显示默认会话。 |
| `/switch SESSION_ID` | 切换普通消息的默认目标，仅允许切换到本人有权操作且未结束的会话。 |
| 回复某条 Telegram 客户消息 | 精确选择这条消息所属的客户会话，优先于 `/switch` 设置的默认目标。消息映射失效、无权限或会话已结束时会拒绝发送，不会退回默认会话。 |
| 普通文字消息 | 未指定 reply_to 时发送到当前默认会话；没有有效默认会话时提示选择，不猜测接收客户。 |
| `/end` | 结束当前默认客服会话并释放一个窗口；不会解除 Telegram 绑定。 |

每位客服可同时接待的会话数量由“Telegram 客服窗口上限”控制，默认 1。所有可用窗口都满时，新会话进入队列；结束会话释放窗口后，系统继续分配等待中的会话。多窗口接待时，优先直接回复对应客户消息；仅需改变普通消息的默认目标时使用 `/switch`。

## 定时任务与重试

系统在请求结束时立即尝试发送通知；失败的消息保存在加密 outbox 中，不阻断订单和客服操作。`shop:telegram-sync` 已在应用内每分钟调度，用于发送重试、结束超时接待和分配排队会话，无需再添加第二条专用调度。

生产环境需要保持 Laravel scheduler 运行。例如在 crontab 中配置，替换 PHP 和项目的绝对路径，并确保执行账号对运行目录有权限：

```cron
* * * * * cd /path/to/shopweb && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

也可以由进程管理器持续运行 `php artisan schedule:work`。手动检查使用 `php artisan shop:telegram-sync`；Windows 本地使用 `.tools/php/php.exe artisan shop:telegram-sync`。

发送失败按指数退避持续重试，间隔最长 1 小时。网络恢复后，等待后续调度即可，无需重复操作订单。Webhook update 会去重，但外部发送成功后进程立即退出仍可能造成重发。

## 常见问题

- 找不到配置菜单：确认使用超级管理员账号，已部署并执行迁移；普通客服账号没有机器人配置权限。
- 无法注册 Webhook：检查 Token 是否完整、`APP_URL` 是否为公网 HTTPS、TLS 证书是否有效、反向代理是否保留 HTTPS 协议，以及服务器到 Telegram 的网络。注册失败会保留配置，可直接重试。
- Webhook 返回 403：检查反向代理是否透传 secret 请求头；更换 Token 后重新注册 Webhook。不要把 Token 或密钥贴进日志和工单。
- 无法绑定或收不到通知：先私聊机器人发送 `/start` 获取数字 Chat ID，在后台保存该数字，再发送一次 `/start`；不要填写用户名或个人链接。确认未拉黑机器人，且个人 Telegram 通知开关已启用。更换 ID 后必须重新 `/start`。
- 发送 `/start` 完全无回复：保存 Token 不等于注册成功，先检查机器人设置页的 Webhook 注册时间。服务器必须能连接 `https://api.telegram.org`，连接超时会导致注册或回复失败；同时检查公网 Webhook 是否可达与 scheduler 是否正常。不要通过公开 Token 或关闭密钥校验排障。
- 能收订单通知但不能接待客户：检查后台账号是否具备客服权限；财务等角色接收订单通知不等于具有客服权限。
- 接单失败或会话排队：用 `/sessions` 检查已占用窗口，结束无需继续的会话；需要更多并发窗口时由超级管理员调整上限。被他人领取的会话不能重复接单。
- 多窗口回复目标不明确：直接回复该客户的 Telegram 消息，或先执行 `/switch SESSION_ID` 再发送普通消息；结束或失效的会话不会自动改投其他客户。
- 通知长期延迟：确认 scheduler 正在运行，手动执行 `shop:telegram-sync` 检查结果，并检查 `storage/logs/laravel.log` 的脱敏发送失败提示。网络恢复后，失败记录会按重试时间继续发送。

官方参考：[BotFather](https://core.telegram.org/bots/features#botfather)、[Bot API](https://core.telegram.org/bots/api)。
