<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="canonical" href="{{ url()->current() }}">

    <!-- Favicon & App Icons -->
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">

    <!-- Structured Data (Example: JSON-LD Schema.org) -->
    <script type="application/ld+json">
@verbatim
{
    "@context": "https://schema.org",
    "@type": "SoftwareApplication",
    "name": "Larasonic",
    "url": "https://larasonic.com/",
    "image": "https://larasonic.com/images/og.webp",
    "description": "A modern Laravel SaaS starter kit for the VILT stack. Clone the repo, start building scalable and maintainable applications quickly.",
    "applicationCategory": "DeveloperTool",
    "operatingSystem": "All",
    "offers": {
        "@type": "Offer",
        "price": "0",
        "priceCurrency": "USD",
        "category": "Free"
    }
}
@endverbatim
    </script>

    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.js'])
    @inertiaHead
  </head>

  <body class="font-sans antialiased min-h-screen bg-gradient-to-br from-emerald-100 via-sky-100 to-fuchsia-100 text-gray-900 leading-loose text-[18px] md:text-[20px]">
    @inertia
  </body>
</html>
