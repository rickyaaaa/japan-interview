<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Japanese Interview Assessment</title>

        <script>
            window.interviewConfig = {
                csrfToken: @json(csrf_token()),
                questions: @json($questions),
                routes: {
                    sessions: @json(route('sessions.store')),
                },
            };
        </script>
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
    <body class="min-h-screen bg-zinc-100 text-zinc-950 antialiased">
        <main
            x-data="interviewApp"
            x-cloak
            class="min-h-screen"
        >
            <section class="border-b border-zinc-200 bg-white">
                <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex size-10 shrink-0 items-center justify-center rounded-md bg-emerald-700 text-sm font-bold text-white">
                            JI
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-zinc-950">Japanese Interview</p>
                            <p class="truncate text-xs text-zinc-500">Frontend prototype</p>
                        </div>
                    </div>

                    <div class="hidden items-center gap-2 text-xs font-medium text-zinc-500 sm:flex">
                        <span class="rounded-full border border-zinc-200 px-3 py-1">10 questions</span>
                        <span class="rounded-full border border-zinc-200 px-3 py-1">Mock AI review</span>
                    </div>
                </div>
            </section>

            <section x-show="step === 'start'" class="mx-auto grid max-w-7xl gap-8 px-4 py-8 sm:px-6 lg:grid-cols-[0.95fr_1.05fr] lg:px-8 lg:py-10">
                <div class="flex flex-col justify-between gap-8">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-[0.16em] text-emerald-700">Candidate session</p>
                        <h1 class="mt-4 max-w-3xl text-4xl font-semibold leading-tight tracking-normal text-zinc-950 sm:text-5xl">
                            Mulai tes wawancara Bahasa Jepang
                        </h1>
                        <p class="mt-5 max-w-2xl text-base leading-7 text-zinc-600">
                            Kandidat menjawab 10 pertanyaan lisan. Versi ini menampilkan pengalaman frontend lengkap dengan perekam suara dan hasil AI yang disimulasikan.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-md border border-zinc-200 bg-white p-4">
                            <p class="text-2xl font-semibold text-zinc-950">10</p>
                            <p class="mt-1 text-sm text-zinc-500">Pertanyaan runtun</p>
                        </div>
                        <div class="rounded-md border border-zinc-200 bg-white p-4">
                            <p class="text-2xl font-semibold text-zinc-950">AI</p>
                            <p class="mt-1 text-sm text-zinc-500">Transkrip & skor mock</p>
                        </div>
                        <div class="rounded-md border border-zinc-200 bg-white p-4">
                            <p class="text-2xl font-semibold text-zinc-950">Web</p>
                            <p class="mt-1 text-sm text-zinc-500">Mobile & desktop</p>
                        </div>
                    </div>
                </div>

                <form
                    class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm sm:p-6"
                    x-on:submit.prevent="startInterview"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-semibold text-zinc-950">Login</h2>
                            <p class="mt-1 text-sm leading-6 text-zinc-500">Silakan login untuk memulai sesi tes.</p>
                        </div>
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">Demo</span>
                    </div>

                    <div class="mt-6 space-y-4">
                        <label class="block">
                            <span class="text-sm font-medium text-zinc-700">Username</span>
                            <input
                                x-model="candidate.username"
                                type="text"
                                required
                                placeholder="Username"
                                class="mt-2 w-full rounded-md border border-zinc-300 bg-white px-3 py-3 text-sm text-zinc-950 outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100"
                            >
                        </label>

                        <label class="block">
                            <span class="text-sm font-medium text-zinc-700">Password</span>
                            <input
                                x-model="candidate.password"
                                type="password"
                                required
                                placeholder="Password"
                                class="mt-2 w-full rounded-md border border-zinc-300 bg-white px-3 py-3 text-sm text-zinc-950 outline-none transition focus:border-emerald-600 focus:ring-4 focus:ring-emerald-100"
                            >
                        </label>
                    </div>

                    <div x-show="errorMessage" class="mt-5 rounded-md border border-red-200 bg-red-50 p-3 text-sm leading-6 text-red-800" x-text="errorMessage"></div>

                    <button
                        type="submit"
                        class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-md bg-zinc-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800 focus:outline-none focus:ring-4 focus:ring-zinc-300"
                    >
                        Login
                        <span aria-hidden="true">→</span>
                    </button>
                </form>
            </section>

            <section x-show="step === 'interview'" class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-[280px_1fr]">
                    <aside class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-semibold text-zinc-950" x-text="candidate.username"></p>
                        <p class="mt-1 truncate text-xs text-zinc-500">Administrator</p>

                        <div class="mt-6">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium text-zinc-700">Progress</span>
                                <span class="font-semibold text-zinc-950" x-text="`${currentIndex + 1}/10`"></span>
                            </div>
                            <div class="mt-3 h-2 rounded-full bg-zinc-200">
                                <div class="h-2 rounded-full bg-emerald-600 transition-all duration-500" x-bind:style="`width: ${progressPercent}%`"></div>
                            </div>
                        </div>

                        <ol class="mt-6 grid grid-cols-5 gap-2 lg:grid-cols-2">
                            <template x-for="(_, index) in questions" x-bind:key="index">
                                <li>
                                    <span
                                        class="flex h-9 items-center justify-center rounded-md border text-sm font-semibold"
                                        x-bind:class="{
                                            'border-emerald-600 bg-emerald-600 text-white': index === currentIndex,
                                            'border-emerald-200 bg-emerald-50 text-emerald-800': index < currentIndex,
                                            'border-zinc-200 bg-zinc-50 text-zinc-500': index > currentIndex
                                        }"
                                        x-text="index + 1"
                                    ></span>
                                </li>
                            </template>
                        </ol>
                    </aside>

                    <div class="space-y-6">
                        <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.14em] text-emerald-700" x-text="`Pertanyaan ${currentIndex + 1}`"></p>
                                    <h2 class="mt-3 text-2xl font-semibold leading-relaxed text-zinc-950 sm:text-3xl" x-text="currentQuestion"></h2>
                                </div>
                                <span class="shrink-0 rounded-full border border-zinc-200 px-3 py-1 text-xs font-semibold text-zinc-500">Bahasa Jepang</span>
                            </div>
                        </div>

                        <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
                            <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
                                <div x-show="errorMessage" class="mb-5 rounded-md border border-red-200 bg-red-50 p-3 text-sm leading-6 text-red-800" x-text="errorMessage"></div>

                                <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-zinc-500">Status rekaman</p>
                                        <p class="mt-1 text-xl font-semibold text-zinc-950">
                                            <span x-show="recorderState === 'idle'">Izin mikrofon belum diminta</span>
                                            <span x-show="recorderState === 'requesting'">Meminta izin mikrofon</span>
                                            <span x-show="recorderState === 'ready'">Siap merekam</span>
                                            <span x-show="recorderState === 'recording'">Sedang merekam</span>
                                            <span x-show="recorderState === 'stopped'">Rekaman siap dikirim</span>
                                            <span x-show="recorderState === 'processing'">Memproses jawaban</span>
                                            <span x-show="recorderState === 'processed'">Jawaban selesai dinilai</span>
                                        </p>
                                    </div>

                                    <div class="rounded-md border border-zinc-200 bg-zinc-50 px-4 py-3 text-center">
                                        <p class="text-xs font-medium uppercase tracking-[0.16em] text-zinc-500">Timer</p>
                                        <p class="mt-1 font-mono text-2xl font-semibold text-zinc-950" x-text="formattedTime"></p>
                                    </div>
                                </div>

                                <div class="mt-6 flex h-24 items-end gap-2 rounded-md border border-zinc-200 bg-zinc-50 p-4">
                                    <template x-for="bar in 24" x-bind:key="bar">
                                        <span
                                            class="w-full rounded-t-sm transition-all duration-300"
                                            x-bind:class="recorderState === 'recording' ? 'bg-red-500' : recorderState === 'processed' ? 'bg-emerald-500' : 'bg-zinc-300'"
                                            x-bind:style="`height: ${recorderState === 'recording' ? 18 + ((bar * 13 + elapsedSeconds * 7) % 58) : 20 + ((bar * 9) % 35)}%`"
                                        ></span>
                                    </template>
                                </div>

                                <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    <button
                                        type="button"
                                        x-on:click="requestPermission"
                                        x-bind:disabled="recorderState !== 'idle'"
                                        class="rounded-md border border-zinc-300 px-4 py-3 text-sm font-semibold text-zinc-800 transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-45"
                                    >
                                        Minta izin
                                    </button>
                                    <button
                                        type="button"
                                        x-on:click="startRecording"
                                        x-bind:disabled="!['ready', 'stopped'].includes(recorderState)"
                                        class="rounded-md bg-red-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-45"
                                    >
                                        Rekam
                                    </button>
                                    <button
                                        type="button"
                                        x-on:click="stopRecording"
                                        x-bind:disabled="recorderState !== 'recording'"
                                        class="rounded-md bg-zinc-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-45"
                                    >
                                        Stop
                                    </button>
                                    <button
                                        type="button"
                                        x-on:click="submitAnswer"
                                        x-bind:disabled="recorderState !== 'stopped'"
                                        class="rounded-md bg-emerald-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-45"
                                    >
                                        Kirim
                                    </button>
                                </div>
                            </div>

                            <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                                <h3 class="text-base font-semibold text-zinc-950">Hasil pertanyaan</h3>
                                <div x-show="recorderState === 'processing'" class="mt-5 rounded-md border border-amber-200 bg-amber-50 p-4">
                                    <p class="text-sm font-semibold text-amber-900">AI sedang mengevaluasi</p>
                                    <p class="mt-2 text-sm leading-6 text-amber-800">Simulasi transkripsi, pronunciation, grammar, dan feedback.</p>
                                </div>

                                <div x-show="activeAnswer" class="mt-5 space-y-4">
                                    <div>
                                        <p class="text-sm font-medium text-zinc-500">Transkrip mock</p>
                                        <p class="mt-2 rounded-md bg-zinc-50 p-3 text-sm leading-6 text-zinc-800" x-text="activeAnswer?.transcript"></p>
                                    </div>
                                    <div class="flex items-center justify-between rounded-md border border-emerald-200 bg-emerald-50 p-3">
                                        <span class="text-sm font-medium text-emerald-900">Skor</span>
                                        <span class="text-2xl font-semibold text-emerald-800" x-text="activeAnswer?.score"></span>
                                    </div>
                                    <p class="text-sm leading-6 text-zinc-600" x-text="activeAnswer?.feedback"></p>
                                </div>

                                <p x-show="!activeAnswer && recorderState !== 'processing'" class="mt-5 text-sm leading-6 text-zinc-500">
                                    Hasil akan muncul setelah kandidat mengirim jawaban.
                                </p>

                                <button
                                    type="button"
                                    x-show="recorderState === 'processed'"
                                    x-on:click="nextQuestion"
                                    class="mt-5 inline-flex w-full items-center justify-center rounded-md bg-zinc-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800"
                                >
                                    <span x-text="currentIndex === questions.length - 1 ? 'Lihat hasil akhir' : 'Lanjut ke soal berikutnya'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section x-show="step === 'results'" class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-[340px_1fr]">
                    <aside class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
                        <p class="text-sm font-semibold uppercase tracking-[0.14em] text-emerald-700">Hasil akhir</p>
                        <h2 class="mt-3 text-3xl font-semibold text-zinc-950" x-text="candidate.username"></h2>
                        <p class="mt-2 text-sm text-zinc-500">Administrator</p>

                        <div class="mt-8 rounded-md bg-zinc-950 p-5 text-white">
                            <p class="text-sm font-medium text-zinc-300">Total score</p>
                            <p class="mt-2 text-5xl font-semibold" x-text="totalScore"></p>
                        </div>

                        <div class="mt-5 space-y-4 text-sm leading-6">
                            <div>
                                <p class="font-semibold text-zinc-950">Kekuatan utama</p>
                                <p class="text-zinc-600" x-text="strongestArea"></p>
                            </div>
                            <div>
                                <p class="font-semibold text-zinc-950">Area perbaikan</p>
                                <p class="text-zinc-600" x-text="improvementArea"></p>
                            </div>
                        </div>

                        <button
                            type="button"
                            x-on:click="restartDemo"
                            class="mt-6 w-full rounded-md border border-zinc-300 px-4 py-3 text-sm font-semibold text-zinc-800 transition hover:bg-zinc-50"
                        >
                            Ulangi demo
                        </button>
                    </aside>

                    <div class="rounded-lg border border-zinc-200 bg-white shadow-sm">
                        <div class="border-b border-zinc-200 p-5 sm:p-6">
                            <h3 class="text-xl font-semibold text-zinc-950">Detail jawaban</h3>
                            <p class="mt-1 text-sm text-zinc-500">Ringkasan mock untuk seluruh 10 pertanyaan.</p>
                        </div>

                        <div class="divide-y divide-zinc-200">
                            <template x-for="answer in answers" x-bind:key="answer.number">
                                <article class="grid gap-4 p-5 sm:p-6 xl:grid-cols-[1fr_110px]">
                                    <div>
                                        <p class="text-sm font-semibold text-emerald-700" x-text="`Pertanyaan ${answer.number}`"></p>
                                        <h4 class="mt-2 text-lg font-semibold leading-7 text-zinc-950" x-text="answer.question"></h4>
                                        <p class="mt-3 text-sm leading-6 text-zinc-600" x-text="answer.feedback"></p>
                                        <p class="mt-3 rounded-md bg-zinc-50 p-3 text-sm leading-6 text-zinc-800" x-text="answer.transcript"></p>
                                    </div>
                                    <div class="flex items-center justify-between gap-3 xl:block xl:text-right">
                                        <p class="text-sm text-zinc-500" x-text="formatDuration(answer.duration_seconds)"></p>
                                        <p class="text-3xl font-semibold text-zinc-950" x-text="answer.score"></p>
                                    </div>
                                </article>
                            </template>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
