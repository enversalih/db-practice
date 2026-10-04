<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="PostgreSQL ilişkisel veritabanı eğitim laboratuvarı">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <title>Relasyon Laboratuvarı</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>@inertia</body>
</html>
