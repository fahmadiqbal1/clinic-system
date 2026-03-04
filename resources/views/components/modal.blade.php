@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl'
])

@php
$modalSize = match($maxWidth) {
    'sm' => 'modal-sm',
    'md' => '',
    'lg' => 'modal-lg',
    'xl' => 'modal-xl',
    '2xl' => 'modal-xl',
    default => 'modal-lg',
};
@endphp

<div class="modal fade{{ $show ? ' show' : '' }}" id="modal-{{ $name }}" tabindex="-1" aria-labelledby="modal-{{ $name }}-label" aria-hidden="true"@if($show) style="display:block"@endif>
    <div class="modal-dialog {{ $modalSize }}">
        <div class="modal-content">
            {{ $slot }}
        </div>
    </div>
</div>
@if($show)
<div class="modal-backdrop fade show"></div>
<script>document.addEventListener('DOMContentLoaded',function(){new bootstrap.Modal(document.getElementById('modal-{{ $name }}')).show()});</script>
@endif
