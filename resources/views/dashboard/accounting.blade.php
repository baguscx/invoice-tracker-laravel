@extends('layouts.app')

@section('title', 'Accounting - Invoice Tracker')

@section('content')
<section id="accountingPanel" class="role-panel" data-role="ACCOUNTING">
  <div class="hero">
    <div><div class="eyebrow">ACCOUNTING WORKSPACE</div><h1>Meja Accounting — Tugas Saya</h1><p>Hanya invoice yang ditugaskan ke akun Accounting ini: verifikasi → proses pembayaran → selesai, atau kembalikan ke Resepsionis jika ada data/dokumen yang perlu dikoreksi.</p></div>
  </div>

  <section class="summary">
    <div class="stat action"><span>Siap Diverifikasi</span><strong id="accWaitingCount">0</strong><small>Ditugaskan ke saya</small></div>
    <div class="stat"><span>Sedang Diproses</span><strong id="accActiveCount">0</strong><small>Verifikasi / pembayaran</small></div>
    <div class="stat overdue"><span>Prioritas</span><strong id="accUrgentCount">0</strong><small>Jatuh tempo</small></div>
    <div class="stat done"><span>Selesai</span><strong id="accDoneCount">0</strong><small>Riwayat tugas saya</small></div>
  </section>
  <section id="accUrgentSection" class="workspace-card urgent-card hidden">
    <div class="section-head"><div><h2>Prioritas Hari Ini</h2><p>Invoice aktif yang jatuh tempo hari ini atau sudah terlambat.</p></div></div>
    <div id="accUrgentList" class="task-list compact-cards"></div>
  </section>

  <div class="workspace-grid two-col accounting-lanes">
    <section class="workspace-card task-lane">
      <div class="section-head"><div><h2>Siap Diverifikasi</h2><p>Mulai verifikasi. Jika ditemukan kekeliruan, invoice dapat dikembalikan ke Resepsionis dengan catatan.</p></div>
        <input id="accWaitingSearch" class="inline-search" placeholder="Cari tanda terima / invoice / PO / supplier...">
      </div>
      <div id="accWaitingList" class="task-list"></div>
      <div id="accWaitingEmpty" class="empty compact-empty hidden">Tidak ada invoice menunggu Accounting.</div>
    </section>
    <section class="workspace-card task-lane">
      <div class="section-head"><div><h2>Sedang Diproses</h2><p>Tambahkan catatan progres, selesaikan ketika tuntas, atau kembalikan ke Resepsionis jika perlu koreksi.</p></div>
        <input id="accActiveSearch" class="inline-search" placeholder="Cari tanda terima / invoice / PO / supplier...">
      </div>
      <div id="accActiveList" class="task-list"></div>
      <div id="accActiveEmpty" class="empty compact-empty hidden">Tidak ada invoice yang sedang diproses.</div>
    </section>
  </div>

  <section class="workspace-card">
    <div class="section-head"><div><h2>Riwayat selesai</h2><p>Invoice yang sudah diselesaikan oleh akun Accounting ini.</p></div>
      <input id="accHistorySearch" class="inline-search" placeholder="Cari tanda terima / invoice / supplier...">
    </div>
    <div class="table-scroll"><table class="invoice-table"><thead><tr><th>No Tanda Terima</th><th>Supplier</th><th>Amount</th><th>Jatuh Tempo</th><th>Tanggal Selesai</th><th></th></tr></thead><tbody id="accHistoryBody"></tbody></table></div>
    <div id="accHistoryEmpty" class="empty hidden">Belum ada invoice selesai.</div>
  </section>
</section>
@endsection
