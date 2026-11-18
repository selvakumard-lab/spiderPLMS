@php
    // Platform branding & default theme come from Super Admin → System Settings (cached).
    $plmsSettings = rescue(fn () => \App\Models\SystemSetting::everything(), \App\Models\SystemSetting::DEFAULTS, false);
    $plmsName = $plmsSettings['app_name'] ?? config('app.name');
    $plmsTheme = $plmsSettings['default_theme'] ?? 'system';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
        <meta name="theme-color" content="#D6121B" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <meta name="description" content="{{ $plmsName }} — plan, deliver and bill every project from one multi-tenant workspace." />
        <title>{{ $plmsName }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}" />
        <script>
            // Apply the saved (or platform-default) theme before first paint to avoid a light flash.
            (function () {
                try {
                    var pref = localStorage.getItem('plms.theme') || @json($plmsTheme);
                    var dark = pref === 'dark' || (pref === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', dark);
                } catch (e) {}
            })();
        </script>
        @viteReactRefresh
        @vite('resources/js/app.tsx')
    </head>
    <body>
        <div id="root"></div>
    </body>
</html>
