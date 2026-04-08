<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $settings->organization_name ?? 'جمعية أبي ذر الغفاري' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary:   #1a56db;
            --primary-dark: #1245b8;
            --accent:    #0ea5e9;
            --teal:      #06b6d4;
            --green:     #10b981;
            --dark:      #0f172a;
            --dark2:     #1e293b;
            --muted:     #94a3b8;
            --light:     #f1f5f9;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Cairo', sans-serif;
            background: var(--dark);
            color: white;
            overflow-x: hidden;
        }

        /* ─── Scrollbar ─── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: var(--dark2); }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }

        /* ─── NAV ─── */
        .nav-wrap {
            position: fixed; top: 0; left: 0; right: 0; z-index: 999;
            padding: 0;
            transition: background .3s, backdrop-filter .3s, box-shadow .3s;
        }
        .nav-wrap.scrolled {
            background: rgba(15,23,42,.85);
            backdrop-filter: blur(20px);
            box-shadow: 0 1px 0 rgba(255,255,255,.05);
        }
        .nav-inner {
            max-width: 1200px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
            padding: 1.1rem 1.5rem;
        }
        .nav-brand {
            display: flex; align-items: center; gap: .75rem;
            font-size: 1.15rem; font-weight: 700; color: white; text-decoration: none;
        }
        .nav-brand .icon-wrap {
            width: 38px; height: 38px; border-radius: 10px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem;
        }
        .nav-brand img { height: 38px; border-radius: 10px; object-fit: contain; }
        .nav-links { display: flex; align-items: center; gap: 2rem; list-style: none; }
        .nav-links a {
            color: #cbd5e1; font-size: .9rem; font-weight: 500;
            text-decoration: none; transition: color .2s;
        }
        .nav-links a:hover { color: white; }
        .btn-nav {
            background: var(--primary); color: white !important;
            padding: .55rem 1.4rem; border-radius: 8px;
            font-size: .88rem; font-weight: 600; text-decoration: none;
            transition: background .2s, transform .2s;
        }
        .btn-nav:hover { background: var(--primary-dark); transform: translateY(-1px); }
        .nav-toggler {
            display: none; background: none; border: none; color: white;
            font-size: 1.3rem; cursor: pointer;
        }

        /* ─── HERO ─── */
        .hero {
            min-height: 100vh;
            display: flex; align-items: center;
            position: relative; overflow: hidden;
            background: var(--dark);
            padding-top: 80px;
        }
        .hero-bg {
            position: absolute; inset: 0; z-index: 0;
        }
        /* Grid lines */
        .hero-bg::before {
            content: '';
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(26,86,219,.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(26,86,219,.08) 1px, transparent 1px);
            background-size: 60px 60px;
        }
        /* Glow blobs */
        .blob {
            position: absolute; border-radius: 50%;
            filter: blur(90px); opacity: .25; pointer-events: none;
        }
        .blob-1 { width: 600px; height: 600px; background: var(--primary); top: -200px; right: -150px; animation: blobFloat 10s ease-in-out infinite; }
        .blob-2 { width: 500px; height: 500px; background: var(--teal); bottom: -150px; left: -100px; animation: blobFloat 14s ease-in-out infinite reverse; }
        .blob-3 { width: 300px; height: 300px; background: var(--accent); top: 40%; left: 40%; animation: blobFloat 8s ease-in-out infinite 2s; }
        @keyframes blobFloat {
            0%,100% { transform: translate(0,0) scale(1); }
            33% { transform: translate(30px,-30px) scale(1.05); }
            66% { transform: translate(-20px,20px) scale(.95); }
        }

        .hero-inner {
            position: relative; z-index: 1;
            max-width: 1200px; margin: 0 auto; padding: 0 1.5rem;
            display: grid; grid-template-columns: 1fr 1fr; align-items: center; gap: 4rem;
        }
        .hero-badge {
            display: inline-flex; align-items: center; gap: .5rem;
            background: rgba(26,86,219,.15); border: 1px solid rgba(26,86,219,.3);
            padding: .4rem 1rem; border-radius: 20px;
            font-size: .8rem; color: #93c5fd; margin-bottom: 1.5rem;
        }
        .hero-badge span { width: 8px; height: 8px; border-radius: 50%; background: #3b82f6; display: inline-block; animation: pulse-dot 1.5s infinite; }
        @keyframes pulse-dot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(.8)} }

        .hero h1 {
            font-size: clamp(2.2rem, 4.5vw, 3.5rem);
            font-weight: 800; line-height: 1.2; margin-bottom: 1.5rem;
            letter-spacing: -.5px;
        }
        .hero h1 .highlight {
            background: linear-gradient(135deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .hero p {
            font-size: 1.1rem; color: #94a3b8; line-height: 1.8; margin-bottom: 2.5rem;
        }
        .hero-btns { display: flex; gap: 1rem; flex-wrap: wrap; }
        .btn-hero-primary {
            display: inline-flex; align-items: center; gap: .6rem;
            background: var(--primary); color: white;
            padding: .85rem 2rem; border-radius: 10px;
            font-weight: 700; font-size: 1rem; text-decoration: none;
            transition: all .25s; border: none;
        }
        .btn-hero-primary:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 12px 30px rgba(26,86,219,.4); color: white; }
        .btn-hero-secondary {
            display: inline-flex; align-items: center; gap: .6rem;
            background: rgba(255,255,255,.06); color: white;
            padding: .85rem 2rem; border-radius: 10px;
            font-weight: 600; font-size: 1rem; text-decoration: none;
            border: 1px solid rgba(255,255,255,.12); transition: all .25s;
        }
        .btn-hero-secondary:hover { background: rgba(255,255,255,.1); transform: translateY(-2px); color: white; }

        /* Hero visual */
        .hero-visual {
            display: flex; align-items: center; justify-content: center;
        }
        .dashboard-mock {
            background: rgba(30,41,59,.8);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 20px; padding: 1.5rem;
            backdrop-filter: blur(20px);
            box-shadow: 0 40px 80px rgba(0,0,0,.5);
            width: 100%; max-width: 480px;
            animation: floatCard 6s ease-in-out infinite;
        }
        @keyframes floatCard {
            0%,100% { transform: translateY(0); }
            50% { transform: translateY(-12px); }
        }
        .mock-header {
            display: flex; align-items: center; gap: .5rem;
            margin-bottom: 1.2rem;
        }
        .mock-dot { width: 12px; height: 12px; border-radius: 50%; }
        .mock-title { font-size: .8rem; color: #64748b; margin-right: auto; }
        .mock-stats { display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; margin-bottom: 1rem; }
        .mock-stat {
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.06);
            border-radius: 12px; padding: 1rem;
        }
        .mock-stat-icon { font-size: 1.2rem; margin-bottom: .4rem; }
        .mock-stat-val { font-size: 1.3rem; font-weight: 700; line-height: 1; }
        .mock-stat-lbl { font-size: .7rem; color: #64748b; margin-top: .2rem; }
        .mock-bar-wrap { margin-bottom: 1rem; }
        .mock-bar-label { display: flex; justify-content: space-between; font-size: .72rem; color: #64748b; margin-bottom: .35rem; }
        .mock-bar { height: 6px; border-radius: 3px; background: rgba(255,255,255,.06); overflow: hidden; }
        .mock-bar-fill { height: 100%; border-radius: 3px; }
        .mock-list { }
        .mock-list-item {
            display: flex; align-items: center; gap: .6rem;
            padding: .55rem 0; border-bottom: 1px solid rgba(255,255,255,.04);
            font-size: .78rem; color: #94a3b8;
        }
        .mock-list-item:last-child { border-bottom: none; }
        .mock-avatar {
            width: 28px; height: 28px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: .7rem; font-weight: 700; flex-shrink: 0;
        }
        .mock-list-name { flex: 1; }
        .mock-badge { padding: .15rem .55rem; border-radius: 20px; font-size: .65rem; font-weight: 600; }

        /* ─── STATS BAR ─── */
        .stats-bar {
            background: rgba(30,41,59,.6);
            border-top: 1px solid rgba(255,255,255,.05);
            border-bottom: 1px solid rgba(255,255,255,.05);
            backdrop-filter: blur(20px);
        }
        .stats-bar-inner {
            max-width: 1200px; margin: 0 auto;
            display: grid; grid-template-columns: repeat(4, 1fr);
            padding: 0 1.5rem;
        }
        .stat-cell {
            padding: 2.5rem 2rem; text-align: center;
            border-left: 1px solid rgba(255,255,255,.05);
        }
        .stat-cell:last-child { border-left: none; }
        .stat-num {
            font-size: 2.2rem; font-weight: 800;
            background: linear-gradient(135deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
            line-height: 1;
        }
        .stat-lbl { font-size: .85rem; color: var(--muted); margin-top: .4rem; }

        /* ─── FEATURES ─── */
        .section { padding: 100px 0; }
        .section-inner { max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; }
        .section-tag {
            display: inline-block;
            background: rgba(26,86,219,.15); border: 1px solid rgba(26,86,219,.3);
            color: #93c5fd; padding: .3rem .9rem; border-radius: 20px;
            font-size: .78rem; font-weight: 600; margin-bottom: 1rem;
        }
        .section-title {
            font-size: clamp(1.8rem, 3vw, 2.5rem); font-weight: 800;
            line-height: 1.25; margin-bottom: 1rem;
        }
        .section-subtitle { font-size: 1rem; color: var(--muted); max-width: 550px; line-height: 1.7; }

        .features-grid {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem; margin-top: 4rem;
        }
        .feat-card {
            background: rgba(30,41,59,.5);
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 16px; padding: 2rem;
            transition: all .3s; position: relative; overflow: hidden;
        }
        .feat-card::before {
            content: ''; position: absolute; inset: 0; border-radius: 16px;
            background: linear-gradient(135deg, rgba(26,86,219,.1), transparent);
            opacity: 0; transition: opacity .3s;
        }
        .feat-card:hover { transform: translateY(-6px); border-color: rgba(26,86,219,.3); }
        .feat-card:hover::before { opacity: 1; }
        .feat-icon {
            width: 52px; height: 52px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; margin-bottom: 1.2rem;
        }
        .feat-card h4 { font-size: 1.05rem; font-weight: 700; margin-bottom: .6rem; }
        .feat-card p { font-size: .88rem; color: var(--muted); line-height: 1.7; }

        /* ─── ROLES ─── */
        .roles-section { background: rgba(15,23,42,.8); }
        .roles-grid {
            display: grid; grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem; margin-top: 4rem;
        }
        .role-card {
            background: rgba(30,41,59,.5);
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 16px; padding: 2rem 2.5rem;
            display: flex; align-items: flex-start; gap: 1.5rem;
            transition: all .3s;
        }
        .role-card:hover { transform: translateY(-4px); border-color: rgba(26,86,219,.3); }
        .role-icon-wrap {
            width: 56px; height: 56px; border-radius: 14px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 1.4rem;
        }
        .role-card h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: .5rem; }
        .role-card p { font-size: .88rem; color: var(--muted); line-height: 1.7; }

        /* ─── CTA ─── */
        .cta-section {
            background: linear-gradient(135deg, var(--primary), #7c3aed);
            position: relative; overflow: hidden;
        }
        .cta-section::before {
            content: ''; position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        .cta-inner {
            position: relative; z-index: 1; text-align: center;
            max-width: 700px; margin: 0 auto; padding: 0 1.5rem;
        }
        .cta-inner h2 { font-size: 2.5rem; font-weight: 800; margin-bottom: 1rem; }
        .cta-inner p { font-size: 1.05rem; opacity: .85; margin-bottom: 2.5rem; line-height: 1.7; }
        .btn-cta {
            display: inline-flex; align-items: center; gap: .6rem;
            background: white; color: var(--primary);
            padding: .9rem 2.5rem; border-radius: 10px;
            font-weight: 700; font-size: 1rem; text-decoration: none;
            transition: all .25s;
        }
        .btn-cta:hover { transform: translateY(-2px); box-shadow: 0 15px 40px rgba(0,0,0,.3); color: var(--primary-dark); }

        /* ─── FOOTER ─── */
        .footer {
            background: #060d1a;
            border-top: 1px solid rgba(255,255,255,.05);
        }
        .footer-top {
            max-width: 1200px; margin: 0 auto; padding: 4rem 1.5rem 2rem;
            display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 3rem;
        }
        .footer-brand { }
        .footer-brand .brand-name {
            display: flex; align-items: center; gap: .75rem;
            font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;
        }
        .footer-brand .brand-name .icon-wrap {
            width: 36px; height: 36px; border-radius: 9px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex; align-items: center; justify-content: center; font-size: .9rem;
        }
        .footer-brand p { font-size: .88rem; color: var(--muted); line-height: 1.7; max-width: 300px; }
        .footer-col h6 { font-size: .88rem; font-weight: 700; color: #e2e8f0; margin-bottom: 1.2rem; }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: .65rem; }
        .footer-col ul li a { font-size: .85rem; color: var(--muted); text-decoration: none; transition: color .2s; }
        .footer-col ul li a:hover { color: white; }
        .footer-bottom {
            max-width: 1200px; margin: 0 auto;
            padding: 1.5rem 1.5rem;
            border-top: 1px solid rgba(255,255,255,.05);
            display: flex; align-items: center; justify-content: space-between;
            font-size: .82rem; color: var(--muted);
        }
        .footer-bottom a { color: #60a5fa; text-decoration: none; }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 992px) {
            .hero-inner { grid-template-columns: 1fr; gap: 2.5rem; }
            .hero-visual { order: -1; }
            .dashboard-mock { max-width: 380px; }
            .features-grid { grid-template-columns: repeat(2, 1fr); }
            .stats-bar-inner { grid-template-columns: repeat(2, 1fr); }
            .stat-cell { border-bottom: 1px solid rgba(255,255,255,.05); }
            .footer-top { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .nav-links.open {
                display: flex; flex-direction: column; gap: 1rem;
                position: fixed; top: 0; left: 0; right: 0;
                background: rgba(15,23,42,.97); backdrop-filter: blur(20px);
                padding: 5rem 2rem 2rem; z-index: 998;
            }
            .nav-toggler { display: block; }
            .features-grid { grid-template-columns: 1fr; }
            .roles-grid { grid-template-columns: 1fr; }
            .stats-bar-inner { grid-template-columns: 1fr 1fr; }
            .footer-top { grid-template-columns: 1fr; gap: 2rem; }
            .footer-bottom { flex-direction: column; gap: .5rem; text-align: center; }
        }
    </style>
</head>
<body>

<!-- ─── NAV ─── -->
<nav class="nav-wrap" id="navbar">
    <div class="nav-inner">
        <a class="nav-brand" href="/">
            @if(isset($settings) && $settings->logo)
                <img src="{{ '/storage/app/public/' . $settings->logo }}" alt="Logo">
            @else
                <div class="icon-wrap"><i class="fas fa-hand-holding-heart"></i></div>
            @endif
            <span>{{ $settings->organization_name ?? 'جمعية أبي ذر الغفاري' }}</span>
        </a>

        <ul class="nav-links" id="navLinks">
            <li><a href="#features">المميزات</a></li>
            <li><a href="#roles">الأدوار</a></li>
            <li><a href="#stats">الإحصائيات</a></li>
            @if(auth()->check())
                <li><a href="{{ route('dashboard') }}" class="btn-nav"><i class="fas fa-th-large"></i> لوحة التحكم</a></li>
            @else
                <li><a href="{{ route('login') }}" class="btn-nav"><i class="fas fa-sign-in-alt"></i> تسجيل الدخول</a></li>
            @endif
        </ul>

        <button class="nav-toggler" id="navToggler" aria-label="القائمة">
            <i class="fas fa-bars" id="toggleIcon"></i>
        </button>
    </div>
</nav>

<!-- ─── HERO ─── -->
<section class="hero">
    <div class="hero-bg">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
    </div>
    <div class="hero-inner">
        <div>
            <div class="hero-badge">
                <span></span>
                النظام المتكامل لإدارة الجمعيات الخيرية
            </div>
            <h1>
                أدر جمعيتك<br>
                <span class="highlight">بذكاء وكفاءة</span>
            </h1>
            <p>منصة شاملة تُمكّنك من إدارة العهد والمصروفات والحالات الاجتماعية والتقارير — كل شيء في مكان واحد.</p>
            <div class="hero-btns">
                @if(auth()->check())
                    <a href="{{ route('dashboard') }}" class="btn-hero-primary">
                        <i class="fas fa-th-large"></i> لوحة التحكم
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn-hero-primary">
                        <i class="fas fa-sign-in-alt"></i> ابدأ الآن
                    </a>
                    <a href="#features" class="btn-hero-secondary">
                        <i class="fas fa-play-circle"></i> اكتشف المميزات
                    </a>
                @endif
            </div>
        </div>

        <div class="hero-visual">
            <div class="dashboard-mock">
                <div class="mock-header">
                    <div class="mock-dot" style="background:#ef4444"></div>
                    <div class="mock-dot" style="background:#f59e0b"></div>
                    <div class="mock-dot" style="background:#22c55e"></div>
                    <span class="mock-title">لوحة التحكم</span>
                </div>

                <div class="mock-stats">
                    <div class="mock-stat">
                        <div class="mock-stat-icon" style="color:#60a5fa"><i class="fas fa-wallet"></i></div>
                        <div class="mock-stat-val" style="color:#60a5fa">٢٥,٠٠٠</div>
                        <div class="mock-stat-lbl">رصيد الخزينة</div>
                    </div>
                    <div class="mock-stat">
                        <div class="mock-stat-icon" style="color:#a78bfa"><i class="fas fa-briefcase"></i></div>
                        <div class="mock-stat-val" style="color:#a78bfa">١٢</div>
                        <div class="mock-stat-lbl">عهدة نشطة</div>
                    </div>
                    <div class="mock-stat">
                        <div class="mock-stat-icon" style="color:#34d399"><i class="fas fa-receipt"></i></div>
                        <div class="mock-stat-val" style="color:#34d399">٨,٤٠٠</div>
                        <div class="mock-stat-lbl">مصروفات الشهر</div>
                    </div>
                    <div class="mock-stat">
                        <div class="mock-stat-icon" style="color:#fb923c"><i class="fas fa-users"></i></div>
                        <div class="mock-stat-val" style="color:#fb923c">٤٧</div>
                        <div class="mock-stat-lbl">حالة اجتماعية</div>
                    </div>
                </div>

                <div class="mock-bar-wrap">
                    <div class="mock-bar-label"><span>استهلاك الميزانية</span><span style="color:#60a5fa">٦٨%</span></div>
                    <div class="mock-bar"><div class="mock-bar-fill" style="width:68%;background:linear-gradient(90deg,#1a56db,#0ea5e9)"></div></div>
                </div>
                <div class="mock-bar-wrap">
                    <div class="mock-bar-label"><span>العهد المسترجعة</span><span style="color:#34d399">٤٢%</span></div>
                    <div class="mock-bar"><div class="mock-bar-fill" style="width:42%;background:linear-gradient(90deg,#059669,#34d399)"></div></div>
                </div>

                <div class="mock-list">
                    <div class="mock-list-item">
                        <div class="mock-avatar" style="background:rgba(96,165,250,.15);color:#60a5fa">أح</div>
                        <span class="mock-list-name">أحمد محمد</span>
                        <span class="mock-badge" style="background:rgba(34,197,94,.15);color:#4ade80">نشط</span>
                    </div>
                    <div class="mock-list-item">
                        <div class="mock-avatar" style="background:rgba(167,139,250,.15);color:#a78bfa">سا</div>
                        <span class="mock-list-name">سارة أحمد</span>
                        <span class="mock-badge" style="background:rgba(251,146,60,.15);color:#fb923c">معلق</span>
                    </div>
                    <div class="mock-list-item">
                        <div class="mock-avatar" style="background:rgba(52,211,153,.15);color:#34d399">مح</div>
                        <span class="mock-list-name">محمد علي</span>
                        <span class="mock-badge" style="background:rgba(96,165,250,.15);color:#60a5fa">مكتمل</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ─── STATS BAR ─── -->
<div class="stats-bar" id="stats">
    <div class="stats-bar-inner">
        <div class="stat-cell">
            <div class="stat-num" data-count="500">0</div>
            <div class="stat-lbl">جمعية مسجلة</div>
        </div>
        <div class="stat-cell">
            <div class="stat-num" data-count="50000">0</div>
            <div class="stat-lbl">مستفيد</div>
        </div>
        <div class="stat-cell">
            <div class="stat-num" data-count="1500">0</div>
            <div class="stat-lbl">موظف نشط</div>
        </div>
        <div class="stat-cell">
            <div class="stat-num" data-count="99">0</div>
            <div class="stat-lbl">% رضا المستخدمين</div>
        </div>
    </div>
</div>

<!-- ─── FEATURES ─── -->
<section class="section" id="features">
    <div class="section-inner">
        <div style="text-align:center;max-width:600px;margin:0 auto;">
            <div class="section-tag">المميزات</div>
            <h2 class="section-title">كل ما تحتاجه في منصة واحدة</h2>
            <p class="section-subtitle" style="margin:0 auto;">أدوات متكاملة مصممة لتبسيط العمل وضمان الشفافية المالية الكاملة</p>
        </div>

        <div class="features-grid">
            <div class="feat-card">
                <div class="feat-icon" style="background:rgba(96,165,250,.15);color:#60a5fa">
                    <i class="fas fa-wallet"></i>
                </div>
                <h4>إدارة الخزينة</h4>
                <p>تتبع دقيق للأرصدة والحركات المالية مع تقارير فورية وسجل كامل لكل عملية</p>
            </div>
            <div class="feat-card">
                <div class="feat-icon" style="background:rgba(167,139,250,.15);color:#a78bfa">
                    <i class="fas fa-briefcase"></i>
                </div>
                <h4>نظام العهد</h4>
                <p>إصدار العهد وتتبعها لحظة بلحظة مع ضمانات الاسترجاع وإشعارات تلقائية</p>
            </div>
            <div class="feat-card">
                <div class="feat-icon" style="background:rgba(52,211,153,.15);color:#34d399">
                    <i class="fas fa-receipt"></i>
                </div>
                <h4>إدارة المصروفات</h4>
                <p>تصنيف رباعي المستويات للمصروفات مع مراجعة المحاسب وقفل التعديل تلقائياً</p>
            </div>
            <div class="feat-card">
                <div class="feat-icon" style="background:rgba(251,146,60,.15);color:#fb923c">
                    <i class="fas fa-users"></i>
                </div>
                <h4>الحالات الاجتماعية</h4>
                <p>متابعة شاملة للمستفيدين وتوثيق احتياجاتهم وربطها بالمصروفات مباشرة</p>
            </div>
            <div class="feat-card">
                <div class="feat-icon" style="background:rgba(244,63,94,.15);color:#f43f5e">
                    <i class="fas fa-chart-line"></i>
                </div>
                <h4>التقارير والإحصائيات</h4>
                <p>لوحات بيانية تفاعلية ومرئية توضح أداء الجمعية بصورة واضحة ودقيقة</p>
            </div>
            <div class="feat-card">
                <div class="feat-icon" style="background:rgba(6,182,212,.15);color:#22d3ee">
                    <i class="fas fa-tasks"></i>
                </div>
                <h4>إدارة المهام</h4>
                <p>نظام مهام متكامل مع التفويض والتعليقات وتتبع التقدم لكل الفريق</p>
            </div>
        </div>
    </div>
</section>

<!-- ─── ROLES ─── -->
<section class="section roles-section" id="roles">
    <div class="section-inner">
        <div style="text-align:center;max-width:600px;margin:0 auto;">
            <div class="section-tag">الأدوار والصلاحيات</div>
            <h2 class="section-title">صلاحيات محددة لكل دور</h2>
            <p class="section-subtitle" style="margin:0 auto;">نظام صلاحيات مرن يضمن وصول كل عضو فقط للأقسام التي يحتاجها</p>
        </div>

        <div class="roles-grid">
            <div class="role-card">
                <div class="role-icon-wrap" style="background:rgba(96,165,250,.15);color:#60a5fa">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div>
                    <h3>مدير الجمعية</h3>
                    <p>صلاحيات كاملة على جميع الأقسام — التقارير، المالية، الموظفين، والإعدادات</p>
                </div>
            </div>
            <div class="role-card">
                <div class="role-icon-wrap" style="background:rgba(52,211,153,.15);color:#34d399">
                    <i class="fas fa-calculator"></i>
                </div>
                <div>
                    <h3>محاسب</h3>
                    <p>إدارة الخزينة، مراجعة المصروفات وقفلها، إصدار التقارير المالية المفصلة</p>
                </div>
            </div>
            <div class="role-card">
                <div class="role-icon-wrap" style="background:rgba(167,139,250,.15);color:#a78bfa">
                    <i class="fas fa-user-check"></i>
                </div>
                <div>
                    <h3>مندوب ميداني</h3>
                    <p>استلام العهد وتسجيل المصروفات والحالات الاجتماعية من الميدان مباشرة</p>
                </div>
            </div>
            <div class="role-card">
                <div class="role-icon-wrap" style="background:rgba(251,146,60,.15);color:#fb923c">
                    <i class="fas fa-eye"></i>
                </div>
                <div>
                    <h3>مشرف</h3>
                    <p>عرض جميع البيانات والتقارير مع صلاحية التعليق دون إمكانية التعديل</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ─── CTA ─── -->
<section class="section cta-section">
    <div class="cta-inner">
        <h2>ابدأ اليوم بلا تأخير</h2>
        <p>انضم لمئات الجمعيات التي تدير عملياتها بكفاءة وشفافية عالية باستخدام منصتنا</p>
        @if(auth()->check())
            <a href="{{ route('dashboard') }}" class="btn-cta">
                <i class="fas fa-th-large"></i> انتقل للوحة التحكم
            </a>
        @else
            <a href="{{ route('login') }}" class="btn-cta">
                <i class="fas fa-sign-in-alt"></i> تسجيل الدخول الآن
            </a>
        @endif
    </div>
</section>

<!-- ─── FOOTER ─── -->
<footer class="footer">
    <div class="footer-top">
        <div class="footer-brand">
            <div class="brand-name">
                <div class="icon-wrap"><i class="fas fa-hand-holding-heart"></i></div>
                {{ $settings->organization_name ?? 'جمعية أبي ذر الغفاري' }}
            </div>
            <p>منصة متكاملة وآمنة لإدارة الجمعيات الخيرية. نؤمن بالشفافية المالية والعمل المؤسسي المنظم.</p>
        </div>
        <div class="footer-col">
            <h6>روابط سريعة</h6>
            <ul>
                <li><a href="#features">المميزات</a></li>
                <li><a href="#roles">الأدوار</a></li>
                <li><a href="#stats">الإحصائيات</a></li>
                <li><a href="{{ route('login') }}">تسجيل الدخول</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h6>الدعم</h6>
            <ul>
                <li><a href="#">الأسئلة الشائعة</a></li>
                <li><a href="#">الدعم الفني</a></li>
                <li><a href="#">سياسة الخصوصية</a></li>
                <li><a href="#">شروط الاستخدام</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <span>&copy; {{ date('Y') }} {{ $settings->organization_name ?? 'جمعية أبي ذر الغفاري' }}. جميع الحقوق محفوظة.</span>
        <span>تطوير <a href="https://masarsoft.io" target="_blank">masarsoft.io</a></span>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Nav scroll effect
const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.scrollY > 40);
});

// Nav mobile toggle
const toggler = document.getElementById('navToggler');
const navLinks = document.getElementById('navLinks');
const toggleIcon = document.getElementById('toggleIcon');
toggler.addEventListener('click', () => {
    navLinks.classList.toggle('open');
    toggleIcon.className = navLinks.classList.contains('open') ? 'fas fa-times' : 'fas fa-bars';
});
navLinks.querySelectorAll('a').forEach(a => {
    a.addEventListener('click', () => {
        navLinks.classList.remove('open');
        toggleIcon.className = 'fas fa-bars';
    });
});

// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
        const target = document.querySelector(a.getAttribute('href'));
        if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
});

// Counter animation
function animateCounter(el) {
    const target = parseInt(el.dataset.count);
    const duration = 1800;
    const step = target / (duration / 16);
    let current = 0;
    const timer = setInterval(() => {
        current = Math.min(current + step, target);
        el.textContent = Math.floor(current).toLocaleString('ar');
        if (current >= target) clearInterval(timer);
    }, 16);
}
const counters = document.querySelectorAll('[data-count]');
const io = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { animateCounter(e.target); io.unobserve(e.target); } });
}, { threshold: .5 });
counters.forEach(c => io.observe(c));
</script>
</body>
</html>
