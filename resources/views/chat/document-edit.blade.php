<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $message->attachment_name }} — Éditeur — {{ config('app.name', 'EPSV') }}</title>

        <link rel="icon" id="app-favicon" href="/favicon.ico" type="image/x-icon">

        @if ($message->isEditableXlsx())
            @vite(['resources/css/app.css', 'resources/js/xlsx-editor.jsx'])
        @else
            @vite(['resources/css/app.css', 'resources/js/docx-editor.jsx'])
        @endif
    </head>
    <body class="h-full bg-slate-100 font-sans antialiased">
        @if ($message->isEditableXlsx())
            <div
                id="xlsx-editor-app"
                class="h-full"
                data-document-url="{{ route('chat.attachment', $message) }}"
                data-save-url="{{ route('chat.documents.save', $message) }}"
                data-return-url="{{ route('chat.index', ['c' => $conversation->id]) }}"
                data-file-name="{{ $message->attachment_name }}"
            ></div>
        @else
            <div
                id="docx-editor-app"
                class="h-full"
                data-document-url="{{ route('chat.attachment', $message) }}"
                data-save-url="{{ route('chat.documents.save', $message) }}"
                data-return-url="{{ route('chat.index', ['c' => $conversation->id]) }}"
                data-file-name="{{ $message->attachment_name }}"
            ></div>
        @endif

        <noscript>
            <p style="padding: 1rem; font-family: sans-serif;">
                L’éditeur de documents nécessite JavaScript.
                <a href="{{ route('chat.index', ['c' => $conversation->id]) }}">Retour à la messagerie</a>
            </p>
        </noscript>
    </body>
</html>
