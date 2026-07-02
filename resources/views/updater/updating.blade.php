<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GarmentsOS PRO is updating</title>
    <style>
        :root {
            color-scheme: light;
            --brand: #2563eb;
            --bg: #eef1f4;
            --text: #0f172a;
            --muted: #526173;
            --border: #c7d0dc;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            background: var(--bg);
            color: var(--text);
            font-family: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        }

        main {
            width: min(92vw, 540px);
            padding: 42px;
            border: 1px solid var(--border);
            border-radius: 20px;
            background: #fff;
            text-align: center;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.12);
        }

        .spinner {
            width: 42px;
            height: 42px;
            margin: 0 auto 22px;
            border: 4px solid #dbe3ef;
            border-top-color: var(--brand);
            border-radius: 999px;
            animation: spin 1s linear infinite;
        }

        h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }

        p {
            margin: 14px 0 0;
            color: var(--muted);
            line-height: 1.55;
        }

        .meta {
            margin-top: 18px;
            font-size: 12px;
            color: #7b8797;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>
<body>
    <main>
        <div class="spinner" aria-hidden="true"></div>
        <h1>GarmentsOS PRO is updating</h1>
        <p>Please wait. The app will reopen automatically after the update completes.</p>
        @if (!empty($updateLock['target_version']))
            <p class="meta">Target version: {{ $updateLock['target_version'] }}</p>
        @endif
    </main>

    <script>
        const statusUrl = @json(route('developer.updater.update-lock-status'));
        const homeUrl = @json(url('/'));

        const poll = async () => {
            try {
                const response = await fetch(statusUrl, {
                    headers: {
                        'Accept': 'application/json',
                    },
                    cache: 'no-store',
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();
                if (payload && payload.updating === false) {
                    window.location.replace(homeUrl);
                }
            } catch (error) {
                // The app/container may be restarting; keep waiting quietly.
            }
        };

        window.setInterval(poll, 5000);
        poll();
    </script>
</body>
</html>
