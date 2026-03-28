@php
    $full    = floor($rating);
    $half    = ($rating - $full) >= 0.5 ? 1 : 0;
    $empty   = 5 - $full - $half;
    $color   = $rating >= 4 ? '#f59e0b' : ($rating >= 3 ? '#f97316' : '#ef4444');
@endphp
<div class="d-flex align-items-center gap-1" title="{{ $rating }} / 5">
    @for($i = 0; $i < $full; $i++)
        <i class="fas fa-star" style="color: {{ $color }}; font-size: 0.9rem;"></i>
    @endfor
    @if($half)
        <i class="fas fa-star-half-alt" style="color: {{ $color }}; font-size: 0.9rem;"></i>
    @endif
    @for($i = 0; $i < $empty; $i++)
        <i class="far fa-star" style="color: #d1d5db; font-size: 0.9rem;"></i>
    @endfor
    <small class="text-muted ms-1">({{ number_format($rating, 1) }})</small>
</div>
