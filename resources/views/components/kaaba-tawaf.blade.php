{{--
    Animated Kaaba-with-tawaf icon: a small isometric Kaaba with digits circling it,
    the same motion as the auth backdrop, shrunk to an icon. Sizes off font-size, so set
    a text-* class or an inline font-size. Digits are tuned to read on a light surface.
--}}
<span {{ $attributes->class('kaaba-tawaf')->merge(['aria-hidden' => 'true']) }}>
    <canvas data-kaaba-tawaf></canvas>
</span>

@once
    <style>
        .kaaba-tawaf {
            display: inline-block;
            width: 1em;
            height: 1em;
            font-size: 3rem; /* default size; override per usage */
        }

        .kaaba-tawaf canvas {
            width: 100%;
            height: 100%;
            display: block;
        }
    </style>

    <script>
        (function () {
            const DIGITS = '01'; // binary — 1s and 0s in tawaf

            function initIcon(canvas) {
                if (canvas.dataset.running) {
                    return;
                }
                canvas.dataset.running = '1';

                const ctx = canvas.getContext('2d');
                const COUNT = 16;
                const RINGS = 2;
                const TILT = 0.42;

                let cx = 0, cy = 0, cube = 0, frame = 0;
                let digits = Array.from({ length: COUNT }, () => {
                    const ring = Math.floor(Math.random() * RINGS);

                    return {
                        char: DIGITS[Math.floor(Math.random() * DIGITS.length)],
                        angle: Math.random() * Math.PI * 2,
                        ring,
                        speed: (0.020 - ring * 0.004) * (0.75 + Math.random() * 0.5),
                        wobble: Math.random() * Math.PI * 2,
                    };
                });

                function resize() {
                    const rect = canvas.getBoundingClientRect();
                    const dpr = window.devicePixelRatio || 1;
                    canvas.width = Math.max(1, rect.width * dpr);
                    canvas.height = Math.max(1, rect.height * dpr);
                    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

                    cx = rect.width / 2;
                    cy = rect.height / 2;
                    cube = Math.min(rect.width, rect.height) * 0.2;
                }

                function ringRadius(ring) {
                    return cube * (1.7 + ring * 0.7);
                }

                function drawDigit(d) {
                    const r = ringRadius(d.ring) + Math.sin(d.wobble + frame * 0.03) * 1.5;
                    const x = cx + Math.cos(d.angle) * r;
                    const y = cy + Math.sin(d.angle) * r * TILT;
                    const depth = (Math.sin(d.angle) + 1) / 2;
                    const size = Math.max(6, cube * (0.42 + depth * 0.4));

                    ctx.font = size + 'px monospace';
                    // sky-600, deepening on the near side — reads on the light card.
                    ctx.fillStyle = 'rgba(2, 132, 199, ' + (0.3 + depth * 0.55).toFixed(3) + ')';
                    ctx.fillText(d.char, x, y);
                }

                function drawCube() {
                    const w = cube, h = cube * 0.46, v = cube * 1.18;
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
                    const edge = Math.max(0.5, cube * 0.03);

                    // Soft contact shadow.
                    ctx.save();
                    ctx.globalAlpha = 0.18;
                    ctx.fillStyle = '#0f172a';
                    ctx.beginPath();
                    ctx.ellipse(cx, cy + v * 0.58, w * 1.5, h * 1.55, 0, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.restore();

                    path([topN, topE, topS, topW]);
                    ctx.fillStyle = grad(cx, topN[1], cx, topS[1], '#3c3c45', '#2a2a31');
                    ctx.fill();
                    ctx.lineWidth = edge; ctx.strokeStyle = '#4a4a54'; ctx.stroke();

                    path([topW, topS, botS, botW]);
                    ctx.fillStyle = grad(topW[0], topW[1], botS[0], botS[1], '#1d1d22', '#121216');
                    ctx.fill();
                    ctx.lineWidth = edge; ctx.strokeStyle = '#26262d'; ctx.stroke();

                    path([topS, topE, botE, botS]);
                    ctx.fillStyle = grad(topE[0], topE[1], botS[0], botS[1], '#101014', '#070709');
                    ctx.fill();
                    ctx.lineWidth = edge; ctx.strokeStyle = '#1a1a1f'; ctx.stroke();

                    ctx.beginPath();
                    ctx.moveTo(topS[0], topS[1]); ctx.lineTo(botS[0], botS[1]);
                    ctx.lineWidth = edge; ctx.strokeStyle = 'rgba(120,120,132,0.5)'; ctx.stroke();

                    const left = (t, f) => [cx - w + w * t, (cy - v * 0.5) + h * t + v * f];
                    const right = (t, f) => [cx + w * t, (cy + h - v * 0.5) - h * t + v * f];
                    const band = 0.30, bandLo = 0.44;

                    path([left(0, band), left(1, band), left(1, bandLo), left(0, bandLo)]);
                    ctx.fillStyle = grad(cx, cy + h * band, cx, cy + h * bandLo, '#e8cd6a', '#c39a34');
                    ctx.fill();
                    path([right(0, band), right(1, band), right(1, bandLo), right(0, bandLo)]);
                    ctx.fillStyle = grad(cx, cy + h * band, cx, cy + h * bandLo, '#c9a445', '#9d7d29');
                    ctx.fill();

                    const dl = 0.46, dr = 0.72, dt = 0.52, db = 0.88;
                    path([right(dl, dt), right(dr, dt), right(dr, db), right(dl, db)]);
                    ctx.fillStyle = grad(cx, cy + h * dt, cx, cy + h * db, '#d9b64a', '#a9842f');
                    ctx.fill();
                    ctx.lineWidth = edge * 0.8; ctx.strokeStyle = '#f0d888'; ctx.stroke();
                }

                const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                function draw() {
                    if (!document.body.contains(canvas)) {
                        window.removeEventListener('resize', resize);

                        return;
                    }
                    if (canvas.offsetParent === null) {
                        requestAnimationFrame(draw);

                        return;
                    }

                    const rect = canvas.getBoundingClientRect();
                    ctx.clearRect(0, 0, rect.width, rect.height);
                    frame++;

                    if (!reduce) {
                        for (const d of digits) d.angle -= d.speed;
                    }

                    for (const d of digits) if (Math.sin(d.angle) <= 0) drawDigit(d);
                    drawCube();
                    for (const d of digits) if (Math.sin(d.angle) > 0) drawDigit(d);

                    requestAnimationFrame(draw);
                }

                resize();
                window.addEventListener('resize', resize);
                requestAnimationFrame(draw);
            }

            function initAll() {
                document.querySelectorAll('canvas[data-kaaba-tawaf]').forEach(initIcon);
            }

            document.addEventListener('DOMContentLoaded', initAll);
            document.addEventListener('livewire:navigated', initAll);
            initAll();
        })();
    </script>
@endonce
