<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Queue Application - Mevrik Assessment</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
            background: rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
        }
        .status {
            background: rgba(255, 255, 255, 0.2);
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .endpoint {
            background: rgba(0, 0, 0, 0.2);
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            font-family: monospace;
        }
        .badge {
            display: inline-block;
            padding: 5px 10px;
            background: rgba(76, 175, 80, 0.8);
            border-radius: 3px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Laravel Queue Application</h1>
        <h2>Mevrik Software Engineer Assessment</h2>

        <div class="status">
            <h3>✅ Application Status: <span class="badge">RUNNING</span></h3>
            <p>Laravel {{ app()->version() }} - Queue Driver: {{ config('queue.default') }}</p>
        </div>

        <div class="status">
            <h3>📍 Available API Endpoints:</h3>

            <div class="endpoint">
                <strong>GET /api/health</strong><br>
                Check application health status
            </div>

            <div class="endpoint">
                <strong>POST /api/queue/notification</strong><br>
                Create a notification job<br>
                Body: {"user_id": 1, "message": "Your message"}
            </div>

            <div class="endpoint">
                <strong>POST /api/queue/log</strong><br>
                Create a log job<br>
                Body: {"message": "Log message", "level": "info"}
            </div>

            <div class="endpoint">
                <strong>GET /api/queue/status</strong><br>
                Get queue statistics and pending jobs
            </div>
        </div>

        <div class="status">
            <h3>🔧 Server Information:</h3>
            <p>PHP Version: {{ PHP_VERSION }}</p>
            <p>Server: Ubuntu 24.04.3 LTS</p>
            <p>Queue Workers: Active (Supervised)</p>
        </div>
    </div>
</body>
</html>