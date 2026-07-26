<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('azowar.locales.'.app()->getLocale().'.rtl', false) ? 'rtl' : 'ltr' }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-neutral-950">
        <div class="grid min-h-svh lg:grid-cols-2">
            {{-- Branding panel --}}
            <div class="relative hidden overflow-hidden p-10 lg:flex lg:flex-col" style="background: linear-gradient(180deg, rgba(39, 28, 237, 1) 0%, rgba(9, 9, 121, 1) 0%, rgba(0, 212, 255, 1) 75%);">
                {{-- Tawaf backdrop: a centred Kaaba with digits circling it. The whole
                     panel is hidden below lg, so this never shows on mobile. --}}
                <canvas data-tawaf class="pointer-events-none absolute inset-0 size-full"></canvas>
                {{-- Subtle depth vignette so the headline stays readable over the backdrop --}}
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-zinc-900/10 via-transparent to-transparent"></div>

                {{-- Headline + rotating highlights --}}
                <div class="relative z-10 mt-auto max-w-md">
                    {{-- Rotating description: typewriter, anchored directly above the headline (random on load) --}}
                    <blockquote
                        data-rotating-quote
                        class="mb-6 flex min-h-28 items-end text-base leading-relaxed transition-opacity duration-700"
                        style="color: #cbd5e1; font-style: italic; text-shadow: 0 1px 3px rgba(0, 0, 0, 0.55);"
                    >
                        <p><span aria-hidden="true">&ldquo;</span><span data-quote-text></span><span aria-hidden="true">&rdquo;</span></p>
                    </blockquote>
                    <h2 class="text-3xl font-bold leading-tight text-white" style="text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);">
                        {{ __('Umrah Management System') }}
                    </h2>
                    <p class="mt-3 text-base leading-relaxed text-white/90" style="text-shadow: 0 1px 3px rgba(0, 0, 0, 0.45);">
                        {{ __('Pilgrims, packages, visas, and departures organised from first booking to safe return.') }}
                    </p>
                </div>
            </div>

            {{-- Form panel --}}
            <div class="flex flex-col items-center justify-center bg-gradient-to-br from-sky-50 via-indigo-50 to-slate-100 p-6 sm:p-10 dark:from-zinc-950 dark:via-zinc-900 dark:to-zinc-950">
                <div class="w-full" style="max-width: 24.64rem;">
                    <div
                        class="rounded-2xl border bg-white/60 p-6 backdrop-blur-xl sm:p-8 dark:bg-zinc-900/50"
                        style="border-color: rgba(59, 130, 246, 0.18); box-shadow: 0 14px 36px -10px rgba(56, 189, 248, 0.22), 0 4px 16px rgba(59, 130, 246, 0.08);"
                    >
                        <div class="auth-fields flex flex-col gap-6">
                            {{ $slot }}
                        </div>
                    </div>

                    <p class="mt-6 text-center text-xs text-zinc-500 dark:text-zinc-400">
                        &copy; {{ date('Y') }} Computerize&reg;. {{ __('All rights reserved.') }}
                        <a
                            href="https://www.computerize.biz"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="underline underline-offset-2 transition hover:text-zinc-700 dark:hover:text-zinc-200"
                        >www.computerize.biz</a>
                    </p>
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        {{-- Auth field styling: subtle gradient + a softer dotted grey focus outline --}}
        <style>
            .auth-fields input,
            .auth-fields textarea,
            .auth-fields select {
                background-image: linear-gradient(180deg, #ffffff, #f8fafc);
            }

            .auth-fields input:focus,
            .auth-fields input:focus-visible,
            .auth-fields textarea:focus,
            .auth-fields textarea:focus-visible,
            .auth-fields select:focus,
            .auth-fields select:focus-visible {
                outline: 1px dotted #9ca3af !important;
                outline-offset: 2px !important;
                border-color: #cbd5e1 !important;
                box-shadow: none !important;
                background-image: linear-gradient(180deg, #f0f9ff, #eff6ff) !important;
            }

            .dark .auth-fields input,
            .dark .auth-fields textarea,
            .dark .auth-fields select {
                background-image: linear-gradient(180deg, #1f2937, #111827);
            }

            .dark .auth-fields input:focus,
            .dark .auth-fields input:focus-visible,
            .dark .auth-fields textarea:focus,
            .dark .auth-fields textarea:focus-visible,
            .dark .auth-fields select:focus,
            .dark .auth-fields select:focus-visible {
                outline: 1px dotted #6b7280 !important;
                border-color: #374151 !important;
                background-image: linear-gradient(180deg, #1e293b, #0f172a) !important;
            }

            /* Typewriter caret for the rotating messages */
            [data-rotating-quote] [data-quote-text]::after {
                content: '▋';
                margin-inline-start: 2px;
                color: #94a3b8;
                animation: tw-blink 1s step-end infinite;
            }

            @keyframes tw-blink {
                50% { opacity: 0; }
            }
        </style>

        {{-- Rotating descriptions: typed out, held, erased, then a new one at random --}}
        <script>
            (function () {
                const QUOTES = @json(config('azowar.auth_quotes.'.app()->getLocale(), config('azowar.auth_quotes.en', [])));

                function rotateQuotes() {
                    const block = document.querySelector('[data-rotating-quote]');
                    const target = block ? block.querySelector('[data-quote-text]') : null;
                    if (!block || !target || block.dataset.running || QUOTES.length === 0) {
                        return;
                    }
                    block.dataset.running = '1';
                    block.style.opacity = '1';

                    const TYPE_MS = 32;   // per character while typing
                    const ERASE_MS = 14;  // per character while erasing
                    const HOLD_MS = 4000; // fully-typed sentence stays this long
                    const GAP_MS = 400;   // pause before typing the next one

                    let index = Math.floor(Math.random() * QUOTES.length);
                    const timers = [];

                    function alive() {
                        if (document.body.contains(block)) {
                            return true;
                        }
                        timers.forEach(clearTimeout);
                        return false;
                    }

                    function later(fn, ms) {
                        timers.push(setTimeout(fn, ms));
                    }

                    function type(text, i) {
                        if (!alive()) return;
                        target.textContent = text.slice(0, i);
                        if (i < text.length) {
                            later(() => type(text, i + 1), TYPE_MS);
                        } else {
                            later(() => erase(text, text.length), HOLD_MS);
                        }
                    }

                    function erase(text, i) {
                        if (!alive()) return;
                        target.textContent = text.slice(0, i);
                        if (i > 0) {
                            later(() => erase(text, i - 1), ERASE_MS);
                        } else {
                            let next = index;
                            while (QUOTES.length > 1 && next === index) {
                                next = Math.floor(Math.random() * QUOTES.length);
                            }
                            index = next;
                            later(() => type(QUOTES[index], 0), GAP_MS);
                        }
                    }

                    type(QUOTES[index], 0);
                }

                document.addEventListener('DOMContentLoaded', rotateQuotes);
                document.addEventListener('livewire:navigated', rotateQuotes);
                rotateQuotes();
            })();
        </script>

        {{-- Backdrop: a centred Kaaba-like cube with digits circling it in tawaf. --}}
        <script>
            (function () {
                function startTawaf() {
                    const canvas = document.querySelector('canvas[data-tawaf]');
                    if (!canvas || canvas.dataset.running) {
                        return;
                    }
                    canvas.dataset.running = '1';

                    const ctx = canvas.getContext('2d');
                    const DIGITS = '01';       // binary — 1s and 0s in tawaf
                    const COUNT = 90;          // circling digits
                    const RINGS = 5;           // concentric orbits
                    const TILT = 0.42;         // vertical squash, so orbits read as a floor plane, not flat rings

                    let cx = 0, cy = 0, cube = 0, frame = 0;
                    let digits = [];

                    function seed() {
                        digits = Array.from({ length: COUNT }, () => {
                            const ring = Math.floor(Math.random() * RINGS);

                            return {
                                char: DIGITS[Math.floor(Math.random() * DIGITS.length)],
                                angle: Math.random() * Math.PI * 2,
                                ring,
                                // Outer rings drift slower, like a real crowd.
                                speed: (0.0075 - ring * 0.0009) * (0.75 + Math.random() * 0.5),
                                wobble: Math.random() * Math.PI * 2,
                            };
                        });
                    }

                    function resize() {
                        const rect = canvas.getBoundingClientRect();
                        const dpr = window.devicePixelRatio || 1;
                        canvas.width = rect.width * dpr;
                        canvas.height = rect.height * dpr;
                        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

                        cx = rect.width / 2;
                        cy = rect.height / 2;
                        cube = Math.max(34, Math.min(rect.width, rect.height) * 0.11);
                    }

                    function ringRadius(ring) {
                        return cube * (1.85 + ring * 0.62);
                    }

                    /** One digit, positioned on its elliptical orbit. */
                    function drawDigit(d) {
                        const r = ringRadius(d.ring) + Math.sin(d.wobble + frame * 0.02) * 3;
                        const x = cx + Math.cos(d.angle) * r;
                        const y = cy + Math.sin(d.angle) * r * TILT;

                        // sin(angle) > 0 is the near side of the orbit: bigger and brighter.
                        const depth = (Math.sin(d.angle) + 1) / 2;
                        const size = 9 + depth * 7;

                        ctx.font = size + 'px monospace';
                        ctx.fillStyle = 'rgba(224, 242, 254, ' + (0.25 + depth * 0.6).toFixed(3) + ')';
                        ctx.fillText(d.char, x, y);
                    }

                    /** The Kaaba: isometric cube, kiswah black, gold hizam band and door. */
                    function drawCube() {
                        const w = cube;            // half-width
                        const h = cube * 0.46;     // half-depth of the top face
                        const v = cube * 1.18;     // vertical height of the side faces

                        const topN = [cx, cy - h - v * 0.5];
                        const topE = [cx + w, cy - v * 0.5];
                        const topS = [cx, cy + h - v * 0.5];
                        const topW = [cx - w, cy - v * 0.5];
                        const botS = [cx, cy + h + v * 0.5];
                        const botW = [cx - w, cy - v * 0.5 + v];
                        const botE = [cx + w, cy - v * 0.5 + v];

                        const path = (pts) => {
                            ctx.beginPath();
                            ctx.moveTo(pts[0][0], pts[0][1]);
                            for (let i = 1; i < pts.length; i++) ctx.lineTo(pts[i][0], pts[i][1]);
                            ctx.closePath();
                        };
                        const grad = (x1, y1, x2, y2, c1, c2) => {
                            const g = ctx.createLinearGradient(x1, y1, x2, y2);
                            g.addColorStop(0, c1);
                            g.addColorStop(1, c2);

                            return g;
                        };
                        const edge = Math.max(0.6, cube * 0.02);

                        // Ground shadow so the cube sits on the tawaf plane — broad and faint.
                        ctx.save();
                        ctx.globalAlpha = 0.14;
                        ctx.fillStyle = '#000';
                        ctx.beginPath();
                        ctx.ellipse(cx, cy + v * 0.58, w * 1.85, h * 2, 0, 0, Math.PI * 2);
                        ctx.filter = 'blur(6px)';
                        ctx.fill();
                        ctx.restore();

                        // Faces, each with a subtle gradient and a crisp edge.
                        path([topN, topE, topS, topW]);
                        ctx.fillStyle = grad(cx, topN[1], cx, topS[1], '#3c3c45', '#2a2a31');
                        ctx.fill();
                        ctx.lineWidth = edge; ctx.strokeStyle = '#4a4a54'; ctx.stroke();

                        path([topW, topS, botS, botW]);           // left face (lit)
                        ctx.fillStyle = grad(topW[0], topW[1], botS[0], botS[1], '#1d1d22', '#121216');
                        ctx.fill();
                        ctx.lineWidth = edge; ctx.strokeStyle = '#26262d'; ctx.stroke();

                        path([topS, topE, botE, botS]);           // right face (shadowed)
                        ctx.fillStyle = grad(topE[0], topE[1], botS[0], botS[1], '#101014', '#070709');
                        ctx.fill();
                        ctx.lineWidth = edge; ctx.strokeStyle = '#1a1a1f'; ctx.stroke();

                        // Front vertical edge catches a faint highlight.
                        ctx.beginPath();
                        ctx.moveTo(topS[0], topS[1]); ctx.lineTo(botS[0], botS[1]);
                        ctx.lineWidth = edge; ctx.strokeStyle = 'rgba(120,120,132,0.5)'; ctx.stroke();

                        // Hizam + door, as fractions of face height so they follow each face's slope.
                        const left = (t, f) => [cx - w + w * t, (cy - v * 0.5) + h * t + v * f];
                        const right = (t, f) => [cx + w * t, (cy + h - v * 0.5) - h * t + v * f];
                        const band = 0.30, bandLo = 0.44;

                        // Band with a bright top edge and a darker lower edge for relief.
                        path([left(0, band), left(1, band), left(1, bandLo), left(0, bandLo)]);
                        ctx.fillStyle = grad(cx, cy + h * band, cx, cy + h * bandLo, '#e8cd6a', '#c39a34');
                        ctx.fill();
                        path([right(0, band), right(1, band), right(1, bandLo), right(0, bandLo)]);
                        ctx.fillStyle = grad(cx, cy + h * band, cx, cy + h * bandLo, '#c9a445', '#9d7d29');
                        ctx.fill();

                        // Door: gold panel with a thin inset frame.
                        const dl = 0.46, dr = 0.72, dt = 0.52, db = 0.88;
                        path([right(dl, dt), right(dr, dt), right(dr, db), right(dl, db)]);
                        ctx.fillStyle = grad(cx, cy + h * dt, cx, cy + h * db, '#d9b64a', '#a9842f');
                        ctx.fill();
                        ctx.lineWidth = edge * 0.8; ctx.strokeStyle = '#f0d888'; ctx.stroke();
                    }

                    function draw() {
                        // Stop and clean up once the panel leaves the DOM (e.g. wire:navigate).
                        if (!document.body.contains(canvas)) {
                            window.removeEventListener('resize', resize);

                            return;
                        }

                        // Idle cheaply while the panel is hidden (e.g. mobile: display:none)
                        // — keep the loop alive so it resumes if the viewport grows.
                        if (canvas.offsetParent === null) {
                            requestAnimationFrame(draw);

                            return;
                        }

                        const rect = canvas.getBoundingClientRect();
                        ctx.clearRect(0, 0, rect.width, rect.height);
                        frame++;

                        // Tawaf runs anticlockwise, so the angle decreases.
                        for (const d of digits) {
                            d.angle -= d.speed;
                        }

                        // Far half first, then the cube, then the near half — so the crowd
                        // passes behind and in front of the Kaaba rather than through it.
                        for (const d of digits) {
                            if (Math.sin(d.angle) <= 0) drawDigit(d);
                        }
                        drawCube();
                        for (const d of digits) {
                            if (Math.sin(d.angle) > 0) drawDigit(d);
                        }

                        requestAnimationFrame(draw);
                    }

                    seed();
                    resize();
                    window.addEventListener('resize', resize);
                    requestAnimationFrame(draw);
                }

                document.addEventListener('DOMContentLoaded', startTawaf);
                document.addEventListener('livewire:navigated', startTawaf);
                startTawaf();
            })();
        </script>

        @fluxScripts
    </body>
</html>
