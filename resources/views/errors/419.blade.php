<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>انتهت صلاحية الجلسة</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Cairo', sans-serif; }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #374151;
        }
        .error-card {
            background: linear-gradient(180deg, #1f2937 0%, #374151 100%);
            color: #f9fafb;
            border-radius: 1rem;
            padding: 3rem 2.5rem;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            max-width: 480px;
            width: 100%;
        }
        .error-code {
            font-size: 5rem;
            font-weight: 700;
            color: #f59e0b;
            line-height: 1;
        }
        .error-icon { font-size: 3rem; color: #f59e0b; margin-bottom: 1rem; }
        .error-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
        .error-desc { color: #d1d5db; margin-bottom: 2rem; line-height: 1.8; }
        .btn-back {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: opacity 0.2s;
        }
        .btn-back:hover { opacity: 0.9; color: white; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon"><i class="fas fa-clock"></i></div>
        <div class="error-code">419</div>
        <div class="error-title">انتهت صلاحية الجلسة</div>
        <div class="error-desc">
            لقد انتهت صلاحية جلستك بسبب طول وقت عدم النشاط.<br>
            يُرجى العودة للنموذج وإعادة إرسال البيانات.
        </div>
        <a href="javascript:window.history.back()" class="btn-back">
            <i class="fas fa-arrow-right me-2"></i> العودة للنموذج
        </a>
    </div>
</body>
</html>
