<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>系统繁忙</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f8fafc;
            color: #0f172a;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        main {
            width: min(92vw, 28rem);
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 20px 45px rgb(15 23 42 / 12%);
            padding: 1.5rem;
        }

        h1 {
            margin: 0;
            font-size: 1.25rem;
        }

        p {
            margin: .75rem 0 0;
            color: #475569;
            line-height: 1.7;
        }

        button {
            margin-top: 1rem;
            border: 1px solid #2563eb;
            border-radius: 999px;
            background: #2563eb;
            color: #fff;
            padding: .625rem 1rem;
            font: inherit;
        }
    </style>
</head>
<body>
    <main>
        <h1>系统繁忙</h1>
        <p>当前访问压力较高，系统正在保护数据库和缓存，预计 {{ $recoveryMinutes ?? 10 }} 分钟内恢复。</p>
        <p>页面会按指数退避策略重试，避免大量请求同时冲击后台。下次重试约 {{ $retryAfter ?? 3 }} 秒后进行。</p>
        <button type="button" onclick="window.setTimeout(() => window.location.reload(), {{ max(1, (int) ($retryAfter ?? 3)) * 1000 }})">稍后自动重试</button>
    </main>
</body>
</html>
