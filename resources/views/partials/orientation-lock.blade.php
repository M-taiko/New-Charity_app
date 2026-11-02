{{-- Orientation lock: portrait-only on phones (desktops/tablets unaffected) --}}
<div class="orientation-lock" dir="rtl">
    <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="7" y="3" width="10" height="18" rx="2"></rect>
        <path d="M2 12a10 10 0 0 1 3.5-7.6"></path>
        <path d="M2 12a10 10 0 0 0 3.5 7.6"></path>
        <path d="M2 12h4"></path>
    </svg>
    <p class="orientation-lock__title">الوضع الأفقي معطّل</p>
    <p class="orientation-lock__text">أدر جهازك إلى الوضع العمودي لاستخدام النظام</p>
</div>
<style>
    .orientation-lock { display: none; }
    @media (orientation: landscape) and (max-height: 500px) {
        .orientation-lock {
            position: fixed; inset: 0; z-index: 99999;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 12px; padding: 24px; text-align: center;
            background: #4f2f1d; color: #f7f3ef;
        }
        .orientation-lock__title { font-size: 1.15rem; font-weight: 700; margin: 0; }
        .orientation-lock__text { font-size: .9rem; opacity: .8; margin: 0; max-width: 320px; }
    }
</style>
