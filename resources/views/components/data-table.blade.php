<div class="card" data-aos="fade-up">
    <div class="card-header">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h5 style="margin: 0;">
                    <i class="{{ $icon ?? 'fas fa-table' }}"></i>
                    {{ $title ?? 'الجدول' }}
                </h5>
            </div>
            @if($action ?? false)
            <a href="{{ $actionUrl }}" class="btn btn-primary btn-sm">
                <i class="{{ $actionIcon ?? 'fas fa-plus' }}"></i>
                {{ $actionLabel ?? 'إضافة جديد' }}
            </a>
            @endif
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="{{ $id ?? 'dataTable' }}">
                <thead>
                    <tr>
                        {{ $slot }}
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </div>
</div>
