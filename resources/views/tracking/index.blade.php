@extends('layouts.guest')

@section('title', 'Cek Posisi Invoice')

@section('content')
<section class="public-page">
  <div class="topbar"><div class="topbar-inner">
    <div class="brand"><span class="brand-mark">IF</span><div>Invoice Tracker<small>Laravel Blade + MySQL</small></div></div>
    <div class="top-actions">
      <button data-theme-toggle class="btn btn-soft icon-btn" type="button">◐</button>
      @auth
        <a href="{{ route('dashboard') }}" class="btn btn-primary">Dashboard</a>
      @else
        <a href="{{ route('login') }}" class="btn btn-primary">Dashboard</a>
      @endauth
    </div>
  </div></div>

  <div class="public-wrap">
    <div class="public-title"><h1>Cek Posisi Invoice</h1><p>Masukkan No Tanda Terima, No Invoice, atau No PO secara lengkap.</p></div>
    <form method="GET" action="{{ route('tracking.index') }}" class="searchbar">
      <input name="q" value="{{ $query }}" required autocomplete="off" placeholder="Contoh: TT-20260925-001">
      <button class="btn btn-primary" type="submit">Cari</button>
    </form>

    <div class="public-result">
      @if($error)
        <div class="result-card" style="text-align:center;color:var(--danger)">{{ $error }}</div>
      @elseif($query !== '' && count($results) === 0)
        <div class="result-card" style="text-align:center;color:var(--muted)">Invoice tidak ditemukan.</div>
      @endif

      @foreach($results as $inv)
        <div class="result-card">
          <div class="result-head">
            <div>
              <strong style="font-size:17px">{{ $inv['receiptNo'] ?: $inv['invoiceNo'] }}</strong>
              <div class="muted" style="font-size:12px;margin-top:3px">Invoice: {{ $inv['invoiceNo'] ?: '-' }} · {{ $inv['supplier'] }}</div>
            </div>
            <span class="badge {{ $inv['status'] === 'Selesai' ? 'green' : ($inv['status'] === 'Batal' ? 'red' : 'gray') }}">{{ $inv['status'] }}</span>
          </div>
          <div class="result-grid">
            <div class="info"><small>Posisi Sekarang</small><strong>{{ $inv['position'] ?: '-' }}</strong></div>
            <div class="info"><small>PIC / User</small><strong>{{ $inv['picUser'] ?: '-' }}</strong></div>
            <div class="info"><small>Jatuh Tempo</small><strong>{{ $inv['dueDate'] ?: '-' }} · {{ $inv['dueStatus'] }}</strong></div>
            <div class="info"><small>Kekurangan</small><strong>{{ $inv['missingDocuments'] ?: 'Lengkap' }}</strong></div>
          </div>
          @if(!empty($inv['timeline']))
            <div class="public-timeline"><strong>Perjalanan invoice</strong><div class="timeline" style="margin-top:10px">
              @foreach(array_slice($inv['timeline'], 0, 7) as $h)
                <div class="timeline-item"><div class="timeline-dot"></div><div class="timeline-card"><strong>{{ $h['toStatus'] ?: '-' }}</strong><div class="timeline-meta">{{ $h['time'] }}</div></div></div>
              @endforeach
            </div></div>
          @endif
        </div>
      @endforeach
    </div>
    <div class="muted" style="text-align:center;margin-top:16px;font-size:11px">Invoice Tracker · Laravel MVC</div>
  </div>
</section>
@endsection
