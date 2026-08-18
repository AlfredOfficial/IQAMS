@props([
    'title' => config('app.name', 'IQAMS'),
    'bodyClass' => 'font-sans antialiased',
    'includeQrCode' => false,
    'alpineData' => '{}',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} - {{ config('app.name', 'IQAMS') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet">

    @if($includeQrCode)
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body {{ $attributes->class($bodyClass) }} x-data="{{ $alpineData }}">
    {{ $slot }}
</body>
</html>
