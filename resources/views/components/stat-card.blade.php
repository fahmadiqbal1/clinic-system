{{--
    Reusable Glass Stat Card Component
    Props:
      icon    : Bootstrap Icons class e.g. 'bi-cash-stack'
      label   : Display label text
      value   : The stat value to display
      color   : success | danger | primary | warning | info | secondary  (default: primary)
      link    : Optional href for the card to be clickable
      trend   : Optional string like '+12%' or '-3%' shown as a badge
      delay   : Fade-in delay class (1-5, optional)
--}}
@props([
    'icon'  => 'bi-circle',
    'label' => '',
    'value' => '—',
    'color' => 'primary',
    'link'  => null,
    'trend' => null,
    'delay' => null,
])

@php
    $trendColor = $trend && str_starts_with($trend, '+') ? 'success' : ($trend && str_starts_with($trend, '-') ? 'danger' : 'secondary');
    $delayClass = $delay ? " delay-{$delay}" : '';
@endphp

<div class="glass-card p-3 hover-lift{{ $delayClass }}{{ $link ? ' cursor-pointer' : '' }}"
     @if($link) onclick="window.location='{{ $link }}'" role="{{ $link ? 'button' : '' }}" @endif>
    <div class="d-flex align-items-center gap-3">
        <div class="stat-icon stat-icon-{{ $color }}">
            <i class="bi {{ $icon }}"></i>
        </div>
        <div class="flex-fill">
            <div class="text-muted small">{{ $label }}</div>
            <div class="stat-value glow-{{ $color }} skeleton-target">{{ $value }}</div>
            @if($trend)
                <span class="badge bg-{{ $trendColor }} bg-opacity-20 text-{{ $trendColor }} mt-1" style="font-size:0.7rem;">{{ $trend }}</span>
            @endif
        </div>
    </div>
</div>
