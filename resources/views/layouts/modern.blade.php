<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>نظام إدارة المؤسسة الخيرية</title>

    <!-- Bootstrap 5 RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            font-family: 'Cairo', sans-serif;
        }

        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #e0e7ff;
            --secondary: #8b5cf6;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #06b6d4;
            --dark: #1f2937;
            --light: #f9fafb;
            --border: #e5e7eb;
            --text: #374151;
            --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Navbar Styles */
        .navbar {
            background: linear-gradient(135deg, var(--dark) 0%, var(--primary-dark) 100%);
            box-shadow: var(--shadow-lg);
            padding: 1rem 0;
            border-bottom: 3px solid var(--primary);
            z-index: 101;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: white !important;
            letter-spacing: 0.5px;
        }

        .navbar-brand i {
            margin-left: 0.5rem;
            color: var(--primary-light);
        }

        /* Sidebar Styles */
        .sidebar {
            background: linear-gradient(180deg, var(--dark) 0%, #374151 100%);
            min-height: 100vh;
            padding: 2rem 0;
            position: fixed;
            width: 280px;
            right: 0;
            top: 80px;
            z-index: 100;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
            overflow-y: auto;
            max-height: calc(100vh - 80px);
        }

        .sidebar-nav {
            list-style: none;
            padding: 1rem 0;
        }

        .sidebar-nav li {
            margin: 0.5rem 0;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            color: #d1d5db;
            text-decoration: none;
            transition: all 0.3s ease;
            border-right: 4px solid transparent;
        }

        .sidebar-nav a i {
            width: 24px;
            margin-left: 0.75rem;
            font-size: 1.1rem;
        }

        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background-color: rgba(99, 102, 241, 0.1);
            color: var(--primary-light);
            border-right-color: var(--primary);
            padding-right: calc(1.5rem - 4px);
            padding-left: 1.75rem;
        }

        /* Main Content */
        .main-content {
            margin-right: 280px;
            margin-top: 80px;
            padding: 2rem;
            min-height: 100vh;
        }

        /* Notification Bell */
        .notification-bell {
            position: relative;
            cursor: pointer;
        }

        .notification-bell i {
            font-size: 1.5rem;
            color: white;
            transition: all 0.3s ease;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            left: -8px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }

        .notification-dropdown {
            position: absolute;
            top: 70px;
            left: 0;
            width: 400px;
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            max-height: 500px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-item {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .notification-item:hover {
            background-color: var(--light);
        }

        .notification-item.unread {
            background-color: var(--primary-light);
            border-right: 4px solid var(--primary);
        }

        .notification-item-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .notification-item-text {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .notification-item-time {
            font-size: 0.75rem;
            color: #9ca3af;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
            border-top: 4px solid var(--primary);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            border: none;
        }

        .card-header h5 {
            margin: 0;
            font-weight: 600;
        }

        /* Stat Cards */
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            text-align: center;
            transition: all 0.3s ease;
            border-top: 4px solid var(--primary);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card.success {
            border-top-color: var(--success);
        }

        .stat-card.danger {
            border-top-color: var(--danger);
        }

        .stat-card.warning {
            border-top-color: var(--warning);
        }

        .stat-card.info {
            border-top-color: var(--info);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin: 1rem 0;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.1;
        }

        /* Buttons */
        .btn {
            border-radius: 8px;
            font-weight: 600;
            padding: 0.65rem 1.5rem;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
        }

        .btn-secondary {
            background-color: #e5e7eb;
            color: var(--dark);
        }

        .btn-secondary:hover {
            background-color: #d1d5db;
            color: var(--dark);
        }

        /* Tables */
        .table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }

        .table thead th {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 1rem;
        }

        .table tbody tr {
            transition: all 0.3s ease;
            border-bottom: 1px solid var(--border);
        }

        .table tbody tr:hover {
            background-color: var(--light);
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
        }

        /* Modal */
        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            border-radius: 12px 12px 0 0;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        /* Badges */
        .badge {
            padding: 0.65rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .badge.bg-success {
            background-color: #d1fae5 !important;
            color: #065f46;
        }

        .badge.bg-danger {
            background-color: #fee2e2 !important;
            color: #7f1d1d;
        }

        .badge.bg-warning {
            background-color: #fef3c7 !important;
            color: #78350f;
        }

        .badge.bg-info {
            background-color: #cffafe !important;
            color: #164e63;
        }

        /* Toast */
        .toast {
            border: none;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            border-right: 4px solid var(--primary);
        }

        .toast-header {
            background: white;
            border: none;
            border-bottom: 1px solid var(--border);
        }

        .toast-body {
            padding: 1rem;
        }

        /* Responsive */
        @media (max-width: 991px) {
            .sidebar {
                width: 250px;
            }

            .main-content {
                margin-right: 250px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                transform: translateX(100%);
                width: 280px;
                transition: transform 0.3s ease;
                z-index: 1000;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-right: 0;
                padding: 1rem;
            }

            .navbar {
                padding: 0.75rem 0;
            }

            .stat-card {
                margin-bottom: 1rem;
            }

            .notification-dropdown {
                width: 350px;
                left: -100px;
            }

            .table {
                font-size: 0.9rem;
            }

            .table td {
                padding: 0.75rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                margin-top: 129px;
            }

            .stat-number {
                font-size: 1.75rem;
            }

            .notification-dropdown {
                width: 300px;
                left: -150px;
            }

            .card {
                margin-bottom: 1rem;
            }
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Toggle Button */
        .sidebar-toggle {
            display: none;
            background: white;
            border: none;
            color: var(--dark);
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        @media (max-width: 768px) {
            .sidebar-toggle {
                display: block;
            }
        }

        /* User Menu */
        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.25rem;
        }

        .user-name {
            color: white;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-role {
            color: #d1d5db;
            font-size: 0.8rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }

        .empty-state-icon {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }

        .empty-state-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .empty-state-text {
            color: #6b7280;
            font-size: 0.95rem;
        }


         .modal-backdrop {
        display: none !important;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid px-4">
            <!-- Sidebar Toggle -->
            <button class="navbar-toggler sidebar-toggle" type="button" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </button>

            <!-- Brand -->
            <a class="navbar-brand" href="{{ route('dashboard') }}" style="display: flex; align-items: center; gap: 0.75rem;">
                @php
                    $logo = \App\Models\Setting::get('organization_logo');
                    $orgName = \App\Models\Setting::get('organization_name', 'منصة الخير');
                @endphp

                @if($logo)
                    <img src="{{ '/storage/app/public/' . $logo }}" alt="{{ $orgName }}" style="max-height: 40px; object-fit: contain;">
                @else
                    <i class="fas fa-hand-holding-heart"></i>
                @endif
                <span>{{ $orgName }}</span>
            </a>

            <!-- Right Side Items -->
            <div class="ms-auto d-flex align-items-center gap-3">
                <!-- Notifications -->
                <div class="notification-bell" onclick="toggleNotifications()">
                    <i class="fas fa-bell"></i>
                    @php
                        $unreadCount = \App\Models\Notification::where('user_id', auth()->id())
                            ->where('is_read', false)->count();
                    @endphp
                    @if($unreadCount > 0)
                        <span class="notification-badge">{{ $unreadCount }}</span>
                    @endif

                    <!-- Notification Dropdown -->
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div style="padding: 1rem; border-bottom: 1px solid var(--border);">
                            <h6 style="margin: 0;">الإخطارات</h6>
                        </div>

                        @php
                            $notifications = \App\Models\Notification::where('user_id', auth()->id())
                                ->latest()
                                ->limit(10)
                                ->get();
                        @endphp

                        @forelse($notifications as $notification)
                            <form action="{{ route('notifications.read', $notification->id) }}" method="POST" style="margin: 0;">
                                @csrf
                                <button type="submit" class="notification-item {{ !$notification->is_read ? 'unread' : '' }}" style="width: 100%; text-align: right; border: none; background: none; padding: 1rem; cursor: pointer; transition: all 0.3s ease; display: block;">
                                    <div class="notification-item-title">{{ $notification->title }}</div>
                                    <div class="notification-item-text">{{ $notification->message }}</div>
                                    <div class="notification-item-time">
                                        <i class="fas fa-clock"></i>
                                        {{ $notification->created_at->diffForHumans() }}
                                    </div>
                                </button>
                            </form>
                        @empty
                            <div style="padding: 2rem; text-align: center; color: #6b7280;">
                                <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                <p style="margin: 0;">لا توجد إخطارات</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- User Menu -->
                <div class="user-menu dropdown">
                    <div class="d-flex align-items-center gap-2" data-bs-toggle="dropdown" style="cursor: pointer;">
                        <div class="user-avatar" style="width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                            @if(auth()->user()->profile_picture)
                                <img src="{{ '/storage/app/public/' . auth()->user()->profile_picture }}" alt="{{ auth()->user()->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                            @else
                                <span style="font-size: 1.5rem;">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                            @endif
                        </div>
                        <div class="user-info d-none d-lg-flex">
                            <div class="user-name">{{ auth()->user()->name }}</div>
                            <div class="user-role">{{ auth()->user()->getRoleNames()->first() ?? 'مستخدم' }}</div>
                        </div>
                        <i class="fas fa-chevron-down" style="color: white; font-size: 0.8rem;"></i>
                    </div>

                    <ul class="dropdown-menu dropdown-menu-end" style="border-radius: 12px; border: none; box-shadow: var(--shadow-lg);">
                        <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="fas fa-user-circle"></i> الملف الشخصي</a></li>
                        <li><a class="dropdown-item" href="{{ route('settings.index') }}"><i class="fas fa-cog"></i> الإعدادات</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="{{ route('logout') }}" method="POST" style="display: inline;">
                                @csrf
                                <button type="submit" class="dropdown-item" style="cursor: pointer;">
                                    <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="p-4">
            <!-- Logo Section -->
            @php
                $logo = \App\Models\Setting::get('organization_logo');
            @endphp

            @if($logo)
                <div style="text-align: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <img src="{{ '/storage/app/public/' . $logo }}" alt="شعار المؤسسة" style="max-height: 60px; object-fit: contain; filter: brightness(1.2);">
                </div>
            @endif

            <h6 class="text-white-50 mb-4 text-uppercase" style="font-size: 0.85rem; letter-spacing: 1px;">
                <i class="fas fa-compass"></i> القائمة الرئيسية
            </h6>
            <ul class="sidebar-nav">
                <li>
                    <a href="{{ route('dashboard') }}" class="@if(Route::current()->getName() == 'dashboard') active @endif">
                        <i class="fas fa-chart-line"></i>
                        <span>لوحة التحكم</span>
                    </a>
                </li>

                @can('manage_treasury')
                <li>
                    <a href="{{ route('treasury.index') }}" class="@if(Route::current()->getName() == 'treasury.index') active @endif">
                        <i class="fas fa-coins"></i>
                        <span>الخزينة</span>
                    </a>
                </li>
                @endcan

                @can('manage_treasury')
                <li>
                    <a href="{{ route('custodies.index') }}" class="@if(Route::current()->getName() == 'custodies.index') active @endif">
                        <i class="fas fa-hand-handshake"></i>
                        <span>العهد</span>
                    </a>
                </li>
                @endcan

                @can('spend_money')
                <li>
                    <a href="{{ route('expenses.index') }}" class="@if(Route::current()->getName() == 'expenses.index') active @endif">
                        <i class="fas fa-receipt"></i>
                        <span>المصروفات</span>
                    </a>
                </li>
                @endcan

                @role('مندوب')
                <li>
                    <a href="{{ route('expenses.agent') }}" class="@if(Route::current()->getName() == 'expenses.agent') active @endif">
                        <i class="fas fa-wallet"></i>
                        <span>مصروفاتي</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('agent.transactions') }}" class="@if(Route::current()->getName() == 'agent.transactions') active @endif">
                        <i class="fas fa-exchange-alt"></i>
                        <span>حركاتي من الخزينة</span>
                    </a>
                </li>
                @endrole

                <li>
                    <a href="{{ route('social_cases.index') }}" class="@if(Route::current()->getName() == 'social_cases.index') active @endif">
                        <i class="fas fa-people-group"></i>
                        <span>الحالات الاجتماعية</span>
                    </a>
                </li>

                @can('create_social_case')
                <li>
                    <a href="{{ route('social_cases.researcher') }}" class="@if(Route::current()->getName() == 'social_cases.researcher') active @endif">
                        <i class="fas fa-file-alt"></i>
                        <span>حالاتي الاجتماعية</span>
                    </a>
                </li>
                @endcan

                @can('manage_treasury')
                <li>
                    <a href="{{ route('reports.dashboard') }}" class="@if(Route::current()->getName() == 'reports.dashboard') active @endif">
                        <i class="fas fa-chart-bar"></i>
                        <span>التقارير</span>
                    </a>
                </li>
                <li>
                    <a href="{{ route('analytics.researcher') }}" class="@if(Route::current()->getName() == 'analytics.researcher') active @endif">
                        <i class="fas fa-chart-line"></i>
                        <span>إحصائيات الباحثين</span>
                    </a>
                </li>
                @endcan

                @can('manage_users')
                <li style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
                    <a href="{{ route('users.index') }}" class="@if(Route::current()->getName() == 'users.index') active @endif">
                        <i class="fas fa-users"></i>
                        <span>المستخدمون</span>
                    </a>
                </li>
                @endcan

                @can('manage_settings')
                <li>
                    <a href="{{ route('settings.index') }}" class="@if(Route::current()->getName() == 'settings.index') active @endif">
                        <i class="fas fa-sliders"></i>
                        <span>الإعدادات</span>
                    </a>
                </li>
                @endcan
            </ul>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Alerts -->
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert" data-aos="fade-in">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <i class="fas fa-exclamation-circle" style="font-size: 1.5rem;"></i>
                    <div>
                        <strong>خطأ!</strong>
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert" data-aos="fade-in">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
                    <div>
                        <strong>نجح!</strong>
                        <div>{{ session('success') }}</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @yield('content')
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <script>
        // Initialize AOS
        AOS.init({
            duration: 600,
            once: false,
            mirror: true
        });

        // Toggle Sidebar
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        }

        // Close sidebar on link click
        document.querySelectorAll('.sidebar-nav a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    document.getElementById('sidebar').classList.remove('show');
                }
            });
        });

        // Toggle Notifications
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');

            // Mark as read
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                const title = item.querySelector('.notification-item-title').textContent;
                // Mark notification as read (you can make an AJAX call here)
            });
        }

        // Close notification dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notificationDropdown');
            const bell = document.querySelector('.notification-bell');
            if (!bell.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Auto-hide alerts
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    </script>

    @stack('scripts')
</body>
</html>
