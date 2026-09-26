const STATUS_FLOW = ['Baru / Diterima','Menunggu User','Sedang Diproses User','Menunggu Diterima Resepsionis','Kembali ke Resepsionis','Menunggu Accounting','Diproses Accounting','Selesai'];
const DOCS = [['GR','Goods Receipt'],['PO','Purchase Order'],['FP','Faktur Pajak'],['SJ','Surat Jalan'],['INV','Invoice'],['LAINNYA','Lainnya']];
const bootstrap = window.InvoiceTracker || {};
const BASE_URL = String(window.APP_BASE_URL || '').replace(/\/+$/, '');
let currentUser = bootstrap.user || null;
let invoices = Array.isArray(bootstrap.invoices) ? bootstrap.invoices : [];
let assignableUsers = Array.isArray(bootstrap.users) ? bootstrap.users : [];
let accountingUsers = Array.isArray(bootstrap.accountingUsers) ? bootstrap.accountingUsers : [];
let modalInvoice = null;
let adminUsers = [];
let adminActivity = [];
let adminCancelled = [];
const $ = id => document.getElementById(id);

function appUrl(path=''){
  const normalizedPath=String(path).replace(/^\/+/, '');
  if(!normalizedPath)return BASE_URL||'/';
  return `${BASE_URL}/${normalizedPath}`;
}

initDashboard();

function initDashboard(){
  applySavedTheme();
  renderDocumentChecks();
  bindDashboardEvents();
  populatePicOptions();
  populateAccountingPicOptions();
  populateAdminFilters();
  renderRoleWorkspace();
  if(currentUser?.role==='ADMIN') loadAdminExtras();
}

function bindDashboardEvents(){
  $('themeBtn')?.addEventListener('click',toggleTheme);
  document.querySelectorAll('.add-invoice-btn').forEach(btn=>btn.addEventListener('click',()=>openInvoiceModal()));
  document.querySelectorAll('.import-invoice-btn').forEach(btn=>btn.addEventListener('click',openImportModal));
  $('invoiceForm')?.addEventListener('submit',saveInvoice);
  $('importForm')?.addEventListener('submit',importInvoices);
  $('closeImportModalBtn')?.addEventListener('click',closeImportModal);
  $('cancelImportBtn')?.addEventListener('click',closeImportModal);
  $('closeInvoiceModalBtn')?.addEventListener('click',closeInvoiceModal);
  $('cancelInvoiceBtn')?.addEventListener('click',closeInvoiceModal);
  $('closeDetailBtn')?.addEventListener('click',closeDetail);
  $('closeDetailBottomBtn')?.addEventListener('click',closeDetail);
  $('closeReceiptModalBtn')?.addEventListener('click',closeReceiptPreview);
  $('closeReceiptBottomBtn')?.addEventListener('click',closeReceiptPreview);
  $('printReceiptBtn')?.addEventListener('click',printReceipt);
  $('invoiceModal')?.addEventListener('click',e=>{if(e.target.id==='invoiceModal')closeInvoiceModal()});
  $('importModal')?.addEventListener('click',e=>{if(e.target.id==='importModal')closeImportModal()});
  $('detailModal')?.addEventListener('click',e=>{if(e.target.id==='detailModal')closeDetail()});
  $('receiptModal')?.addEventListener('click',e=>{if(e.target.id==='receiptModal')closeReceiptPreview()});
  $('actionDialogCancel')?.addEventListener('click',()=>resolveActionDialog(null));
  $('actionDialogConfirm')?.addEventListener('click',confirmActionDialog);
  $('actionDialog')?.addEventListener('click',e=>{if(e.target.id==='actionDialog')resolveActionDialog(null)});
  $('dashboardPage')?.addEventListener('click',handleDashboardClick);

  ['adminSearch','adminStatus','adminPic','adminAccounting','adminDue','recNewSearch','recReturnSearch','recMonitorSearch','recMonitorStatus','recMonitorPic','recMonitorAccounting','recMonitorSupplier','recMonitorDateFrom','recMonitorDateTo','recMonitorSort','userInboxSearch','userActiveSearch','userHistorySearch','accWaitingSearch','accActiveSearch','accHistorySearch'].forEach(id=>{
    const el=$(id); if(!el)return;
    const eventName=el.tagName==='INPUT' && el.type!=='date'?'input':'change';
    el.addEventListener(eventName,renderRoleWorkspace);
  });
  $('recMonitorReset')?.addEventListener('click',resetReceptionMonitorFilters);
  $('adminRefreshActivity')?.addEventListener('click',loadAdminExtras);
  $('adminUserForm')?.addEventListener('submit',saveAdminUser);
  $('adminUserReset')?.addEventListener('click',resetAdminUserForm);
}

async function requestJson(url,{method='GET',body=null}={}){
  const options={method,credentials:'same-origin',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''}};
  if(body!==null){options.headers['Content-Type']='application/json';options.body=JSON.stringify(body)}
  const response=await fetch(url,options);
  return parseApiResponse(response);
}

async function requestForm(url,formData){
  const response=await fetch(url,{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},body:formData});
  return parseApiResponse(response);
}

async function parseApiResponse(response){
  const raw=await response.text();
  let data=null;
  if(raw){try{data=JSON.parse(raw)}catch(_){data=null}}

  if(response.ok&&data!==null)return data;
  if(response.ok&&!raw)return {};

  const status=response.status;
  const prefix=`HTTP ${status}`;
  const validationMessage=status===422&&data?.errors
    ? Object.values(data.errors).flat().filter(Boolean).join(' ')
    : '';
  const serverMessage=typeof data?.message==='string'?data.message.trim():'';
  const knownMessages={
    400:'Data permintaan tidak valid.',
    401:'Sesi login telah berakhir. Silakan login kembali.',
    403:'Anda tidak memiliki izin untuk melakukan aksi ini.',
    404:'Endpoint atau data yang diminta tidak ditemukan.',
    419:'Sesi atau token CSRF telah kedaluwarsa. Muat ulang halaman lalu coba lagi.',
    422:'Data yang dikirim tidak valid.',
    429:'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.',
    500:'Terjadi kesalahan pada server. Silakan coba lagi atau hubungi administrator.',
    502:'Server sementara tidak dapat dijangkau.',
    503:'Layanan sementara tidak tersedia.',
  };

  if(!response.ok){
    const safeMessage=status>=500?(knownMessages[status]||knownMessages[500]):(validationMessage||serverMessage||knownMessages[status]||'Permintaan gagal diproses server.');
    throw new Error(`${prefix}: ${safeMessage}`);
  }

  if(response.redirected)throw new Error('Respons server bukan JSON. Sesi login mungkin telah berakhir; silakan muat ulang halaman dan login kembali.');
  throw new Error(`HTTP ${status}: Respons server bukan JSON. Periksa URL aplikasi dan konfigurasi server.`);
}

function renderRoleWorkspace(){
  if(!currentUser)return;
  if(currentUser.role==='ADMIN')renderAdmin();
  if(currentUser.role==='RESEPSIONIS')renderReception();
  if(currentUser.role==='USER')renderUserPic();
  if(currentUser.role==='ACCOUNTING')renderAccounting();
}

function handleDashboardClick(e){
  const btn=e.target.closest('button[data-act]'); if(!btn)return;
  const act=btn.dataset.act,id=btn.dataset.id||'';
  if(act==='detail')openDetail(id);
  if(act==='print-receipt')openReceiptPreviewById(id);
  if(act==='edit')openInvoiceModalById(id);
  if(act==='work-user')openWorkModalById(id,'work-user');
  if(act==='work-accounting')openWorkModalById(id,'work-accounting');
  if(act==='transition')runTransition(id,btn.dataset.transition||'');
  if(act==='send-accounting'){
    const select=$(`acc-pick-${id}`);
    const target=select?.value||'';
    if(!target){toast('Pilih Accounting Tujuan terlebih dahulu.','warning');return;}
    runTransition(id,'SEND_TO_ACCOUNTING',target);
  }
  if(act==='direct-accounting'){
    const select=$(`acc-direct-${id}`);
    const target=select?.value||'';
    if(!target){toast('Pilih Accounting Tujuan terlebih dahulu.','warning');return;}
    runTransition(id,'DIRECT_TO_ACCOUNTING',target);
  }
  if(act==='delete')removeInvoice(id);
  if(act==='admin-stage'){const s=btn.dataset.status||'';if($('adminStatus')){$('adminStatus').value=s;renderAdmin();}}
  if(act==='edit-user')editAdminUser(btn.dataset.username||'');
}

function renderAdmin(){
  $('adminTotal').textContent=invoices.length;
  $('adminActive').textContent=invoices.filter(x=>!['Selesai','Batal'].includes(x.status)).length;
  $('adminOverdue').textContent=invoices.filter(isUrgent).length;
  $('adminDone').textContent=invoices.filter(x=>x.status==='Selesai').length;
  $('adminWorkflow').innerHTML=STATUS_FLOW.map((s,i)=>`<button class="workflow-step" type="button" data-act="admin-stage" data-status="${escapeAttr(s)}"><span class="num">${i+1}</span><strong>${invoices.filter(x=>x.status===s).length}</strong><small>${escapeHtml(s)}</small></button>`).join('');

  const activeByPic={};
  invoices.filter(x=>['Menunggu User','Sedang Diproses User'].includes(x.status)).forEach(x=>{
    const k=x.picUser||'(belum dipilih)';activeByPic[k]??={waiting:0,active:0};if(x.status==='Menunggu User')activeByPic[k].waiting++;else activeByPic[k].active++;
  });
  const workload=Object.entries(activeByPic).sort((a,b)=>(b[1].waiting+b[1].active)-(a[1].waiting+a[1].active));
  $('adminWorkloadBody').innerHTML=workload.length?workload.map(([pic,v])=>`<tr><td><strong>${escapeHtml(pic)}</strong></td><td>${v.waiting}</td><td>${v.active}</td><td><strong>${v.waiting+v.active}</strong></td></tr>`).join(''):'<tr><td colspan="4" class="muted">Tidak ada workload User/PIC aktif.</td></tr>';

  const activeByAccounting={};
  invoices.filter(x=>['Menunggu Accounting','Diproses Accounting'].includes(x.status)).forEach(x=>{
    const k=x.accountingPic||'(belum dipilih)';activeByAccounting[k]??={waiting:0,active:0};if(x.status==='Menunggu Accounting')activeByAccounting[k].waiting++;else activeByAccounting[k].active++;
  });
  const accountingWorkload=Object.entries(activeByAccounting).sort((a,b)=>(b[1].waiting+b[1].active)-(a[1].waiting+a[1].active));
  if($('adminAccountingWorkloadBody')) $('adminAccountingWorkloadBody').innerHTML=accountingWorkload.length?accountingWorkload.map(([pic,v])=>`<tr><td><strong>${escapeHtml(pic)}</strong></td><td>${v.waiting}</td><td>${v.active}</td><td><strong>${v.waiting+v.active}</strong></td></tr>`).join(''):'<tr><td colspan="4" class="muted">Tidak ada workload Accounting aktif.</td></tr>';

  const q=normalizeKey($('adminSearch')?.value||''),st=$('adminStatus')?.value||'',pic=$('adminPic')?.value||'',acc=$('adminAccounting')?.value||'',due=$('adminDue')?.value||'';
  const rows=invoices.filter(x=>(!q||invoiceHaystack(x).includes(q))&&(!st||x.status===st)&&(!pic||x.picUser===pic)&&(!acc||x.accountingPic===acc)&&(!due||x.dueStatus===due));
  $('adminInvoiceBody').innerHTML=rows.map(inv=>invoiceRowAdmin(inv)).join('');$('adminInvoiceEmpty').classList.toggle('hidden',rows.length>0);
  renderAdminUsers();renderAdminActivity();
}

function renderReception(){
  const newAll=invoices.filter(x=>x.status==='Baru / Diterima');
  const returnAll=invoices.filter(x=>['Menunggu Diterima Resepsionis','Kembali ke Resepsionis'].includes(x.status));
  const atUser=invoices.filter(x=>['Menunggu User','Sedang Diproses User','Menunggu Diterima Resepsionis'].includes(x.status));
  const atAcc=invoices.filter(x=>['Menunggu Accounting','Diproses Accounting','Selesai'].includes(x.status));
  $('recNewCount').textContent=newAll.length;$('recReturnCount').textContent=returnAll.length;$('recAtUserCount').textContent=atUser.length;$('recAtAccCount').textContent=atAcc.length;

  const newQ=normalizeKey($('recNewSearch')?.value||'');
  const returnQ=normalizeKey($('recReturnSearch')?.value||'');
  const newRows=newAll.filter(x=>!newQ||invoiceHaystack(x).includes(newQ));
  const returned=returnAll.filter(x=>!returnQ||invoiceHaystack(x).includes(returnQ));
  $('recNewList').innerHTML=newRows.map(inv=>taskCard(inv,'reception-new')).join('');
  $('recNewEmpty').textContent=newAll.length&&!newRows.length?'Tidak ada invoice sesuai pencarian.':'Tidak ada invoice baru.';
  $('recNewEmpty').classList.toggle('hidden',newRows.length>0);
  $('recReturnList').innerHTML=returned.map(inv=>taskCard(inv,inv.status==='Menunggu Diterima Resepsionis'?'reception-awaiting-receipt':'reception-return')).join('');
  $('recReturnEmpty').textContent=returnAll.length&&!returned.length?'Tidak ada invoice sesuai pencarian.':'Belum ada invoice yang menunggu / sudah kembali ke Resepsionis.';
  $('recReturnEmpty').classList.toggle('hidden',returned.length>0);

  const monitorBase=invoices.filter(x=>!['Baru / Diterima','Menunggu Diterima Resepsionis','Kembali ke Resepsionis','Batal'].includes(x.status));
  populateReceptionMonitorFilters(monitorBase);

  const q=normalizeKey($('recMonitorSearch')?.value||'');
  const status=$('recMonitorStatus')?.value||'';
  const pic=$('recMonitorPic')?.value||'';
  const accounting=$('recMonitorAccounting')?.value||'';
  const supplier=$('recMonitorSupplier')?.value||'';
  let dateFrom=$('recMonitorDateFrom')?.value||'';
  let dateTo=$('recMonitorDateTo')?.value||'';
  if(dateFrom&&dateTo&&dateFrom>dateTo)[dateFrom,dateTo]=[dateTo,dateFrom];

  const sortBy=$('recMonitorSort')?.value||'received_desc';
  const monitor=monitorBase.filter(inv=>{
    if(q&&!invoiceHaystack(inv).includes(q))return false;
    if(status&&inv.status!==status)return false;
    if(pic&&inv.picUser!==pic)return false;
    if(accounting&&inv.accountingPic!==accounting)return false;
    if(supplier&&inv.supplier!==supplier)return false;
    const received=normalizeDateOnly(inv.receivedDate);
    if(dateFrom&&(!received||received<dateFrom))return false;
    if(dateTo&&(!received||received>dateTo))return false;
    return true;
  }).sort((a,b)=>compareReceptionMonitor(a,b,sortBy));

  $('recMonitorBody').innerHTML=monitor.map(inv=>`<tr><td>${invoiceIdentity(inv)}</td><td>${escapeHtml(inv.supplier)}</td><td>${escapeHtml(inv.position||'-')}</td><td>${escapeHtml(inv.picUser||'-')}</td><td>${escapeHtml(inv.accountingPic||'-')}</td><td>${statusBadge(inv.status)}</td><td>${dueBadge(inv.dueStatus)}<div class="stage-time">${formatDate(inv.dueDate)}</div></td><td><button class="btn btn-soft" type="button" data-act="detail" data-id="${escapeAttr(inv.id)}">Detail</button></td></tr>`).join('');
  $('recMonitorEmpty').classList.toggle('hidden',monitor.length>0);
  if($('recMonitorResultInfo'))$('recMonitorResultInfo').textContent=`${monitor.length} dari ${monitorBase.length} invoice`;
}

function populateReceptionMonitorFilters(rows){
  syncFilterSelect('recMonitorStatus','Semua status',[...new Set(rows.map(x=>x.status).filter(Boolean))]);
  syncFilterSelect('recMonitorPic','Semua User/PIC',[...new Set(rows.map(x=>x.picUser).filter(Boolean))]);
  syncFilterSelect('recMonitorAccounting','Semua Accounting',[...new Set(rows.map(x=>x.accountingPic).filter(Boolean))]);
  syncFilterSelect('recMonitorSupplier','Semua supplier',[...new Set(rows.map(x=>x.supplier).filter(Boolean))]);
}

function compareReceptionMonitor(a,b,sortBy){
  const textCompare=(left,right)=>String(left||'').localeCompare(String(right||''),'id',{sensitivity:'base',numeric:true});
  const dateCompare=(left,right,direction)=>{
    const l=String(left||'').trim();
    const r=String(right||'').trim();
    if(!l&&!r)return 0;
    if(!l)return 1;
    if(!r)return -1;
    return l===r?0:(l<r?-1:1)*direction;
  };

  let result=0;
  switch(sortBy){
    case 'received_asc':
      result=dateCompare(normalizeDateOnly(a.receivedDate),normalizeDateOnly(b.receivedDate),1);
      break;
    case 'due_asc':
      result=dateCompare(normalizeDateOnly(a.dueDate),normalizeDateOnly(b.dueDate),1);
      break;
    case 'due_desc':
      result=dateCompare(normalizeDateOnly(a.dueDate),normalizeDateOnly(b.dueDate),-1);
      break;
    case 'updated_desc':
      result=dateCompare(a.updatedAt,b.updatedAt,-1);
      break;
    case 'receipt_asc':
      result=textCompare(a.receiptNo,b.receiptNo);
      break;
    case 'receipt_desc':
      result=-textCompare(a.receiptNo,b.receiptNo);
      break;
    case 'supplier_asc':
      result=textCompare(a.supplier,b.supplier);
      break;
    case 'supplier_desc':
      result=-textCompare(a.supplier,b.supplier);
      break;
    case 'received_desc':
    default:
      result=dateCompare(normalizeDateOnly(a.receivedDate),normalizeDateOnly(b.receivedDate),-1);
      break;
  }

  if(result===0)result=dateCompare(a.updatedAt,b.updatedAt,-1);
  if(result===0)result=textCompare(a.receiptNo,b.receiptNo);
  return result;
}

function syncFilterSelect(id,allLabel,values){
  const el=$(id);if(!el)return;
  const current=el.value;
  const sorted=values.slice().sort((a,b)=>String(a).localeCompare(String(b),'id',{sensitivity:'base',numeric:true}));
  el.innerHTML=`<option value="">${escapeHtml(allLabel)}</option>`+sorted.map(v=>`<option value="${escapeAttr(v)}">${escapeHtml(v)}</option>`).join('');
  if(sorted.includes(current))el.value=current;
}

function resetReceptionMonitorFilters(){
  ['recMonitorSearch','recMonitorStatus','recMonitorPic','recMonitorAccounting','recMonitorSupplier','recMonitorDateFrom','recMonitorDateTo'].forEach(id=>{if($(id))$(id).value=''});
  if($('recMonitorSort'))$('recMonitorSort').value='received_desc';
  renderReception();
}

function renderUserPic(){
  const inboxAll=invoices.filter(x=>x.status==='Menunggu User');
  const activeAll=invoices.filter(x=>x.status==='Sedang Diproses User');
  const returned=invoices.filter(x=>['Menunggu Diterima Resepsionis','Kembali ke Resepsionis'].includes(x.status));
  const done=invoices.filter(x=>x.status==='Selesai');
  $('userInboxCount').textContent=inboxAll.length;$('userActiveCount').textContent=activeAll.length;$('userReturnedCount').textContent=returned.length;$('userDoneCount').textContent=done.length;

  const inboxQ=normalizeKey($('userInboxSearch')?.value||'');
  const activeQ=normalizeKey($('userActiveSearch')?.value||'');
  const historyQ=normalizeKey($('userHistorySearch')?.value||'');
  const inbox=inboxAll.filter(x=>!inboxQ||invoiceHaystack(x).includes(inboxQ));
  const active=activeAll.filter(x=>!activeQ||invoiceHaystack(x).includes(activeQ));
  $('userInboxList').innerHTML=inbox.map(inv=>taskCard(inv,'user-inbox')).join('');
  $('userInboxEmpty').textContent=inboxAll.length&&!inbox.length?'Tidak ada tugas sesuai pencarian.':'Tidak ada tugas baru.';
  $('userInboxEmpty').classList.toggle('hidden',inbox.length>0);
  $('userActiveList').innerHTML=active.map(inv=>taskCard(inv,'user-active')).join('');
  $('userActiveEmpty').textContent=activeAll.length&&!active.length?'Tidak ada invoice sesuai pencarian.':'Tidak ada invoice yang sedang dikerjakan.';
  $('userActiveEmpty').classList.toggle('hidden',active.length>0);
  const histAll=invoices.filter(x=>!['Menunggu User','Sedang Diproses User'].includes(x.status));
  const hist=histAll.filter(x=>!historyQ||invoiceHaystack(x).includes(historyQ));
  $('userHistoryBody').innerHTML=hist.map(inv=>`<tr><td>${invoiceIdentity(inv)}</td><td>${escapeHtml(inv.supplier)}</td><td>${statusBadge(inv.status)}</td><td>${escapeHtml(inv.position||'-')}</td><td>${dueBadge(inv.dueStatus)}<div class="stage-time">${formatDate(inv.dueDate)}</div></td><td>${docsHtml(inv.missingDocuments)}</td><td><button class="btn btn-soft" type="button" data-act="detail" data-id="${escapeAttr(inv.id)}">Detail</button></td></tr>`).join('');
  $('userHistoryEmpty').textContent=histAll.length&&!hist.length?'Tidak ada riwayat sesuai pencarian.':'Belum ada riwayat.';
  $('userHistoryEmpty').classList.toggle('hidden',hist.length>0);
}

function renderAccounting(){
  const waitingAll=invoices.filter(x=>x.status==='Menunggu Accounting');
  const activeAll=invoices.filter(x=>x.status==='Diproses Accounting');
  const urgent=invoices.filter(x=>['Menunggu Accounting','Diproses Accounting'].includes(x.status)&&isUrgent(x));
  const done=invoices.filter(x=>x.status==='Selesai');
  $('accWaitingCount').textContent=waitingAll.length;$('accActiveCount').textContent=activeAll.length;$('accUrgentCount').textContent=urgent.length;$('accDoneCount').textContent=done.length;
  $('accUrgentSection').classList.toggle('hidden',!urgent.length);$('accUrgentList').innerHTML=urgent.map(inv=>taskCard(inv,'accounting-urgent')).join('');
  const waitingQ=normalizeKey($('accWaitingSearch')?.value||'');
  const activeQ=normalizeKey($('accActiveSearch')?.value||'');
  const waiting=waitingAll.filter(x=>!waitingQ||invoiceHaystack(x).includes(waitingQ));
  const active=activeAll.filter(x=>!activeQ||invoiceHaystack(x).includes(activeQ));
  $('accWaitingList').innerHTML=waiting.map(inv=>taskCard(inv,'accounting-waiting')).join('');
  $('accWaitingEmpty').textContent=waitingAll.length&&!waiting.length?'Tidak ada invoice sesuai pencarian.':'Tidak ada invoice menunggu Accounting.';
  $('accWaitingEmpty').classList.toggle('hidden',waiting.length>0);
  $('accActiveList').innerHTML=active.map(inv=>taskCard(inv,'accounting-active')).join('');
  $('accActiveEmpty').textContent=activeAll.length&&!active.length?'Tidak ada invoice sesuai pencarian.':'Tidak ada invoice yang sedang diproses.';
  $('accActiveEmpty').classList.toggle('hidden',active.length>0);
  const q=normalizeKey($('accHistorySearch')?.value||'');const hist=done.filter(x=>!q||invoiceHaystack(x).includes(q));
  $('accHistoryBody').innerHTML=hist.map(inv=>`<tr><td>${invoiceIdentity(inv)}</td><td>${escapeHtml(inv.supplier)}</td><td class="money">${formatRupiah(inv.amount)}</td><td>${formatDate(inv.dueDate)}</td><td>${formatDateTime(inv.completedAt)}</td><td><button class="btn btn-soft" type="button" data-act="detail" data-id="${escapeAttr(inv.id)}">Detail</button></td></tr>`).join('');
  $('accHistoryEmpty').classList.toggle('hidden',hist.length>0);
}

function taskCard(inv,kind){
  const urgent=isUrgent(inv);let actions='';let helper='';
  if(kind==='reception-new'){
    const miss=parseDocs(inv.missingDocuments);
    const accOptions=accountingUsers.map(u=>`<option value="${escapeAttr(u.username)}" ${inv.accountingPic===u.username?'selected':''}>${escapeHtml(u.name)} (${escapeHtml(u.username)})</option>`).join('');
    helper=miss.length
      ? `Dokumen belum lengkap (${escapeHtml(miss.join(', '))}). Kirim ke User/PIC untuk dilengkapi.`
      : 'Dokumen lengkap. Bisa langsung dikirim ke Accounting atau tetap melalui User/PIC bila perlu pengecekan.';
    actions=`<button class="btn btn-soft" type="button" data-act="print-receipt" data-id="${escapeAttr(inv.id)}">🖨 Cetak Tanda Terima</button><button class="btn btn-soft" type="button" data-act="edit" data-id="${escapeAttr(inv.id)}">Edit / Tentukan Tujuan</button><button class="btn btn-danger" type="button" data-act="transition" data-transition="CANCEL" data-id="${escapeAttr(inv.id)}">Batal</button>`+
      `<button class="btn btn-primary" type="button" data-act="transition" data-transition="SEND_TO_USER" data-id="${escapeAttr(inv.id)}" ${inv.picUser?'':'disabled title="Pilih User/PIC dulu"'}>Kirim ke User</button>`+
      (!miss.length?`<select id="acc-direct-${escapeAttr(inv.id)}" class="task-select" aria-label="Accounting tujuan"><option value="">Pilih Accounting Tujuan</option>${accOptions}</select><button class="btn btn-purple" type="button" data-act="direct-accounting" data-id="${escapeAttr(inv.id)}">Langsung ke Accounting</button>`:'');
  }else if(kind==='reception-awaiting-receipt'){
    helper=`User/PIC ${escapeHtml(inv.picUser||'-')} sudah menyatakan dokumen diserahkan. Konfirmasi hanya setelah dokumen fisik benar-benar diterima.`;
    actions=`<button class="btn btn-soft" type="button" data-act="detail" data-id="${escapeAttr(inv.id)}">Detail</button><button class="btn btn-success" type="button" data-act="transition" data-transition="CONFIRM_RECEPTION_RECEIPT" data-id="${escapeAttr(inv.id)}">Konfirmasi Terima Dokumen</button>`;
  }else if(kind==='reception-return'){
    const options=accountingUsers.map(u=>`<option value="${escapeAttr(u.username)}" ${inv.accountingPic===u.username?'selected':''}>${escapeHtml(u.name)} (${escapeHtml(u.username)})</option>`).join('');
    const last=lastNote(inv.notes);
    helper=(parseDocs(inv.missingDocuments).length?'Masih ada kekurangan dokumen':'Dokumen lengkap — pilih staf Accounting tujuan')+(last?` · Catatan terakhir: ${escapeHtml(last)}`:'');
    actions=`<button class="btn btn-soft" type="button" data-act="edit" data-id="${escapeAttr(inv.id)}">Cek Akhir</button><button class="btn btn-danger" type="button" data-act="transition" data-transition="CANCEL" data-id="${escapeAttr(inv.id)}">Batal</button><select id="acc-pick-${escapeAttr(inv.id)}" class="task-select" aria-label="Accounting PIC tujuan"><option value="">Pilih Accounting PIC</option>${options}</select><button class="btn btn-purple" type="button" data-act="send-accounting" data-id="${escapeAttr(inv.id)}">Kirim ke Accounting PIC</button>`;
  }else if(kind==='user-inbox'){
    helper='Tugas baru dari Resepsionis';
    actions=`<button class="btn btn-soft" type="button" data-act="detail" data-id="${escapeAttr(inv.id)}">Detail</button><button class="btn btn-primary" type="button" data-act="transition" data-transition="START_USER_PROCESS" data-id="${escapeAttr(inv.id)}">Mulai Kerjakan</button>`;
  }else if(kind==='user-active'){
    const miss=parseDocs(inv.missingDocuments);helper=miss.length?`Kurang: ${escapeHtml(miss.join(', '))}`:'Dokumen sudah lengkap';
    actions=`<button class="btn btn-warning" type="button" data-act="work-user" data-id="${escapeAttr(inv.id)}">Checklist Dokumen</button><button class="btn btn-success" type="button" data-act="transition" data-transition="RETURN_TO_RECEPTION" data-id="${escapeAttr(inv.id)}" ${miss.length?'disabled title="Lengkapi dokumen dulu"':''}>Serahkan ke Resepsionis</button>`;
  }else if(kind==='accounting-waiting'||kind==='accounting-urgent'){
    helper=`Tugas Accounting: ${escapeHtml(inv.accountingPic||currentUser.username||'-')}`;
    actions=`<button class="btn btn-soft" type="button" data-act="detail" data-id="${escapeAttr(inv.id)}">Detail</button><button class="btn btn-success" type="button" data-act="transition" data-transition="START_ACCOUNTING" data-id="${escapeAttr(inv.id)}">Mulai Verifikasi</button><button class="btn btn-danger" type="button" data-act="transition" data-transition="RETURN_ACCOUNTING_TO_RECEPTION" data-id="${escapeAttr(inv.id)}">Kembalikan</button>`;
  }else if(kind==='accounting-active'){
    helper=`Sedang dikerjakan oleh ${escapeHtml(inv.accountingPic||currentUser.username||'-')}`;
    actions=`<button class="btn btn-soft" type="button" data-act="work-accounting" data-id="${escapeAttr(inv.id)}">Update Progres</button><button class="btn btn-danger" type="button" data-act="transition" data-transition="RETURN_ACCOUNTING_TO_RECEPTION" data-id="${escapeAttr(inv.id)}">Kembalikan ke Resepsionis</button><button class="btn btn-success" type="button" data-act="transition" data-transition="COMPLETE" data-id="${escapeAttr(inv.id)}">Selesaikan</button>`;
  }
  return `<article class="task-card ${urgent?'urgent':''}"><div class="task-head"><div><h3>${escapeHtml(primaryNo(inv))}</h3><div class="stage-time table-secondary-text" title="${escapeAttr('Invoice: '+(inv.invoiceNo||'-'))}">Invoice: ${escapeHtml(inv.invoiceNo||'-')}</div><div class="task-meta">${escapeHtml(inv.supplier||'-')} · ${formatRupiah(inv.amount)}</div></div>${statusBadge(inv.status)}</div><div class="task-info"><div><small>Jatuh tempo</small><strong>${formatDate(inv.dueDate)} · ${escapeHtml(inv.dueStatus||'-')}</strong></div></div><div class="muted" style="font-size:11px">${helper}</div><div class="task-actions">${actions}</div></article>`;
}

function invoiceRowAdmin(inv){
  const w=workflowAction(inv);return `<tr><td>${invoiceIdentity(inv)}</td><td>${escapeHtml(inv.supplier)}</td><td><span class="position">${escapeHtml(inv.position||'-')}</span><div class="stage-time">${statusBadge(inv.status)}</div></td><td>${escapeHtml(inv.picUser||'-')}</td><td>${escapeHtml(inv.accountingPic||'-')}</td><td>${docsHtml(inv.missingDocuments)}</td><td>${dueBadge(inv.dueStatus)}<div class="stage-time">${formatDate(inv.dueDate)}</div></td><td><div class="actions"><button class="btn btn-soft" type="button" data-act="detail" data-id="${escapeAttr(inv.id)}">Detail</button><button class="btn btn-soft" type="button" data-act="edit" data-id="${escapeAttr(inv.id)}">Edit</button>${w?`<button class="btn ${w.cls}" type="button" data-act="transition" data-transition="${w.action}" data-id="${escapeAttr(inv.id)}">${escapeHtml(w.label)}</button>`:''}<button class="btn btn-danger" type="button" data-act="delete" data-id="${escapeAttr(inv.id)}">Hapus</button></div></td></tr>`;
}
function primaryNo(inv){return inv?.receiptNo||inv?.invoiceNo||'-'}
function invoiceIdentity(inv){return `<strong>${escapeHtml(primaryNo(inv))}</strong><div class="stage-time table-secondary-text" title="${escapeAttr('Invoice: '+(inv.invoiceNo||'-'))}">Invoice: ${escapeHtml(inv.invoiceNo||'-')}</div>`}
function invoiceHaystack(inv){return normalizeKey([inv.receiptNo,inv.invoiceNo,inv.poNo,inv.supplier,inv.picUser,inv.accountingPic,inv.status].join(' '))}
function normalizeDateOnly(value){const m=String(value||'').match(/^(\d{4})-(\d{2})-(\d{2})/);return m?`${m[1]}-${m[2]}-${m[3]}`:''}
function isUrgent(inv){return !['Selesai','Batal'].includes(inv.status)&&(inv.dueStatus==='Terlambat'||inv.dueStatus==='Jatuh Tempo Hari Ini')}

function workflowAction(inv){
  const role=currentUser.role;
  if(inv.status==='Baru / Diterima'&&['ADMIN','RESEPSIONIS'].includes(role))return{action:'SEND_TO_USER',label:'Kirim User',cls:'btn-primary'};
  if(inv.status==='Menunggu User'&&['ADMIN','USER'].includes(role))return{action:'START_USER_PROCESS',label:'Mulai User',cls:'btn-warning'};
  if(inv.status==='Sedang Diproses User'&&['ADMIN','USER'].includes(role))return{action:'RETURN_TO_RECEPTION',label:'Serahkan ke Resepsionis',cls:'btn-warning'};
  if(inv.status==='Menunggu Diterima Resepsionis'&&['ADMIN','RESEPSIONIS'].includes(role))return{action:'CONFIRM_RECEPTION_RECEIPT',label:'Konfirmasi Diterima',cls:'btn-success'};
  if(inv.status==='Kembali ke Resepsionis'&&['ADMIN','RESEPSIONIS'].includes(role))return{action:'SEND_TO_ACCOUNTING',label:'Kirim Accounting',cls:'btn-purple'};
  if(inv.status==='Menunggu Accounting'&&['ADMIN','ACCOUNTING'].includes(role))return{action:'START_ACCOUNTING',label:'Mulai Accounting',cls:'btn-success'};
  if(inv.status==='Diproses Accounting'&&['ADMIN','ACCOUNTING'].includes(role))return{action:'COMPLETE',label:'Selesaikan',cls:'btn-success'};
  return null;
}

async function runTransition(id,action,targetUsername=''){
  const inv=invoices.find(x=>x.id===id);if(!inv)return;
  let note='';
  if(action==='SEND_TO_ACCOUNTING'||action==='DIRECT_TO_ACCOUNTING'){
    if(!targetUsername){
      const choice=await showActionDialog({
        tone:'info',icon:'👤',title:'Pilih Accounting Tujuan',message:`Pilih staf Accounting untuk ${primaryNo(inv)}.`,
        confirmText:'Pilih',selectLabel:'Accounting Tujuan',selectValue:inv.accountingPic||'',
        selectOptions:accountingUsers.map(u=>({value:u.username,label:`${u.name} (${u.username})`}))
      });
      if(!choice)return;targetUsername=choice.value||'';
    }
    if(!targetUsername){toast('Pilih Accounting Tujuan.','warning');return;}
  }

  const dialogs={
    SEND_TO_USER:{tone:'info',icon:'→',title:'Kirim ke User/PIC',message:`Serahkan ${primaryNo(inv)} ke ${inv.picUser||'User/PIC yang dipilih'}?`,confirmText:'Kirim ke User'},
    DIRECT_TO_ACCOUNTING:{tone:'success',icon:'✓',title:'Langsung ke Accounting',message:`Dokumen ${primaryNo(inv)} sudah lengkap. Lewati tahap User/PIC dan kirim langsung ke Accounting ${targetUsername}?`,confirmText:'Kirim ke Accounting'},
    START_USER_PROCESS:{tone:'info',icon:'▶',title:'Mulai Pengerjaan',message:`Mulai memeriksa dan melengkapi ${primaryNo(inv)}?`,confirmText:'Mulai'},
    RETURN_TO_RECEPTION:{tone:'success',icon:'↩',title:'Serahkan ke Resepsionis',message:`Pastikan dokumen ${primaryNo(inv)} sudah lengkap. Setelah kamu klik Serahkan, status menjadi Menunggu Diterima Resepsionis sampai Resepsionis mengonfirmasi dokumen fisik benar-benar diterima.`,confirmText:'Serahkan'},
    CONFIRM_RECEPTION_RECEIPT:{tone:'success',icon:'✓',title:'Konfirmasi Terima Dokumen',message:`Konfirmasi hanya jika dokumen fisik ${primaryNo(inv)} benar-benar sudah diterima di meja Resepsionis.`,confirmText:'Ya, Dokumen Diterima'},
    SEND_TO_ACCOUNTING:{tone:'success',icon:'→',title:'Kirim ke Accounting',message:`Kirim ${primaryNo(inv)} ke Accounting ${targetUsername}?`,confirmText:'Kirim ke Accounting'},
    START_ACCOUNTING:{tone:'info',icon:'▶',title:'Mulai Verifikasi',message:`Mulai verifikasi dan proses Accounting untuk ${primaryNo(inv)}?`,confirmText:'Mulai Verifikasi'},
    RETURN_ACCOUNTING_TO_RECEPTION:{tone:'danger',icon:'↩',title:'Kembalikan ke Resepsionis',message:`Ada data atau dokumen ${primaryNo(inv)} yang perlu dikoreksi. Jelaskan alasannya agar Resepsionis tahu yang harus diperbaiki.`,confirmText:'Kembalikan',noteLabel:'Catatan koreksi *',notePlaceholder:'Contoh: Nomor PO tidak sesuai, mohon koreksi lalu kirim kembali.',noteRequired:true},
    COMPLETE:{tone:'success',icon:'✓',title:'Selesaikan Invoice',message:`Tandai ${primaryNo(inv)} sebagai selesai?`,confirmText:'Ya, Selesaikan'},
    CANCEL:{tone:'danger',icon:'×',title:'Batalkan Invoice',message:`Batalkan ${primaryNo(inv)}? Data tidak akan dihapus dan pembatalan tetap tercatat di riwayat.`,confirmText:'Ya, Batalkan',noteLabel:'Alasan pembatalan *',notePlaceholder:'Contoh: invoice duplikat, salah input, atau dibatalkan supplier.',noteRequired:true}
  };
  const cfg=dialogs[action]||{tone:'info',icon:'?',title:'Konfirmasi',message:'Lanjutkan aksi ini?',confirmText:'Lanjutkan'};
  const result=await showActionDialog(cfg);
  if(!result)return;
  note=result.note||'';
  try{
    const res=await requestJson(appUrl(`invoices/${encodeURIComponent(id)}/transition`),{method:'PATCH',body:{action,note,targetUsername}});
    replaceInvoice(res.invoice);renderRoleWorkspace();if(currentUser.role==='ADMIN')loadAdminExtras();
    const successMsg=action==='RETURN_TO_RECEPTION'?'Dokumen ditandai sudah diserahkan. Menunggu konfirmasi penerimaan dari Resepsionis.':action==='CONFIRM_RECEPTION_RECEIPT'?'Dokumen fisik dikonfirmasi sudah diterima Resepsionis.':action==='RETURN_ACCOUNTING_TO_RECEPTION'?'Invoice dikembalikan ke Resepsionis beserta catatan koreksi.':action==='DIRECT_TO_ACCOUNTING'?'Invoice langsung dikirim ke Accounting.':action==='CANCEL'?'Invoice berhasil dibatalkan dan riwayatnya tetap tersimpan.':'Status berhasil diperbarui.';
    toast(successMsg,'success');
  }catch(err){toast(err.message,'error')}
}

function populatePicOptions(){const s=$('picUser');if(!s)return;s.innerHTML='<option value="">Belum dipilih</option>'+assignableUsers.map(u=>`<option value="${escapeAttr(u.username)}">${escapeHtml(u.name)} (${escapeHtml(u.username)})</option>`).join('')}
function populateAccountingPicOptions(){const s=$('accountingPic');if(!s)return;s.innerHTML='<option value="">Belum dipilih</option>'+accountingUsers.map(u=>`<option value="${escapeAttr(u.username)}">${escapeHtml(u.name)} (${escapeHtml(u.username)})</option>`).join('')}
function populateAdminFilters(){
  if(!$('adminStatus'))return;const adminStatuses=[...STATUS_FLOW,'Batal'];$('adminStatus').innerHTML='<option value="">Semua status</option>'+adminStatuses.map(s=>`<option>${escapeHtml(s)}</option>`).join('');
  const pics=[...new Set(invoices.map(x=>x.picUser).filter(Boolean))].sort();$('adminPic').innerHTML='<option value="">Semua User/PIC</option>'+pics.map(x=>`<option value="${escapeAttr(x)}">${escapeHtml(x)}</option>`).join('');
  const accs=[...new Set(invoices.map(x=>x.accountingPic).filter(Boolean))].sort();if($('adminAccounting'))$('adminAccounting').innerHTML='<option value="">Semua Accounting PIC</option>'+accs.map(x=>`<option value="${escapeAttr(x)}">${escapeHtml(x)}</option>`).join('');
}

function openInvoiceModalById(id){const inv=invoices.find(x=>x.id===id);if(inv)openInvoiceModal(inv,'base')}
function openWorkModalById(id,mode){const inv=invoices.find(x=>x.id===id);if(inv)openInvoiceModal(inv,mode)}
function openInvoiceModal(inv=null,mode='base'){
  modalInvoice=inv;$('invoiceForm').reset();$('invoiceId').value=inv?.id||'';$('invoiceMode').value=mode;
  $('invoiceNo').value=inv?.invoiceNo||'';$('poNo').value=inv?.poNo||'';$('supplier').value=inv?.supplier||'';$('receivedDate').value=inv?.receivedDate||'';$('amount').value=inv?.amount??'';$('receiptNo').value=inv?.receiptNo||'';$('dueDate').value=inv?.dueDate||'';$('picUser').value=inv?.picUser||'';$('accountingPic').value=inv?.accountingPic||'';$('notes').value=inv?.notes||'';
  const selected=parseDocs(inv?.missingDocuments);document.querySelectorAll('input[name="missingDocument"]').forEach(el=>el.checked=selected.includes(el.value));
  const workUser=mode==='work-user',workAcc=mode==='work-accounting',work=workUser||workAcc;
  $('invoiceModalTitle').textContent=workUser?'Lengkapi Dokumen & Catatan':workAcc?'Update Progres Accounting':(inv?'Edit Data Invoice':'Terima Invoice Baru');
  $('invoiceModalSubtitle').textContent=inv?`${primaryNo(inv)} · Invoice: ${inv.invoiceNo||'-'} · ${inv.status}`:'Status awal otomatis: Baru / Diterima';
  $('currentStatusNote').classList.toggle('hidden',!inv);if(inv)$('currentStatusNote').innerHTML=`Status: <strong>${escapeHtml(inv.status)}</strong> · Posisi: <strong>${escapeHtml(inv.position)}</strong>`;
  ['invoiceNo','poNo','supplier','receivedDate','amount','receiptNo','dueDate','picUser','accountingPic'].forEach(id=>$(id).disabled=work);
  $('picField').classList.toggle('hidden',work);
  $('accountingPicField').classList.toggle('hidden',work);
  $('missingDocsField').classList.toggle('hidden',workAcc);
  $('saveInvoiceBtn').textContent=work?'Simpan Update':'Simpan';
  if($('savePrintInvoiceBtn')) $('savePrintInvoiceBtn').classList.toggle('hidden',!!inv||work||currentUser?.role!=='RESEPSIONIS');
  $('invoiceModal').classList.remove('hidden');
}
function closeInvoiceModal(){$('invoiceModal').classList.add('hidden');modalInvoice=null}

function openImportModal(){
  $('importForm')?.reset();
  $('importModal')?.classList.remove('hidden');
}
function closeImportModal(){$('importModal')?.classList.add('hidden')}
async function importInvoices(e){
  e.preventDefault();
  const file=$('invoiceImportFile')?.files?.[0];
  if(!file){toast('Pilih file spreadsheet terlebih dahulu.','warning');return;}
  const button=$('submitImportBtn');
  const formData=new FormData();formData.append('file',file);
  setLoading(button,true,'Mengimpor...');
  try{
    const res=await requestForm(appUrl('invoices/import'),formData);
    const imported=Array.isArray(res.invoices)?res.invoices:[];
    imported.forEach(replaceInvoice);
    closeImportModal();populateAdminFilters();renderRoleWorkspace();
    if(currentUser?.role==='ADMIN')loadAdminExtras();
    toast(res.message||`${imported.length} invoice berhasil diimpor.`,'success');
  }catch(err){toast(err.message,'error')}
  finally{setLoading(button,false,'Impor Invoice')}
}

async function saveInvoice(e){
  e.preventDefault();
  const submitBtn=e.submitter||$('saveInvoiceBtn');
  const wantsPrint=submitBtn?.id==='savePrintInvoiceBtn';
  const id=$('invoiceId').value,mode=$('invoiceMode').value;
  const docs=Array.from(document.querySelectorAll('input[name="missingDocument"]:checked')).map(x=>x.value);
  setLoading(submitBtn,true,wantsPrint?'Menyimpan...':'Menyimpan...');
  try{
    let res;
    if(mode==='work-user')res=await requestJson(appUrl(`invoices/${encodeURIComponent(id)}/work`),{method:'PATCH',body:{missingDocuments:docs,notes:$('notes').value.trim()}});
    else if(mode==='work-accounting')res=await requestJson(appUrl(`invoices/${encodeURIComponent(id)}/work`),{method:'PATCH',body:{notes:$('notes').value.trim()}});
    else{
      const data={invoiceNo:$('invoiceNo').value.trim(),poNo:$('poNo').value.trim(),supplier:$('supplier').value.trim(),receivedDate:$('receivedDate').value,amount:$('amount').value,receiptNo:$('receiptNo').value.trim(),dueDate:$('dueDate').value,picUser:$('picUser').value,accountingPic:$('accountingPic').value,missingDocuments:docs,notes:$('notes').value.trim()};
      res=id?await requestJson(appUrl(`invoices/${encodeURIComponent(id)}`),{method:'PUT',body:data}):await requestJson(appUrl('invoices'),{method:'POST',body:data});
    }
    if(res?.invoice){if(id)replaceInvoice(res.invoice);else invoices.unshift(res.invoice)}
    const savedInvoice=res?.invoice||null;
    closeInvoiceModal();populateAdminFilters();renderRoleWorkspace();toast('Data berhasil disimpan.','success');
    if(wantsPrint&&savedInvoice)openReceiptPreview(savedInvoice);
  }catch(err){toast(err.message,'error')}
  finally{
    const label=submitBtn?.id==='savePrintInvoiceBtn'?'🖨 Simpan & Cetak':(mode.startsWith('work')?'Simpan Update':'Simpan');
    setLoading(submitBtn,false,label);
  }
}

async function removeInvoice(id){const inv=invoices.find(x=>x.id===id);if(!inv)return;const ok=await showActionDialog({tone:'danger',icon:'!',title:'Hapus Invoice',message:`Hapus ${primaryNo(inv)}? Data invoice akan dihapus dari database.`,confirmText:'Hapus Invoice'});if(!ok)return;try{await requestJson(appUrl(`invoices/${encodeURIComponent(id)}`),{method:'DELETE'});invoices=invoices.filter(x=>x.id!==id);renderRoleWorkspace();toast('Invoice berhasil dihapus.','success')}catch(err){toast(err.message,'error')}}
function replaceInvoice(inv){const i=invoices.findIndex(x=>x.id===inv.id);if(i>=0)invoices[i]=inv;else invoices.unshift(inv)}

function openReceiptPreviewById(id){const inv=invoices.find(x=>x.id===id);if(inv)openReceiptPreview(inv)}
function openReceiptPreview(inv){
  if(!inv||!$('receiptPrintArea'))return;
  const missing=parseDocs(inv.missingDocuments);
  const docStatus=missing.length?`Belum lengkap — kurang: ${escapeHtml(missing.join(', '))}`:'Lengkap';
  const printedAt=new Intl.DateTimeFormat('id-ID',{dateStyle:'medium',timeStyle:'short'}).format(new Date());
  $('receiptPrintArea').innerHTML=`
    <div class="receipt-header">
      <div><div class="receipt-kicker">PT Indofood CBP Sukses Makmur Tbk</div><div class="receipt-title">TANDA TERIMA INVOICE</div><div class="receipt-subtitle">Bukti penerimaan dokumen invoice</div></div>
      <div class="receipt-number"><small>No. Tanda Terima</small><strong>${escapeHtml(inv.receiptNo||'-')}</strong></div>
    </div>
    <div class="receipt-divider"></div>
    <div class="receipt-grid">
      <div class="receipt-field"><span>Tanggal Masuk</span><strong>${formatDate(inv.receivedDate)}</strong></div>
      <div class="receipt-field"><span>Supplier</span><strong>${escapeHtml(inv.supplier||'-')}</strong></div>
      <div class="receipt-field"><span>No. Invoice</span><strong>${escapeHtml(inv.invoiceNo||'-')}</strong></div>
      <div class="receipt-field"><span>No. PO</span><strong>${escapeHtml(inv.poNo||'-')}</strong></div>
      <div class="receipt-field"><span>Nominal</span><strong>${formatRupiah(inv.amount)}</strong></div>
      <div class="receipt-field"><span>Jatuh Tempo</span><strong>${formatDate(inv.dueDate)}</strong></div>
      <div class="receipt-field full"><span>Kelengkapan Dokumen</span><strong>${docStatus}</strong></div>
    </div>
    <div class="receipt-note">Dokumen invoice tersebut telah diterima oleh Resepsionis dan selanjutnya akan diproses sesuai alur internal perusahaan.</div>
    ${inv.qrUrl?`<div class="receipt-tracking"><img class="receipt-qr" src="${escapeAttr(inv.qrUrl)}" alt="QR pelacakan invoice"><div><strong>Pindai untuk cek posisi invoice</strong><span>Tautan memiliki kode akses rahasia. Jangan sebarkan di luar pihak terkait.</span></div></div>`:''}
    <div class="receipt-footer"><span>Dicetak: ${escapeHtml(printedAt)}</span><span>No. Tanda Terima: ${escapeHtml(inv.receiptNo||'-')}</span></div>`;
  $('receiptModal').classList.remove('hidden');
}
function closeReceiptPreview(){$('receiptModal')?.classList.add('hidden')}
function printReceipt(){
  const source=$('receiptPrintArea');
  if(!source?.innerHTML.trim()){toast('Data tanda terima belum tersedia.','warning');return;}

  const frame=document.createElement('iframe');
  frame.setAttribute('aria-hidden','true');
  frame.style.position='fixed';
  frame.style.right='0';
  frame.style.bottom='0';
  frame.style.width='1px';
  frame.style.height='1px';
  frame.style.border='0';
  frame.style.opacity='0';
  frame.style.pointerEvents='none';
  document.body.appendChild(frame);

  const doc=frame.contentWindow.document;
  doc.open();
  doc.write(`<!doctype html>
<html><head><meta charset="utf-8"><title>Tanda Terima Invoice</title>
<style>
  @page{size:A4 portrait;margin:0}
  html,body{margin:0!important;padding:0!important;width:210mm;height:297mm;background:#fff;color:#111827;font-family:Arial,sans-serif}
  *{box-sizing:border-box}
  .print-page{position:relative;width:210mm;height:297mm;overflow:hidden;background:#fff}
  .receipt-half{position:absolute;left:0;width:210mm;height:148.5mm;padding:9mm 12mm 7mm;overflow:hidden;background:#fff}
  .receipt-half.top{top:0}
  .receipt-half.bottom{top:148.5mm}
  .cut-line{position:absolute;left:8mm;right:8mm;top:148.5mm;border-top:.3mm dashed #9ca3af;height:0}
  .cut-label{position:absolute;top:146.2mm;left:50%;transform:translateX(-50%);background:#fff;padding:0 2mm;color:#9ca3af;font-size:7pt;line-height:1;white-space:nowrap}
  .receipt-sheet{width:100%;height:100%;padding:0;border:0;box-shadow:none;background:#fff;color:#111827;font-family:Arial,sans-serif;overflow:hidden}
  .receipt-header{display:flex;justify-content:space-between;gap:8mm;align-items:flex-start}
  .receipt-kicker{font-size:7pt;font-weight:800;letter-spacing:.16em;color:#64748b}
  .receipt-title{font-size:16pt;font-weight:900;letter-spacing:.02em;margin-top:1.5mm;line-height:1.05}
  .receipt-subtitle{font-size:7.5pt;color:#64748b;margin-top:1mm}
  .receipt-number{text-align:right;border:.3mm solid #cbd5e1;border-radius:2.5mm;padding:2.2mm 3mm;min-width:42mm}
  .receipt-number small{display:block;color:#64748b;font-size:6.5pt;text-transform:uppercase;letter-spacing:.08em}
  .receipt-number strong{display:block;font-size:10pt;margin-top:1mm;word-break:break-word}
  .receipt-divider{height:.55mm;background:#111827;margin:4mm 0 3mm}
  .receipt-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:7mm;row-gap:0}
  .receipt-field{border-bottom:.25mm solid #e2e8f0;padding:2.2mm 1mm 2mm}
  .receipt-field.full{grid-column:1/-1}
  .receipt-field span{display:block;font-size:6.4pt;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.7mm}
  .receipt-field strong{font-size:8.7pt;line-height:1.25;word-break:break-word}
  .receipt-note{margin-top:3mm;padding:2.2mm 3mm;background:#f8fafc;border:.25mm solid #e2e8f0;border-radius:2mm;font-size:7pt;line-height:1.3}
  .receipt-tracking{display:flex;align-items:center;gap:3mm;margin-top:2.5mm;padding:2mm;border:.25mm solid #e2e8f0;border-radius:2mm}
  .receipt-qr{width:21mm;height:21mm;flex:0 0 21mm}.receipt-tracking strong{display:block;font-size:7pt}.receipt-tracking span{display:block;margin-top:1mm;color:#64748b;font-size:6.2pt;line-height:1.25}
  .receipt-footer{display:flex;justify-content:space-between;gap:4mm;margin-top:2mm;padding-top:1.5mm;border-top:.25mm dashed #cbd5e1;color:#64748b;font-size:6pt}
</style></head><body>
<div class="print-page">
  <div class="receipt-half top"><div class="receipt-sheet">${source.innerHTML}</div></div>
  <div class="cut-line"></div><div class="cut-label">potong / sobek di sini</div>
  <div class="receipt-half bottom"><div class="receipt-sheet">${source.innerHTML}</div></div>
</div>
</body></html>`);
  doc.close();

  const cleanup=()=>{try{frame.remove()}catch(_){}};
  let printStarted=false;
  const runPrint=()=>{
    if(printStarted)return;printStarted=true;
    try{
      frame.contentWindow.focus();
      frame.contentWindow.addEventListener('afterprint',cleanup,{once:true});
      frame.contentWindow.print();
      setTimeout(cleanup,10000);
    }catch(err){
      cleanup();
      toast('Gagal membuka dialog cetak. Coba izinkan print pada browser.','error');
    }
  };
  const images=Array.from(doc.images||[]);
  if(images.length){
    Promise.all(images.map(img=>img.complete?Promise.resolve():new Promise(resolve=>{img.addEventListener('load',resolve,{once:true});img.addEventListener('error',resolve,{once:true})}))).then(runPrint);
    setTimeout(runPrint,2000);
  }else setTimeout(runPrint,180);
}

async function openDetail(id){
  const inv=invoices.find(x=>x.id===id);if(!inv)return;$('detailTitle').textContent=primaryNo(inv);$('detailSubtitle').textContent=`No Invoice: ${inv.invoiceNo||'-'} · ${inv.supplier} · ${inv.position}`;$('detailContent').innerHTML='<div class="muted">Memuat riwayat...</div>';$('detailModal').classList.remove('hidden');
  try{const history=await requestJson(appUrl(`invoices/${encodeURIComponent(id)}/history`));$('detailContent').innerHTML=renderDetail(inv,history||[])}catch(err){$('detailContent').innerHTML=`<div class="muted">${escapeHtml(err.message)}</div>`}
}
function closeDetail(){$('detailModal').classList.add('hidden')}
function historyEventTitle(h){
  const from=String(h.fromStatus||'').trim(),to=String(h.toStatus||'').trim();
  if(!from&&to)return 'Invoice dibuat → '+to;
  if(from&&to&&from!==to)return from+' → '+to;
  return 'Pembaruan pada '+(to||from||'invoice');
}
function renderDetail(inv,history){
  const currentIdx=STATUS_FLOW.indexOf(inv.status);const pipeline=STATUS_FLOW.map((s,i)=>`<div class="pipe ${i<currentIdx?'done':''} ${i===currentIdx?'active':''}">${i+1}. ${escapeHtml(s)}</div>`).join('');
  const hist=history.length?history.map(h=>`<div class="timeline-item"><div class="timeline-dot"></div><div class="timeline-card"><strong>${escapeHtml(historyEventTitle(h))}</strong><div class="timeline-meta">${formatDateTime(h.time)} · ${escapeHtml(h.actorName||h.actorUsername||'-')} (${escapeHtml(roleLabel(h.role))})</div>${h.note?`<div class="timeline-note">${escapeHtml(h.note)}</div>`:''}</div></div>`).join(''):'<div class="muted">Belum ada activity log.</div>';
  const cancelled=history.find(h=>h.toStatus==='Batal');
  const cancelInfo=inv.status==='Batal'&&cancelled?`<div class="cancel-audit"><strong>Invoice Dibatalkan</strong><div class="result-grid"><div class="info"><small>Dibatalkan Oleh</small><strong>${escapeHtml(cancelled.actorName||cancelled.actorUsername||'-')}</strong></div><div class="info"><small>Waktu</small><strong>${formatDateTime(cancelled.time)}</strong></div><div class="info"><small>Alasan</small><strong>${escapeHtml(cancelled.note||'-')}</strong></div></div></div>`:'';
  return `<div class="pipeline">${pipeline}</div>${cancelInfo}<div class="result-grid"><div class="info"><small>No Invoice</small><strong>${escapeHtml(inv.invoiceNo||'-')}</strong></div><div class="info"><small>No PO</small><strong>${escapeHtml(inv.poNo||'-')}</strong></div><div class="info"><small>Status</small><strong>${escapeHtml(inv.status)}</strong></div><div class="info"><small>Posisi</small><strong>${escapeHtml(inv.position)}</strong></div><div class="info"><small>User / PIC</small><strong>${escapeHtml(inv.picUser||'-')}</strong></div><div class="info"><small>Accounting PIC</small><strong>${escapeHtml(inv.accountingPic||'-')}</strong></div><div class="info"><small>Jatuh Tempo</small><strong>${formatDate(inv.dueDate)} · ${escapeHtml(inv.dueStatus)}</strong></div><div class="info"><small>Kekurangan</small><strong>${escapeHtml(inv.missingDocuments||'Lengkap')}</strong></div></div><h4 style="margin:20px 0 10px">Timeline / Riwayat Invoice</h4><div class="timeline">${hist}</div>`;
}

async function loadAdminExtras(){
  if(currentUser?.role!=='ADMIN')return;
  try{const [users,activity,cancelled]=await Promise.all([requestJson(appUrl('admin/users')),requestJson(appUrl('admin/activity?limit=30')),requestJson(appUrl('admin/cancelled-invoices'))]);adminUsers=Array.isArray(users)?users:[];adminActivity=Array.isArray(activity)?activity:[];adminCancelled=Array.isArray(cancelled)?cancelled:[];renderAdminUsers();renderAdminActivity();renderAdminCancelled();}catch(err){toast(err.message,'error')}
}
function renderAdminCancelled(){
  if(!$('adminCancelledBody'))return;
  $('adminCancelledCount').textContent=adminCancelled.length;
  $('adminCancelledBody').innerHTML=adminCancelled.map(inv=>`<tr><td>${invoiceIdentity(inv)}</td><td>${escapeHtml(inv.supplier||'-')}</td><td><strong>${escapeHtml(inv.cancelledBy||inv.cancelledByUsername||'-')}</strong><div class="stage-time">${escapeHtml(roleLabel(inv.cancelledByRole||''))}</div></td><td>${formatDateTime(inv.cancelledAt||inv.updatedAt)}</td><td class="cancel-reason">${escapeHtml(inv.reason||'-')}</td><td><button class="btn btn-soft" type="button" data-act="detail" data-id="${escapeAttr(inv.id)}">Detail</button></td></tr>`).join('');
  $('adminCancelledEmpty').classList.toggle('hidden',adminCancelled.length>0);
}
function renderAdminUsers(){
  if(!$('adminUsersBody'))return;$('adminUsersBody').innerHTML=adminUsers.length?adminUsers.map(u=>`<tr><td><strong>${escapeHtml(u.username)}</strong></td><td>${escapeHtml(u.name)}</td><td>${escapeHtml(roleLabel(u.role))}</td><td>${escapeHtml(u.email||'-')}</td><td>${u.active?'<span class="badge green">Aktif</span>':'<span class="badge red">Nonaktif</span>'}</td><td><button class="btn btn-soft" type="button" data-act="edit-user" data-username="${escapeAttr(u.username)}">Edit</button></td></tr>`).join(''):'<tr><td colspan="6" class="muted">Belum ada user.</td></tr>';
}
function activityPrimaryNo(a){const inv=invoices.find(x=>x.id===a.invoiceId);return inv?primaryNo(inv):(a.invoiceNo||'-')}
function renderAdminActivity(){
  if(!$('adminActivityList'))return;$('adminActivityList').innerHTML=adminActivity.length?adminActivity.map(a=>`<div class="activity-row"><span class="activity-dot"></span><div><strong>${escapeHtml(activityPrimaryNo(a))} · ${escapeHtml(a.toStatus||'-')}</strong><p>${escapeHtml(a.actorName||a.actorUsername||'-')} (${escapeHtml(roleLabel(a.role))})${a.note?' — '+escapeHtml(a.note):''}</p><time>${formatDateTime(a.time)}</time></div></div>`).join(''):'<div class="muted">Belum ada aktivitas.</div>';
}
function editAdminUser(username){const u=adminUsers.find(x=>x.username===username);if(!u)return;$('adminUserUsername').value=u.username;$('adminUserUsername').readOnly=true;$('adminUserName').value=u.name||'';$('adminUserRole').value=u.role;$('adminUserEmail').value=u.email||'';$('adminUserPassword').value='';$('adminUserActive').checked=!!u.active;$('adminUserPassword').placeholder='Kosong = password lama tetap'}
function resetAdminUserForm(){$('adminUserForm').reset();$('adminUserUsername').readOnly=false;$('adminUserActive').checked=true;$('adminUserPassword').placeholder='Password baru / kosong = tetap'}
async function saveAdminUser(e){e.preventDefault();const submit=e.submitter;setLoading(submit,true,'Menyimpan...');try{const payload={username:$('adminUserUsername').value.trim(),name:$('adminUserName').value.trim(),role:$('adminUserRole').value,email:$('adminUserEmail').value.trim(),password:$('adminUserPassword').value,active:$('adminUserActive').checked};await requestJson(appUrl('admin/users'),{method:'POST',body:payload});resetAdminUserForm();await loadAdminExtras();toast('User berhasil disimpan.','success')}catch(err){toast(err.message,'error')}finally{setLoading(submit,false,'Simpan User')}}

function renderDocumentChecks(){$('documentChecks').innerHTML=DOCS.map(([v,l])=>`<label class="check"><input type="checkbox" name="missingDocument" value="${v}"><span><strong>${v}</strong> — ${l}</span></label>`).join('')}
function parseDocs(v){return String(v||'').split(',').map(x=>x.trim()).filter(Boolean)}
function docsHtml(v){const d=parseDocs(v);return d.length?`<div class="doc-tags">${d.map(x=>`<span class="doc-tag">${escapeHtml(x)}</span>`).join('')}</div>`:'<span class="badge green">Lengkap</span>'}
function statusBadge(s){const map={'Baru / Diterima':'gray','Menunggu User':'blue','Sedang Diproses User':'yellow','Menunggu Diterima Resepsionis':'orange','Kembali ke Resepsionis':'orange','Menunggu Accounting':'purple','Diproses Accounting':'green','Selesai':'green','Batal':'red'};return `<span class="badge ${map[s]||'gray'}">${escapeHtml(s||'-')}</span>`}
function dueBadge(s){const cls=s==='Terlambat'?'red':s==='Jatuh Tempo Hari Ini'?'yellow':s==='Selesai'?'green':s==='Batal'?'red':'gray';return `<span class="badge ${cls}">${escapeHtml(s||'-')}</span>`}
function roleLabel(r){return {ADMIN:'Admin',RESEPSIONIS:'Resepsionis',USER:'User / PIC',ACCOUNTING:'Accounting'}[r]||r||'-'}
function workspaceLabel(r){return {ADMIN:'Control Center',RESEPSIONIS:'Meja Resepsionis',USER:'Tugas User / PIC',ACCOUNTING:'Meja Accounting'}[r]||'Workspace'}
function formatRupiah(v){return new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:0}).format(Number(v||0))}
function formatDate(v){if(!v)return '-';const m=String(v).slice(0,10).match(/^(\d{4})-(\d{2})-(\d{2})$/);return m?`${m[3]}/${m[2]}/${m[1]}`:escapeHtml(v)}
function formatDateTime(v){if(!v)return '-';const s=String(v);const m=s.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);return m?`${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}`:escapeHtml(s)}
function normalizeKey(v){return String(v??'').normalize('NFKC').trim().replace(/\s+/g,' ').toLowerCase()}
function setLoading(btn,on,text){if(!btn)return;if(on){btn.dataset.old=btn.textContent;btn.disabled=true;btn.textContent=text}else{btn.disabled=false;btn.textContent=text||btn.dataset.old||'Simpan'}}
let actionDialogResolver=null;
function showActionDialog(opts={}){
  return new Promise(resolve=>{
    actionDialogResolver=resolve;
    const tone=opts.tone||'info';
    $('actionDialog').dataset.tone=tone;
    $('actionDialogIcon').textContent=opts.icon||({success:'✓',danger:'!',warning:'!',info:'i'}[tone]||'i');
    $('actionDialogTitle').textContent=opts.title||'Konfirmasi';
    $('actionDialogMessage').textContent=opts.message||'';
    $('actionDialogConfirm').textContent=opts.confirmText||'Lanjutkan';
    $('actionDialogConfirm').className='btn '+(tone==='danger'?'btn-danger':tone==='success'?'btn-success':'btn-primary');
    const hasSelect=Array.isArray(opts.selectOptions);
    $('actionDialogSelectWrap').classList.toggle('hidden',!hasSelect);
    if(hasSelect){
      $('actionDialogSelectLabel').textContent=opts.selectLabel||'Pilih';
      $('actionDialogSelect').innerHTML='<option value="">Pilih...</option>'+opts.selectOptions.map(o=>`<option value="${escapeAttr(o.value)}">${escapeHtml(o.label)}</option>`).join('');
      $('actionDialogSelect').value=opts.selectValue||'';
    }
    const hasNote=!!(opts.noteLabel||opts.noteRequired||opts.notePlaceholder);
    $('actionDialogNoteWrap').classList.toggle('hidden',!hasNote);
    $('actionDialogNoteLabel').textContent=opts.noteLabel||'Catatan';
    $('actionDialogNote').value=opts.noteValue||'';
    $('actionDialogNote').placeholder=opts.notePlaceholder||'Tulis catatan...';
    $('actionDialogNoteHelp').textContent=opts.noteRequired?'Catatan wajib diisi sebelum melanjutkan.':'';
    $('actionDialogNote').dataset.required=opts.noteRequired?'1':'0';
    $('actionDialog').classList.remove('hidden');
    setTimeout(()=>{if(hasNote)$('actionDialogNote').focus();else if(hasSelect)$('actionDialogSelect').focus();else $('actionDialogConfirm').focus()},30);
  });
}
function confirmActionDialog(){
  const required=$('actionDialogNote').dataset.required==='1';
  const note=$('actionDialogNote').value.trim();
  if(required&&!note){
    $('actionDialogNote').classList.add('input-error');
    $('actionDialogNoteHelp').textContent='Catatan wajib diisi.';
    $('actionDialogNote').focus();return;
  }
  $('actionDialogNote').classList.remove('input-error');
  resolveActionDialog({value:$('actionDialogSelect').value||'',note});
}
function resolveActionDialog(result){
  $('actionDialog').classList.add('hidden');
  $('actionDialogNote').classList.remove('input-error');
  const resolve=actionDialogResolver;actionDialogResolver=null;if(resolve)resolve(result);
}
function toast(msg,type='info'){
  const el=$('toast');if(!el)return;
  const icons={success:'✓',error:'!',warning:'!',info:'i'};
  const titles={success:'Berhasil',error:'Terjadi masalah',warning:'Perlu diperhatikan',info:'Informasi'};
  el.className=`toast toast-${type}`;
  el.innerHTML=`<span class="toast-icon">${icons[type]||'i'}</span><span class="toast-copy"><strong>${titles[type]||'Informasi'}</strong><span>${escapeHtml(msg||'Terjadi kesalahan.')}</span></span>`;
  el.classList.remove('hidden');clearTimeout(window.__toastTimer);window.__toastTimer=setTimeout(()=>el.classList.add('hidden'),4200);
}
function lastNote(v){const rows=String(v||'').split(/\r?\n/).map(x=>x.trim()).filter(Boolean);return rows.length?rows[rows.length-1]:''}
function safeJson(v){try{return v?JSON.parse(v):null}catch(_){return null}}
function escapeHtml(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]))}
function escapeAttr(v){return escapeHtml(v).replace(/`/g,'&#96;')}
function applySavedTheme(){let t='light';try{t=localStorage.getItem('invoiceTrackerTheme')||((window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches)?'dark':'light')}catch(_){}document.documentElement.dataset.theme=t}
function toggleTheme(){const next=document.documentElement.dataset.theme==='dark'?'light':'dark';document.documentElement.dataset.theme=next;try{localStorage.setItem('invoiceTrackerTheme',next)}catch(_){}}
