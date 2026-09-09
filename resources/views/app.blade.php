<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])

        {{-- The palette an administrator chose, as overrides of the custom
             properties every component reads. After the stylesheet, because
             both define the same properties on :root and the later one wins.
             Empty unless somebody changed a colour. --}}
        @if (! empty($themeStylesheet))
            <style>{!! $themeStylesheet !!}</style>
        @endif
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />

        {{-- Rendered by the shell rather than by a layout, so it reaches every
             page there is — the sign-in page, the consent screen and the
             administration alike — without each of them having to remember. --}}
        <p
            class="text-muted-foreground/70 pointer-events-none fixed inset-x-0 bottom-0 z-50 py-2 text-center text-[11px]"
        >
            Crafted by an Artisan &hearts;
            <a
                href="https://lauroguedes.dev"
                target="_blank"
                rel="noopener noreferrer"
                class="hover:text-foreground pointer-events-auto underline-offset-2 transition-colors hover:underline"
            >Lauro Guedes</a>
        </p>
    </body>
</html>
