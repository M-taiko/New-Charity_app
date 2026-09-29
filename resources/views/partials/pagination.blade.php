@if ($paginator->hasPages())
    <nav aria-label="التنقل بين الصفحات">
        <ul class="pagination app-pagination justify-content-center flex-wrap mb-0">
            {{-- السابق (في RTL يشير لليمين) --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled">
                    <span class="page-link app-page-nav" aria-hidden="true"><i class="fas fa-chevron-right"></i></span>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link app-page-nav" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="السابق" title="السابق">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            @endif

            @foreach ($elements as $element)
                {{-- الفاصل "..." --}}
                @if (is_string($element))
                    <li class="page-item disabled"><span class="page-link app-page-dots">{{ $element }}</span></li>
                @endif

                {{-- أرقام الصفحات --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                        @else
                            <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- التالي (في RTL يشير لليسار) --}}
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link app-page-nav" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="التالي" title="التالي">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
            @else
                <li class="page-item disabled">
                    <span class="page-link app-page-nav" aria-hidden="true"><i class="fas fa-chevron-left"></i></span>
                </li>
            @endif
        </ul>
    </nav>

    <style>
        /* تنسيق ترقيم الصفحات بما يناسب هوية التطبيق — لا يمس ترقيم الصفحات الافتراضي في الصفحات الأخرى */
        .pagination.app-pagination { gap: .4rem; }
        .pagination.app-pagination .page-link {
            min-width: 38px;
            height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 .65rem;
            margin: 0;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-weight: 600;
            font-size: .9rem;
            color: #475569;
            background: #fff;
            line-height: 1;
            transition: all .2s ease;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }
        .pagination.app-pagination .page-link:hover {
            border-color: #4facfe;
            color: #0284c7;
            background: rgba(79, 172, 254, .08);
            text-decoration: none;
        }
        .pagination.app-pagination .page-item.active .page-link {
            border: none;
            color: #fff;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            box-shadow: 0 4px 12px rgba(79, 172, 254, .35);
        }
        .pagination.app-pagination .page-item.disabled .page-link {
            color: #cbd5e1;
            background: #f8fafc;
            border-color: #e2e8f0;
            cursor: not-allowed;
            opacity: .7;
        }
        .pagination.app-pagination .page-link.app-page-nav { border-radius: 50%; padding: 0; }
        .pagination.app-pagination .page-link.app-page-dots {
            border: none;
            background: transparent;
            box-shadow: none;
            color: #94a3b8;
            font-weight: 700;
        }
    </style>
@endif
