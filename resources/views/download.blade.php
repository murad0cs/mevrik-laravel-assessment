<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download File - Laravel Queue System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .download-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
            text-align: center;
            animation: slideUp 0.5s ease;
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .icon svg {
            width: 40px;
            height: 40px;
            fill: white;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .status {
            color: #666;
            margin-bottom: 30px;
            font-size: 16px;
        }
        .file-info {
            background: #f7f7f7;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }
        .file-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .file-info-row:last-child {
            margin-bottom: 0;
        }
        .file-info-label {
            color: #888;
            font-weight: 500;
        }
        .file-info-value {
            color: #333;
            font-weight: 600;
        }
        .download-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .download-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        .download-button:active {
            transform: translateY(0);
        }
        .error-message {
            background: #fee;
            color: #c33;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .success-message {
            background: #e6f7e6;
            color: #2d662d;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .back-link {
            margin-top: 30px;
            display: inline-block;
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        .back-link:hover {
            color: #764ba2;
        }
    </style>
</head>
<body>
    <div class="download-container">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
            </svg>
        </div>

        @if($error)
            <h1>Download Not Available</h1>
            <div class="error-message">
                {{ $error }}
            </div>
        @else
            <h1>File Ready for Download</h1>

            @if($status === 'completed')
                <p class="status">Your processed file is ready!</p>

                <div class="file-info">
                    <div class="file-info-row">
                        <span class="file-info-label">File ID:</span>
                        <span class="file-info-value">{{ substr($fileId, 0, 8) }}...</span>
                    </div>
                    <div class="file-info-row">
                        <span class="file-info-label">Original Name:</span>
                        <span class="file-info-value">{{ $originalName ?? 'Unknown' }}</span>
                    </div>
                    <div class="file-info-row">
                        <span class="file-info-label">Processing Type:</span>
                        <span class="file-info-value">{{ ucfirst(str_replace('_', ' ', $processingType)) }}</span>
                    </div>
                    <div class="file-info-row">
                        <span class="file-info-label">Processed At:</span>
                        <span class="file-info-value">{{ $processedAt ?? 'Recently' }}</span>
                    </div>
                </div>

                <a href="{{ url('/api/queue/download/' . $fileId) }}?direct=1"
                   class="download-button"
                   id="downloadBtn"
                   download>
                    Download File
                </a>

                <div class="success-message" id="downloadMessage" style="display: none;">
                    Download started! Check your downloads folder.
                </div>

                <div style="margin-top: 20px; font-size: 14px; color: #666;">
                    <p id="autoDownloadStatus" style="display: none;">
                        Auto-download will start in <span id="countdown">2</span> seconds...
                    </p>
                    <p style="margin-top: 10px;">
                        If download doesn't start automatically,<br>
                        please click the Download button above.
                    </p>
                </div>
            @elseif($status === 'processing')
                <p class="status">File is still being processed...</p>
                <div class="file-info">
                    <div class="file-info-row">
                        <span class="file-info-label">Status:</span>
                        <span class="file-info-value">Processing <span class="loading"></span></span>
                    </div>
                </div>
                <p style="color: #666; font-size: 14px; margin-top: 20px;">
                    Please refresh this page in a few moments.
                </p>
                <button onclick="location.reload()" class="download-button">
                    Refresh Status
                </button>
            @elseif($status === 'queued')
                <p class="status">File is queued for processing...</p>
                <div class="file-info">
                    <div class="file-info-row">
                        <span class="file-info-label">Status:</span>
                        <span class="file-info-value">Queued</span>
                    </div>
                </div>
                <p style="color: #666; font-size: 14px; margin-top: 20px;">
                    Processing will begin shortly. Please refresh this page.
                </p>
                <button onclick="location.reload()" class="download-button">
                    Refresh Status
                </button>
            @else
                <p class="status">File status: {{ $status }}</p>
                <div class="error-message">
                    Unable to download file at this time.
                </div>
            @endif
        @endif

        <a href="{{ url('/api/queue/status') }}" class="back-link">
            ← Back to Queue Status
        </a>
    </div>

    @if(!$error && $status === 'completed')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const downloadBtn = document.getElementById('downloadBtn');
            const downloadMessage = document.getElementById('downloadMessage');
            const autoDownloadStatus = document.getElementById('autoDownloadStatus');
            const countdownElement = document.getElementById('countdown');

            // Check if we should auto-download
            const urlParams = new URLSearchParams(window.location.search);
            const shouldAutoDownload = !urlParams.get('direct') && !urlParams.get('noauto');

            // Function to trigger download
            function triggerDownload() {
                if (!downloadBtn) return;

                console.log('Triggering download...');

                // Method 1: Direct window.location
                window.location.href = downloadBtn.href;

                // Show success message
                if (downloadMessage) {
                    downloadMessage.style.display = 'block';
                }

                // Hide countdown
                if (autoDownloadStatus) {
                    autoDownloadStatus.style.display = 'none';
                }
            }

            // Auto-download with countdown
            if (shouldAutoDownload) {
                if (autoDownloadStatus) {
                    autoDownloadStatus.style.display = 'block';
                }

                let countdown = 2;
                const countdownInterval = setInterval(function() {
                    countdown--;
                    if (countdownElement) {
                        countdownElement.textContent = countdown;
                    }

                    if (countdown <= 0) {
                        clearInterval(countdownInterval);
                        triggerDownload();
                    }
                }, 1000);
            }

            // Handle manual download click
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function(e) {
                    console.log('Manual download clicked');
                    if (downloadMessage) {
                        downloadMessage.style.display = 'block';
                    }
                    // Don't prevent default - let the link work normally
                });
            }
        });
    </script>
    @endif

    @if(!$error && ($status === 'processing' || $status === 'queued'))
    <script>
        // Auto-refresh for processing files
        setTimeout(function() {
            location.reload();
        }, 5000); // Refresh every 5 seconds
    </script>
    @endif
</body>
</html>