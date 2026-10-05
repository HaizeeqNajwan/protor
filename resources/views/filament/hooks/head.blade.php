@php
    $themePath = public_path('css/protor/theme.css');
@endphp

<link rel="preconnect" href="https://fonts.bunny.net">
<link rel="stylesheet" href="https://fonts.bunny.net/css?family=space-grotesk:500,600,700&display=swap">
<link rel="stylesheet" href="{{ asset('css/protor/theme.css') }}?v={{ is_file($themePath) ? filemtime($themePath) : '1' }}">
<meta name="theme-color" content="#232622">
