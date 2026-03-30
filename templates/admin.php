<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TinyProxy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: { primary: { 50: '#eff6ff', 100: '#dbeafe', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8' } }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="min-h-screen text-slate-800">
    <nav class="w-full bg-white border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-2">
                    <a href="/" class="flex items-center gap-2 group">
                        <div class="bg-primary-500 text-white p-2 rounded-lg group-hover:bg-primary-600 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        </div>
                        <span class="font-bold text-xl tracking-tight text-slate-900 group-hover:text-primary-600 transition-colors">Dashboard</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">Proxy Analytics</h1>
                <p class="text-slate-500 mt-1">Real-time statistics and cache management.</p>
            </div>
            <div class="flex gap-3">
                <button onclick="clearCache()" class="px-4 py-2 bg-white border border-red-200 text-red-600 hover:bg-red-50 hover:border-red-300 rounded-lg font-medium text-sm transition-all shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Clear Cache
                </button>
                <a href="/api/stats" target="_blank" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 rounded-lg font-medium text-sm transition-all shadow-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                    View JSON
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Total Requests -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-blue-50 text-blue-600 rounded-xl">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500">Total Requests</p>
                        <p class="text-2xl font-bold text-slate-900"><?php echo htmlspecialchars((string)($stats['total_requests'] ?? '0')); ?></p>
                    </div>
                </div>
            </div>

            <!-- Cache Hit Rate -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-emerald-50 text-emerald-600 rounded-xl">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500">Cache Hit Rate</p>
                        <p class="text-2xl font-bold text-slate-900"><?php echo htmlspecialchars((string)($stats['cache_hit_rate'] ?? '0')); ?>%</p>
                    </div>
                </div>
            </div>

            <!-- Error Rate -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-red-50 text-red-600 rounded-xl">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500">Error Rate</p>
                        <p class="text-2xl font-bold text-slate-900"><?php echo htmlspecialchars((string)($stats['error_rate'] ?? '0')); ?>%</p>
                    </div>
                </div>
            </div>

            <!-- Avg Response -->
            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-purple-50 text-purple-600 rounded-xl">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-slate-500">Avg Response</p>
                        <p class="text-2xl font-bold text-slate-900"><?php echo htmlspecialchars((string)($stats['avg_response_time'] ?? '0')); ?>s</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/50">
                <h3 class="font-semibold text-slate-800">System Information</h3>
            </div>
            <div class="p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                    <div>
                        <dt class="text-sm font-medium text-slate-500">PHP Version</dt>
                        <dd class="mt-1 text-sm text-slate-900 font-mono"><?php echo phpversion(); ?></dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-slate-500">Server Software</dt>
                        <dd class="mt-1 text-sm text-slate-900 font-mono"><?php echo htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?></dd>
                    </div>
                </dl>
            </div>
        </div>
    </main>

    <script>
        function clearCache() {
            if (!confirm('Are you sure you want to clear the proxy cache? This action cannot be undone.')) return;
            
            const btn = event.currentTarget;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-red-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Clearing...';
            btn.disabled = true;

            fetch('/api/cache/clear', { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Cache cleared successfully!');
                        location.reload();
                    } else {
                        alert('Failed to clear cache.');
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                })
                .catch(err => {
                    alert('Error: ' + err);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
        }
    </script>
</body>
</html>