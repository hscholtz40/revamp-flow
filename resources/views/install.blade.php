<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JobCardOnline Installer</title>
    <link rel="icon" href="/favicon.ico">
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, Noto Sans, Helvetica Neue, Arial, "Apple Color Emoji", "Segoe UI Emoji"; background: #f6f7f9; margin: 0; padding: 2rem; }
        .container { max-width: 760px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); padding: 2rem; }
        h1 { margin-top: 0; font-size: 1.5rem; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .grid-1 { grid-template-columns: 1fr; }
        label { display: block; font-weight: 600; margin-bottom: .25rem; }
        input { width: 100%; padding: .6rem .75rem; border: 1px solid #e5e7eb; border-radius: 8px; font-size: .95rem; }
        .actions { margin-top: 1.5rem; display: flex; gap: .75rem; }
        button { background: #2563eb; color: white; border: 0; padding: .8rem 1rem; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .muted { color: #6b7280; font-size: .9rem; }
        .error { color: #b91c1c; font-size: .9rem; }
    </style>
    @csrf
</head>
<body>
    <div class="container">
        <h1>JobCardOnline Installer</h1>
        @if ($errors->any())
            <div class="error">Please fix the errors below.</div>
        @endif
        <form method="POST" action="{{ route('install.perform') }}">
            @csrf
            <div class="grid">
                <div>
                    <label>App URL</label>
                    <input name="app_url" type="url" value="{{ old('app_url', url('/')) }}" required>
                </div>
            </div>

            <h3>Database</h3>
            <div class="grid">
                <div>
                    <label>DB Host</label>
                    <input name="db_host" type="text" value="{{ old('db_host', '127.0.0.1') }}" required>
                </div>
                <div>
                    <label>DB Port</label>
                    <input name="db_port" type="number" value="{{ old('db_port', 3306) }}" required>
                </div>
                <div>
                    <label>DB Database</label>
                    <input name="db_database" type="text" value="{{ old('db_database') }}" required>
                </div>
                <div>
                    <label>DB Username</label>
                    <input name="db_username" type="text" value="{{ old('db_username') }}" required>
                </div>
                <div class="grid-1">
                    <label>DB Password</label>
                    <input name="db_password" type="text" value="{{ old('db_password') }}">
                </div>
            </div>

            <h3>Administrator</h3>
            <div class="grid">
                <div>
                    <label>Admin Email</label>
                    <input name="admin_email" type="email" value="{{ old('admin_email') }}" required>
                </div>
                <div>
                    <label>Admin Password</label>
                    <input name="admin_password" type="password" value="{{ old('admin_password') }}" required>
                </div>
            </div>

            <div class="actions">
                <button type="submit">Install</button>
                <div class="muted">This will run migrations and seed default data.</div>
            </div>
        </form>
    </div>
</body>
</html>


