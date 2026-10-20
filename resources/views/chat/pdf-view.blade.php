<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $message->attachment_name }} — Visionneuse — {{ config('app.name', 'EPSV') }}</title>

        <link rel="icon" id="app-favicon" href="/favicon.ico" type="image/x-icon">

        @vite(['resources/css/app.css', 'resources/js/pdf-viewer.jsx'])
    </head>
    <body class="h-full bg-slate-100 font-sans antialiased">
        <div
            id="pdf-viewer-app"
            class="h-full"
            data-document-url="{{ route('chat.attachment', $message) }}"
            data-download-url="{{ route('chat.attachment', $message) }}"
            data-return-url="{{ route('chat.index', ['c' => $conversation->id]) }}"
            data-file-name="{{ $message->attachment_name }}"
        ></div>

        <noscript>
            <p style="padding: 1rem; font-family: sans-serif;">
                La visionneuse PDF nécessite JavaScript.
                <a href="{{ route('chat.index', ['c' => $conversation->id]) }}">Retour à la messagerie</a>
            </p>
        </noscript>
    </body>
</html>
