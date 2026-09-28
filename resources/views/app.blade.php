<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themeMode }}" data-accent="{{ $themeAccent }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'POS') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        <script>
            (function () {
                try {
                    var modes = ['light', 'dark'];
                    var accents = ['system', 'orange', 'blue', 'emerald', 'purple', 'rose', 'teal', 'pink', 'slate'];
                    var defaultMode = @json($themeMode);
                    var defaultAccent = @json($themeAccent);
                    var storedMode = localStorage.getItem('pos.theme_mode');
                    var storedAccent = localStorage.getItem('pos.theme_accent');
                    var mode = storedMode || defaultMode;
                    var accent = storedAccent || defaultAccent;
                    if (mode === 'system') {
                        mode = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                    } else if (modes.indexOf(mode) === -1) {
                        mode = defaultMode;
                    }
                    if (accents.indexOf(accent) === -1) {
                        accent = defaultAccent;
                    }
                    document.documentElement.dataset.theme = mode;
                    document.documentElement.dataset.accent = accent;
                    document.documentElement.style.colorScheme = mode;
                } catch (e) {}
            })();
        </script>
    </head>
    <body class="antialiased">
        <div id="app"></div>
    </body>
</html>
