<!DOCTYPE html>
<html lang="es" class="no-js">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="theme-color" content="#F4FAF8">
  <title>ClinicApp | Gestión clínica modular para Guatemala</title>
  <meta name="description" content="Sistema de gestión clínica modular para Guatemala. Elegí solo los módulos que necesités. Implementación en 11 días.">

  <meta property="og:title" content="ClinicApp RS Solutions | Gestión clínica modular">
  <meta property="og:description" content="Sistema de gestión clínica modular para Guatemala. Elegí solo los módulos que necesités.">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://clinicapp.gt/">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="ClinicApp RS Solutions | Gestión clínica modular">
  <meta name="twitter:description" content="Sistema de gestión clínica modular para Guatemala.">

  <link rel="icon" href="logo.png" type="image/png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap">

  <script>document.documentElement.classList.replace('no-js', 'js');</script>

  <style>
    /* ================================================================
       ClinicApp — página de presentación
       Concepto "tejido vivo": partículas que forman un latido,
       una forma líquida que respira y vidrio solo donde hay algo detrás.
       ================================================================ */
    :root {
      --teal-950: #062E2E;
      --teal-900: #08403F;
      --teal-800: #0A4F4F;
      --teal-700: #0C5C5C;   /* color de marca */
      --teal-600: #11706E;
      --teal-500: #1A8C87;
      --teal-300: #7CC9BE;
      --mint-100: #E6F4EF;
      --mint-50:  #F4FAF8;
      --gold-500: #F5B85A;   /* acento de marca */
      --gold-600: #D9962F;
      --ink:      #0F2A2A;
      --ink-2:    #4A6361;
      --line:     rgba(12, 92, 92, .14);
      --radius:   18px;
      --shadow:   0 18px 40px -22px rgba(8, 64, 63, .35);
      --font:     'Sora', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
      --ease:     cubic-bezier(.2, .8, .2, 1);
    }

    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
    body {
      margin: 0;
      font-family: var(--font);
      color: var(--ink);
      background: var(--mint-50);
      line-height: 1.6;
      -webkit-font-smoothing: antialiased;
      overflow-x: hidden;
    }
    body.menu-open { overflow: hidden; }
    img, svg, canvas { display: block; }
    img { max-width: 100%; height: auto; }
    a { color: inherit; text-decoration: none; }
    button, input, textarea { font: inherit; color: inherit; }
    button { cursor: pointer; background: none; border: 0; padding: 0; }
    h1, h2, h3, p { margin: 0; }
    ul { margin: 0; padding: 0; list-style: none; }
    [hidden] { display: none !important; }

    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
    .skip { position: absolute; left: 1rem; top: -4rem; z-index: 100; background: var(--teal-700); color: #fff; padding: .6rem 1rem; border-radius: 10px; }
    .skip:focus { top: 1rem; }
    :focus-visible { outline: 3px solid var(--gold-500); outline-offset: 3px; border-radius: 6px; }

    .wrap { width: 100%; max-width: 1200px; margin: 0 auto; padding: 0 1.25rem; }
    .icon { width: 1.25em; height: 1.25em; flex: none; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

    /* ── Vidrio ──────────────────────────────────────────────────── */
    .glass {
      background: rgba(255, 255, 255, .62);
      border: 1px solid rgba(255, 255, 255, .75);
      box-shadow: var(--shadow), inset 0 1px 0 rgba(255, 255, 255, .8);
    }
    @supports ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
      .glass {
        background: rgba(255, 255, 255, .48);
        -webkit-backdrop-filter: blur(18px) saturate(150%);
        backdrop-filter: blur(18px) saturate(150%);
      }
    }

    /* ── Botones ─────────────────────────────────────────────────── */
    .btn {
      --x: 50%; --y: 50%;
      position: relative;
      isolation: isolate;
      overflow: hidden;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .55rem;
      min-height: 52px;
      padding: 0 1.5rem;
      border-radius: 999px;
      font-weight: 600;
      font-size: .98rem;
      transition: transform .2s var(--ease), box-shadow .2s var(--ease), color .2s;
    }
    .btn:active { transform: translateY(1px); }
    /* Relleno líquido que nace desde el puntero */
    .btn::before {
      content: "";
      position: absolute;
      z-index: -1;
      left: var(--x); top: var(--y);
      width: 0; height: 0;
      border-radius: 42% 58% 55% 45% / 48% 42% 58% 52%;
      transform: translate(-50%, -50%);
      transition: width .55s var(--ease), height .55s var(--ease);
    }
    .btn:hover::before { width: 260%; height: 520%; animation: wobble 2.4s linear infinite; }
    @keyframes wobble { to { transform: translate(-50%, -50%) rotate(360deg); } }

    .btn-primary { background: var(--teal-700); color: #fff; box-shadow: 0 12px 26px -12px rgba(12, 92, 92, .7); }
    .btn-primary::before { background: var(--teal-900); }
    .btn-ghost { background: rgba(255, 255, 255, .6); color: var(--teal-800); border: 1px solid var(--line); }
    .btn-ghost::before { background: var(--mint-100); }
    .btn-gold { background: var(--gold-500); color: var(--teal-950); }
    .btn-gold::before { background: #FFD08A; }
    .btn .icon { transition: transform .2s var(--ease); }
    .btn:hover .icon-arrow { transform: translateX(3px); }

    /* ── Navegación ──────────────────────────────────────────────── */
    .nav {
      position: fixed;
      top: .75rem; left: 0; right: 0;
      z-index: 50;
      padding: 0 1rem;
      transition: top .3s var(--ease);
    }
    .nav-bar {
      max-width: 1200px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      height: 64px;
      padding: 0 .65rem 0 1rem;
      border-radius: 999px;
      transition: box-shadow .3s, background .3s;
    }
    .nav.scrolled .nav-bar { box-shadow: 0 14px 34px -20px rgba(8, 64, 63, .45), inset 0 1px 0 rgba(255,255,255,.8); }
    .brand { display: flex; align-items: center; gap: .6rem; font-weight: 700; color: var(--teal-800); letter-spacing: -.01em; }
    .brand img { width: 34px; height: 34px; }
    .brand small { font-weight: 500; color: var(--ink-2); font-size: .8rem; display: block; line-height: 1; }
    .nav-links { display: none; align-items: center; gap: .25rem; }
    .nav-links a { padding: .55rem .85rem; border-radius: 999px; font-size: .92rem; color: var(--ink-2); font-weight: 500; transition: color .2s, background .2s; }
    .nav-links a:hover { color: var(--teal-800); background: rgba(12, 92, 92, .07); }
    .nav-links a.btn-primary, .nav-links a.btn-primary:hover { color: #fff; background: var(--teal-700); }
    .nav-links .btn { min-height: 44px; padding: 0 1.15rem; margin-left: .5rem; font-size: .92rem; }
    .menu-btn { width: 46px; height: 46px; border-radius: 999px; display: grid; place-items: center; color: var(--teal-800); }
    .menu-btn:hover { background: rgba(12, 92, 92, .08); }

    .mobile-menu {
      position: fixed;
      inset: 0;
      z-index: 60;
      display: flex;
      flex-direction: column;
      padding: 5.5rem 1.5rem 2rem;
      background: rgba(244, 250, 248, .92);
      -webkit-backdrop-filter: blur(16px);
      backdrop-filter: blur(16px);
    }
    .mobile-menu a { font-size: 1.5rem; font-weight: 600; padding: .85rem 0; border-bottom: 1px solid var(--line); color: var(--teal-900); }
    .mobile-menu .btn { margin-top: 1.5rem; border-bottom: 0; font-size: 1rem; color: #fff; }
    .mobile-close { position: absolute; top: 1.4rem; right: 1.4rem; width: 46px; height: 46px; border-radius: 999px; display: grid; place-items: center; color: var(--teal-800); }

    /* ── Inicio ──────────────────────────────────────────────────── */
    .hero {
      position: relative;
      min-height: 100vh;
      min-height: 100svh;
      padding: 7.5rem 0 9.5rem;
      overflow: hidden;
      background:
        radial-gradient(60% 50% at 85% 20%, rgba(124, 201, 190, .35), transparent 70%),
        radial-gradient(45% 40% at 10% 90%, rgba(245, 184, 90, .14), transparent 70%),
        var(--mint-50);
    }
    #particles { position: absolute; inset: 0; width: 100%; height: 100%; z-index: 0; }
    .hero-grid {
      position: relative;
      z-index: 1;
      display: grid;
      gap: 3rem;
      align-items: center;
    }
    .pill {
      display: inline-flex;
      align-items: center;
      gap: .5rem;
      padding: .4rem .9rem .4rem .5rem;
      border-radius: 999px;
      font-size: .85rem;
      font-weight: 500;
      color: var(--teal-800);
    }
    .pill .dot { width: 22px; height: 22px; border-radius: 50%; background: var(--teal-700); display: grid; place-items: center; }
    .pill .dot::after { content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--gold-500); animation: beat 1.2s ease-in-out infinite; }
    @keyframes beat { 0%, 100% { transform: scale(1); } 15% { transform: scale(1.5); } 30% { transform: scale(1); } 45% { transform: scale(1.3); } }

    .hero h1 {
      margin-top: 1.25rem;
      font-size: clamp(2.3rem, 5.4vw, 4.1rem);
      line-height: 1.04;
      font-weight: 700;
      letter-spacing: -.035em;
      color: var(--teal-900);
      max-width: 16ch;
      text-wrap: balance;
    }
    .hero-lede { margin-top: 1.4rem; font-size: clamp(1.02rem, 1.6vw, 1.15rem); color: var(--ink-2); max-width: 46ch; }
    .hero-ctas { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 2rem; }
    .trust { display: flex; flex-wrap: wrap; gap: .5rem 1.25rem; margin-top: 1.75rem; font-size: .88rem; color: var(--ink-2); }
    .trust span { display: inline-flex; align-items: center; gap: .4rem; }
    .trust .icon { color: var(--teal-600); width: 1.05em; height: 1.05em; }

    /* Forma líquida + panel de vidrio */
    .hero-visual { position: relative; min-height: 420px; display: grid; place-items: center; }
    .liquid { position: absolute; width: min(600px, 125%); aspect-ratio: 1; left: 60%; top: 46%; transform: translate(-50%, -50%); }
    .liquid svg { width: 100%; height: 100%; overflow: visible; }
    .hero-card {
      position: relative;
      width: min(400px, 100%);
      border-radius: 24px;
      padding: 1.4rem;
    }
    .hc-head { display: flex; align-items: center; gap: .75rem; padding-bottom: 1rem; border-bottom: 1px solid rgba(12, 92, 92, .12); }
    .hc-head img { width: 36px; height: 36px; border-radius: 10px; background: #fff; padding: 4px; }
    .hc-title { font-weight: 600; font-size: .95rem; color: var(--teal-900); }
    .hc-sub { font-size: .78rem; color: var(--ink-2); }
    .hc-live { margin-left: auto; display: inline-flex; align-items: center; gap: .35rem; font-size: .72rem; font-weight: 600; color: var(--teal-700); background: rgba(255,255,255,.7); padding: .25rem .6rem; border-radius: 999px; }
    .hc-live::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: #1FB57A; box-shadow: 0 0 0 4px rgba(31, 181, 122, .18); }
    .hc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .7rem; margin-top: 1rem; }
    .hc-stat { background: rgba(255, 255, 255, .7); border: 1px solid rgba(255,255,255,.9); border-radius: 14px; padding: .9rem; }
    .hc-stat b { display: block; font-size: 1.5rem; line-height: 1.1; font-weight: 700; color: var(--teal-800); letter-spacing: -.02em; }
    .hc-stat span { font-size: .78rem; color: var(--ink-2); }
    .hc-ecg { margin-top: 1rem; height: 46px; border-radius: 12px; background: rgba(12, 92, 92, .06); overflow: hidden; }
    .hc-ecg path { stroke: var(--teal-600); stroke-width: 2; fill: none; stroke-dasharray: 600; stroke-dashoffset: 600; animation: draw 2.4s linear infinite; }
    @keyframes draw { to { stroke-dashoffset: 0; } }
    .hc-foot { margin-top: .9rem; font-size: .78rem; color: var(--ink-2); display: flex; align-items: center; gap: .4rem; }

    .float-chip {
      position: absolute;
      display: flex;
      align-items: center;
      gap: .55rem;
      padding: .6rem .85rem;
      border-radius: 14px;
      font-size: .8rem;
      font-weight: 500;
      color: var(--teal-900);
      animation: float 6s ease-in-out infinite;
    }
    .float-chip .ic { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; background: var(--teal-700); color: #fff; }
    .float-chip.gold .ic { background: var(--gold-500); color: var(--teal-950); }
    .chip-1 { top: 6%; left: -2%; }
    .chip-2 { bottom: 8%; right: -3%; animation-delay: -3s; }
    @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }

    .scroll-cue { position: absolute; left: 50%; bottom: 1.25rem; transform: translateX(-50%); z-index: 1; width: 26px; height: 42px; border-radius: 999px; border: 2px solid rgba(12, 92, 92, .3); }
    .scroll-cue::after { content: ""; position: absolute; left: 50%; top: 8px; width: 4px; height: 8px; margin-left: -2px; border-radius: 4px; background: var(--teal-700); animation: cue 1.8s var(--ease) infinite; }
    @keyframes cue { 0% { opacity: 0; transform: translateY(-4px); } 40% { opacity: 1; } 100% { opacity: 0; transform: translateY(12px); } }

    /* ── Cifras ──────────────────────────────────────────────────── */
    .stats { position: relative; z-index: 2; margin-top: -2.5rem; }
    .stats-inner {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      border-radius: 24px;
      overflow: hidden;
      background: var(--teal-800);
      color: #fff;
      box-shadow: 0 30px 60px -30px rgba(6, 46, 46, .6);
    }
    .stat { padding: 1.6rem 1.25rem; text-align: center; position: relative; }
    .stat + .stat::before { content: ""; position: absolute; left: 0; top: 25%; bottom: 25%; width: 1px; background: rgba(255,255,255,.15); }
    .stat:nth-child(3)::before { display: none; }
    .stat b { display: block; font-size: clamp(1.8rem, 4vw, 2.6rem); line-height: 1.1; font-weight: 700; letter-spacing: -.03em; font-variant-numeric: tabular-nums; }
    .stat span { font-size: .85rem; color: rgba(255,255,255,.72); }

    /* ── Secciones ───────────────────────────────────────────────── */
    .section { position: relative; padding: 6rem 0; overflow: hidden; }
    .section-head { max-width: 720px; margin: 0 auto 3rem; text-align: center; }
    .section-head h2 { font-size: clamp(1.9rem, 4.2vw, 3rem); line-height: 1.1; font-weight: 700; letter-spacing: -.03em; color: var(--teal-900); }
    .section-head p { margin-top: 1rem; font-size: 1.05rem; color: var(--ink-2); }
    .kicker { display: block; margin-bottom: .85rem; font-size: .9rem; font-weight: 600; color: var(--teal-600); }

    /* Fondo líquido: manchas que cambian de forma detrás del vidrio */
    .blobs { position: absolute; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
    .blob {
      position: absolute;
      filter: blur(40px);
      opacity: .55;
      border-radius: 42% 58% 63% 37% / 41% 44% 56% 59%;
      animation: morph 18s ease-in-out infinite alternate;
      will-change: border-radius, transform;
    }
    .blob.b1 { width: 520px; height: 520px; background: #9ED9CE; top: -120px; left: -140px; }
    .blob.b2 { width: 440px; height: 440px; background: #F9D9A6; bottom: -160px; right: -120px; animation-delay: -6s; }
    .blob.b3 { width: 360px; height: 360px; background: #BFE6DE; top: 40%; left: 55%; animation-delay: -11s; }
    @keyframes morph {
      0%   { border-radius: 42% 58% 63% 37% / 41% 44% 56% 59%; transform: translate(0, 0) rotate(0deg); }
      50%  { border-radius: 63% 37% 39% 61% / 55% 63% 37% 45%; transform: translate(40px, -30px) rotate(40deg); }
      100% { border-radius: 38% 62% 51% 49% / 36% 50% 50% 64%; transform: translate(-20px, 30px) rotate(-20deg); }
    }
    .section > .wrap { position: relative; z-index: 1; }

    /* ── Características ─────────────────────────────────────────── */
    .features { display: grid; gap: 1.1rem; }
    .feature {
      border-radius: var(--radius);
      padding: 1.5rem;
      background: #fff;
      border: 1px solid var(--line);
      display: flex;
      flex-direction: column;
      gap: 1.25rem;
    }
    .feature-ic { width: 46px; height: 46px; border-radius: 14px; display: grid; place-items: center; background: var(--mint-100); color: var(--teal-700); }
    .feature-ic .icon { width: 22px; height: 22px; }
    .feature h3 { font-size: 1.15rem; font-weight: 600; color: var(--teal-900); }
    .feature p { margin-top: .4rem; font-size: .95rem; color: var(--ink-2); }
    /* Mini pantallas */
    .mock { margin-top: auto; border-radius: 14px; background: var(--mint-50); border: 1px solid var(--line); padding: .8rem; font-size: .76rem; }
    .mock-bar { display: flex; align-items: center; gap: .3rem; margin-bottom: .65rem; color: var(--ink-2); font-weight: 600; font-size: .72rem; }
    .mock-bar i { width: 7px; height: 7px; border-radius: 50%; background: #CFE3DE; }
    .mock-bar i:first-child { background: #F3A89C; } .mock-bar i:nth-child(2) { background: #F5D07A; } .mock-bar i:nth-child(3) { background: #9BD8B2; }
    .mock-bar em { font-style: normal; margin-left: .3rem; }
    .mock-kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: .35rem; }
    .mock-kpis div { background: #fff; border-radius: 9px; padding: .45rem .35rem; text-align: center; }
    .mock-kpis b { display: block; font-size: .9rem; color: var(--teal-800); }
    .mock-kpis span { font-size: .62rem; color: var(--ink-2); }
    .mock-bars { display: flex; align-items: flex-end; gap: 4px; height: 40px; margin: .6rem 0; }
    .mock-bars span { flex: 1; border-radius: 4px 4px 0 0; background: linear-gradient(var(--teal-500), var(--teal-300)); transform-origin: bottom; }
    .mock-row { display: flex; justify-content: space-between; align-items: center; gap: .5rem; background: #fff; border-radius: 9px; padding: .4rem .55rem; margin-top: .3rem; }
    .tag { font-size: .66rem; font-weight: 600; padding: .12rem .45rem; border-radius: 999px; background: var(--mint-100); color: var(--teal-700); white-space: nowrap; }
    .tag.amber { background: #FFF1D6; color: #8A5A0B; }
    .tag.gray { background: #EEF1F1; color: #5F6F6E; }
    .tag.blue { background: #E2F1FB; color: #1E5E86; }
    .toggle { width: 26px; height: 15px; border-radius: 999px; background: var(--teal-600); position: relative; flex: none; }
    .toggle::after { content: ""; position: absolute; right: 2px; top: 2px; width: 11px; height: 11px; border-radius: 50%; background: #fff; }
    .toggle.off { background: #CBD8D6; }
    .toggle.off::after { right: auto; left: 2px; }
    .swatches { display: flex; gap: .4rem; margin: .35rem 0 .55rem; }
    .swatches i { width: 22px; height: 22px; border-radius: 7px; }
    .org { display: flex; align-items: center; gap: .55rem; background: #fff; border-radius: 9px; padding: .45rem .55rem; margin-top: .3rem; }
    .org i { width: 24px; height: 24px; border-radius: 7px; flex: none; }
    .org b { display: block; font-size: .74rem; color: var(--ink); }
    .org span { font-size: .64rem; color: var(--ink-2); }

    /* ── Módulos ─────────────────────────────────────────────────── */
    .modules-section { background: var(--mint-100); }
    .modules { display: grid; gap: 1rem; }
    .module {
      position: relative;
      border-radius: var(--radius);
      padding: 1.4rem;
      display: flex;
      flex-direction: column;
      transition: transform .35s var(--ease), box-shadow .35s var(--ease);
    }
    .module:hover { transform: translateY(-4px); box-shadow: 0 26px 50px -26px rgba(8, 64, 63, .45), inset 0 1px 0 rgba(255,255,255,.8); }
    .module .feature-ic { background: var(--teal-700); color: #fff; }
    .module h3 { margin-top: 1rem; font-size: 1.05rem; font-weight: 600; color: var(--teal-900); }
    .module p { margin-top: .35rem; font-size: .88rem; color: var(--ink-2); }
    .price { margin-top: 1rem; display: flex; align-items: baseline; gap: .3rem; }
    .price b { font-size: 1.6rem; font-weight: 700; letter-spacing: -.02em; color: var(--teal-800); }
    .price span { font-size: .85rem; color: var(--ink-2); }
    .tags { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .9rem; }
    .tags .tag { font-size: .72rem; background: rgba(255,255,255,.75); border: 1px solid var(--line); }
    .module-cta { align-items: center; justify-content: center; text-align: center; background: var(--teal-800); color: #fff; border: 0; }
    .module-cta h3 { color: #fff; }
    .module-cta p { color: rgba(255,255,255,.75); }
    .module-cta .feature-ic { background: var(--gold-500); color: var(--teal-950); }
    .module-cta .btn { margin-top: 1.1rem; min-height: 44px; }

    /* ── Calculadora ─────────────────────────────────────────────── */
    .calc { display: grid; gap: 1.5rem; max-width: 1000px; margin: 0 auto; }
    .calc-list { border-radius: 22px; padding: 1.25rem; background: #fff; border: 1px solid var(--line); }
    .calc-list-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .75rem; }
    .calc-list-head p { font-weight: 600; color: var(--teal-900); }
    .count { font-size: .78rem; font-weight: 600; padding: .25rem .65rem; border-radius: 999px; background: var(--mint-100); color: var(--teal-700); }
    .calc-module {
      display: flex;
      align-items: center;
      gap: .75rem;
      padding: .8rem .9rem;
      border-radius: 14px;
      border: 1px solid var(--line);
      margin-top: .5rem;
      cursor: pointer;
      transition: background .2s, border-color .2s;
    }
    .calc-module:hover { background: var(--mint-50); }
    .calc-module.selected { background: var(--mint-100); border-color: rgba(12, 92, 92, .4); }
    .calc-module input { width: 20px; height: 20px; accent-color: var(--teal-700); flex: none; cursor: pointer; }
    .calc-module input:disabled { cursor: default; }
    .calc-module .name { flex: 1; font-size: .95rem; font-weight: 500; }
    .calc-module .amt { font-size: .9rem; font-weight: 600; color: var(--teal-700); font-variant-numeric: tabular-nums; }
    .calc-module .incl { font-size: .78rem; color: var(--ink-2); }

    .calc-side { display: flex; flex-direction: column; gap: .9rem; }
    .total {
      position: relative;
      overflow: hidden;
      isolation: isolate;
      border-radius: 22px;
      padding: 1.6rem;
      min-height: 210px;
      background: var(--teal-950);
      color: #fff;
    }
    .total p { font-size: .9rem; color: rgba(255,255,255,.75); }
    .total .big { margin-top: .4rem; font-size: clamp(2.4rem, 6vw, 3.2rem); font-weight: 700; letter-spacing: -.03em; line-height: 1.05; font-variant-numeric: tabular-nums; }
    .total .big small { font-size: 1rem; font-weight: 500; color: rgba(255,255,255,.7); }
    .total .strike { text-decoration: line-through; opacity: .5; font-size: 1.2rem; margin-right: .4rem; }
    .total .gold { color: var(--gold-500); }
    .total .year { margin-top: .5rem; font-size: .92rem; color: rgba(255,255,255,.8); }
    /* Líquido que sube con el total */
    .fill { position: absolute; inset: 0; z-index: -1; pointer-events: none; }
    .fill-level { position: absolute; left: 0; right: 0; bottom: 0; height: 8%; transition: height 1s var(--ease); }
    .fill-level svg { position: absolute; left: 0; bottom: calc(100% - 2px); width: 200%; height: 26px; animation: wave 6s linear infinite; }
    .fill-level svg.back { animation-duration: 9s; animation-direction: reverse; opacity: .5; }
    .fill-body { position: absolute; inset: 0; background: linear-gradient(180deg, #11706E, #0C5C5C); }
    @keyframes wave { to { transform: translateX(-50%); } }

    .plan-ref { border-radius: 14px; padding: .8rem 1rem; font-size: .88rem; color: var(--ink-2); background: #fff; border: 1px solid var(--line); }
    .plan-ref strong { color: var(--teal-800); }
    .savings { border-radius: 14px; padding: .8rem 1rem; font-size: .9rem; font-weight: 600; background: #FFF4DF; color: #7A4F08; border: 1px solid #F6D9A6; }

    /* ── Costo de no digitalizar ─────────────────────────────────── */
    .roi { max-width: 980px; margin: 0 auto; border-radius: 22px; overflow: hidden; background: #fff; border: 1px solid var(--line); }
    .roi-row { display: grid; grid-template-columns: 1fr; border-top: 1px solid var(--line); }
    .roi-row:first-child { border-top: 0; }
    .roi-head { display: none; }
    .roi-label { padding: 1rem 1.25rem .25rem; font-weight: 600; font-size: .95rem; color: var(--teal-900); }
    .roi-cell { padding: .5rem 1.25rem; font-size: .9rem; }
    .roi-cell .tag-mobile { display: inline-block; font-size: .72rem; font-weight: 600; margin-right: .4rem; }
    .roi-bad { color: #9A3412; }
    .roi-good { color: var(--teal-800); font-weight: 500; padding-bottom: 1rem; }
    .bar { height: 6px; border-radius: 999px; background: #EEF2F1; margin-top: .45rem; overflow: hidden; }
    .bar span { display: block; height: 100%; width: 0; border-radius: inherit; transition: width 1.2s var(--ease); }
    .roi-bad .bar span { background: #F28B6B; }
    .roi-good .bar span { background: linear-gradient(90deg, var(--teal-500), var(--teal-700)); }

    /* ── Precios ─────────────────────────────────────────────────── */
    .pricing-section { background: var(--mint-100); }
    .plans { display: grid; gap: 1.25rem; max-width: 1080px; margin: 0 auto; align-items: stretch; }
    .plan { position: relative; border-radius: 24px; padding: 1.75rem; display: flex; flex-direction: column; }
    .plan.featured { background: var(--teal-800); color: #fff; box-shadow: 0 30px 60px -28px rgba(6, 46, 46, .7); }
    .plan h3 { margin-top: 1rem; font-size: 1.3rem; font-weight: 600; color: var(--teal-900); }
    .plan .who { font-size: .9rem; color: var(--ink-2); }
    .plan .price b { font-size: 2.4rem; }
    .plan .scope { font-size: .85rem; color: var(--ink-2); margin-top: .1rem; }
    .plan ul { margin: 1.25rem 0 1.5rem; display: grid; gap: .6rem; }
    .plan li { display: flex; gap: .55rem; font-size: .92rem; }
    .plan li .icon { color: var(--teal-600); margin-top: .2rem; width: 1.05em; height: 1.05em; }
    .plan .btn { margin-top: auto; width: 100%; }
    .plan.featured h3, .plan.featured .price b { color: #fff; }
    .plan.featured .who, .plan.featured .scope, .plan.featured .price span { color: rgba(255,255,255,.75); }
    .plan.featured li .icon { color: var(--gold-500); }
    .plan.featured .feature-ic { background: var(--gold-500); color: var(--teal-950); }
    .badge-pop { position: absolute; top: -13px; left: 50%; transform: translateX(-50%); padding: .3rem .85rem; border-radius: 999px; font-size: .75rem; font-weight: 700; background: var(--gold-500); color: var(--teal-950); white-space: nowrap; }
    .note { max-width: 720px; margin: 2rem auto 0; text-align: center; padding: 1rem 1.25rem; border-radius: 16px; font-size: .95rem; color: var(--ink-2); }
    .note a { color: var(--teal-700); font-weight: 600; text-decoration: underline; text-underline-offset: 3px; }
    .fineprint { margin-top: 1rem; text-align: center; font-size: .82rem; color: var(--ink-2); }

    /* ── Preguntas ───────────────────────────────────────────────── */
    .faq { max-width: 760px; margin: 0 auto; display: grid; gap: .65rem; }
    .faq-item { border-radius: 16px; background: #fff; border: 1px solid var(--line); overflow: hidden; }
    .faq-item button { width: 100%; display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 1.1rem 1.25rem; text-align: left; font-weight: 600; color: var(--teal-900); }
    .faq-item button .icon { transition: transform .3s var(--ease); color: var(--teal-600); }
    .faq-item button[aria-expanded="true"] .icon { transform: rotate(180deg); }
    .faq-panel { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .35s var(--ease); }
    .faq-panel > div { overflow: hidden; }
    .faq-panel p { padding: 0 1.25rem 1.2rem; color: var(--ink-2); font-size: .95rem; }
    .faq-item.open .faq-panel { grid-template-rows: 1fr; }

    /* ── Contacto ────────────────────────────────────────────────── */
    .contact-section { background: var(--teal-900); color: #fff; }
    .contact-section .blob { opacity: .35; }
    .contact-section .blob.b1 { background: #1A8C87; }
    .contact-section .blob.b2 { background: #D9962F; opacity: .25; }
    .contact { display: grid; gap: 2.5rem; align-items: start; }
    .contact h2 { font-size: clamp(2rem, 4.5vw, 3.1rem); line-height: 1.08; font-weight: 700; letter-spacing: -.03em; }
    .contact .kicker { color: var(--gold-500); }
    .contact-lede { margin-top: 1rem; color: rgba(255,255,255,.78); max-width: 42ch; }
    .contact-info { margin-top: 2rem; display: grid; gap: .9rem; }
    .contact-info a, .contact-info div.ci { display: flex; align-items: center; gap: .85rem; }
    .contact-info .ic { width: 44px; height: 44px; border-radius: 14px; display: grid; place-items: center; background: rgba(255,255,255,.1); color: var(--gold-500); flex: none; }
    .contact-info small { display: block; font-size: .78rem; color: rgba(255,255,255,.6); }
    .contact-info b { font-weight: 500; word-break: break-word; }

    .form-card { border-radius: 24px; padding: 1.5rem; background: rgba(255,255,255,.96); color: var(--ink); box-shadow: 0 30px 60px -30px rgba(0,0,0,.5); }
    @supports ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px))) {
      .form-card { background: rgba(255,255,255,.88); -webkit-backdrop-filter: blur(20px); backdrop-filter: blur(20px); }
    }
    .form-grid { display: grid; gap: 1rem; }
    .field label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: .4rem; color: var(--teal-900); }
    .field input, .field textarea {
      width: 100%;
      padding: .8rem .95rem;
      border-radius: 12px;
      border: 1px solid rgba(12, 92, 92, .2);
      background: #fff;
      font-size: .95rem;
      transition: border-color .2s, box-shadow .2s;
    }
    .field textarea { resize: vertical; min-height: 120px; }
    .field input:focus, .field textarea:focus { outline: none; border-color: var(--teal-600); box-shadow: 0 0 0 4px rgba(17, 112, 110, .15); }
    .honeypot { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
    .check { display: flex; align-items: flex-start; gap: .6rem; font-size: .88rem; color: var(--ink-2); }
    .check input { width: 18px; height: 18px; margin-top: .15rem; accent-color: var(--teal-700); flex: none; }
    .form-card .btn { width: 100%; }
    .form-alt { text-align: center; font-size: .82rem; color: var(--ink-2); }
    .form-alt a { color: var(--teal-700); font-weight: 600; }
    .success { text-align: center; padding: 2rem 1rem; }
    .success .ok { width: 64px; height: 64px; margin: 0 auto 1rem; border-radius: 50%; display: grid; place-items: center; background: var(--mint-100); color: var(--teal-700); }
    .success .ok .icon { width: 30px; height: 30px; }
    .success h3 { font-size: 1.4rem; color: var(--teal-900); }
    .success p { margin: .5rem 0 1.25rem; color: var(--ink-2); }

    /* ── Pie ─────────────────────────────────────────────────────── */
    .footer { background: var(--teal-950); color: rgba(255,255,255,.75); padding: 4rem 0 6rem; font-size: .92rem; }
    .footer-grid { display: grid; gap: 2rem; }
    .footer .brand { color: #fff; }
    .footer .brand img { filter: invert(1); }
    .footer p { margin-top: 1rem; max-width: 42ch; }
    .footer h3 { color: #fff; font-size: .95rem; font-weight: 600; margin-bottom: .85rem; }
    .footer li { margin-top: .5rem; }
    .footer a:hover { color: var(--gold-500); }
    .footer .copy { margin-top: 1.5rem; font-size: .82rem; color: rgba(255,255,255,.55); }

    /* ── Barra fija, aviso y chat ────────────────────────────────── */
    .sticky-cta {
      position: fixed;
      left: 50%;
      bottom: 1rem;
      z-index: 40;
      width: min(680px, calc(100% - 6.5rem));
      transform: translate(-50%, 140%);
      transition: transform .5s var(--ease);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1rem;
      padding: .55rem .55rem .55rem 1.25rem;
      border-radius: 999px;
      font-size: .9rem;
      font-weight: 500;
      color: var(--teal-900);
    }
    .sticky-cta.show { transform: translate(-50%, 0); }
    .sticky-cta .btn { min-height: 42px; padding: 0 1.1rem; font-size: .88rem; flex: none; }

    .toast {
      position: fixed;
      top: 5.5rem;
      left: 50%;
      z-index: 70;
      transform: translate(-50%, -20px);
      opacity: 0;
      pointer-events: none;
      display: flex;
      align-items: center;
      gap: .6rem;
      padding: .8rem 1.1rem;
      border-radius: 14px;
      background: var(--teal-900);
      color: #fff;
      font-size: .9rem;
      box-shadow: 0 20px 40px -20px rgba(0,0,0,.5);
      transition: opacity .3s, transform .3s var(--ease);
      max-width: calc(100% - 2rem);
    }
    .toast.show { opacity: 1; transform: translate(-50%, 0); }
    .toast.error { background: #8A2C17; }

    .chat { position: fixed; right: 1rem; bottom: 1rem; z-index: 45; }
    .chat-toggle { width: 58px; height: 58px; border-radius: 50%; background: #1FB57A; color: #fff; display: grid; place-items: center; box-shadow: 0 16px 30px -12px rgba(31, 181, 122, .7); transition: transform .2s var(--ease); }
    .chat-toggle:hover { transform: scale(1.06); }
    .chat-toggle .icon { width: 26px; height: 26px; }
    .chat-toggle .i-close { display: none; }
    .chat.open .chat-toggle .i-chat { display: none; }
    .chat.open .chat-toggle .i-close { display: block; }
    .chat-panel {
      position: absolute;
      right: 0;
      bottom: 72px;
      width: min(340px, calc(100vw - 2rem));
      border-radius: 20px;
      overflow: hidden;
      background: #fff;
      box-shadow: 0 30px 60px -24px rgba(6, 46, 46, .5);
      transform-origin: bottom right;
      transform: scale(.9);
      opacity: 0;
      pointer-events: none;
      transition: transform .25s var(--ease), opacity .25s;
    }
    .chat.open .chat-panel { transform: scale(1); opacity: 1; pointer-events: auto; }
    .chat-head { display: flex; align-items: center; gap: .7rem; padding: .95rem 1rem; background: var(--teal-800); color: #fff; }
    .chat-head .av { width: 38px; height: 38px; border-radius: 50%; background: rgba(255,255,255,.15); display: grid; place-items: center; }
    .chat-head small { display: block; font-size: .75rem; color: #9BE7C4; }
    .chat-body { height: 240px; overflow-y: auto; padding: 1rem; background: var(--mint-50); display: flex; flex-direction: column; gap: .5rem; }
    .msg { max-width: 85%; }
    .msg div { padding: .6rem .8rem; border-radius: 14px; font-size: .88rem; }
    .msg.in div { background: #fff; border: 1px solid var(--line); border-bottom-left-radius: 4px; }
    .msg.out { align-self: flex-end; }
    .msg.out div { background: var(--teal-700); color: #fff; border-bottom-right-radius: 4px; }
    .msg small { font-size: .7rem; color: var(--ink-2); padding: 0 .3rem; }
    .typing span { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: var(--ink-2); margin: 0 1px; animation: bounce 1s infinite; }
    .typing span:nth-child(2) { animation-delay: .15s; } .typing span:nth-child(3) { animation-delay: .3s; }
    @keyframes bounce { 0%, 60%, 100% { transform: translateY(0); opacity: .5; } 30% { transform: translateY(-4px); opacity: 1; } }
    .chat-input { display: flex; gap: .5rem; padding: .75rem; border-top: 1px solid var(--line); }
    .chat-input input { flex: 1; padding: .6rem .8rem; border-radius: 999px; border: 1px solid var(--line); font-size: .9rem; }
    .chat-input input:focus { outline: none; border-color: var(--teal-600); }
    .chat-input button { width: 40px; height: 40px; border-radius: 50%; background: var(--teal-700); color: #fff; display: grid; place-items: center; flex: none; }

    /* ── Aparición al hacer scroll (una sola vez por bloque) ─────── */
    .js .reveal { opacity: 0; transform: translateY(24px); transition: opacity .8s var(--ease), transform .8s var(--ease); transition-delay: var(--d, 0s); }
    .js .reveal.in { opacity: 1; transform: none; }

    /* ── Responsive ──────────────────────────────────────────────── */
    @media (min-width: 640px) {
      .wrap { padding: 0 1.75rem; }
      .features { grid-template-columns: repeat(2, 1fr); }
      .modules { grid-template-columns: repeat(2, 1fr); }
      .form-grid.two { grid-template-columns: 1fr 1fr; }
      .footer-grid { grid-template-columns: 1.6fr 1fr 1fr; }
    }
    @media (min-width: 768px) {
      .stats-inner { grid-template-columns: repeat(4, 1fr); }
      .stat:nth-child(3)::before { display: block; }
      .roi-head { display: grid; grid-template-columns: 1.1fr 1fr 1fr; background: var(--mint-50); }
      .roi-head div { padding: 1rem 1.25rem; font-weight: 600; font-size: .92rem; display: flex; align-items: center; gap: .45rem; }
      .roi-head .bad { color: #9A3412; background: #FFF6F1; }
      .roi-head .good { color: var(--teal-800); background: var(--mint-100); }
      .roi-row { grid-template-columns: 1.1fr 1fr 1fr; align-items: center; }
      .roi-label { padding: 1.1rem 1.25rem; }
      .roi-cell { padding: 1.1rem 1.25rem; }
      .roi-bad { background: #FFFBF8; }
      .roi-good { padding-bottom: 1.1rem; background: #FAFDFC; }
      .roi-cell .tag-mobile { display: none; }
      .plans { grid-template-columns: repeat(3, 1fr); }
      .plan.featured { transform: translateY(-12px); }
      .calc { grid-template-columns: 1.15fr 1fr; align-items: start; }
      .calc-side { position: sticky; top: 6rem; }
    }
    @media (min-width: 1024px) {
      .nav-links { display: flex; }
      .menu-btn { display: none; }
      .hero-grid { grid-template-columns: 1.1fr 1fr; gap: 2rem; }
      .hero-visual { min-height: 520px; }
      .hero-card { margin-right: 12%; margin-top: 8%; }
      .features { grid-template-columns: repeat(3, 1fr); }
      .modules { grid-template-columns: repeat(4, 1fr); }
      .module-cta { grid-column: span 2; }
      .contact { grid-template-columns: 1fr 1.1fr; gap: 4rem; }
      .form-card { padding: 2rem; }
      .section { padding: 7.5rem 0; }
    }
    @media (max-width: 1023px) {
      .hero { padding-top: 6.5rem; }
      .hero-visual { min-height: 380px; }
      .liquid { width: min(440px, 110%); left: 55%; }
      .chip-1 { left: 0; } .chip-2 { right: 0; }
    }
    @media (max-width: 520px) {
      .float-chip { display: none; }
      .sticky-cta { width: calc(100% - 5.5rem); left: calc(50% - 2.25rem); font-size: .8rem; padding-left: 1rem; }
      .sticky-cta span { line-height: 1.3; }
    }

    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
      .js .reveal { opacity: 1; transform: none; }
    }
  </style>
</head>

<body>
  <!-- Íconos (se reutilizan con <use>) -->
  <svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <defs>
      <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></symbol>
      <symbol id="i-check" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></symbol>
      <symbol id="i-x" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></symbol>
      <symbol id="i-down" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
      <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
      <symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
      <symbol id="i-dash" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></symbol>
      <symbol id="i-users" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/></symbol>
      <symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 3 4.5 6v6c0 4.5 3.2 7.8 7.5 9 4.3-1.2 7.5-4.5 7.5-9V6Z"/><path d="m9 12 2 2 4-4"/></symbol>
      <symbol id="i-grid" viewBox="0 0 24 24"><rect x="3" y="3" width="7.5" height="7.5" rx="2"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="2"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="2"/><path d="M17.25 14v6.5M14 17.25h6.5"/></symbol>
      <symbol id="i-building" viewBox="0 0 24 24"><path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M16 9h2a2 2 0 0 1 2 2v10M2 21h20M8 7h4M8 11h4M8 15h4"/></symbol>
      <symbol id="i-palette" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 0 18c1.1 0 1.8-.8 1.8-1.8 0-.5-.2-.9-.5-1.2-.3-.3-.5-.7-.5-1.2 0-1 .8-1.8 1.8-1.8H17a4 4 0 0 0 4-4c0-4.4-4-8-9-8Z"/><circle cx="7.5" cy="11" r="1.2"/><circle cx="10" cy="7" r="1.2"/><circle cx="15" cy="7.5" r="1.2"/></symbol>
      <symbol id="i-steth" viewBox="0 0 24 24"><path d="M5 3v6a5 5 0 0 0 10 0V3"/><path d="M10 14v2a5 5 0 0 0 10 0v-2"/><circle cx="20" cy="12" r="2"/></symbol>
      <symbol id="i-pill" viewBox="0 0 24 24"><rect x="2.5" y="8" width="19" height="8" rx="4" transform="rotate(-45 12 12)"/><path d="m8.5 8.5 7 7"/></symbol>
      <symbol id="i-flask" viewBox="0 0 24 24"><path d="M9 3h6M10 3v6l-5.5 9.5A1.7 1.7 0 0 0 6 21h12a1.7 1.7 0 0 0 1.5-2.5L14 9V3"/><path d="M7.5 15h9"/></symbol>
      <symbol id="i-bed" viewBox="0 0 24 24"><path d="M3 5v15M3 16h18M21 20v-6a3 3 0 0 0-3-3h-8v5"/><circle cx="7" cy="12" r="2"/></symbol>
      <symbol id="i-box" viewBox="0 0 24 24"><path d="m21 8-9-5-9 5 9 5 9-5Z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/></symbol>
      <symbol id="i-scan" viewBox="0 0 24 24"><path d="M3 8V5a2 2 0 0 1 2-2h3M16 3h3a2 2 0 0 1 2 2v3M21 16v3a2 2 0 0 1-2 2h-3M8 21H5a2 2 0 0 1-2-2v-3"/><path d="M7 12h2l1.5-3 3 6 1.5-3h2"/></symbol>
      <symbol id="i-cart" viewBox="0 0 24 24"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.7 12.3a2 2 0 0 0 2 1.7h8a2 2 0 0 0 2-1.6L21 8H6"/></symbol>
      <symbol id="i-receipt" viewBox="0 0 24 24"><path d="M5 3v18l2.5-1.5L10 21l2-1.5 2 1.5 2.5-1.5L19 21V3l-2.5 1.5L14 3l-2 1.5L10 3 7.5 4.5Z"/><path d="M9 9h6M9 13h6"/></symbol>
      <symbol id="i-wallet" viewBox="0 0 24 24"><path d="M20 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0 0 4h15v4M3 5v14a2 2 0 0 0 2 2h15v-4"/><path d="M21 11h-4a2 2 0 0 0 0 4h4Z"/></symbol>
      <symbol id="i-chart" viewBox="0 0 24 24"><path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/></symbol>
      <symbol id="i-zap" viewBox="0 0 24 24"><path d="M13 2 4 14h7l-1 8 9-12h-7Z"/></symbol>
      <symbol id="i-hospital" viewBox="0 0 24 24"><path d="M3 21V8a2 2 0 0 1 2-2h4V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2h4a2 2 0 0 1 2 2v13M2 21h20"/><path d="M12 8v6M9 11h6M9 21v-3h6v3"/></symbol>
      <symbol id="i-pin" viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/></symbol>
      <symbol id="i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6 8.5-6"/></symbol>
      <symbol id="i-phone" viewBox="0 0 24 24"><path d="M21 16.5v3a2 2 0 0 1-2.2 2A19.8 19.8 0 0 1 2.5 5.2 2 2 0 0 1 4.5 3h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.4 10.9a16 16 0 0 0 4.7 4.7l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2Z"/></symbol>
      <symbol id="i-chat" viewBox="0 0 24 24"><path d="M21 12a8.5 8.5 0 0 1-12.5 7.5L3 21l1.5-5.5A8.5 8.5 0 1 1 21 12Z"/></symbol>
      <symbol id="i-send" viewBox="0 0 24 24"><path d="M22 2 11 13M22 2l-7 20-4-9-9-4Z"/></symbol>
      <symbol id="i-cloud" viewBox="0 0 24 24"><path d="M7 18a4.5 4.5 0 0 1-.6-9A6 6 0 0 1 18 9a4 4 0 0 1-.5 9Z"/></symbol>
      <symbol id="i-devices" viewBox="0 0 24 24"><rect x="2" y="4" width="14" height="11" rx="1.5"/><path d="M6 19h6M9 15v4"/><rect x="17" y="8" width="5" height="11" rx="1.2"/></symbol>
      <symbol id="i-lock" viewBox="0 0 24 24"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></symbol>
      <symbol id="i-heart" viewBox="0 0 24 24"><path d="M20.8 5.6a5.5 5.5 0 0 0-7.8 0L12 6.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 22l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></symbol>
    </defs>
  </svg>

  <a class="skip" href="#main">Saltar al contenido</a>

  <!-- Navegación -->
  <header class="nav" id="navbar">
    <nav class="nav-bar glass" aria-label="Principal">
      <a href="#inicio" class="brand" aria-label="ClinicApp RS Solutions, inicio">
        <img src="logo.png" alt="" width="34" height="34">
        <span>ClinicApp<small>RS Solutions</small></span>
      </a>
      <div class="nav-links">
        <a href="#caracteristicas">Características</a>
        <a href="#modulos">Módulos</a>
        <a href="#precios">Precios</a>
        <a href="#faq">Preguntas</a>
        <a href="#contacto">Contacto</a>
        <a href="https://base.clinicapp.gt/" rel="noopener" class="btn btn-primary">Ingresar <svg class="icon icon-arrow"><use href="#i-arrow"/></svg></a>
      </div>
      <button class="menu-btn" id="menu-btn" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="mobile-menu">
        <svg class="icon"><use href="#i-menu"/></svg>
      </button>
    </nav>
  </header>

  <div class="mobile-menu" id="mobile-menu" hidden>
    <button class="mobile-close" type="button" data-close-menu aria-label="Cerrar menú"><svg class="icon"><use href="#i-x"/></svg></button>
    <a href="#caracteristicas" data-close-menu>Características</a>
    <a href="#modulos" data-close-menu>Módulos</a>
    <a href="#precios" data-close-menu>Precios</a>
    <a href="#faq" data-close-menu>Preguntas</a>
    <a href="#contacto" data-close-menu>Contacto</a>
    <a href="https://base.clinicapp.gt/" rel="noopener" class="btn btn-primary">Ingresar al sistema</a>
  </div>

  <div class="toast" id="toast" role="status" aria-live="polite"><svg class="icon"><use href="#i-check"/></svg><span id="toastMsg"></span></div>

  <main id="main">
    <!-- Inicio -->
    <section class="hero" id="inicio" aria-labelledby="hero-title">
      <canvas id="particles" aria-hidden="true"></canvas>
      <div class="wrap hero-grid">
        <div>
          <span class="pill glass"><span class="dot" aria-hidden="true"></span>Primer sistema modular de Guatemala</span>
          <h1 id="hero-title">Tu clínica merece un sistema tan dedicado como vos.</h1>
          <p class="hero-lede">Elegí solo los módulos que tu clínica necesita (farmacia, laboratorio, hospitalización) y empezá a trabajar en 11 días. Sin contratos largos, sin complicaciones.</p>
          <div class="hero-ctas">
            <a href="#precios" class="btn btn-primary">Ver planes <svg class="icon icon-arrow"><use href="#i-arrow"/></svg></a>
            <a href="#contacto" class="btn btn-ghost" data-plan="Demo" data-price="Gratis">Agendar demo gratis</a>
          </div>
          <div class="trust">
            <span><svg class="icon"><use href="#i-cloud"/></svg> Sin instalación</span>
            <span><svg class="icon"><use href="#i-devices"/></svg> Multi-dispositivo</span>
            <span><svg class="icon"><use href="#i-lock"/></svg> Datos seguros</span>
            <span><svg class="icon"><use href="#i-pin"/></svg> Soporte local</span>
          </div>
        </div>

        <div class="hero-visual">
          <div class="liquid" aria-hidden="true">
            <svg viewBox="0 0 600 600">
              <defs>
                <linearGradient id="lq1" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0" stop-color="#1A8C87"/>
                  <stop offset=".55" stop-color="#0C5C5C"/>
                  <stop offset="1" stop-color="#08403F"/>
                </linearGradient>
                <linearGradient id="lq2" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0" stop-color="#FFD08A"/>
                  <stop offset="1" stop-color="#F5B85A"/>
                </linearGradient>
                <radialGradient id="lqShine" cx=".3" cy=".25" r=".6">
                  <stop offset="0" stop-color="#fff" stop-opacity=".35"/>
                  <stop offset="1" stop-color="#fff" stop-opacity="0"/>
                </radialGradient>
              </defs>
              <path id="blobGold" fill="url(#lq2)" opacity=".9"/>
              <path id="blobMain" fill="url(#lq1)"/>
              <path id="blobShine" fill="url(#lqShine)"/>
            </svg>
          </div>

          <div class="hero-card glass">
            <div class="hc-head">
              <img src="logo.png" alt="" width="36" height="36">
              <div>
                <div class="hc-title">Centro Médico RS</div>
                <div class="hc-sub">Resumen del día</div>
              </div>
              <span class="hc-live">En vivo</span>
            </div>
            <div class="hc-grid">
              <div class="hc-stat"><b>11 días</b><span>Implementación</span></div>
              <div class="hc-stat"><b>100%</b><span>Operativo</span></div>
              <div class="hc-stat"><b>+50</b><span>Clínicas activas</span></div>
              <div class="hc-stat"><b>99.9%</b><span>Disponibilidad</span></div>
            </div>
            <div class="hc-ecg" aria-hidden="true">
              <svg viewBox="0 0 360 46" preserveAspectRatio="none" width="100%" height="46">
                <path d="M0 26h40l6-6 6 6h22l4 4 6-24 6 30 4-10h30l8-6 8 6h40l6-6 6 6h22l4 4 6-24 6 30 4-10h30l8-6 8 6h48"/>
              </svg>
            </div>
            <p class="hc-foot"><svg class="icon" style="color:#1FB57A"><use href="#i-heart"/></svg> Soporte técnico en Guatemala</p>
          </div>

          <div class="float-chip glass chip-1" aria-hidden="true"><span class="ic"><svg class="icon"><use href="#i-steth"/></svg></span>Consulta de las 10:30 confirmada</div>
          <div class="float-chip glass gold chip-2" aria-hidden="true"><span class="ic"><svg class="icon"><use href="#i-flask"/></svg></span>Resultados de laboratorio listos</div>
        </div>
      </div>
    </section>

    <!-- Cifras -->
    <section class="stats" aria-label="Cifras">
      <div class="wrap">
        <div class="stats-inner reveal">
          <div class="stat"><b data-count="50">50</b><span>Clínicas activas</span></div>
          <div class="stat"><b data-count="10K+">10K+</b><span>Pacientes registrados</span></div>
          <div class="stat"><b data-count="99.9%">99.9%</b><span>Disponibilidad</span></div>
          <div class="stat"><b data-count="11">11</b><span>Días de implementación</span></div>
        </div>
      </div>
    </section>

    <!-- Características -->
    <section class="section" id="caracteristicas" aria-labelledby="caracteristicas-title">
      <div class="wrap">
        <div class="section-head reveal">
          <span class="kicker">Características</span>
          <h2 id="caracteristicas-title">Todo lo que tu clínica necesita</h2>
          <p>Una plataforma unificada con herramientas inteligentes para tu práctica médica.</p>
        </div>

        <div class="features">
          <article class="feature reveal">
            <div>
              <div class="feature-ic"><svg class="icon"><use href="#i-dash"/></svg></div>
              <h3 style="margin-top:1rem">Dashboard inteligente</h3>
              <p>Citas, pacientes hospitalizados, alertas de inventario e ingresos del día en un solo vistazo.</p>
            </div>
            <div class="mock" aria-hidden="true">
              <div class="mock-bar"><i></i><i></i><i></i><em>Dashboard</em></div>
              <div class="mock-kpis">
                <div><b>24</b><span>Citas hoy</span></div>
                <div><b>8</b><span>Internados</span></div>
                <div><b>Q4,280</b><span>Ingresos</span></div>
                <div><b>3</b><span>Alertas</span></div>
              </div>
              <div class="mock-bars"><span style="height:40%"></span><span style="height:65%"></span><span style="height:50%"></span><span style="height:85%"></span><span style="height:60%"></span><span style="height:95%"></span><span style="height:72%"></span></div>
              <div class="mock-row"><span>María López, 10:30</span><span class="tag">Confirmada</span></div>
              <div class="mock-row"><span>Carlos Ruiz, 11:00</span><span class="tag amber">Pendiente</span></div>
              <div class="mock-row"><span>Ana García, 11:30</span><span class="tag blue">En consulta</span></div>
            </div>
          </article>

          <article class="feature reveal" style="--d:.08s">
            <div>
              <div class="feature-ic"><svg class="icon"><use href="#i-users"/></svg></div>
              <h3 style="margin-top:1rem">Varios usuarios</h3>
              <p>Control de acceso por roles. Cada persona del equipo con los permisos que su trabajo necesita.</p>
            </div>
            <div class="mock" aria-hidden="true">
              <div class="mock-bar"><i></i><i></i><i></i><em>Usuarios</em></div>
              <div class="mock-row"><span>Dr. Samuel Ramírez</span><span class="tag">Admin</span></div>
              <div class="mock-row"><span>Dra. María García</span><span class="tag blue">Médico</span></div>
              <div class="mock-row"><span>Lic. Carlos Díaz</span><span class="tag amber">Laboratorio</span></div>
              <div class="mock-row"><span>Sra. Ana Pérez</span><span class="tag gray">Recepción</span></div>
              <div class="mock-row"><span style="color:var(--teal-700);font-weight:600">+ Agregar usuario</span></div>
            </div>
          </article>

          <article class="feature reveal" style="--d:.16s">
            <div>
              <div class="feature-ic"><svg class="icon"><use href="#i-shield"/></svg></div>
              <h3 style="margin-top:1rem">Seguridad de nivel empresarial</h3>
              <p>Protección CSRF, sesiones seguras, cifrado y datos aislados por clínica.</p>
            </div>
            <div class="mock" aria-hidden="true">
              <div class="mock-bar"><i></i><i></i><i></i><em>Seguridad</em></div>
              <div class="mock-row"><span>Protección CSRF</span><span class="toggle"></span></div>
              <div class="mock-row"><span>Cifrado SSL/TLS</span><span class="toggle"></span></div>
              <div class="mock-row"><span>Sesiones que expiran</span><span class="toggle"></span></div>
              <div class="mock-row"><span>Datos aislados por clínica</span><span class="toggle"></span></div>
              <div class="mock-row"><span>Registro de actividad</span><span class="toggle"></span></div>
            </div>
          </article>

          <article class="feature reveal">
            <div>
              <div class="feature-ic"><svg class="icon"><use href="#i-grid"/></svg></div>
              <h3 style="margin-top:1rem">Arquitectura modular</h3>
              <p>Activá solo los módulos que necesités. Crecé de a poco sin pagar por lo que no usás.</p>
            </div>
            <div class="mock" aria-hidden="true">
              <div class="mock-bar"><i></i><i></i><i></i><em>Módulos</em></div>
              <div class="mock-row"><span>Core / Consulta</span><span class="toggle"></span></div>
              <div class="mock-row"><span>Farmacia / POS</span><span class="toggle"></span></div>
              <div class="mock-row"><span>Laboratorio</span><span class="toggle"></span></div>
              <div class="mock-row"><span>Hospitalización</span><span class="toggle off"></span></div>
              <div class="mock-row"><span>Imágenes</span><span class="toggle off"></span></div>
            </div>
          </article>

          <article class="feature reveal" style="--d:.08s">
            <div>
              <div class="feature-ic"><svg class="icon"><use href="#i-building"/></svg></div>
              <h3 style="margin-top:1rem">Varias clínicas, un sistema</h3>
              <p>Administrá varias clínicas u hospitales desde una misma instalación, con datos y configuración separados.</p>
            </div>
            <div class="mock" aria-hidden="true">
              <div class="mock-bar"><i></i><i></i><i></i><em>Organizaciones</em></div>
              <div class="org"><i style="background:#0C5C5C"></i><div><b>Clínica Médica RS</b><span>Plan Enterprise, 12 usuarios</span></div></div>
              <div class="org"><i style="background:#F5B85A"></i><div><b>Hospital San Juan</b><span>Plan Profesional, 5 usuarios</span></div></div>
              <div class="org"><i style="background:#7CC9BE"></i><div><b>Centro Médico GT</b><span>Plan Pro, 2 usuarios</span></div></div>
            </div>
          </article>

          <article class="feature reveal" style="--d:.16s">
            <div>
              <div class="feature-ic"><svg class="icon"><use href="#i-palette"/></svg></div>
              <h3 style="margin-top:1rem">Con la identidad de tu clínica</h3>
              <p>Colores, logo y nombre configurables. El sistema se ve como tu clínica.</p>
            </div>
            <div class="mock" aria-hidden="true">
              <div class="mock-bar"><i></i><i></i><i></i><em>Personalización</em></div>
              <div>Color principal</div>
              <div class="swatches"><i style="background:#0C5C5C"></i><i style="background:#0F6670"></i><i style="background:#283251"></i><i style="background:#B4467A"></i><i style="background:#F5B85A"></i></div>
              <div class="mock-row"><span>Logo de la clínica</span><span class="tag gray">logo.png</span></div>
              <div class="mock-row"><span>Modo oscuro</span><span class="toggle"></span></div>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- Módulos -->
    <section class="section modules-section" id="modulos" aria-labelledby="modulos-title">
      <div class="blobs" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span></div>
      <div class="wrap">
        <div class="section-head reveal">
          <span class="kicker">Módulos</span>
          <h2 id="modulos-title">Planes modulares: pagá solo lo que usás</h2>
          <p>Cada módulo funciona por separado. Empezá con el Core gratis y agregá los que necesités.</p>
        </div>

        <div class="modules">
          <article class="module glass reveal"><div class="feature-ic"><svg class="icon"><use href="#i-steth"/></svg></div><h3>Core / Consulta externa</h3><p>Pacientes, citas e historial clínico. La base del sistema.</p><div class="price"><b>Gratis</b><span>base</span></div><div class="tags"><span class="tag">Historial clínico</span><span class="tag">Citas</span><span class="tag">Dashboard</span></div></article>
          <article class="module glass reveal" style="--d:.05s"><div class="feature-ic"><svg class="icon"><use href="#i-pill"/></svg></div><h3>Farmacia / POS</h3><p>Dispensación de medicamentos y punto de venta integrado.</p><div class="price"><b>Q199</b><span>/mes</span></div><div class="tags"><span class="tag">POS</span><span class="tag">Stock</span><span class="tag">Recetas</span><span class="tag">Código de barras</span></div></article>
          <article class="module glass reveal" style="--d:.1s"><div class="feature-ic"><svg class="icon"><use href="#i-flask"/></svg></div><h3>Laboratorio clínico</h3><p>Órdenes de laboratorio, resultados y control de calidad.</p><div class="price"><b>Q249</b><span>/mes</span></div><div class="tags"><span class="tag">Órdenes</span><span class="tag">Resultados</span><span class="tag">Parámetros</span><span class="tag">Reportes</span></div></article>
          <article class="module glass reveal" style="--d:.15s"><div class="feature-ic"><svg class="icon"><use href="#i-bed"/></svg></div><h3>Hospitalización</h3><p>Camas, admisiones, cargos y evolución del paciente.</p><div class="price"><b>Q299</b><span>/mes</span></div><div class="tags"><span class="tag">Camas</span><span class="tag">Admisiones</span><span class="tag">Signos vitales</span><span class="tag">Evolución</span></div></article>
          <article class="module glass reveal"><div class="feature-ic"><svg class="icon"><use href="#i-box"/></svg></div><h3>Inventario</h3><p>Control de existencias con alertas de vencimiento y mínimos.</p><div class="price"><b>Q149</b><span>/mes</span></div><div class="tags"><span class="tag">Stock</span><span class="tag">Alertas</span><span class="tag">Vencimientos</span></div></article>
          <article class="module glass reveal" style="--d:.05s"><div class="feature-ic"><svg class="icon"><use href="#i-scan"/></svg></div><h3>Imágenes / Procedimientos</h3><p>Rayos X, ultrasonidos, ECG y procedimientos menores.</p><div class="price"><b>Q199</b><span>/mes</span></div><div class="tags"><span class="tag">Rayos X</span><span class="tag">Ultrasonido</span><span class="tag">ECG</span></div></article>
          <article class="module glass reveal" style="--d:.1s"><div class="feature-ic"><svg class="icon"><use href="#i-cart"/></svg></div><h3>Compras</h3><p>Órdenes de compra, pagos parciales y cuentas por pagar.</p><div class="price"><b>Q149</b><span>/mes</span></div><div class="tags"><span class="tag">Órdenes</span><span class="tag">Proveedores</span><span class="tag">Pagos</span></div></article>
          <article class="module glass reveal" style="--d:.15s"><div class="feature-ic"><svg class="icon"><use href="#i-receipt"/></svg></div><h3>Ventas</h3><p>Registro de ventas con detalle por producto y reportes diarios.</p><div class="price"><b>Q149</b><span>/mes</span></div><div class="tags"><span class="tag">Ventas</span><span class="tag">Reportes</span><span class="tag">Cierre</span></div></article>
          <article class="module glass reveal"><div class="feature-ic"><svg class="icon"><use href="#i-wallet"/></svg></div><h3>Cuentas hospitalarias</h3><p>Facturación compleja, depósitos y saldos de hospitalización.</p><div class="price"><b>Q199</b><span>/mes</span></div><div class="tags"><span class="tag">Facturación</span><span class="tag">Cargos</span><span class="tag">Abonos</span></div></article>
          <article class="module glass reveal" style="--d:.05s"><div class="feature-ic"><svg class="icon"><use href="#i-chart"/></svg></div><h3>Reportes estadísticos</h3><p>Reportes financieros, de pacientes y de desempeño.</p><div class="price"><b>Q99</b><span>/mes</span></div><div class="tags"><span class="tag">Financieros</span><span class="tag">Pacientes</span><span class="tag">Gráficas</span></div></article>
          <article class="module module-cta reveal" style="--d:.1s"><div class="feature-ic"><svg class="icon"><use href="#i-plus"/></svg></div><h3>¿Necesitás todos?</h3><p>Plan personalizado con descuento por volumen.</p><a href="#contacto" class="btn btn-gold" data-plan="Personalizado">Consultar</a></article>
        </div>
      </div>
    </section>

    <!-- Calculadora -->
    <section class="section" id="calculadora" aria-labelledby="calc-title">
      <div class="wrap">
        <div class="section-head reveal">
          <span class="kicker">Calculadora</span>
          <h2 id="calc-title">Armá tu plan a medida</h2>
          <p>Marcá los módulos que necesités y mirá el costo exacto para tu clínica.</p>
        </div>

        <div class="calc">
          <div class="calc-list reveal">
            <div class="calc-list-head"><p>Módulos disponibles</p><span class="count" id="calc-count">0 seleccionados</span></div>
            <div id="calc-modules">
              <label class="calc-module selected" data-price="0"><input type="checkbox" checked disabled><span class="name">Core / Consulta externa</span><span class="incl">Siempre incluido</span></label>
              <label class="calc-module" data-price="199"><input type="checkbox"><span class="name">Farmacia / POS</span><span class="amt">Q199/mes</span></label>
              <label class="calc-module" data-price="249"><input type="checkbox"><span class="name">Laboratorio clínico</span><span class="amt">Q249/mes</span></label>
              <label class="calc-module" data-price="299"><input type="checkbox"><span class="name">Hospitalización</span><span class="amt">Q299/mes</span></label>
              <label class="calc-module" data-price="149"><input type="checkbox"><span class="name">Inventario</span><span class="amt">Q149/mes</span></label>
              <label class="calc-module" data-price="199"><input type="checkbox"><span class="name">Imágenes / Procedimientos</span><span class="amt">Q199/mes</span></label>
              <label class="calc-module" data-price="149"><input type="checkbox"><span class="name">Compras</span><span class="amt">Q149/mes</span></label>
              <label class="calc-module" data-price="149"><input type="checkbox"><span class="name">Ventas</span><span class="amt">Q149/mes</span></label>
              <label class="calc-module" data-price="199"><input type="checkbox"><span class="name">Cuentas hospitalarias</span><span class="amt">Q199/mes</span></label>
              <label class="calc-module" data-price="99"><input type="checkbox"><span class="name">Reportes estadísticos</span><span class="amt">Q99/mes</span></label>
            </div>
          </div>

          <div class="calc-side reveal" style="--d:.1s">
            <div class="total" aria-live="polite">
              <div class="fill" aria-hidden="true">
                <div class="fill-level" id="calc-fill">
                  <svg class="back" viewBox="0 0 1200 26" preserveAspectRatio="none"><path fill="#1A8C87" d="M0 13 Q75 0 150 13 T300 13 T450 13 T600 13 T750 13 T900 13 T1050 13 T1200 13 V26 H0Z"/></svg>
                  <svg viewBox="0 0 1200 26" preserveAspectRatio="none"><path fill="#11706E" d="M0 13 Q75 26 150 13 T300 13 T450 13 T600 13 T750 13 T900 13 T1050 13 T1200 13 V26 H0Z"/></svg>
                  <div class="fill-body"></div>
                </div>
              </div>
              <p>Total mensual estimado</p>
              <div class="big"><span id="calc-monthly">Q0</span> <small>/mes</small></div>
              <p class="year">Anual: <strong id="calc-yearly">Q0</strong> /año</p>
            </div>
            <div class="plan-ref">Plan Pro: <strong>Q199/mes</strong>, Core + 1 módulo</div>
            <div class="plan-ref">Plan Profesional: <strong>Q398/mes</strong>, Core + 2 módulos</div>
            <div class="plan-ref">Plan Enterprise: <strong>Q1,044/mes</strong>, Core + todos los módulos</div>
            <div class="savings" id="calc-savings" hidden><span id="calc-saving-amount"></span></div>
            <a href="#contacto" class="btn btn-primary" id="calc-request">Solicitar este plan <svg class="icon icon-arrow"><use href="#i-arrow"/></svg></a>
          </div>
        </div>
      </div>
    </section>

    <!-- Costo de no digitalizar -->
    <section class="section" id="roi" aria-labelledby="roi-title" style="padding-top:2rem">
      <div class="wrap">
        <div class="section-head reveal">
          <span class="kicker">El costo de no digitalizar</span>
          <h2 id="roi-title">¿Cuánto le cuesta a tu clínica no tener un sistema?</h2>
          <p>Cada día sin digitalizar es dinero perdido. Mirá la diferencia.</p>
        </div>

        <div class="roi reveal" role="table" aria-label="Comparación con y sin ClinicApp">
          <div class="roi-row roi-head" role="row">
            <div role="columnheader"><span class="sr-only">Aspecto</span></div>
            <div class="bad" role="columnheader"><svg class="icon"><use href="#i-x"/></svg> Sin ClinicApp</div>
            <div class="good" role="columnheader"><svg class="icon"><use href="#i-check"/></svg> Con ClinicApp</div>
          </div>
          <div class="roi-row" role="row"><div class="roi-label" role="rowheader">Tiempo en administración</div><div class="roi-cell roi-bad" role="cell"><span class="tag-mobile">Sin:</span>4 horas al día<div class="bar"><span data-width="25%"></span></div></div><div class="roi-cell roi-good" role="cell"><span class="tag-mobile">Con:</span>30 minutos al día<div class="bar"><span data-width="90%"></span></div></div></div>
          <div class="roi-row" role="row"><div class="roi-label" role="rowheader">Pacientes perdidos por desorganización</div><div class="roi-cell roi-bad" role="cell"><span class="tag-mobile">Sin:</span>~12 pacientes al mes<div class="bar"><span data-width="10%"></span></div></div><div class="roi-cell roi-good" role="cell"><span class="tag-mobile">Con:</span>0 pérdidas<div class="bar"><span data-width="100%"></span></div></div></div>
          <div class="roi-row" role="row"><div class="roi-label" role="rowheader">Dinero perdido en stock vencido</div><div class="roi-cell roi-bad" role="cell"><span class="tag-mobile">Sin:</span>Hasta Q8,000 al mes<div class="bar"><span data-width="15%"></span></div></div><div class="roi-cell roi-good" role="cell"><span class="tag-mobile">Con:</span>Alertas que evitan pérdidas<div class="bar"><span data-width="100%"></span></div></div></div>
          <div class="roi-row" role="row"><div class="roi-label" role="rowheader">Facturas con errores</div><div class="roi-cell roi-bad" role="cell"><span class="tag-mobile">Sin:</span>~15 al mes<div class="bar"><span data-width="30%"></span></div></div><div class="roi-cell roi-good" role="cell"><span class="tag-mobile">Con:</span>100% precisas<div class="bar"><span data-width="100%"></span></div></div></div>
          <div class="roi-row" role="row"><div class="roi-label" role="rowheader">Tiempo para generar un reporte</div><div class="roi-cell roi-bad" role="cell"><span class="tag-mobile">Sin:</span>1 semana<div class="bar"><span data-width="10%"></span></div></div><div class="roi-cell roi-good" role="cell"><span class="tag-mobile">Con:</span>Al instante, con 1 clic<div class="bar"><span data-width="95%"></span></div></div></div>
          <div class="roi-row" role="row"><div class="roi-label" role="rowheader">Costo mensual del sistema</div><div class="roi-cell roi-bad" role="cell"><span class="tag-mobile">Sin:</span>Q0 (pero perdés miles)<div class="bar"><span data-width="5%"></span></div></div><div class="roi-cell roi-good" role="cell"><span class="tag-mobile">Con:</span>Desde Q99/mes<div class="bar"><span data-width="85%"></span></div></div></div>
        </div>
        <div style="text-align:center;margin-top:2rem" class="reveal"><a href="#precios" class="btn btn-primary">Ver planes disponibles <svg class="icon icon-arrow"><use href="#i-arrow"/></svg></a></div>
      </div>
    </section>

    <!-- Precios -->
    <section class="section pricing-section" id="precios" aria-labelledby="precios-title">
      <div class="blobs" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span></div>
      <div class="wrap">
        <div class="section-head reveal">
          <span class="kicker">Planes</span>
          <h2 id="precios-title">Una inversión que se paga sola</h2>
          <p>Elegí los módulos que necesités y empezá a transformar tu clínica hoy.</p>
        </div>

        <div class="plans">
          <article class="plan glass reveal">
            <div class="feature-ic"><svg class="icon"><use href="#i-zap"/></svg></div>
            <h3>Pro</h3>
            <p class="who">Para médicos independientes</p>
            <div class="price"><b>Q199</b><span>/mes</span></div>
            <p class="scope">Core + 1 módulo a elección</p>
            <ul>
              <li><svg class="icon"><use href="#i-check"/></svg>Core / Consulta externa</li>
              <li><svg class="icon"><use href="#i-check"/></svg>1 módulo adicional</li>
              <li><svg class="icon"><use href="#i-check"/></svg>Pacientes ilimitados</li>
              <li><svg class="icon"><use href="#i-check"/></svg>Calendario de citas</li>
              <li><svg class="icon"><use href="#i-check"/></svg>1 usuario incluido</li>
            </ul>
            <a href="#contacto" class="btn btn-ghost" data-plan="Pro" data-price="Q199">Elegir Pro</a>
          </article>

          <article class="plan featured reveal" style="--d:.08s">
            <span class="badge-pop">Más elegido</span>
            <div class="feature-ic"><svg class="icon"><use href="#i-flask"/></svg></div>
            <h3>Profesional</h3>
            <p class="who">Para clínicas en crecimiento</p>
            <div class="price"><b>Q398</b><span>/mes</span></div>
            <p class="scope">Core + 2 módulos a elección</p>
            <ul>
              <li><svg class="icon"><use href="#i-check"/></svg>Core + Farmacia + Inventario</li>
              <li><svg class="icon"><use href="#i-check"/></svg>O Core + Laboratorio + Reportes</li>
              <li><svg class="icon"><use href="#i-check"/></svg>Pacientes ilimitados</li>
              <li><svg class="icon"><use href="#i-check"/></svg>Hasta 3 usuarios</li>
              <li><svg class="icon"><use href="#i-check"/></svg>Soporte prioritario</li>
            </ul>
            <a href="#contacto" class="btn btn-gold" data-plan="Profesional" data-price="Q398">Elegir Profesional</a>
          </article>

          <article class="plan glass reveal" style="--d:.16s">
            <div class="feature-ic"><svg class="icon"><use href="#i-hospital"/></svg></div>
            <h3>Enterprise</h3>
            <p class="who">Para hospitales y grupos médicos</p>
            <div class="price"><b>Q1,044</b><span>/mes</span></div>
            <p class="scope">Core + todos los módulos</p>
            <ul>
              <li><svg class="icon"><use href="#i-check"/></svg>Todos los módulos</li>
              <li><svg class="icon"><use href="#i-check"/></svg>Varias clínicas en un sistema</li>
              <li><svg class="icon"><use href="#i-check"/></svg>Usuarios ilimitados</li>
              <li><svg class="icon"><use href="#i-check"/></svg>Panel de administración</li>
              <li><svg class="icon"><use href="#i-check"/></svg>Soporte 24/7 y capacitación</li>
            </ul>
            <a href="#contacto" class="btn btn-ghost" data-plan="Enterprise" data-price="Q1,044">Elegir Enterprise</a>
          </article>
        </div>

        <p class="note glass reveal">Todo es <strong>editable y adaptable</strong> a cada médico. <a href="#contacto" data-plan="Personalizado">Pedí una cotización personalizada</a>.</p>
        <p class="fineprint">Todos los precios en quetzales (GTQ). Pueden variar según los módulos adicionales.</p>
      </div>
    </section>

    <!-- Preguntas frecuentes -->
    <section class="section" id="faq" aria-labelledby="faq-title">
      <div class="wrap">
        <div class="section-head reveal">
          <span class="kicker">Preguntas frecuentes</span>
          <h2 id="faq-title">Todo lo que necesitás saber</h2>
        </div>
        <div class="faq reveal">
          <div class="faq-item"><button type="button" aria-expanded="false" aria-controls="faq1" id="faq1-b"><span>¿Necesito internet para usar el sistema?</span><svg class="icon"><use href="#i-down"/></svg></button><div class="faq-panel" id="faq1" role="region" aria-labelledby="faq1-b"><div><p>Sí, el sistema funciona por web. Podés trabajar desde cualquier dispositivo con conexión: computadora, tablet o celular. Guatemala tiene cobertura 4G en la mayoría del país, y tus datos están siempre seguros en nuestros servidores con respaldos diarios.</p></div></div></div>
          <div class="faq-item"><button type="button" aria-expanded="false" aria-controls="faq2" id="faq2-b"><span>¿Puedo migrar los datos de mi clínica actual?</span><svg class="icon"><use href="#i-down"/></svg></button><div class="faq-panel" id="faq2" role="region" aria-labelledby="faq2-b"><div><p>Sí. Te ayudamos a migrar pacientes, citas e historiales desde Excel u otros sistemas. Está incluido en la implementación de 11 días. No perdés nada.</p></div></div></div>
          <div class="faq-item"><button type="button" aria-expanded="false" aria-controls="faq3" id="faq3-b"><span>¿Dan capacitación para mi equipo?</span><svg class="icon"><use href="#i-down"/></svg></button><div class="faq-panel" id="faq3" role="region" aria-labelledby="faq3-b"><div><p>Sí. Incluimos capacitación en línea para todo tu personal, además de manuales, videos y soporte por WhatsApp para dudas puntuales.</p></div></div></div>
          <div class="faq-item"><button type="button" aria-expanded="false" aria-controls="faq4" id="faq4-b"><span>¿Puedo probar antes de pagar?</span><svg class="icon"><use href="#i-down"/></svg></button><div class="faq-panel" id="faq4" role="region" aria-labelledby="faq4-b"><div><p>Claro. Ofrecemos una demo gratuita: te mostramos cómo funciona con tus propios datos y te explicamos cada módulo. Sin compromiso.</p></div></div></div>
          <div class="faq-item"><button type="button" aria-expanded="false" aria-controls="faq5" id="faq5-b"><span>¿Qué pasa si tengo problemas técnicos?</span><svg class="icon"><use href="#i-down"/></svg></button><div class="faq-panel" id="faq5" role="region" aria-labelledby="faq5-b"><div><p>Tenés soporte técnico local en Guatemala. Respondemos por WhatsApp, correo y teléfono en horario laboral. Los planes Enterprise incluyen soporte 24/7.</p></div></div></div>
        </div>
      </div>
    </section>

    <!-- Contacto -->
    <section class="section contact-section" id="contacto" aria-labelledby="contacto-title">
      <div class="blobs" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span></div>
      <div class="wrap contact">
        <div class="reveal">
          <span class="kicker">Contacto</span>
          <h2 id="contacto-title">¿Listo para digitalizar tu clínica?</h2>
          <p class="contact-lede">Contanos sobre tu clínica y te enviamos una propuesta personalizada, sin compromiso.</p>
          <div class="contact-info">
            <div class="ci"><span class="ic"><svg class="icon"><use href="#i-pin"/></svg></span><span><small>Ubicación</small><b>Ciudad de Guatemala</b></span></div>
            <a href="mailto:samuel.ramirez25prs@gmail.com"><span class="ic"><svg class="icon"><use href="#i-mail"/></svg></span><span><small>Correo</small><b>samuel.ramirez25prs@gmail.com</b></span></a>
            <a href="https://wa.me/50239029076" target="_blank" rel="noopener noreferrer"><span class="ic"><svg class="icon"><use href="#i-phone"/></svg></span><span><small>WhatsApp</small><b>+502 3902-9076</b></span></a>
          </div>
        </div>

        <div class="form-card reveal" style="--d:.1s" id="form-section">
          <div class="success" id="form-success" hidden>
            <div class="ok"><svg class="icon"><use href="#i-check"/></svg></div>
            <h3>Mensaje enviado</h3>
            <p>Gracias por escribirnos. Te respondemos a la brevedad.</p>
            <button type="button" class="btn btn-ghost" id="form-reset">Enviar otro mensaje</button>
          </div>
          <form id="contact-form" class="form-grid" novalidate>
            <div class="form-grid two">
              <div class="field"><label for="name">Nombre completo *</label><input type="text" id="name" name="name" required autocomplete="name" placeholder="Dr. Juan Pérez"></div>
              <div class="field"><label for="email">Correo electrónico *</label><input type="email" id="email" name="email" required autocomplete="email" placeholder="correo@ejemplo.com"></div>
            </div>
            <div class="form-grid two">
              <div class="field"><label for="phone">Teléfono</label><input type="tel" id="phone" name="phone" autocomplete="tel" placeholder="+502 5555-5555"></div>
              <div class="field"><label for="clinica">Nombre de la clínica</label><input type="text" id="clinica" name="clinica" autocomplete="organization" placeholder="Clínica Médica Ejemplo"></div>
            </div>
            <div class="field"><label for="message">Mensaje *</label><textarea id="message" name="message" rows="4" required placeholder="Contanos sobre tu clínica y qué módulos te interesan"></textarea></div>
            <input type="hidden" name="plan" id="selected-plan" value="">
            <div class="honeypot" aria-hidden="true"><label for="website">No completar</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off"></div>
            <label class="check"><input type="checkbox" id="privacy" required><span>Acepto que me contacten sobre esta solicitud.</span></label>
            <button type="submit" class="btn btn-primary"><span>Enviar mensaje</span><svg class="icon"><use href="#i-send"/></svg></button>
            <p class="form-alt">También podés escribirnos a <a href="mailto:samuel.ramirez25prs@gmail.com">samuel.ramirez25prs@gmail.com</a></p>
          </form>
        </div>
      </div>
    </section>
  </main>

  <!-- Pie -->
  <footer class="footer">
    <div class="wrap footer-grid">
      <div>
        <a href="#inicio" class="brand"><img src="logo.png" alt="" width="36" height="36"><span>ClinicApp<small style="color:rgba(255,255,255,.6)">RS Solutions</small></span></a>
        <p>Sistema de gestión médica modular, seguro y moderno, con herramientas diseñadas para médicos.</p>
        <p class="copy">© <span id="year">2026</span> RS Software Solutions. Todos los derechos reservados.</p>
      </div>
      <div>
        <h3>Enlaces</h3>
        <ul>
          <li><a href="#caracteristicas">Características</a></li>
          <li><a href="#modulos">Módulos</a></li>
          <li><a href="#precios">Precios</a></li>
          <li><a href="#faq">Preguntas frecuentes</a></li>
          <li><a href="#contacto">Contacto</a></li>
        </ul>
      </div>
      <div>
        <h3>Contacto</h3>
        <ul>
          <li>Ciudad de Guatemala</li>
          <li><a href="mailto:samuel.ramirez25prs@gmail.com">samuel.ramirez25prs@gmail.com</a></li>
          <li><a href="https://wa.me/50239029076" target="_blank" rel="noopener noreferrer">+502 3902-9076</a></li>
          <li><a href="https://base.clinicapp.gt/" rel="noopener" style="color:var(--gold-500)">Ingresar al sistema</a></li>
        </ul>
      </div>
    </div>
  </footer>

  <!-- Barra fija -->
  <div class="sticky-cta glass" id="sticky-cta">
    <span>Probá ClinicApp gratis por 30 días. Sin compromiso.</span>
    <a href="#contacto" class="btn btn-primary" data-plan="Demo" data-price="Gratis">Agendar demo</a>
  </div>

  <!-- Chat -->
  <div class="chat" id="chat">
    <div class="chat-panel" id="chat-panel" role="dialog" aria-label="Chat por WhatsApp">
      <div class="chat-head"><span class="av"><svg class="icon"><use href="#i-chat"/></svg></span><div><b>ClinicApp RS Solutions</b><small>En línea</small></div></div>
      <div class="chat-body" id="chat-body">
        <div class="msg in"><div>¡Hola! ¿En qué podemos ayudarte? Dejanos tu consulta y te respondemos por WhatsApp.</div><small>Ahora</small></div>
      </div>
      <div class="chat-input">
        <input type="text" id="chat-input" placeholder="Escribí tu mensaje" maxlength="500" aria-label="Mensaje">
        <button type="button" id="chat-send" aria-label="Enviar mensaje"><svg class="icon"><use href="#i-send"/></svg></button>
      </div>
    </div>
    <button class="chat-toggle" id="chat-toggle" type="button" aria-label="Abrir chat" aria-expanded="false" aria-controls="chat-panel">
      <svg class="icon i-chat"><use href="#i-chat"/></svg>
      <svg class="icon i-close"><use href="#i-x"/></svg>
    </button>
  </div>

  <script>
  (function () {
    'use strict';
    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var WA = '50239029076';
    var yearEl = $('#year'); if (yearEl) yearEl.textContent = new Date().getFullYear();

    /* ── Partículas: células que forman un latido ─────────────── */
    function initParticles() {
      var canvas = $('#particles');
      if (!canvas || !canvas.getContext) return;
      var ctx = canvas.getContext('2d');
      var hero = canvas.parentElement;
      var W = 0, H = 0, dpr = 1, parts = [], line = [], running = true, t0 = performance.now();
      var mouse = { x: -9999, y: -9999 };

      // Un latido (PQRST) en coordenadas relativas
      var BEAT = [[0,0],[.1,0],[.16,-.12],[.22,0],[.3,0],[.33,.12],[.36,-1],[.39,.32],[.42,0],[.55,0],[.62,-.22],[.72,0],[1,0]];

      function buildLine() {
        line = [];
        var baseY = H - (W < 640 ? 56 : 82);
        var amp = W < 640 ? 34 : 54;
        var beatW = Math.max(260, Math.min(420, W / 3.2));
        for (var s = -beatW * .3; s < W + beatW; s += beatW) {
          for (var i = 0; i < BEAT.length; i++) line.push([s + BEAT[i][0] * beatW, baseY + BEAT[i][1] * amp]);
        }
      }
      // Punto sobre la línea a una distancia "d" (0..1) del recorrido
      function pointAt(u) {
        var total = 0, segs = [];
        for (var i = 1; i < line.length; i++) {
          var dx = line[i][0] - line[i-1][0], dy = line[i][1] - line[i-1][1];
          var l = Math.sqrt(dx*dx + dy*dy); segs.push(l); total += l;
        }
        var target = u * total;
        for (var j = 0; j < segs.length; j++) {
          if (target <= segs[j]) { var k = target / segs[j]; return [line[j][0] + (line[j+1][0]-line[j][0])*k, line[j][1] + (line[j+1][1]-line[j][1])*k]; }
          target -= segs[j];
        }
        return line[line.length - 1];
      }

      function resize() {
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        W = hero.clientWidth; H = hero.clientHeight;
        canvas.width = W * dpr; canvas.height = H * dpr;
        canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        buildLine();
        var nLine = W < 640 ? 130 : (W < 1024 ? 200 : 300);
        var nFree = W < 640 ? 10 : 22;
        parts = [];
        for (var i = 0; i < nLine; i++) {
          var u = (i + Math.random() * .35) / nLine;
          var p = pointAt(u);
          parts.push({ hx: p[0], hy: p[1], u: u, x: Math.random() * W, y: Math.random() * H, vx: 0, vy: 0, r: 1.1 + Math.random() * 1.5, onLine: true, ph: Math.random() * 6.28 });
        }
        for (var f = 0; f < nFree; f++) {
          parts.push({ x: (W >= 1024 ? W * .55 + Math.random() * W * .45 : Math.random() * W), y: Math.random() * H, vx: (Math.random() - .5) * .25, vy: (Math.random() - .5) * .25, r: 2 + Math.random() * 3.5, onLine: false, ph: Math.random() * 6.28 });
        }
        t0 = performance.now();
        if (reduced) { parts.forEach(function (p) { if (p.onLine) { p.x = p.hx; p.y = p.hy; } }); draw(t0); }
      }

      function draw(now) {
        var t = (now - t0) / 1000;
        ctx.clearRect(0, 0, W, H);
        // Pulso que recorre la línea cada 2.4 s
        var pulse = (t % 2.4) / 2.4;
        for (var i = 0; i < parts.length; i++) {
          var p = parts[i];
          if (p.onLine) {
            var gather = reduced ? 1 : Math.min(1, Math.max(0, (t - .15) / 1.6));
            var e = 1 - Math.pow(1 - gather, 3);
            var tx = p.hx + Math.sin(t * 1.3 + p.ph) * 2.5;
            var ty = p.hy + Math.cos(t * 1.1 + p.ph) * 2.5;
            if (!reduced) {
              var mdx = p.x - mouse.x, mdy = p.y - mouse.y, md = mdx*mdx + mdy*mdy;
              if (md < 12000) { var f = (12000 - md) / 12000 * 28; var m = Math.sqrt(md) || 1; tx += mdx / m * f; ty += mdy / m * f; }
            }
            p.x += (tx - p.x) * (.04 + e * .08);
            p.y += (ty - p.y) * (.04 + e * .08);
            var near = Math.abs(p.u - pulse); near = Math.min(near, 1 - near);
            var glow = Math.max(0, 1 - near * 14) * e;
            ctx.beginPath();
            ctx.fillStyle = 'rgba(' + (12 + glow * 233 | 0) + ',' + (92 + glow * 92 | 0) + ',' + (92 - glow * 2 | 0) + ',' + (.55 + glow * .45) + ')';
            ctx.arc(p.x, p.y, p.r + glow * 2.2, 0, 6.283);
            ctx.fill();
          } else {
            if (!reduced) {
              p.x += p.vx + Math.sin(t * .6 + p.ph) * .12; p.y += p.vy + Math.cos(t * .5 + p.ph) * .12;
              if (p.x < -10) p.x = W + 10; if (p.x > W + 10) p.x = -10; if (p.y < -10) p.y = H + 10; if (p.y > H + 10) p.y = -10;
            }
            ctx.beginPath();
            ctx.fillStyle = 'rgba(26, 140, 135, .08)';
            ctx.arc(p.x, p.y, p.r * 2.2, 0, 6.283); ctx.fill();
            ctx.beginPath();
            ctx.strokeStyle = 'rgba(12, 92, 92, .13)'; ctx.lineWidth = 1;
            ctx.arc(p.x, p.y, p.r * 2.2, 0, 6.283); ctx.stroke();
          }
        }
        // Hilos entre partículas cercanas al cursor
        if (!reduced && mouse.x > -1) {
          ctx.lineWidth = 1;
          for (var a = 0; a < parts.length; a++) {
            var q = parts[a], dx = q.x - mouse.x, dy = q.y - mouse.y, d = dx*dx + dy*dy;
            if (d < 26000) { ctx.strokeStyle = 'rgba(12, 92, 92,' + (.28 * (1 - d / 26000)) + ')'; ctx.beginPath(); ctx.moveTo(q.x, q.y); ctx.lineTo(mouse.x, mouse.y); ctx.stroke(); }
          }
        }
      }

      function loop(now) { if (running) draw(now); if (!reduced) requestAnimationFrame(loop); }

      resize();
      if (!reduced) requestAnimationFrame(loop);
      var rt; window.addEventListener('resize', function () { clearTimeout(rt); rt = setTimeout(resize, 200); });
      hero.addEventListener('pointermove', function (e) { var r = canvas.getBoundingClientRect(); mouse.x = e.clientX - r.left; mouse.y = e.clientY - r.top; });
      hero.addEventListener('pointerleave', function () { mouse.x = mouse.y = -9999; });
      if ('IntersectionObserver' in window) new IntersectionObserver(function (en) { running = en[0].isIntersecting; }).observe(hero);
      document.addEventListener('visibilitychange', function () { running = !document.hidden; });
    }

    /* ── Forma líquida (liquid morph) ─────────────────────────── */
    function initLiquid() {
      var main = $('#blobMain'), gold = $('#blobGold'), shine = $('#blobShine');
      if (!main) return;
      function blob(cx, cy, r, t, seed, n) {
        var pts = [];
        for (var i = 0; i < n; i++) {
          var a = i / n * Math.PI * 2;
          var k = 1 + .09 * Math.sin(a * 3 + t * .9 + seed) + .06 * Math.sin(a * 5 - t * 1.3 + seed * 2) + .04 * Math.cos(a * 2 + t * .6);
          pts.push([cx + Math.cos(a) * r * k, cy + Math.sin(a) * r * k]);
        }
        // Curva suave (Catmull-Rom a Bézier)
        var d = 'M' + pts[0][0].toFixed(1) + ' ' + pts[0][1].toFixed(1);
        for (var j = 0; j < n; j++) {
          var p0 = pts[(j - 1 + n) % n], p1 = pts[j], p2 = pts[(j + 1) % n], p3 = pts[(j + 2) % n];
          d += 'C' + (p1[0] + (p2[0] - p0[0]) / 6).toFixed(1) + ' ' + (p1[1] + (p2[1] - p0[1]) / 6).toFixed(1) + ' ' +
               (p2[0] - (p3[0] - p1[0]) / 6).toFixed(1) + ' ' + (p2[1] - (p3[1] - p1[1]) / 6).toFixed(1) + ' ' +
               p2[0].toFixed(1) + ' ' + p2[1].toFixed(1);
        }
        return d + 'Z';
      }
      function frame(now) {
        var t = now / 1000;
        main.setAttribute('d', blob(300, 300, 205, t, 0, 9));
        shine.setAttribute('d', blob(300, 300, 205, t, 0, 9));
        gold.setAttribute('d', blob(430 + Math.sin(t * .5) * 12, 170 + Math.cos(t * .4) * 10, 70, t * 1.2, 3, 7));
        if (!reduced) requestAnimationFrame(frame);
      }
      requestAnimationFrame(frame);
    }

    /* ── Aparición al hacer scroll ────────────────────────────── */
    function initReveal() {
      var els = $$('.reveal');
      if (reduced || !('IntersectionObserver' in window)) { els.forEach(function (e) { e.classList.add('in'); }); return; }
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
      }, { threshold: .12, rootMargin: '0px 0px -40px 0px' });
      els.forEach(function (e) { io.observe(e); });
    }

    /* ── Contadores ───────────────────────────────────────────── */
    function initCounters() {
      if (reduced || !('IntersectionObserver' in window)) return;
      $$('[data-count]').forEach(function (el) {
        var raw = el.getAttribute('data-count');
        var num = parseFloat(raw) || 0;
        var suffix = raw.replace(/[\d.,]/g, '');
        var decimals = (raw.split('.')[1] || '').replace(/\D/g, '').length;
        el.textContent = '0' + suffix;
        var io = new IntersectionObserver(function (en) {
          if (!en[0].isIntersecting) return;
          io.disconnect();
          var start = performance.now(), dur = 1400;
          (function step(now) {
            var k = Math.min(1, (now - start) / dur), e = 1 - Math.pow(1 - k, 3);
            el.textContent = (num * e).toFixed(decimals) + suffix;
            if (k < 1) requestAnimationFrame(step); else el.textContent = raw;
          })(start);
        }, { threshold: .5 });
        io.observe(el);
      });
    }

    /* ── Barras de comparación ────────────────────────────────── */
    function initBars() {
      var bars = $$('.bar span');
      function fill() { bars.forEach(function (b) { b.style.width = b.getAttribute('data-width'); }); }
      if (reduced || !('IntersectionObserver' in window)) { fill(); return; }
      var io = new IntersectionObserver(function (en) { if (en[0].isIntersecting) { fill(); io.disconnect(); } }, { threshold: .25 });
      var roi = $('.roi'); if (roi) io.observe(roi);
    }

    /* ── Navegación y barra fija ──────────────────────────────── */
    function initNav() {
      var nav = $('#navbar'), sticky = $('#sticky-cta'), hero = $('#inicio'), contact = $('#contacto');
      function onScroll() {
        var y = window.scrollY;
        nav.classList.toggle('scrolled', y > 20);
        var past = y > hero.offsetHeight * .8;
        var atContact = contact.getBoundingClientRect().top < window.innerHeight * .9;
        sticky.classList.toggle('show', past && !atContact);
      }
      window.addEventListener('scroll', onScroll, { passive: true });
      onScroll();

      var menu = $('#mobile-menu'), btn = $('#menu-btn');
      function setMenu(open) {
        menu.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.classList.toggle('menu-open', open);
        if (open) { var first = menu.querySelector('a'); if (first) first.focus(); } else { btn.focus(); }
      }
      btn.addEventListener('click', function () { setMenu(menu.hidden); });
      $$('[data-close-menu]').forEach(function (el) { el.addEventListener('click', function () { setMenu(false); }); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menu.hidden) setMenu(false); });
    }

    /* ── Botón líquido: el relleno nace donde está el puntero ─── */
    function initButtons() {
      document.addEventListener('pointerover', function (e) {
        var b = e.target.closest && e.target.closest('.btn');
        if (!b) return;
        var r = b.getBoundingClientRect();
        b.style.setProperty('--x', (e.clientX - r.left) + 'px');
        b.style.setProperty('--y', (e.clientY - r.top) + 'px');
      });
    }

    /* ── Preguntas frecuentes ─────────────────────────────────── */
    function initFAQ() {
      $$('.faq-item button').forEach(function (b) {
        b.addEventListener('click', function () {
          var item = b.parentElement, open = !item.classList.contains('open');
          $$('.faq-item').forEach(function (o) { o.classList.remove('open'); o.querySelector('button').setAttribute('aria-expanded', 'false'); });
          item.classList.toggle('open', open);
          b.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
      });
    }

    /* ── Aviso ─────────────────────────────────────────────────── */
    function toast(msg, type) {
      var el = $('#toast');
      $('#toastMsg').textContent = msg;
      el.classList.toggle('error', type === 'error');
      el.classList.add('show');
      clearTimeout(toast._t);
      toast._t = setTimeout(function () { el.classList.remove('show'); }, 4500);
    }

    /* ── Elegir plan → completa el mensaje del formulario ─────── */
    function selectPlan(plan, price) {
      var msg = $('#message'), planInput = $('#selected-plan');
      if (planInput) planInput.value = plan + (price ? ' (' + price + ')' : '');
      if (msg) {
        if (plan === 'Personalizado') msg.value = 'Hola, me gustaría recibir una cotización personalizada para mi clínica.';
        else if (plan === 'Demo') msg.value = 'Hola, me gustaría agendar una demo gratuita de ClinicApp.';
        else msg.value = 'Hola, me interesa el plan ' + plan + (price ? ' (' + price + '/mes)' : '') + '. Me gustaría recibir más información.';
      }
      setTimeout(function () { if (msg) msg.focus({ preventScroll: true }); }, 700);
    }
    function initPlans() {
      document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('[data-plan]');
        if (a) selectPlan(a.getAttribute('data-plan'), a.getAttribute('data-price') || '');
      });
    }

    /* ── Calculadora (mismas reglas de precio que antes) ──────── */
    function initCalc() {
      var mods = $$('.calc-module');
      var monthlyEl = $('#calc-monthly'), yearlyEl = $('#calc-yearly'), countEl = $('#calc-count');
      var savingsEl = $('#calc-savings'), savingAmt = $('#calc-saving-amount'), fill = $('#calc-fill');
      var allTotal = mods.reduce(function (s, m) { return s + (parseInt(m.getAttribute('data-price'), 10) || 0); }, 0);
      var current = 0;
      function fmt(n) { return n.toLocaleString('es-GT'); }
      function update() {
        var total = 0, count = 0;
        mods.forEach(function (m) {
          var cb = m.querySelector('input');
          m.classList.toggle('selected', cb.checked);
          if (cb.checked) { total += parseInt(m.getAttribute('data-price'), 10) || 0; count++; }
        });
        var sel = count - 1, display, base, label = '';
        if (sel === 0) { display = 0; base = 0; }
        else if (sel === 1) { display = 199; base = total; label = 'Plan Pro'; }
        else if (sel === 2) { display = 398; base = total; label = 'Plan Profesional'; }
        else if (sel >= 9) { display = 1044; base = allTotal; label = 'Plan Enterprise'; }
        else { display = total; base = total; }
        current = display;
        if (base > display) monthlyEl.innerHTML = '<span class="strike">Q' + fmt(base) + '</span><span class="gold">Q' + fmt(display) + '</span>';
        else monthlyEl.textContent = 'Q' + fmt(display);
        yearlyEl.textContent = 'Q' + fmt(display * 12);
        countEl.textContent = sel + (sel === 1 ? ' seleccionado' : ' seleccionados');
        if (base > display && display > 0) { savingAmt.textContent = label + ': ahorrás Q' + fmt(base - display) + ' al mes'; savingsEl.hidden = false; }
        else savingsEl.hidden = true;
        // El líquido sube según el total (mínimo visible 8%, máximo 92%)
        if (fill) fill.style.height = (8 + Math.min(1, display / 1044) * 84) + '%';
      }
      mods.forEach(function (m) { m.querySelector('input').addEventListener('change', update); });
      $('#calc-request').addEventListener('click', function () { selectPlan('Personalizado a medida', 'Q' + fmt(current)); });
      update();
    }

    /* ── Formulario de contacto ───────────────────────────────── */
    function initForm() {
      var form = $('#contact-form'), success = $('#form-success');
      if (!form) return;
      $('#form-reset').addEventListener('click', function () { form.reset(); form.hidden = false; success.hidden = true; });
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (form.elements.website.value) { toast('Mensaje recibido.'); form.reset(); return; }
        var name = form.elements.name.value.trim(), email = form.elements.email.value.trim(), message = form.elements.message.value.trim();
        if (!name || !email || !message) { toast('Completá los campos marcados con *.', 'error'); return; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { toast('Revisá el correo: parece incompleto.', 'error'); return; }
        if (!$('#privacy').checked) { toast('Marcá la casilla para que podamos contactarte.', 'error'); return; }
        var btn = form.querySelector('button[type="submit"]'), label = btn.querySelector('span'), orig = label.textContent;
        btn.disabled = true; label.textContent = 'Enviando';
        var done = false;
        var timer = setTimeout(function () { done = true; btn.disabled = false; label.textContent = orig; toast('El envío tardó demasiado. Intentá de nuevo o escribinos por WhatsApp.', 'error'); }, 12000);
        fetch('send_contact.php', { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
          .then(function (res) {
            if (done) return; clearTimeout(timer);
            if (!res || !res.success) throw new Error(res && res.error ? res.error : 'Error');
            toast('Mensaje enviado. Te contactamos en 2 horas hábiles.');
            form.hidden = true; success.hidden = false;
          })
          .catch(function () {
            if (done) return; clearTimeout(timer);
            var text = 'Contacto desde ClinicApp\n\nNombre: ' + name + '\nCorreo: ' + email + '\nTeléfono: ' + form.elements.phone.value + '\nClínica: ' + form.elements.clinica.value + '\nPlan: ' + form.elements.plan.value + '\n\nMensaje:\n' + message;
            window.open('https://wa.me/' + WA + '?text=' + encodeURIComponent(text), '_blank');
            toast('No se pudo enviar el correo. Abrimos WhatsApp con tus datos.');
          })
          .finally(function () { if (!done) { btn.disabled = false; label.textContent = orig; } });
      });
    }

    /* ── Chat → WhatsApp ──────────────────────────────────────── */
    function initChat() {
      var chat = $('#chat'), toggle = $('#chat-toggle'), input = $('#chat-input'), body = $('#chat-body');
      toggle.addEventListener('click', function () {
        var open = chat.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Cerrar chat' : 'Abrir chat');
        if (open) setTimeout(function () { input.focus(); }, 250);
      });
      function bubble(cls, html) { var d = document.createElement('div'); d.className = 'msg ' + cls; d.innerHTML = html; body.appendChild(d); body.scrollTop = body.scrollHeight; return d; }
      function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
      function send() {
        var text = input.value.trim(); if (!text) return;
        input.value = '';
        bubble('out', '<div>' + esc(text) + '</div><small>Ahora</small>');
        var typing = bubble('in', '<div class="typing"><span></span><span></span><span></span></div>');
        setTimeout(function () {
          typing.remove();
          bubble('in', '<div>Gracias por escribirnos. Te llevamos a WhatsApp para seguir la conversación.</div><small>Ahora</small>');
          setTimeout(function () { window.open('https://wa.me/' + WA + '?text=' + encodeURIComponent(text), '_blank'); }, 1100);
        }, 1300);
      }
      $('#chat-send').addEventListener('click', send);
      input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); send(); } });
    }

    initParticles(); initLiquid(); initReveal(); initCounters(); initBars();
    initNav(); initButtons(); initFAQ(); initPlans(); initCalc(); initForm(); initChat();
  })();
  </script>
</body>
</html>
