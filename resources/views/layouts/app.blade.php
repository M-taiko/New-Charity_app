<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة المؤسسة الخيرية</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .sidebar {
            background-color: var(--primary-color);
            min-height: 100vh;
            padding: 20px 0;
            position: fixed;
            width: 250px;
            right: 0;
            top: 0;
            z-index: 100;
        }

        .main-content {
            margin-right: 250px;
            padding: 20px;
            min-height: 100vh;
        }

        .sidebar-nav {
            list-style: none;
            padding: 0;
        }

        .sidebar-nav li a {
            display: block;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: background 0.3s;
            border-right: 4px solid transparent;
        }

        .sidebar-nav li a:hover {
            background-color: rgba(255,255,255,0.1);
            border-right-color: var(--secondary-color);
        }

        .sidebar-nav li a.active {
            background-color: var(--secondary-color);
            border-right-color: white;
        }

        .card {
            border: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--secondary-color);
        }

        .stat-card .label {
            color: #666;
            margin-top: 10px;
        }

        .btn-primary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }

            .main-content {
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark fixed-top">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">نظام إدارة المؤسسة الخيرية</span>
            <div>
                <span class="text-white me-3">{{ Auth::user()->name ?? 'ضيف' }}</span>
                <form action="{{ route('logout') }}" method="POST" style="display:inline;">
                    @csrf
                    <button class="btn btn-outline-light btn-sm" type="submit">تسجيل خروج</button>
                </form>
            </div>
        </div>
    </nav>

    <aside class="sidebar">
        <div class="p-3">
            <h6 class="text-white mb-4">القائمة الرئيسية</h6>
            <ul class="sidebar-nav">
                <li><a href="{{ route('dashboard') }}" @if(Route::current()->getName() == 'dashboard') class="active" @endif>لوحة التحكم</a></li>
                <li><a href="{{ route('treasury.index') }}" @if(Route::current()->getName() == 'treasury.index') class="active" @endif>الخزينة</a></li>
                <li><a href="{{ route('custodies.index') }}" @if(Route::current()->getName() == 'custodies.index') class="active" @endif>العهد</a></li>
                <li><a href="{{ route('expenses.index') }}" @if(Route::current()->getName() == 'expenses.index') class="active" @endif>المصروفات</a></li>
                <li><a href="{{ route('social_cases.index') }}" @if(Route::current()->getName() == 'social_cases.index') class="active" @endif>الحالات الاجتماعية</a></li>
                @can('manage_users')
                <li><a href="{{ route('users.index') }}" @if(Route::current()->getName() == 'users.index') class="active" @endif>المستخدمون</a></li>
                @endcan
                @can('manage_settings')
                <li><a href="{{ route('settings.index') }}" @if(Route::current()->getName() == 'settings.index') class="active" @endif>الإعدادات</a></li>
                @endcan
            </ul>
        </div>
    </aside>

    <div class="main-content" style="margin-top: 60px;">
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>خطأ!</strong>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('success'))
            <div class="toast-container">
                <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header bg-success text-white">
                        <strong class="me-auto">نجاح</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        {{ session('success') }}
                    </div>
                </div>
            </div>
        @endif

        @yield('content')
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show toasts
            const toastElements = document.querySelectorAll('.toast');
            toastElements.forEach(function(toastElement) {
                const toast = new bootstrap.Toast(toastElement);
                toast.show();
            });
        });
    </script>

    @stack('scripts')
</body>
</html>
