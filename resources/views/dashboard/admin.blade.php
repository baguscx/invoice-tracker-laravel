@extends('layouts.app')

@section('title', 'Admin - Invoice Tracker')

@section('content')
<section id="adminPanel" class="role-panel" data-role="ADMIN">
  <div class="hero">
    <div><div class="eyebrow">ADMIN WORKSPACE</div><h1>Control Center</h1><p>Monitor seluruh alur, beban PIC, keterlambatan, user, dan jejak aktivitas.</p></div>
  </div>

  <section class="summary admin-summary">
    <div class="stat"><span>Total Invoice</span><strong id="adminTotal">0</strong><small>Semua data</small></div>
    <div class="stat action"><span>Masih Berjalan</span><strong id="adminActive">0</strong><small>Belum selesai</small></div>
    <div class="stat overdue"><span>Perlu Perhatian</span><strong id="adminOverdue">0</strong><small>Jatuh tempo</small></div>
    <div class="stat done"><span>Selesai</span><strong id="adminDone">0</strong><small>Proses tuntas</small></div>
  </section>
  <section class="workspace-card">
    <div class="section-head"><div><h2>Posisi seluruh invoice</h2><p>Klik tahap untuk memfilter tabel monitoring.</p></div></div>
    <div id="adminWorkflow" class="workflow-strip"></div>
  </section>

  <div class="workspace-grid two-col">
    <section class="workspace-card">
      <div class="section-head"><div><h2>Workload User / PIC</h2><p>Jumlah invoice yang masih berada di masing-masing PIC.</p></div></div>
      <div class="table-scroll compact"><table class="mini-table"><thead><tr><th>PIC</th><th>Menunggu</th><th>Dikerjakan</th><th>Total Aktif</th></tr></thead><tbody id="adminWorkloadBody"></tbody></table></div>
    </section>
    <section class="workspace-card">
      <div class="section-head"><div><h2>Workload Accounting PIC</h2><p>Jumlah invoice Accounting yang ditugaskan ke masing-masing staf.</p></div></div>
      <div class="table-scroll compact"><table class="mini-table"><thead><tr><th>Accounting</th><th>Menunggu</th><th>Diproses</th><th>Total Aktif</th></tr></thead><tbody id="adminAccountingWorkloadBody"></tbody></table></div>
    </section>
    <section class="workspace-card">
      <div class="section-head"><div><h2>Aktivitas terbaru</h2><p>Perpindahan invoice terakhir di seluruh role.</p></div><button id="adminRefreshActivity" class="btn btn-soft" type="button">Refresh</button></div>
      <div id="adminActivityList" class="activity-feed"></div>
    </section>
  </div>

  <section class="workspace-card">
    <div class="section-head"><div><h2>Monitoring semua invoice</h2><p>Admin bersifat monitoring; aksi workflow tetap tersedia bila diperlukan.</p></div></div>
    <div class="filters admin-filters">
      <input id="adminSearch" placeholder="Cari tanda terima, invoice, PO, supplier...">
      <select id="adminStatus"><option value="">Semua status</option></select>
      <select id="adminPic"><option value="">Semua User/PIC</option></select>
      <select id="adminAccounting"><option value="">Semua Accounting PIC</option></select>
      <select id="adminDue"><option value="">Semua jatuh tempo</option><option>Terlambat</option><option>Jatuh Tempo Hari Ini</option><option>Belum Jatuh Tempo</option><option>Belum Ada Jatuh Tempo</option><option>Selesai</option></select>
    </div>
    <div class="table-scroll"><table class="invoice-table"><thead><tr><th>No Tanda Terima</th><th>Supplier</th><th>Posisi</th><th>User/PIC</th><th>Accounting PIC</th><th>Dokumen</th><th>Jatuh Tempo</th><th>Aksi</th></tr></thead><tbody id="adminInvoiceBody"></tbody></table></div>
    <div id="adminInvoiceEmpty" class="empty hidden">Tidak ada invoice sesuai filter.</div>
  </section>

  <section class="workspace-card">
    <div class="section-head"><div><h2>Invoice Dibatalkan <span id="adminCancelledCount" class="badge red">0</span></h2><p>Audit pembatalan: siapa yang membatalkan, kapan, dan alasannya.</p></div></div>
    <div class="table-scroll"><table class="invoice-table"><thead><tr><th>No Tanda Terima</th><th>Supplier</th><th>Dibatalkan Oleh</th><th>Waktu</th><th>Alasan</th><th>Aksi</th></tr></thead><tbody id="adminCancelledBody"></tbody></table></div>
    <div id="adminCancelledEmpty" class="empty hidden">Belum ada invoice yang dibatalkan.</div>
  </section>

  <section class="workspace-card">
    <div class="section-head"><div><h2>Kelola User & Role</h2><p>Tambah akun baru atau ubah role/keaktifan akun.</p></div></div>
    <form id="adminUserForm" class="user-admin-form">
      <input id="adminUserUsername" placeholder="username" required minlength="3">
      <input id="adminUserName" placeholder="Nama">
      <select id="adminUserRole"><option value="RESEPSIONIS">Resepsionis</option><option value="USER">User / PIC</option><option value="ACCOUNTING">Accounting</option><option value="ADMIN">Admin</option></select>
      <input id="adminUserEmail" type="email" placeholder="Email (opsional)">
      <input id="adminUserPassword" type="password" placeholder="Password baru / kosong = tetap">
      <label class="switch-line"><input id="adminUserActive" type="checkbox" checked> Aktif</label>
      <button class="btn btn-primary" type="submit">Simpan User</button>
      <button id="adminUserReset" class="btn btn-soft" type="button">Reset</button>
    </form>
    <div class="table-scroll compact"><table class="mini-table"><thead><tr><th>Username</th><th>Nama</th><th>Role</th><th>Email</th><th>Status</th><th></th></tr></thead><tbody id="adminUsersBody"></tbody></table></div>
  </section>
</section>
@endsection
