<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة الجمعيات الخيرية</title>

    <!-- Bootstrap 5 RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- AOS (Animate On Scroll) -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --light-primary: #f0f4ff;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-light: linear-gradient(135deg, #f0f4ff 0%, #f5f3ff 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9ff;
            overflow-x: hidden;
        }

        /* Navigation Bar */
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            color: var(--primary) !important;
            font-size: 1.3rem;
        }

        .navbar-brand img {
            height: 40px;
            width: auto;
            border-radius: 8px;
        }

        .nav-link {
            color: #555 !important;
            font-weight: 500;
            transition: all 0.3s ease;
            margin: 0 10px;
        }

        .nav-link:hover {
            color: var(--primary) !important;
        }

        .btn-login {
            background: var(--gradient);
            border: none;
            color: white;
            padding: 0.6rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }

        /* Hero Section */
        .hero {
            background: var(--gradient);
            color: white;
            padding: 100px 0;
            position: relative;
            overflow: hidden;
            min-height: 600px;
            display: flex;
            align-items: center;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .hero::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -5%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(30px); }
        }

        .hero-content {
            position: relative;
            z-index: 1;
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.95;
            line-height: 1.6;
        }

        .btn-primary-custom {
            background: white;
            color: var(--primary);
            padding: 0.9rem 2.5rem;
            border-radius: 30px;
            font-weight: 700;
            border: none;
            transition: all 0.3s ease;
            display: inline-block;
            margin: 0.5rem;
            font-size: 1.05rem;
        }

        .btn-primary-custom:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            color: var(--secondary);
        }

        .btn-secondary-custom {
            background: transparent;
            color: white;
            padding: 0.9rem 2.5rem;
            border-radius: 30px;
            font-weight: 700;
            border: 2px solid white;
            transition: all 0.3s ease;
            display: inline-block;
            margin: 0.5rem;
            font-size: 1.05rem;
        }

        .btn-secondary-custom:hover {
            background: white;
            color: var(--primary);
            transform: translateY(-5px);
        }

        /* Features Section */
        .features {
            padding: 80px 0;
            background: white;
        }

        .section-title {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 1rem;
        }

        .section-title p {
            font-size: 1.1rem;
            color: #777;
        }

        .feature-card {
            background: white;
            border-radius: 15px;
            padding: 2.5rem 2rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid #f0f0f0;
            height: 100%;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.15);
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: var(--gradient-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: var(--primary);
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            background: var(--gradient);
            color: white;
            transform: scale(1.1) rotate(5deg);
        }

        .feature-card h4 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.8rem;
        }

        .feature-card p {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Roles Section */
        .roles {
            padding: 80px 0;
            background: var(--gradient-light);
        }

        .role-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            height: 100%;
        }

        .role-card:hover {
            border-color: var(--primary);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.15);
            transform: translateY(-10px);
        }

        .role-icon {
            width: 100px;
            height: 100px;
            background: var(--gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
            color: white;
            transition: all 0.3s ease;
        }

        .role-card:hover .role-icon {
            transform: scale(1.1) rotateY(10deg);
        }

        .role-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 1rem;
        }

        .role-card p {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Stats Section */
        .stats {
            background: var(--gradient);
            color: white;
            padding: 80px 0;
            position: relative;
            overflow: hidden;
        }

        .stats::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .stat-item {
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .stat-number {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 1.1rem;
            opacity: 0.95;
        }

        /* CTA Section */
        .cta {
            background: linear-gradient(135deg, #f0f4ff 0%, #f5f3ff 100%);
            padding: 60px 0;
            text-align: center;
        }

        .cta h2 {
            font-size: 2.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 1.5rem;
        }

        .cta p {
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Footer */
        .footer {
            background: #1a1a2e;
            color: white;
            padding: 60px 0 20px;
        }

        .footer-section h5 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 0.8rem;
        }

        .footer-section ul li a {
            color: #aaa;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .footer-section ul li a:hover {
            color: var(--primary);
            padding-right: 0.5rem;
        }

        .footer-bottom {
            border-top: 1px solid #333;
            padding-top: 2rem;
            margin-top: 2rem;
            text-align: center;
            color: #aaa;
        }

        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 50%;
            color: var(--primary);
            margin: 0 0.5rem;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: var(--gradient);
            color: white;
            transform: translateY(-5px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.2rem;
            }

            .hero p {
                font-size: 1rem;
            }

            .section-title h2 {
                font-size: 1.8rem;
            }

            .stat-number {
                font-size: 2.5rem;
            }

            .cta h2 {
                font-size: 1.8rem;
            }
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.8s ease-in;
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

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
        }

        /* Utility Classes */
        .text-gradient {
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .bg-light-primary {
            background-color: var(--light-primary);
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="/">
                @if(isset($settings) && $settings->logo)
                    <img src="{{ asset('storage/' . $settings->logo) }}" alt="Logo">
                @else
                    <i class="fas fa-hand-holding-heart"></i>
                @endif
                <span>{{ $settings->organization_name ?? 'نظام إدارة الجمعيات' }}</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#features">المميزات</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#roles">الأدوار</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#stats">الإحصائيات</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">اتصل بنا</a>
                    </li>
                    @if(auth()->check())
                        <li class="nav-item">
                            <a class="btn btn-login" href="{{ route('dashboard') }}">لوحة التحكم</a>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="btn btn-login" href="{{ route('login') }}">تسجيل الدخول</a>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-up" data-aos-duration="800">
                    <div class="hero-content">
                        <h1 class="fade-in">
                            {{ $settings->organization_name ?? 'نظام إدارة الجمعيات' }}
                        </h1>
                        <p class="fade-in" style="animation-delay: 0.2s;">
                            منصة متكاملة وسهلة الاستخدام لإدارة الجمعيات الخيرية بكفاءة عالية وتقارير دقيقة
                        </p>
                        <div style="animation-delay: 0.4s;" class="fade-in">
                            @if(auth()->check())
                                <a href="{{ route('dashboard') }}" class="btn-primary-custom">
                                    <i class="fas fa-arrow-left"></i> انتقل إلى لوحة التحكم
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="btn-primary-custom">
                                    <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
                                </a>

                            @endif
                        </div>
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left" data-aos-duration="1000">
                    <div style="text-align: center;">
                        <i class="fas fa-chart-pie" style="font-size: 250px; opacity: 0.2; animation: float 6s ease-in-out infinite;"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-title" data-aos="fade-up" data-aos-duration="800">
                <h2>المميزات الرئيسية</h2>
                <p>احصل على كل الأدوات التي تحتاجها لإدارة جمعيتك بكفاءة</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="0">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <h4>إدارة الخزنة</h4>
                        <p>تتبع الأموال والمدفوعات والعائدات بدقة عالية مع تقارير مفصلة شاملة</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="100">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <h4>إدارة الأوصياء</h4>
                        <p>نظام متكامل لإدارة الأوصياء والعهد مع ضمانات أمان عالية</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="200">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <h4>إدارة المصروفات</h4>
                        <p>تسجيل وتصنيف المصروفات بسهولة مع فلاتر بحث متقدمة</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="300">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4>الحالات الاجتماعية</h4>
                        <p>متابعة شاملة للحالات الاجتماعية والمستفيدين من الخدمات</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="400">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h4>التقارير والإحصائيات</h4>
                        <p>تقارير مفصلة وشاملة مع رسوم بيانية توضيحية متقدمة</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="500">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4>الأمان والحماية</h4>
                        <p>نظام أمني متقدم مع تشفير عالي وحماية البيانات الشاملة</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Roles Section -->
    <section class="roles" id="roles">
        <div class="container">
            <div class="section-title" data-aos="fade-up" data-aos-duration="800">
                <h2>الأدوار والصلاحيات</h2>
                <p>نظام متعدد الأدوار يوفر صلاحيات محددة لكل عضو</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-6 col-md-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="0">
                    <div class="role-card">
                        <div class="role-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3>مدير الجمعية</h3>
                        <p>صلاحيات إدارية كاملة على جميع أنظمة الجمعية والتقارير الشاملة</p>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="100">
                    <div class="role-card">
                        <div class="role-icon">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <h3>محاسب</h3>
                        <p>إدارة الشؤون المالية والمحاسبة والتوازن وإصدار التقارير المالية</p>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="200">
                    <div class="role-card">
                        <div class="role-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <h3>وكيل الجمعية</h3>
                        <p>تنفيذ العمليات اليومية والمتابعة والإشراف على المشاريع الميدانية</p>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6" data-aos="fade-up" data-aos-duration="800" data-aos-delay="300">
                    <div class="role-card">
                        <div class="role-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h3>باحث اجتماعي</h3>
                        <p>دراسة الحالات الاجتماعية والمستفيدين وتوثيق احتياجاتهم</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats" id="stats">
        <div class="container">
            <div class="row text-center">
                <div class="col-lg-3 col-md-6 col-sm-12 stat-item" data-aos="fade-up" data-aos-duration="800" data-aos-delay="0">
                    <div class="stat-number" data-count="500">0</div>
                    <div class="stat-label">جمعية مسجلة</div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-12 stat-item" data-aos="fade-up" data-aos-duration="800" data-aos-delay="100">
                    <div class="stat-number" data-count="50000">0</div>
                    <div class="stat-label">مستفيد</div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-12 stat-item" data-aos="fade-up" data-aos-duration="800" data-aos-delay="200">
                    <div class="stat-number" data-count="1500">0</div>
                    <div class="stat-label">موظف نشط</div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-12 stat-item" data-aos="fade-up" data-aos-duration="800" data-aos-delay="300">
                    <div class="stat-number" data-count="99">0</div>
                    <div class="stat-label">% رضا العملاء</div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta" id="contact">
        <div class="container">
            <div data-aos="fade-up" data-aos-duration="800">
                <h2>ابدأ معنا اليوم</h2>
                <p>انضم إلى آلاف الجمعيات التي تستخدم نظامنا لإدارة عملياتها بكفاءة عالية</p>
                <div>
                    @if(auth()->check())
                        <a href="{{ route('dashboard') }}" class="btn btn-primary" style="padding: 0.8rem 2rem; font-size: 1.05rem;">
                            <i class="fas fa-arrow-left"></i> انتقل إلى لوحة التحكم
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-primary" style="padding: 0.8rem 2rem; font-size: 1.05rem;">
                            <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
                        </a>
                        <a href="{{ route('login') }}" class="btn btn-outline-primary" style="padding: 0.8rem 2rem; font-size: 1.05rem;">
                                                    </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-6 footer-section">
                    <h5>{{ $settings->organization_name ?? 'عن النظام' }}</h5>
                    <p style="color: #aaa; margin-top: 1rem;">منصة متكاملة لإدارة الجمعيات الخيرية بكفاءة وأمان عالي</p>
                    <div class="social-links" style="margin-top: 1.5rem;">
                        <a href="#" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" title="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 footer-section">
                    <h5>الروابط السريعة</h5>
                    <ul>
                        <li><a href="#features">المميزات</a></li>
                        <li><a href="#roles">الأدوار</a></li>
                        <li><a href="#stats">الإحصائيات</a></li>
                        <li><a href="#contact">اتصل بنا</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 footer-section">
                    <h5>المساعدة والدعم</h5>
                    <ul>
                        <li><a href="#">الأسئلة الشائعة</a></li>
                        <li><a href="#">الدعم الفني</a></li>
                        <li><a href="#">التوثيق</a></li>
                        <li><a href="#">الاتصال</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 footer-section">
                    <h5>القانونية</h5>
                    <ul>
                        <li><a href="#">سياسة الخصوصية</a></li>
                        <li><a href="#">شروط الاستخدام</a></li>
                        <li><a href="#">سياسة الأمان</a></li>
                        <li><a href="#">اتفاقية المستخدم</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2026 {{ $settings->organization_name ?? 'نظام إدارة الجمعيات' }}. تم تطويره بواسطة <a href="https://masarsoft.io" target="_blank" style="color: #667eea; text-decoration: none;">masarsoft.io</a> - جميع الحقوق محفوظة.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <script>
        // Initialize AOS
        AOS.init({
            duration: 1000,
            once: true
        });

        // Counter Animation
        const counters = document.querySelectorAll('[data-count]');
        const speed = 200;

        function startCounter(counter) {
            const target = parseInt(counter.getAttribute('data-count'));
            const increment = target / speed;

            const updateCounter = () => {
                const count = parseInt(counter.innerText);
                if (count < target) {
                    counter.innerText = Math.ceil(count + increment);
                    setTimeout(updateCounter, 50);
                } else {
                    counter.innerText = target;
                }
            };

            updateCounter();
        }

        const observerOptions = {
            threshold: 0.5
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    startCounter(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        counters.forEach(counter => observer.observe(counter));

        // Smooth Scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navbar Active State
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.boxShadow = '0 5px 20px rgba(0, 0, 0, 0.1)';
            } else {
                navbar.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.08)';
            }
        });
    </script>
</body>
</html>
