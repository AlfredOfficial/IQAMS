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

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if($includeQrCode)
        @vite('resources/js/qrcode.js')
    @endif
</head>
<body {{ $attributes->class($bodyClass) }} x-data="{{ $alpineData }}">
    {{ $slot }}
</body>
</html>
