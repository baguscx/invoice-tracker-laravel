@extends('layouts.app')

@section('title', 'Resepsionis - Invoice Tracker')

@section('content')
<section id="receptionPanel" class="role-panel" data-role="RESEPSIONIS">
  <div class="hero">
    <div><div class="eyebrow">RESEPSIONIS WORKSPACE</div><h1>Meja Penerimaan Invoice</h1><p>Tugas utama: catat invoice → jika lengkap bisa langsung ke Accounting, jika perlu dilengkapi kirim ke User/PIC → terima kembali → Accounting.</p></div>
    <button class="btn btn-primary add-invoice-btn" type="button">+ Terima Invoice Baru</button>
  </div>

  <section class="summary">
    <div class="stat action"><span>Invoice Baru</span><strong id="recNewCount">0</strong><small>Perlu pilih PIC</small></div>
    <div class="stat overdue"><span>Serah Terima Masuk</span><strong id="recReturnCount">0</strong><small>Konfirmasi fisik / cek akhir</small></div>
    <div class="stat"><span>Sedang di User</span><strong id="recAtUserCount">0</strong><small>Sedang diproses PIC</small></div>
    <div class="stat done"><span>Sudah ke Accounting</span><strong id="recAtAccCount">0</strong><small>Monitoring</small></div>
  </section>
  <div class="workspace-grid two-col reception-lanes">
    <section class="workspace-card task-lane">
      <div class="section-head"><div><h2>1. Invoice Baru</h2><p>Jika dokumen lengkap, kirim langsung ke Accounting. Jika belum lengkap, teruskan ke User/PIC.</p></div>
        <input id="recNewSearch" class="inline-search" placeholder="Cari tanda terima / invoice / PO / supplier...">
      </div>
      <div id="recNewList" class="task-list"></div>
      <div id="recNewEmpty" class="empty compact-empty hidden">Tidak ada invoice baru.</div>
    </section>
    <section class="workspace-card task-lane">
      <div class="section-head"><div><h2>2. Invoice Kembali ke Resepsionis</h2><p>Dokumen dari User/PIC harus dikonfirmasi diterima secara fisik sebelum dianggap kembali. Setelah itu cek akhir dan teruskan ke Accounting.</p></div>
        <input id="recReturnSearch" class="inline-search" placeholder="Cari tanda terima / invoice / PO / supplier...">
      </div>
      <div id="recReturnList" class="task-list"></div>
      <div id="recReturnEmpty" class="empty compact-empty hidden">Belum ada invoice yang menunggu / sudah kembali ke Resepsionis.</div>
    </section>
  </div>

  <section class="workspace-card">
    <div class="section-head">
      <div><h2>Monitoring posisi invoice</h2><p>Read-only untuk invoice yang sedang berada di User/PIC atau Accounting.</p></div>
      <span id="recMonitorResultInfo" class="role-hint">0 invoice</span>
    </div>
    <div class="rec-monitor-filter-panel">
      <div class="rec-monitor-filter-top">
        <label class="monitor-filter-field monitor-search-field">
          <span>Cari invoice</span>
          <input id="recMonitorSearch" placeholder="No Tanda Terima, No Invoice, No PO, atau Supplier">
        </label>
        <label class="monitor-filter-field">
          <span>Urutkan</span>
          <select id="recMonitorSort" aria-label="Urutkan monitoring">
            <option value="received_desc">Terbaru</option>
            <option value="received_asc">Terlama</option>
            <option value="due_asc">Jatuh Tempo Terdekat</option>
            <option value="due_desc">Jatuh Tempo Terjauh</option>
            <option value="updated_desc">Terakhir Diperbarui</option>
            <option value="receipt_asc">No Tanda Terima A–Z</option>
            <option value="receipt_desc">No Tanda Terima Z–A</option>
            <option value="supplier_asc">Supplier A–Z</option>
            <option value="supplier_desc">Supplier Z–A</option>
          </select>
        </label>
        <div class="monitor-reset-wrap">
          <button id="recMonitorReset" class="btn btn-soft" type="button">Reset Filter</button>
        </div>
      </div>

      <div class="rec-monitor-filter-grid">
        <label class="monitor-filter-field">
          <span>Status</span>
          <select id="recMonitorStatus"><option value="">Semua status</option></select>
        </label>
        <label class="monitor-filter-field">
          <span>User / PIC</span>
          <select id="recMonitorPic"><option value="">Semua User/PIC</option></select>
        </label>
        <label class="monitor-filter-field">
          <span>Accounting PIC</span>
          <select id="recMonitorAccounting"><option value="">Semua Accounting</option></select>
        </label>
        <label class="monitor-filter-field">
          <span>Supplier</span>
          <select id="recMonitorSupplier"><option value="">Semua supplier</option></select>
        </label>
        <label class="monitor-filter-field">
          <span>Tanggal masuk dari</span>
          <input id="recMonitorDateFrom" type="date">
        </label>
        <label class="monitor-filter-field">
          <span>Tanggal masuk sampai</span>
          <input id="recMonitorDateTo" type="date">
        </label>
      </div>
    </div>
    <div class="table-scroll"><table class="invoice-table"><thead><tr><th>No Tanda Terima</th><th>Supplier</th><th>Posisi</th><th>User/PIC</th><th>Accounting PIC</th><th>Status</th><th>Jatuh Tempo</th><th></th></tr></thead><tbody id="recMonitorBody"></tbody></table></div>
    <div id="recMonitorEmpty" class="empty hidden">Tidak ada invoice sesuai filter.</div>
  </section>
</section>
@endsection
