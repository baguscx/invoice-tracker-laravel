@extends('layouts.guest')

@section('title', 'Login - Invoice Tracker')

@section('content')
<section class="login-page">
  <div class="login-wrap"><div class="login-card">
    <h2>Login Invoice Tracker</h2>
    <p>Dashboard otomatis menyesuaikan role akun.</p>

    @if ($errors->any())
      <div class="status-note" style="margin-bottom:14px">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
      @csrf
      <div class="field"><label>Username</label><input name="username" value="{{ old('username') }}" autocomplete="username" required autofocus></div>
      <div class="field"><label>Password</label><input name="password" type="password" autocomplete="current-password" required></div>
      <div class="login-actions">
        <a class="btn btn-soft" href="{{ route('tracking.index') }}">Kembali</a>
        <button class="btn btn-primary" type="submit">Login</button>
      </div>
    </form>
  </div></div>
</section>
@endsection
