@php
    $themePath = public_path('css/protor/theme.css');
    $dsPath = public_path('css/protor/design-system.css');
@endphp

{{-- theme.css = legacy structure; design-system.css = tokens + components + skin (loads last, wins). --}}
<link rel="stylesheet" href="{{ asset('css/protor/theme.css') }}?v={{ is_file($themePath) ? filemtime($themePath) : '1' }}">
<link rel="stylesheet" href="{{ asset('css/protor/design-system.css') }}?v={{ is_file($dsPath) ? filemtime($dsPath) : '1' }}">
<meta name="theme-color" content="#050507">
