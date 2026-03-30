<!DOCTYPE html>
<html lang="en" class="antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TinyProxy - Modern PHP Proxy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: { 50: '#eff6ff', 100: '#dbeafe', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8' },
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .bg-pattern {
            background-color: #f8fafc;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 32px 32px;
        }
    </style>
</head>
<body class="bg-pattern min-h-screen flex flex-col text-slate-800">
    <nav class="w-full glass z-50 sticky top-0 border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-2">
                    <div class="bg-primary-500 text-white p-2 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path></svg>
                    </div>
                    <span class="font-bold text-xl tracking-tight text-slate-900">TinyProxy</span>
                </div>
                <div class="flex gap-4">
                    <a href="/api/health" class="text-sm font-medium text-slate-600 hover:text-primary-600 transition-colors">Health</a>
                    <a href="/admin" class="text-sm font-medium text-slate-600 hover:text-primary-600 transition-colors">Dashboard</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow flex flex-col items-center justify-center px-4 sm:px-6 lg:px-8 py-12">
        <div class="w-full max-w-3xl text-center space-y-8">
            <div class="space-y-4 animate-fade-in-up">
                <h1 class="text-5xl sm:text-6xl font-extrabold text-slate-900 tracking-tight">
                    Browse <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary-500 to-indigo-600">Securely.</span>
                </h1>
                <p class="text-lg sm:text-xl text-slate-600 max-w-2xl mx-auto">
                    A lightning-fast, highly secure PHP proxy server designed for modern web applications. 
                    Bypass restrictions while maintaining privacy and performance.
                </p>
            </div>

            <div class="glass p-2 sm:p-3 rounded-2xl shadow-xl border border-slate-200/60 max-w-2xl mx-auto relative group transition-all duration-300 hover:shadow-2xl hover:shadow-primary-500/10">
                <form method="GET" action="/" class="flex flex-col sm:flex-row gap-3 relative z-10">
                    <div class="relative flex-grow flex items-center">
                        <svg class="absolute left-4 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path></svg>
                        <input type="url" name="url" placeholder="https://example.com" required 
                               class="w-full pl-12 pr-4 py-4 bg-white/80 rounded-xl border border-slate-200 focus:border-primary-500 focus:ring-2 focus:ring-primary-200 outline-none transition-all text-lg placeholder:text-slate-400 text-slate-700 shadow-inner">
                    </div>
                    <button type="submit" class="px-8 py-4 bg-primary-600 hover:bg-primary-700 text-white rounded-xl font-semibold text-lg shadow-lg shadow-primary-500/30 transition-all active:scale-95 flex items-center justify-center gap-2">
                        <span>Launch</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </form>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-12">
                <div class="glass p-6 rounded-2xl text-left border border-slate-100 hover:-translate-y-1 transition-transform duration-300">
                    <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h3 class="font-bold text-slate-900 mb-2">High Performance</h3>
                    <p class="text-slate-600 text-sm">Advanced file-based caching with automatic GZIP compression ensures lightning-fast load times.</p>
                </div>
                <div class="glass p-6 rounded-2xl text-left border border-slate-100 hover:-translate-y-1 transition-transform duration-300">
                    <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <h3 class="font-bold text-slate-900 mb-2">Enterprise Security</h3>
                    <p class="text-slate-600 text-sm">Built-in SSRF prevention, request rate limiting, and strictly isolated network configurations.</p>
                </div>
                <div class="glass p-6 rounded-2xl text-left border border-slate-100 hover:-translate-y-1 transition-transform duration-300">
                    <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                    </div>
                    <h3 class="font-bold text-slate-900 mb-2">Smart Modifications</h3>
                    <p class="text-slate-600 text-sm">Automatic URL rewriting, ad blocking, and asset transformations on the fly.</p>
                </div>
            </div>
        </div>
    </main>

    <footer class="py-6 text-center text-slate-500 text-sm">
        <p>&copy; <?php echo date('Y'); ?> TinyProxy. All rights reserved.</p>
    </footer>
</body>
</html>
