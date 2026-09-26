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
@php
  $roleLabels = ['ADMIN' => 'Admin', 'RESEPSIONIS' => 'Resepsionis', 'USER' => 'User / PIC', 'ACCOUNTING' => 'Accounting'];
  $workspaceLabels = ['ADMIN' => 'Control Center', 'RESEPSIONIS' => 'Meja Resepsionis', 'USER' => 'Tugas User / PIC', 'ACCOUNTING' => 'Meja Accounting'];
  $authUser = auth()->user();
@endphp
<section id="dashboardPage" class="shell">
  <div class="topbar"><div class="topbar-inner">
    <div class="brand"><span class="brand-mark">IF</span><div>Invoice Tracker<small>{{ $workspaceLabels[$authUser->role] ?? 'Workspace' }}</small></div></div>
    <div class="top-actions">
      <div class="user-chip"><span class="name">{{ $authUser->name }}</span><span class="role-pill">{{ $roleLabels[$authUser->role] ?? $authUser->role }}</span></div>
      <button id="themeBtn" class="btn btn-soft icon-btn" type="button" title="Ganti tema">◐</button>
      <a href="{{ route('tracking.index') }}" class="btn btn-soft">Cek Publik ↗</a>
      <form method="POST" action="{{ route('logout') }}" style="margin:0">
        @csrf
        <button class="btn btn-danger" type="submit">Logout</button>
      </form>
    </div>
  </div></div>

  <main class="container">
    @yield('content')
  </main>
</section>

@include('partials.modals')

<script>
window.APP_BASE_URL = @json(url('/'));
window.InvoiceTracker = @json($bootstrap);
</script>
<script src="{{ asset('js/dashboard.js') }}"></script>
</body>
</html>
