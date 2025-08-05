{{-- Log Viewer Component for Black Dashboard --}}
<div class="card bg-dark" id="logViewerCard">
    <div class="card-header bg-gradient-primary d-flex justify-content-between align-items-center">
        <h5 class="card-title text-white mb-0">
            <i class="fas fa-file-alt"></i> Log Viewer
        </h5>
        <div class="d-flex align-items-center">
            <span id="logStatus" class="badge badge-secondary mr-2">Static</span>
            <button type="button" class="btn btn-sm btn-outline-light" data-toggle="collapse"
                data-target="#logViewerBody">
                <i class="fas fa-minus"></i>
            </button>
        </div>
    </div>

    <div class="collapse show" id="logViewerBody">
        <div class="card-body p-0">
            <!-- Controls -->
            <div class="p-3 border-bottom border-secondary">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" id="startLiveBtn" class="btn btn-success">
                                <i class="fas fa-play"></i> Start Live
                            </button>
                            <button type="button" id="pauseLiveBtn" class="btn btn-warning" disabled>
                                <i class="fas fa-pause"></i> Pause
                            </button>
                            <button type="button" id="clearLogsBtn" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Clear
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6 text-right">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" id="downloadLogBtn" class="btn btn-info">
                                <i class="fas fa-download"></i> Download
                            </button>
                            <button type="button" id="refreshLogBtn" class="btn btn-primary">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="row mt-2">
                    <div class="col-md-4">
                        <select id="logLevelFilter" class="form-control form-control-sm bg-dark text-white">
                            <option value="">All Levels</option>
                            <option value="emergency">Emergency</option>
                            <option value="alert">Alert</option>
                            <option value="critical">Critical</option>
                            <option value="error">Error</option>
                            <option value="warning">Warning</option>
                            <option value="notice">Notice</option>
                            <option value="info">Info</option>
                            <option value="debug">Debug</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" id="logSearchFilter"
                            class="form-control form-control-sm bg-dark text-white" placeholder="Search logs...">
                    </div>
                    <div class="col-md-4">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="checkbox" id="autoScrollCheck" checked>
                            <label class="form-check-label text-white" for="autoScrollCheck">
                                Auto Scroll
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Log Content -->
            <div id="logContainer" style="height: 400px; overflow-y: auto; background: #1a1a1a;">
                <pre id="logContent" class="text-white p-3 mb-0"
                    style="font-size: 12px; line-height: 1.4; white-space: pre-wrap; word-wrap: break-word;">
Loading logs...
                </pre>
            </div>

            <!-- Footer with stats -->
            <div class="card-footer bg-dark border-top border-secondary">
                <div class="row text-center">
                    <div class="col-4">
                        <small class="text-muted">Total Lines: <span id="totalLines" class="text-white">0</span></small>
                    </div>
                    <div class="col-4">
                        <small class="text-muted">Last Updated: <span id="lastUpdated"
                                class="text-white">Never</span></small>
                    </div>
                    <div class="col-4">
                        <small class="text-muted">File Size: <span id="fileSize" class="text-white">0 KB</span></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Custom styles for log viewer */
    #logViewerCard .card-header {
        background: linear-gradient(87deg, #5e72e4 0, #825ee4 100%) !important;
    }

    #logContainer {
        font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    }

    #logContent {
        margin: 0;
        background: transparent;
    }

    .log-line {
        padding: 2px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .log-line:hover {
        background-color: rgba(255, 255, 255, 0.05);
    }

    .log-level-emergency,
    .log-level-alert,
    .log-level-critical,
    .log-level-error {
        color: #ff6b6b !important;
        background-color: rgba(255, 107, 107, 0.1);
    }

    .log-level-warning {
        color: #feca57 !important;
        background-color: rgba(254, 202, 87, 0.1);
    }

    .log-level-notice,
    .log-level-info {
        color: #48cae4 !important;
        background-color: rgba(72, 202, 228, 0.1);
    }

    .log-level-debug {
        color: #a8a8a8 !important;
        background-color: rgba(168, 168, 168, 0.1);
    }

    /* Scrollbar styling */
    #logContainer::-webkit-scrollbar {
        width: 8px;
    }

    #logContainer::-webkit-scrollbar-track {
        background: #2a2a2a;
    }

    #logContainer::-webkit-scrollbar-thumb {
        background: #555;
        border-radius: 4px;
    }

    #logContainer::-webkit-scrollbar-thumb:hover {
        background: #777;
    }

    /* Loading animation */
    .loading-dots::after {
        content: '';
        display: inline-block;
        width: 20px;
        text-align: left;
        animation: dots 2s infinite;
    }

    @keyframes dots {

        0%,
        20% {
            content: '.';
        }

        40% {
            content: '..';
        }

        60% {
            content: '...';
        }

        80%,
        100% {
            content: '';
        }
    }
</style>

<script>
    class LogViewer {
        constructor() {
            this.isLive = false;
            this.interval = null;
            this.lastSize = 0;
            this.lines = [];
            this.filteredLines = [];

            this.initializeEventListeners();
            this.loadInitialLogs();
        }

        initializeEventListeners() {
            // Control buttons
            document.getElementById('startLiveBtn').addEventListener('click', () => this.startLive());
            document.getElementById('pauseLiveBtn').addEventListener('click', () => this.pauseLive());
            document.getElementById('clearLogsBtn').addEventListener('click', () => this.clearLogs());
            document.getElementById('downloadLogBtn').addEventListener('click', () => this.downloadLog());
            document.getElementById('refreshLogBtn').addEventListener('click', () => this.refreshLogs());

            // Filters
            document.getElementById('logLevelFilter').addEventListener('change', () => this.applyFilters());
            document.getElementById('logSearchFilter').addEventListener('input', () => this.applyFilters());
        }

        async loadInitialLogs() {
            try {
                const response = await fetch('/admin/logs/content', {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                            'content')
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    this.updateLogContent(data.content);
                    this.updateStats(data.stats);
                }
            } catch (error) {
                this.showError('Failed to load logs: ' + error.message);
            }
        }

        startLive() {
            this.isLive = true;
            document.getElementById('startLiveBtn').disabled = true;
            document.getElementById('pauseLiveBtn').disabled = false;
            document.getElementById('logStatus').textContent = 'Live';
            document.getElementById('logStatus').className = 'badge badge-success mr-2';

            // Start polling
            this.interval = setInterval(() => this.fetchLatestLogs(), 700);
            this.fetchLatestLogs();
        }

        pauseLive() {
            this.isLive = false;
            document.getElementById('startLiveBtn').disabled = false;
            document.getElementById('pauseLiveBtn').disabled = true;
            document.getElementById('logStatus').textContent = 'Paused';
            document.getElementById('logStatus').className = 'badge badge-warning mr-2';

            if (this.interval) {
                clearInterval(this.interval);
                this.interval = null;
            }
        }

        async fetchLatestLogs() {
            this.loadInitialLogs();
        }

        updateLogContent(content) {
            this.lines = content.split('\n').filter(line => line.trim());
            this.applyFilters();
            this.scrollToBottom();
        }

        appendLogContent(content) {
            const newLines = content.split('\n').filter(line => line.trim());
            this.lines.push(...newLines);
            this.applyFilters();

            if (document.getElementById('autoScrollCheck').checked) {
                this.scrollToBottom();
            }
        }

        applyFilters() {
            const levelFilter = document.getElementById('logLevelFilter').value;
            const searchFilter = document.getElementById('logSearchFilter').value.toLowerCase();

            this.filteredLines = this.lines.filter(line => {
                // Level filter
                if (levelFilter && !line.toLowerCase().includes(levelFilter)) {
                    return false;
                }

                // Search filter
                if (searchFilter && !line.toLowerCase().includes(searchFilter)) {
                    return false;
                }

                return true;
            });

            this.renderLogs();
        }

        renderLogs() {
            const logContent = document.getElementById('logContent');
            const processedLines = this.filteredLines.map(line => this.formatLogLine(line));
            logContent.innerHTML = processedLines.join('\n');

            document.getElementById('totalLines').textContent = this.filteredLines.length;
        }

        formatLogLine(line) {
            // Detect log level and apply styling
            const logLevels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
            let cssClass = '';

            for (const level of logLevels) {
                if (line.toLowerCase().includes(level)) {
                    cssClass = `log-level-${level}`;
                    break;
                }
            }

            return `<div class="log-line ${cssClass}">${this.escapeHtml(line)}</div>`;
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        scrollToBottom() {
            const container = document.getElementById('logContainer');
            container.scrollTop = container.scrollHeight;
        }

        clearLogs() {
            if (confirm('Are you sure you want to clear the log display?')) {
                document.getElementById('logContent').innerHTML = '<div class="text-muted">Logs cleared...</div>';
                this.lines = [];
                this.filteredLines = [];
                document.getElementById('totalLines').textContent = '0';
            }
        }

        async downloadLog() {
            try {
                const response = await fetch('/admin/logs/download', {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute(
                            'content')
                    }
                });

                if (response.ok) {
                    const blob = await response.blob();
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `laravel-log-${new Date().toISOString().split('T')[0]}.log`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                }
            } catch (error) {
                this.showError('Failed to download log: ' + error.message);
            }
        }

        refreshLogs() {
            const refreshBtn = document.getElementById('refreshLogBtn');
            const icon = refreshBtn.querySelector('i');

            icon.classList.add('fa-spin');
            refreshBtn.disabled = true;

            this.loadInitialLogs().finally(() => {
                icon.classList.remove('fa-spin');
                refreshBtn.disabled = false;
            });
        }

        updateStats(stats) {
            document.getElementById('lastUpdated').textContent = new Date().toLocaleTimeString();
            document.getElementById('fileSize').textContent = stats?.size || '0 KB';
        }

        showError(message) {
            document.getElementById('logContent').innerHTML = `<div class="text-danger">${message}</div>`;
        }
    }

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        new LogViewer();
    });
</script>
