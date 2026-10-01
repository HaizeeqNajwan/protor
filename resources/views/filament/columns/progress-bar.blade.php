@php
    $value = (int) $getState();
    $tone = match (true) {
        $value >= 100 => 'success',
        $value >= 50 => 'primary',
        $value > 0 => 'warning',
        default => 'muted',
    };
@endphp

<div class="pt-cell-progress pt-tone--{{ $tone }}">
    <span class="pt-bar"><span style="width: {{ $value }}%"></span></span>
    <b class="pt-num">{{ $value }}%</b>
</div>
