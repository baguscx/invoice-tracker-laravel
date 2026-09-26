<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', 'Invoice Tracker')</title>
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
  @yield('content')
  <div id="toast" class="toast hidden" role="status" aria-live="polite"></div>
  <script>
  (function(){
    const saved = localStorage.getItem('invoiceTrackerTheme');
    const theme = saved || ((window.matchMedia && window.matchMedia('(prefers-color-scheme:dark)').matches) ? 'dark' : 'light');
    document.documentElement.dataset.theme = theme;
    document.querySelectorAll('[data-theme-toggle]').forEach(btn => btn.addEventListener('click', () => {
      const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
      document.documentElement.dataset.theme = next;
      localStorage.setItem('invoiceTrackerTheme', next);
    }));
  })();
  </script>
</body>
</html>
