@extends('layouts.app')

@section('title', 'User PIC - Invoice Tracker')

@section('content')
<section id="userPanel" class="role-panel" data-role="USER">
  <div class="hero">
    <div><div class="eyebrow">USER / PIC WORKSPACE</div><h1>Tugas Invoice Saya</h1><p>Hanya invoice yang ditugaskan ke akun ini. Mulai, lengkapi dokumen, lalu kembalikan.</p></div>
  </div>

  <section class="summary">
    <div class="stat action"><span>Tugas Masuk</span><strong id="userInboxCount">0</strong><small>Belum dimulai</small></div>
    <div class="stat overdue"><span>Sedang Dikerjakan</span><strong id="userActiveCount">0</strong><small>Perlu dilengkapi</small></div>
    <div class="stat"><span>Sudah Diserahkan</span><strong id="userReturnedCount">0</strong><small>Menunggu diterima / proses berikutnya</small></div>
    <div class="stat done"><span>Selesai</span><strong id="userDoneCount">0</strong><small>Riwayat tugas</small></div>
  </section>
  <div class="workspace-grid two-col user-lanes">
    <section class="workspace-card task-lane">
      <div class="section-head"><div><h2>Inbox Tugas</h2><p>Invoice baru dari Resepsionis yang menunggu kamu mulai.</p></div>
        <input id="userInboxSearch" class="inline-search" placeholder="Cari tanda terima / invoice / PO / supplier...">
      </div>
      <div id="userInboxList" class="task-list"></div>
      <div id="userInboxEmpty" class="empty compact-empty hidden">Tidak ada tugas baru.</div>
    </section>
    <section class="workspace-card task-lane">
      <div class="section-head"><div><h2>Sedang Dikerjakan</h2><p>Checklist dokumen harus bersih sebelum bisa diserahkan ke Resepsionis.</p></div>
        <input id="userActiveSearch" class="inline-search" placeholder="Cari tanda terima / invoice / PO / supplier...">
      </div>
      <div id="userActiveList" class="task-list"></div>
      <div id="userActiveEmpty" class="empty compact-empty hidden">Tidak ada invoice yang sedang dikerjakan.</div>
    </section>
  </div>

  <section class="workspace-card">
    <div class="section-head"><div><h2>Riwayat tugas saya</h2><p>Invoice yang sudah kamu serahkan dan perjalanan proses berikutnya.</p></div>
      <input id="userHistorySearch" class="inline-search" placeholder="Cari riwayat...">
    </div>
    <div class="table-scroll"><table class="invoice-table"><thead><tr><th>No Tanda Terima</th><th>Supplier</th><th>Status</th><th>Posisi</th><th>Jatuh Tempo</th><th>Dokumen</th><th></th></tr></thead><tbody id="userHistoryBody"></tbody></table></div>
    <div id="userHistoryEmpty" class="empty hidden">Belum ada riwayat.</div>
  </section>
</section>
@endsection
