<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Administrator</title>
    
    <!-- Style x-cloak darurat -->
    <style>[x-cloak] { display: none !important; }</style>

    <!-- 1. Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- 2. Tailwind Config -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Instrument Sans', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- 3. Tradisional CSS -->
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    <!-- 4. App JS (Must be loaded before AlpineJS CDN to register components before Alpine initializes) -->
    <script src="{{ asset('js/app.js') }}" defer></script>

    <!-- 5. AlpineJS -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-zinc-100 flex items-center justify-center min-h-screen antialiased">
    <div class="bg-white p-8 rounded-lg shadow-sm border border-zinc-200 w-full max-w-md">
        
        <div class="mb-6 text-center">
            <div class="mx-auto flex size-12 items-center justify-center rounded-md bg-emerald-700 text-lg font-bold text-white mb-4">
                JI
            </div>
            <h2 class="text-2xl font-bold text-zinc-950">Login Administrator</h2>
            <p class="text-sm text-zinc-500 mt-1">Silakan masuk ke panel admin Anda.</p>
        </div>
        
        @if ($errors->any())
            <div class="bg-red-50 text-red-600 border border-red-200 p-3 rounded-md mb-5 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-zinc-700 mb-1">Username</label>
                <!-- Name adalah 'email' karena pada seeder menggunakan kolom email -->
                <input type="text" id="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full px-4 py-3 border border-zinc-300 rounded-md bg-white text-sm text-zinc-950 outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100">
            </div>
            
            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-zinc-700 mb-1">Password</label>
                <input type="password" id="password" name="password" required 
                    class="w-full px-4 py-3 border border-zinc-300 rounded-md bg-white text-sm text-zinc-950 outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100">
            </div>
            
            <button type="submit" class="w-full bg-emerald-700 hover:bg-emerald-800 text-white font-semibold py-3 px-4 rounded-md focus:outline-none focus:ring-4 focus:ring-emerald-300 transition-colors">
                Masuk ke Dashboard
            </button>
        </form>
    </div>
</body>
</html>
