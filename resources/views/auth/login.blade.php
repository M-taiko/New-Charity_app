<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - نظام إدارة المؤسسة الخيرية</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            font-family: 'Cairo', 'Segoe UI', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 450px;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-section {
            margin-bottom: 30px;
            display: flex;
            justify-content: center;
        }

        .logo-section img {
            max-height: 80px;
            object-fit: contain;
            border-radius: 10px;
            animation: zoomIn 0.8s ease-out;
        }

        @keyframes zoomIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .login-header h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 8px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .login-header p {
            color: #999;
            font-size: 14px;
            margin: 0;
        }

        .form-control {
            background: #f8f9ff;
            border: 2px solid #e0e7ff;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: white;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            color: #333;
        }

        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-label i {
            color: #667eea;
            font-size: 16px;
        }

        .form-check-input {
            border-color: #e0e7ff;
            width: 20px;
            height: 20px;
        }

        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }

        .form-check-label {
            color: #666;
            font-size: 14px;
            cursor: pointer;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            width: 100%;
            padding: 13px;
            font-weight: 600;
            margin-top: 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-size: 16px;
            color: white;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            color: white;
            text-decoration: none;
        }

        .divider {
            text-align: center;
            margin: 25px 0;
            position: relative;
            color: #ccc;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e0e7ff;
        }

        .divider span {
            background: white;
            padding: 0 10px;
            position: relative;
            font-size: 13px;
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 14px;
        }

        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .register-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .test-credentials {
            background: linear-gradient(135deg, #f0e7ff 0%, #f8f9ff 100%);
            border: 1px solid #e0e7ff;
            border-right: 4px solid #667eea;
            border-radius: 10px;
            padding: 15px;
            margin-top: 25px;
            font-size: 12px;
            color: #667eea;
            line-height: 1.8;
        }

        .test-credentials strong {
            color: #667eea;
            display: block;
            margin-bottom: 10px;
            font-size: 13px;
        }

        .test-credentials code {
            background: white;
            padding: 2px 6px;
            border-radius: 4px;
            color: #764ba2;
            font-size: 11px;
        }

        .alert {
            border-radius: 10px;
            border: none;
            border-left: 4px solid;
        }

        .alert-danger {
            background: #fee;
            border-left-color: #ef4444;
            color: #c33;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            @php
                $logo = \App\Models\Setting::get('organization_logo');
            @endphp

            @if($logo)
                <div class="logo-section">
                    <img src="{{ asset('storage/' . $logo) }}" alt="شعار المؤسسة">
                </div>
            @endif

            <h1>
                @php
                    $orgName = \App\Models\Setting::get('organization_name', config('app.name'));
                @endphp
                {{ $orgName }}
            </h1>
            <p>تسجيل الدخول إلى النظام</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle"></i>
                {{ $errors->first('email') }}
            </div>
        @endif

        <form action="{{ route('login') }}" method="POST">
            @csrf

            <div class="mb-4">
                <label class="form-label">
                    <i class="fas fa-envelope"></i>
                    البريد الإلكتروني
                </label>
                <input type="email" name="email" class="form-control" required autofocus value="{{ old('email') }}" placeholder="أدخل بريدك الإلكتروني">
            </div>

            <div class="mb-4">
                <label class="form-label">
                    <i class="fas fa-lock"></i>
                    كلمة المرور
                </label>
                <input type="password" name="password" class="form-control" required placeholder="أدخل كلمة المرور">
            </div>

            <div class="form-check mb-4">
                <input type="checkbox" name="remember" class="form-check-input" id="remember">
                <label class="form-check-label" for="remember">
                    تذكرني في هذا الجهاز
                </label>
            </div>

            <button type="submit" class="btn btn-login">
                <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
            </button>
        </form>

        <div class="divider">
            <span>أو</span>
        </div>



    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
