<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Error</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 40px;
            background: #f8fafc;
            color: #334155;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        .error-title {
            font-size: 24px;
            font-weight: 600;
            color: #dc2626;
            margin-bottom: 16px;
        }
        .error-message {
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 24px;
        }
        .error-code {
            font-size: 14px;
            color: #64748b;
            background: #f1f5f9;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: 'Monaco', 'Menlo', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="error-title">Application Error</h1>
        <p class="error-message">{{ $message ?? 'An error occurred while processing your request.' }}</p>
        @if(app()->hasDebugModeEnabled())
            <div class="error-code">
                {{ $exception->getMessage() }}
            </div>
        @endif
    </div>
</body>
</html>
