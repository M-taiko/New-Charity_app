<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول — {{ \App\Models\Setting::get('organization_name', config('app.name')) }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Cairo', sans-serif;
            background: #0f172a;
            color: white;
            min-height: 100vh;
            display: flex;
            overflow: hidden;
        }

        /* ─── LEFT PANEL (branding) ─── */
        .panel-left {
            flex: 0 0 55%;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            padding: 4rem 5rem;
            background: #0f172a;
        }

        /* Grid background */
        .panel-left::before {
            content: '';
            position: absolute; inset: 0; z-index: 0;
            background-image:
                linear-gradient(rgba(26,86,219,.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(26,86,219,.1) 1px, transparent 1px);
            background-size: 50px 50px;
        }

        /* Glow blobs */
        .blob {
            position: absolute; border-radius: 50%;
            filter: blur(80px); pointer-events: none;
        }
        .blob-a { width: 500px; height: 500px; background: rgba(26,86,219,.3); top: -150px; right: -100px; animation: blobMove 12s ease-in-out infinite; }
        .blob-b { width: 400px; height: 400px; background: rgba(6,182,212,.2); bottom: -100px; left: -80px; animation: blobMove 16s ease-in-out infinite reverse; }
        .blob-c { width: 250px; height: 250px; background: rgba(139,92,246,.25); top: 50%; left: 40%; transform: translate(-50%,-50%); animation: blobMove 10s ease-in-out infinite 3s; }
        @keyframes blobMove {
            0%,100% { transform: translate(0,0); }
            33%  { transform: translate(20px,-25px); }
            66%  { transform: translate(-15px,15px); }
        }

        .panel-content {
            position: relative; z-index: 1;
            max-width: 480px;
        }

        .panel-logo {
            display: inline-flex; align-items: center; gap: .75rem;
            margin-bottom: 3rem;
        }
        .logo-icon {
            width: 50px; height: 50px; border-radius: 14px;
            background: linear-gradient(135deg, #1a56db, #0ea5e9);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem; flex-shrink: 0;
            box-shadow: 0 8px 25px rgba(26,86,219,.4);
        }
        .logo-icon img { height: 50px; border-radius: 14px; object-fit: contain; }
        .logo-text { font-size: 1.15rem; font-weight: 700; }

        .panel-tagline {
            display: inline-block;
            background: rgba(26,86,219,.15);
            border: 1px solid rgba(26,86,219,.3);
            color: #93c5fd; padding: .35rem .9rem;
            border-radius: 20px; font-size: .75rem; font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .panel-heading {
            font-size: clamp(2rem, 3.5vw, 2.8rem);
            font-weight: 800; line-height: 1.2;
            margin-bottom: 1.2rem;
        }
        .panel-heading .grad {
            background: linear-gradient(135deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }

        .panel-sub {
            font-size: 1rem; color: #94a3b8; line-height: 1.8; margin-bottom: 3rem;
        }

        .feature-list { list-style: none; display: flex; flex-direction: column; gap: .75rem; }
        .feature-list li {
            display: flex; align-items: center; gap: .75rem;
            font-size: .9rem; color: #94a3b8;
        }
        .feat-check {
            width: 24px; height: 24px; border-radius: 6px;
            background: rgba(16,185,129,.15); color: #34d399;
            display: flex; align-items: center; justify-content: center;
            font-size: .7rem; flex-shrink: 0;
        }

        /* ─── RIGHT PANEL (form) ─── */
        .panel-right {
            flex: 0 0 45%;
            background: #0f1929;
            display: flex; align-items: center; justify-content: center;
            padding: 2rem;
            position: relative;
            border-right: 1px solid rgba(255,255,255,.05);
        }

        .login-box {
            width: 100%; max-width: 400px;
        }

        .login-box-header {
            margin-bottom: 2rem;
        }
        .login-box-header h2 { font-size: 1.6rem; font-weight: 800; margin-bottom: .4rem; }
        .login-box-header p  { font-size: .88rem; color: #64748b; }

        /* Form elements */
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block; font-size: .82rem; font-weight: 600;
            color: #94a3b8; margin-bottom: .5rem;
        }
        .input-wrap { position: relative; }
        .input-icon {
            position: absolute; top: 50%; transform: translateY(-50%);
            right: 1rem; color: #475569; font-size: .85rem; pointer-events: none;
            transition: color .2s;
        }
        .form-control {
            width: 100%;
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 10px;
            padding: .8rem 1rem .8rem 2.8rem;
            font-size: .92rem; font-family: 'Cairo', sans-serif;
            color: white; transition: all .25s;
            outline: none;
        }
        .form-control::placeholder { color: #334155; }
        .form-control:focus {
            background: rgba(26,86,219,.06);
            border-color: #1a56db;
            box-shadow: 0 0 0 3px rgba(26,86,219,.15);
        }
        .form-control:focus ~ .input-icon { color: #60a5fa; }

        /* Password toggle */
        .pass-toggle {
            position: absolute; top: 50%; left: 1rem; transform: translateY(-50%);
            color: #475569; cursor: pointer; font-size: .85rem; transition: color .2s;
            background: none; border: none; padding: 0;
        }
        .pass-toggle:hover { color: #94a3b8; }

        /* Remember row */
        .remember-row {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        .custom-check {
            display: flex; align-items: center; gap: .55rem; cursor: pointer;
        }
        .custom-check input { display: none; }
        .check-box {
            width: 18px; height: 18px; border-radius: 5px;
            border: 1.5px solid #334155; background: transparent;
            display: flex; align-items: center; justify-content: center;
            transition: all .2s; flex-shrink: 0;
        }
        .check-box i { font-size: .6rem; color: white; opacity: 0; transition: opacity .2s; }
        .custom-check input:checked ~ .check-box {
            background: #1a56db; border-color: #1a56db;
        }
        .custom-check input:checked ~ .check-box i { opacity: 1; }
        .custom-check span { font-size: .82rem; color: #64748b; }

        /* Submit button */
        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #1a56db, #0ea5e9);
            border: none; border-radius: 10px;
            padding: .9rem; font-size: .95rem; font-weight: 700;
            color: white; font-family: 'Cairo', sans-serif;
            cursor: pointer; transition: all .25s; letter-spacing: .3px;
            display: flex; align-items: center; justify-content: center; gap: .6rem;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(26,86,219,.45);
        }
        .btn-submit:active { transform: translateY(0); }
        .btn-submit .spinner { display: none; }
        .btn-submit.loading .spinner { display: inline-block; }
        .btn-submit.loading .btn-text { display: none; }

        /* Divider */
        .divider {
            display: flex; align-items: center; gap: 1rem;
            margin: 1.5rem 0; color: #1e293b;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px; background: rgba(255,255,255,.06);
        }
        .divider span { font-size: .78rem; color: #334155; white-space: nowrap; }

        /* Back link */
        .back-link {
            text-align: center; margin-top: 1.5rem;
        }
        .back-link a {
            font-size: .82rem; color: #475569; text-decoration: none;
            display: inline-flex; align-items: center; gap: .4rem;
            transition: color .2s;
        }
        .back-link a:hover { color: #94a3b8; }

        /* Error */
        .alert-err {
            background: rgba(239,68,68,.1);
            border: 1px solid rgba(239,68,68,.25);
            border-radius: 10px; padding: .8rem 1rem;
            color: #fca5a5; font-size: .85rem;
            display: flex; align-items: center; gap: .6rem;
            margin-bottom: 1.5rem;
        }

        /* Responsive */
        @media (max-width: 900px) {
            body { flex-direction: column; overflow: auto; }
            .panel-left {
                flex: 0 0 auto; padding: 2.5rem 2rem; align-items: center; text-align: center;
            }
            .panel-content { max-width: 100%; }
            .feature-list { align-items: center; }
            .panel-logo { justify-content: center; }
            .panel-right { flex: 0 0 auto; padding: 2rem; min-height: 100vh; }
            .panel-sub { display: none; }
            .feature-list { display: none; }
            .panel-heading { font-size: 1.8rem; margin-bottom: 0; }
            .panel-left { padding-bottom: 1.5rem; }
        }
        @media (max-width: 480px) {
            .panel-left { display: none; }
            .panel-right { flex: 1; border-right: none; }
        }

        /* Loading spinner */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spin-icon { animation: spin .8s linear infinite; display: inline-block; }
    </style>
</head>
<body>

    <!-- LEFT PANEL -->
    <div class="panel-left">
        <div class="blob blob-a"></div>
        <div class="blob blob-b"></div>
        <div class="blob blob-c"></div>

        <div class="panel-content">
            <div class="panel-logo">
                @php $logo = \App\Models\Setting::get('organization_logo'); @endphp
                @if($logo)
                    <div class="logo-icon" style="padding:0;background:none;">
                        <img src="{{ '/storage/app/public/' . $logo }}" alt="شعار">
                    </div>
                @else
                    <div class="logo-icon"><i class="fas fa-hand-holding-heart"></i></div>
                @endif
                <span class="logo-text">{{ \App\Models\Setting::get('organization_name', config('app.name')) }}</span>
            </div>

            <div class="panel-tagline">نظام الإدارة المتكامل</div>

            <h1 class="panel-heading">
                مرحباً بك في<br>
                <span class="grad">منصة الجمعية</span>
            </h1>

            <p class="panel-sub">
                منصة شاملة لإدارة الجمعيات الخيرية — العهد، المصروفات، الحالات الاجتماعية، والتقارير في مكان واحد.
            </p>

            <ul class="feature-list">
                <li>
                    <div class="feat-check"><i class="fas fa-check"></i></div>
                    إدارة كاملة للخزينة والعهد المالية
                </li>
                <li>
                    <div class="feat-check"><i class="fas fa-check"></i></div>
                    تقارير مفصلة وإحصائيات فورية
                </li>
                <li>
                    <div class="feat-check"><i class="fas fa-check"></i></div>
                    نظام صلاحيات متعدد الأدوار
                </li>
                <li>
                    <div class="feat-check"><i class="fas fa-check"></i></div>
                    متابعة الحالات الاجتماعية والمستفيدين
                </li>
            </ul>
        </div>
    </div>

    <!-- RIGHT PANEL (Form) -->
    <div class="panel-right">
        <div class="login-box">
            <div class="login-box-header">
                <h2>تسجيل الدخول</h2>
                <p>أدخل بيانات حسابك للمتابعة</p>
            </div>

            @if ($errors->any())
            <div class="alert-err">
                <i class="fas fa-exclamation-circle"></i>
                {{ $errors->first('email') }}
            </div>
            @endif

            <form action="{{ route('login') }}" method="POST" id="loginForm">
                @csrf

                <div class="form-group">
                    <label for="email">البريد الإلكتروني</label>
                    <div class="input-wrap">
                        <input
                            type="email" name="email" id="email"
                            class="form-control"
                            placeholder="example@mail.com"
                            value="{{ old('email') }}"
                            required autofocus
                            autocomplete="email"
                        >
                        <i class="fas fa-envelope input-icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">كلمة المرور</label>
                    <div class="input-wrap">
                        <input
                            type="password" name="password" id="password"
                            class="form-control"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        >
                        <i class="fas fa-lock input-icon"></i>
                        <button type="button" class="pass-toggle" id="passToggle" tabindex="-1">
                            <i class="fas fa-eye" id="passIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="remember-row">
                    <label class="custom-check">
                        <input type="checkbox" name="remember" id="remember">
                        <div class="check-box"><i class="fas fa-check"></i></div>
                        <span>تذكرني</span>
                    </label>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <span class="btn-text"><i class="fas fa-sign-in-alt"></i> تسجيل الدخول</span>
                    <span class="spinner"><i class="fas fa-circle-notch spin-icon"></i> جارٍ التحقق...</span>
                </button>
            </form>

            <div class="divider"><span>أو</span></div>

            <div class="back-link">
                <a href="{{ url('/') }}">
                    <i class="fas fa-arrow-right"></i>
                    العودة للصفحة الرئيسية
                </a>
            </div>
        </div>
    </div>

    <script>
        // Password toggle
        const passInput  = document.getElementById('password');
        const passToggle = document.getElementById('passToggle');
        const passIcon   = document.getElementById('passIcon');
        passToggle.addEventListener('click', () => {
            const show = passInput.type === 'password';
            passInput.type = show ? 'text' : 'password';
            passIcon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
        });

        // Submit loading state
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });
    </script>
</body>
</html>
