@extends('layouts.modern')

@section('content')
<div class="container-fluid" style="height: calc(100vh - 80px); display: flex; flex-direction: column;">
    <!-- Header -->
    <div class="row mb-3" data-aos="fade-down">
        <div class="col-12">
            <h1 style="margin: 0; font-size: 1.75rem; font-weight: 700;">
                <i class="fas fa-comments"></i> المحادثة الجماعية
            </h1>
            <p class="text-muted" style="margin: 0.25rem 0 0 0; font-size: 0.875rem;">
                <i class="fas fa-circle text-success" style="font-size: 0.6rem;"></i>
                مجموعة الفريق — مرئية لجميع الموظفين
            </p>
        </div>
    </div>

    <!-- Chat Container -->
    <div class="card" style="flex: 1; display: flex; flex-direction: column; min-height: 0;">
        <!-- Messages -->
        <div class="card-body" id="messagesContainer" style="
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            background: #f8fafc;
            min-height: 0;
        ">
            @forelse($messages as $msg)
            @php $isMe = $msg->user_id === auth()->id(); @endphp
            <div class="msg-row d-flex gap-2 {{ $isMe ? 'flex-row-reverse' : '' }}" data-msg-id="{{ $msg->id }}">
                {{-- Avatar --}}
                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:40px;height:40px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;font-weight:700;font-size:.9rem;align-self:flex-end;">
                    {{ mb_substr($msg->user->name, 0, 1) }}
                </div>
                {{-- Bubble --}}
                <div style="max-width: 65%;">
                    @if(!$isMe)
                    <div style="font-size:.75rem;font-weight:600;color:#6b7280;margin-bottom:.25rem;padding-right:.5rem;">
                        {{ $msg->user->name }}
                        <span style="font-weight:400;margin-right:.5rem;">
                            {{ $msg->user->getRoleNames()->first() ?? '' }}
                        </span>
                    </div>
                    @endif
                    <div class="d-flex align-items-end gap-1 {{ $isMe ? 'flex-row-reverse' : '' }}">
                        <div style="
                            padding: .6rem 1rem;
                            border-radius: {{ $isMe ? '18px 18px 4px 18px' : '18px 18px 18px 4px' }};
                            {{ $isMe ? 'background: linear-gradient(135deg,#667eea,#764ba2); color: white;' : 'background: white; color: #1f2937; box-shadow: 0 1px 3px rgba(0,0,0,.08);' }}
                            font-size: .9rem;
                            line-height: 1.5;
                            word-break: break-word;
                            white-space: pre-wrap;
                        ">{{ $msg->body }}</div>
                        @if($isMe || auth()->user()->hasRole('مدير'))
                        <button onclick="deleteMessage({{ $msg->id }}, this)"
                                style="background:none;border:none;color:#d1d5db;font-size:.7rem;cursor:pointer;padding:.2rem;opacity:0;"
                                class="del-btn"
                                title="حذف">
                            <i class="fas fa-trash"></i>
                        </button>
                        @endif
                    </div>
                    <div style="font-size:.7rem;color:#9ca3af;margin-top:.2rem;{{ $isMe ? 'text-align:left;' : 'text-align:right;' }}padding: 0 .5rem;">
                        {{ $msg->created_at->format('H:i') }} · {{ $msg->created_at->format('d/m') }}
                    </div>
                </div>
            </div>
            @empty
            <div class="text-center py-5 text-muted" id="emptyState">
                <i class="fas fa-comments" style="font-size:3rem;margin-bottom:1rem;display:block;opacity:.3;"></i>
                لا توجد رسائل بعد. كن أول من يبدأ المحادثة!
            </div>
            @endforelse
        </div>

        <!-- Typing area -->
        <div class="card-footer" style="background:white;border-top:1px solid #e5e7eb;padding:1rem;">
            <form id="chatForm" action="{{ route('chat.store') }}" method="POST">
                @csrf
                <div class="d-flex gap-2 align-items-end">
                    <textarea
                        id="msgInput"
                        name="body"
                        class="form-control"
                        placeholder="اكتب رسالتك... (Enter للإرسال، Shift+Enter لسطر جديد)"
                        rows="1"
                        maxlength="1000"
                        style="resize:none;border-radius:12px;font-size:.9rem;"
                        required
                        onkeydown="handleKey(event)"
                        oninput="autoResize(this)"
                    ></textarea>
                    <button type="submit" class="btn btn-primary" style="border-radius:12px;padding:.6rem 1.2rem;white-space:nowrap;">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const myUserId = {{ auth()->id() }};
    const isManager = {{ auth()->user()->hasRole('مدير') ? 'true' : 'false' }};
    let lastMsgId = {{ $messages->isNotEmpty() ? $messages->last()->id : 0 }};
    const pollUrl = '{{ route("api.chat.poll") }}';
    const deleteUrl = id => `/chat/${id}`;
    const csrfToken = '{{ csrf_token() }}';

    // Scroll to bottom
    function scrollBottom() {
        const c = document.getElementById('messagesContainer');
        c.scrollTop = c.scrollHeight;
    }
    scrollBottom();

    // Auto-resize textarea
    function autoResize(el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 120) + 'px';
    }

    // Enter to send
    function handleKey(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('chatForm').requestSubmit();
        }
    }

    // Show/hide delete button on hover
    document.getElementById('messagesContainer').addEventListener('mouseover', e => {
        const row = e.target.closest('.msg-row');
        if (row) row.querySelectorAll('.del-btn').forEach(b => b.style.opacity = '1');
    });
    document.getElementById('messagesContainer').addEventListener('mouseout', e => {
        const row = e.target.closest('.msg-row');
        if (row) row.querySelectorAll('.del-btn').forEach(b => b.style.opacity = '0');
    });

    // Build message HTML
    function buildMsgHtml(msg) {
        const isMe = msg.is_me;
        const nameHtml = !isMe ? `
            <div style="font-size:.75rem;font-weight:600;color:#6b7280;margin-bottom:.25rem;padding-right:.5rem;">
                ${msg.name}
            </div>` : '';
        const delBtn = (isMe || isManager) ? `
            <button onclick="deleteMessage(${msg.id}, this)"
                    style="background:none;border:none;color:#d1d5db;font-size:.7rem;cursor:pointer;padding:.2rem;opacity:0;"
                    class="del-btn" title="حذف">
                <i class="fas fa-trash"></i>
            </button>` : '';
        const bubbleStyle = isMe
            ? 'background:linear-gradient(135deg,#667eea,#764ba2);color:white;'
            : 'background:white;color:#1f2937;box-shadow:0 1px 3px rgba(0,0,0,.08);';
        const radius = isMe ? '18px 18px 4px 18px' : '18px 18px 18px 4px';

        return `
        <div class="msg-row d-flex gap-2 ${isMe ? 'flex-row-reverse' : ''}" data-msg-id="${msg.id}">
            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:40px;height:40px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;font-weight:700;font-size:.9rem;align-self:flex-end;">
                ${msg.name.charAt(0)}
            </div>
            <div style="max-width:65%;">
                ${nameHtml}
                <div class="d-flex align-items-end gap-1 ${isMe ? 'flex-row-reverse' : ''}">
                    <div style="padding:.6rem 1rem;border-radius:${radius};${bubbleStyle}font-size:.9rem;line-height:1.5;word-break:break-word;white-space:pre-wrap;">${escHtml(msg.body)}</div>
                    ${delBtn}
                </div>
                <div style="font-size:.7rem;color:#9ca3af;margin-top:.2rem;${isMe ? 'text-align:left;' : 'text-align:right;'}padding:0 .5rem;">
                    ${msg.time}
                </div>
            </div>
        </div>`;
    }

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // Poll for new messages every 5 seconds
    async function pollMessages() {
        try {
            const res = await fetch(`${pollUrl}?since=${lastMsgId}`);
            const msgs = await res.json();
            if (msgs.length > 0) {
                const container = document.getElementById('messagesContainer');
                const empty = document.getElementById('emptyState');
                if (empty) empty.remove();

                const wasAtBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 60;

                msgs.forEach(msg => {
                    container.insertAdjacentHTML('beforeend', buildMsgHtml(msg));
                    lastMsgId = Math.max(lastMsgId, msg.id);
                });

                if (wasAtBottom) scrollBottom();
            }
        } catch(e) {}
    }

    setInterval(pollMessages, 5000);

    // Delete message
    async function deleteMessage(id, btn) {
        if (!confirm('حذف الرسالة؟')) return;
        try {
            await fetch(deleteUrl(id), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
            });
            const row = btn.closest('.msg-row');
            row.style.transition = 'opacity .3s';
            row.style.opacity = '0';
            setTimeout(() => row.remove(), 300);
        } catch(e) {}
    }

    // Submit via AJAX to avoid full page reload
    document.getElementById('chatForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const input = document.getElementById('msgInput');
        const body = input.value.trim();
        if (!body) return;

        const fd = new FormData(this);
        input.value = '';
        input.style.height = 'auto';

        try {
            const res = await fetch('{{ route("chat.store") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: fd
            });
            // Immediately poll for the new message
            await pollMessages();
        } catch(e) {}
    });
</script>
@endpush

<style>
    /* Ensure layout fills screen properly */
    .main-content { height: calc(100vh - 70px); display: flex; flex-direction: column; }
    .container-fluid { flex: 1; min-height: 0; }
</style>
@endsection
