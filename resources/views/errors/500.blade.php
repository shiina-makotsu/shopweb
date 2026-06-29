<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>页面暂时不可用</title>
    <style>
        :root {
            color-scheme: light dark;
        }

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
            width: min(92vw, 30rem);
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 20px 48px rgb(15 23 42 / 12%);
            padding: 1.5rem;
        }

        .code {
            margin: 0 0 .75rem;
            color: #2563eb;
            font-size: .85rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: 1.35rem;
            line-height: 1.35;
        }

        p {
            margin: .75rem 0 0;
            color: #475569;
            line-height: 1.75;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            margin-top: 1.25rem;
        }

        a,
        button {
            border: 1px solid #2563eb;
            border-radius: 999px;
            background: #2563eb;
            color: #fff;
            padding: .625rem 1rem;
            font: inherit;
            text-decoration: none;
            cursor: pointer;
        }

        button.secondary {
            border-color: #cbd5e1;
            background: #fff;
            color: #0f172a;
        }

        @media (prefers-color-scheme: dark) {
            body {
                background: #020617;
                color: #e2e8f0;
            }

            main {
                border-color: #334155;
                background: #0f172a;
                box-shadow: none;
            }

            p {
                color: #cbd5e1;
            }

            button.secondary {
                border-color: #475569;
                background: #111827;
                color: #e2e8f0;
            }
        }
    </style>
</head>
<body>
    <main>
        <p class="code">{{ $status ?? 500 }}</p>
        <h1>页面暂时不可用</h1>
        <p>当前功能模块遇到异常，系统已经记录并触发后台告警。你可以稍后重试，或先返回首页继续浏览。</p>
        <div class="actions">
            <a href="/">回到首页</a>
            <button class="secondary" type="button" onclick="window.location.reload()">重新加载</button>
        </div>
    </main>
</body>
</html>
