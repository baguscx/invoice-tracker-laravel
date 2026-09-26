<div id="invoiceModal" class="modal-backdrop hidden">
  <div class="modal">
    <div class="modal-head"><div><h3 id="invoiceModalTitle">Terima Invoice Baru</h3><div id="invoiceModalSubtitle" class="muted" style="font-size:12px;margin-top:4px"></div></div><button id="closeInvoiceModalBtn" class="btn btn-soft icon-btn" type="button">×</button></div>
    <form id="invoiceForm">
      <div class="modal-body">
        <div id="currentStatusNote" class="status-note hidden"></div>
        <input id="invoiceId" type="hidden"><input id="invoiceMode" type="hidden" value="base">
        <div class="form-grid">
          <div class="field"><label>No Invoice *</label><input id="invoiceNo" required></div>
          <div class="field"><label>No PO *</label><input id="poNo" required></div>
          <div class="field full"><label>Supplier *</label><input id="supplier" required></div>
          <div class="field"><label>Tanggal Masuk</label><input id="receivedDate" type="date"></div>
          <div class="field"><label>Amount</label><input id="amount" type="number" min="0" step="1"></div>
          <div class="field"><label>No Tanda Terima</label><input id="receiptNo" readonly placeholder="Otomatis saat disimpan"><small class="muted">Dibuat otomatis: TT-YYYYMMDD-001.</small></div>
          <div class="field"><label>Jatuh Tempo</label><input id="dueDate" type="date"></div>
          <div class="field full" id="picField"><label>User / PIC Tujuan</label><select id="picUser"><option value="">Belum dipilih</option></select></div>
          <div class="field full" id="accountingPicField"><label>Accounting PIC Tujuan</label><select id="accountingPic"><option value="">Belum dipilih</option></select><small class="muted">Boleh dipilih sejak awal. Tugas baru muncul di dashboard Accounting setelah invoice diserahkan ke tahap Accounting.</small></div>
          <div class="field full" id="missingDocsField"><label>Kekurangan Dokumen</label><div id="documentChecks" class="check-grid"></div><small id="missingDocsHelp" class="muted">Centang dokumen yang masih kurang.</small></div>
          <div class="field full"><label>Keterangan / Catatan</label><textarea id="notes" placeholder="Catatan proses invoice..."></textarea></div>
        </div>
      </div>
      <div class="modal-actions"><button id="cancelInvoiceBtn" class="btn btn-soft" type="button">Batal</button><button id="savePrintInvoiceBtn" class="btn btn-soft hidden" type="submit">🖨 Simpan & Cetak</button><button id="saveInvoiceBtn" class="btn btn-primary" type="submit">Simpan</button></div>
    </form>
  </div>
</div>
<div id="detailModal" class="modal-backdrop hidden">
  <div class="modal large">
    <div class="modal-head"><div><h3 id="detailTitle">Detail Invoice</h3><div id="detailSubtitle" class="muted" style="font-size:12px;margin-top:4px"></div></div><button id="closeDetailBtn" class="btn btn-soft icon-btn" type="button">×</button></div>
    <div class="modal-body"><div id="detailContent"></div></div>
    <div class="modal-actions"><button id="closeDetailBottomBtn" class="btn btn-soft" type="button">Tutup</button></div>
  </div>
</div>

<div id="receiptModal" class="modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="receiptModalTitle">
  <div class="modal receipt-modal">
    <div class="modal-head">
      <div><h3 id="receiptModalTitle">Preview Tanda Terima Invoice</h3><div class="muted" style="font-size:12px;margin-top:4px">Format cetak: 1 lembar A4 portrait berisi 2 tanda terima identik — atas dan bawah.</div></div>
      <button id="closeReceiptModalBtn" class="btn btn-soft icon-btn" type="button">×</button>
    </div>
    <div class="modal-body receipt-preview-wrap">
      <div id="receiptPrintArea" class="receipt-sheet"></div>
    </div>
    <div class="modal-actions">
      <button id="closeReceiptBottomBtn" class="btn btn-soft" type="button">Tutup</button>
      <button id="printReceiptBtn" class="btn btn-primary" type="button">🖨 Cetak Tanda Terima</button>
    </div>
  </div>
</div>

<div id="actionDialog" class="modal-backdrop hidden" role="dialog" aria-modal="true" aria-labelledby="actionDialogTitle">
  <div class="modal dialog-modal">
    <div class="dialog-body">
      <div id="actionDialogIcon" class="dialog-icon">✓</div>
      <div>
        <h3 id="actionDialogTitle">Konfirmasi</h3>
        <p id="actionDialogMessage" class="muted"></p>
      </div>
      <div id="actionDialogSelectWrap" class="field full hidden">
        <label id="actionDialogSelectLabel">Pilih tujuan</label>
        <select id="actionDialogSelect"></select>
      </div>
      <div id="actionDialogNoteWrap" class="field full hidden">
        <label id="actionDialogNoteLabel">Catatan</label>
        <textarea id="actionDialogNote" placeholder="Tulis catatan..."></textarea>
        <small id="actionDialogNoteHelp" class="muted"></small>
      </div>
    </div>
    <div class="modal-actions">
      <button id="actionDialogCancel" class="btn btn-soft" type="button">Batal</button>
      <button id="actionDialogConfirm" class="btn btn-primary" type="button">Lanjutkan</button>
    </div>
  </div>
</div>
<div id="toast" class="toast hidden" role="status" aria-live="polite"></div>
