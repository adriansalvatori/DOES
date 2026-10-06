@if (app()->environment('local'))
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon-local.ico') }}?v=local">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-local-32x32.png') }}?v=local">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-local-16x16.png') }}?v=local">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon-local.png') }}?v=local">
    <link rel="shortcut icon" href="{{ asset('favicon-local.ico') }}?v=local">
@else
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=3">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=3">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v=3">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=3">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}?v=3">
@endif
