<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class InvoiceTrackerService
{
    public const ROLES = ['ADMIN','RESEPSIONIS','USER','ACCOUNTING'];
    public const STATUSES = ['Baru / Diterima','Menunggu User','Sedang Diproses User','Menunggu Diterima Resepsionis','Kembali ke Resepsionis','Menunggu Accounting','Diproses Accounting','Selesai','Batal'];
    public const DOCUMENTS = ['GR','PO','FP','SJ','INV','LAINNYA'];

    public function dashboardData(User $user): array
    {
        $invoices = $this->visibleQuery($user)->with(['picUser:id,username,name','accountingPic:id,username,name'])->latest('updated_at')->get();
        return [
            'user'=>$this->publicUser($user),
            'invoices'=>$invoices->map(fn($i)=>$this->toClient($i))->values()->all(),
            'users'=>in_array($user->role,['ADMIN','RESEPSIONIS'],true) ? User::where('active',true)->where('role','USER')->orderBy('name')->get()->map(fn($u)=>$this->publicAssignable($u))->all() : [],
            'accountingUsers'=>in_array($user->role,['ADMIN','RESEPSIONIS'],true) ? User::where('active',true)->where('role','ACCOUNTING')->orderBy('name')->get()->map(fn($u)=>$this->publicAssignable($u))->all() : [],
            'statuses'=>self::STATUSES,
            'documents'=>self::DOCUMENTS,
        ];
    }

    public function visibleQuery(User $user): Builder
    {
        $q = Invoice::query();
        if (in_array($user->role,['ADMIN','RESEPSIONIS'],true)) return $q;
        if ($user->role==='USER') return $q->where('pic_user_id',$user->id);
        if ($user->role==='ACCOUNTING') return $q->where('accounting_pic_id',$user->id);
        return $q->whereRaw('1=0');
    }

    public function canSee(User $user, Invoice $invoice): bool
    {
        if (in_array($user->role,['ADMIN','RESEPSIONIS'],true)) return true;
        if ($user->role==='USER') return (int)$invoice->pic_user_id===(int)$user->id;
        if ($user->role==='ACCOUNTING') return (int)$invoice->accounting_pic_id===(int)$user->id;
        return false;
    }

    public function publicSearch(string $query): array
    {
        $query=trim($query);
        if ($query==='') throw new RuntimeException('Masukkan No Tanda Terima, No Invoice, atau No PO.');
        $rows=Invoice::with(['picUser','activities'])->where(function($q) use($query){
            $q->where('invoice_no',$query)->orWhere('po_no',$query)->orWhere('receipt_no',$query);
        })->latest('updated_at')->get();
        $results=$rows->map(function(Invoice $inv){
            $due=$this->dueState($inv->due_date,$inv->status);
            return [
                'id'=>$inv->id,'invoiceNo'=>$inv->invoice_no,'poNo'=>$inv->po_no,'supplier'=>$inv->supplier,
                'receivedDate'=>$this->date($inv->received_date),'receiptNo'=>$inv->receipt_no,'dueDate'=>$this->date($inv->due_date),
                'status'=>$inv->status,'position'=>$inv->position ?: $this->positionForStatus($inv->status),
                'missingDocuments'=>$inv->missing_documents ?: '','dueStatus'=>$due['status'],'daysToDue'=>$due['daysToDue'],
                'picUser'=>$inv->picUser?->username ?: '',
                'timeline'=>$inv->activities->filter(fn($a)=>!$a->from_status || $a->from_status!==$a->to_status)->map(fn($a)=>[
                    'time'=>$this->datetime($a->created_at),'fromStatus'=>$a->from_status ?: '','toStatus'=>$a->to_status ?: '','durationHours'=>(float)$a->duration_hours,
                ])->values()->all(),
            ];
        })->all();
        return ['count'=>count($results),'results'=>$results];
    }

    public function create(User $actor, array $data): array
    {
        $this->requireRole($actor,['ADMIN','RESEPSIONIS']);
        $this->validateBase($data);
        return DB::transaction(function() use($actor,$data){
            $pic=$this->userByUsername($data['picUser']??'', 'USER', false);
            $acc=$this->userByUsername($data['accountingPic']??'', 'ACCOUNTING', false);
            $received=$this->parseDate($data['receivedDate']??null);
            $receipt=trim((string)($data['receiptNo']??'')) ?: $this->nextReceiptNo($received);
            $inv=Invoice::create([
                'invoice_no'=>trim((string)$data['invoiceNo']), 'po_no'=>trim((string)$data['poNo']), 'supplier'=>$this->normalizeSupplier($data['supplier']),
                'received_date'=>$received, 'amount'=>$this->number($data['amount']??0), 'receipt_no'=>$receipt,
                'due_date'=>$this->parseDate($data['dueDate']??null), 'status'=>'Baru / Diterima',
                'missing_documents'=>$this->normalizeDocs($data['missingDocuments']??''), 'notes'=>trim((string)($data['notes']??'')),
                'pic_user_id'=>$pic?->id, 'position'=>$this->positionForStatus('Baru / Diterima'), 'status_updated_at'=>now(), 'accounting_pic_id'=>$acc?->id,
            ]);
            $this->appendHistory($inv,$actor,'','Baru / Diterima',0,'Invoice diterima dan dicatat ke sistem. No Tanda Terima: '.$receipt.'.');
            return ['success'=>true,'invoice'=>$this->toClient($inv->fresh(['picUser','accountingPic']))];
        });
    }

    public function update(User $actor, string $id, array $data): array
    {
        $this->requireRole($actor,['ADMIN','RESEPSIONIS']); $this->validateBase($data);
        return DB::transaction(function() use($actor,$id,$data){
            $inv=Invoice::with(['picUser','accountingPic'])->lockForUpdate()->findOrFail($id);
            if ($actor->role==='RESEPSIONIS' && !in_array($inv->status,['Baru / Diterima','Kembali ke Resepsionis'],true)) throw new RuntimeException('Resepsionis hanya dapat mengedit data saat invoice berada di Resepsionis.');
            $before=$this->toClient($inv);
            $pic=$this->userByUsername($data['picUser']??'', 'USER', false); $acc=$this->userByUsername($data['accountingPic']??'', 'ACCOUNTING', false);
            $received=$this->parseDate($data['receivedDate']??null);
            $inv->fill([
                'invoice_no'=>trim((string)$data['invoiceNo']), 'po_no'=>trim((string)$data['poNo']), 'supplier'=>$this->normalizeSupplier($data['supplier']),
                'received_date'=>$received,'amount'=>$this->number($data['amount']??0),'receipt_no'=>trim((string)($data['receiptNo']??'')) ?: $inv->receipt_no ?: $this->nextReceiptNo($received),
                'due_date'=>$this->parseDate($data['dueDate']??null),'missing_documents'=>$this->normalizeDocs($data['missingDocuments']??''),'notes'=>trim((string)($data['notes']??'')),
                'pic_user_id'=>$pic?->id,'accounting_pic_id'=>$acc?->id,'position'=>$this->positionForStatus($inv->status),
            ])->save();
            $after=$this->toClient($inv->fresh(['picUser','accountingPic']));
            $labels=['invoiceNo'=>'No Invoice','poNo'=>'No PO','supplier'=>'Supplier','receivedDate'=>'Tanggal Masuk','amount'=>'Amount','receiptNo'=>'No Tanda Terima','dueDate'=>'Jatuh Tempo','missingDocuments'=>'Kekurangan Dokumen','notes'=>'Keterangan','picUser'=>'User/PIC','accountingPic'=>'Accounting PIC'];
            $changes=[]; foreach($labels as $k=>$label) if((string)($before[$k]??'')!==(string)($after[$k]??'')) $changes[]=$label;
            $this->appendHistory($inv,$actor,$inv->status,$inv->status,0,$changes ? 'Data invoice diperbarui: '.implode(', ',$changes).'.' : 'Data invoice disimpan ulang tanpa perubahan utama.');
            return ['success'=>true,'invoice'=>$after];
        });
    }

    public function updateWorkInfo(User $actor,string $id,array $payload): array
    {
        return DB::transaction(function() use($actor,$id,$payload){
            $inv=Invoice::with(['picUser','accountingPic'])->lockForUpdate()->findOrFail($id);
            if(!$this->canSee($actor,$inv)) throw new RuntimeException('Invoice tidak dapat diakses.');
            if(!in_array($actor->role,self::ROLES,true)) throw new RuntimeException('Role tidak diizinkan.');
            if($actor->role==='USER'){
                if((int)$inv->pic_user_id!==(int)$actor->id) throw new RuntimeException('Invoice ini bukan assignment Anda.');
                if(!in_array($inv->status,['Menunggu User','Sedang Diproses User'],true)) throw new RuntimeException('Dokumen hanya dapat diperbarui saat invoice berada di User/PIC.');
            }
            if($actor->role==='ACCOUNTING'){
                if((int)$inv->accounting_pic_id!==(int)$actor->id) throw new RuntimeException('Invoice ini bukan assignment Accounting Anda.');
                if(!in_array($inv->status,['Menunggu Accounting','Diproses Accounting'],true)) throw new RuntimeException('Catatan hanya dapat diperbarui saat invoice berada di Accounting.');
            }
            $changes=[];
            if(array_key_exists('missingDocuments',$payload)){
                $v=$this->normalizeDocs($payload['missingDocuments']); if(($inv->missing_documents??'')!==$v){$inv->missing_documents=$v;$changes[]='Checklist dokumen: '.($v?:'Lengkap');}
            }
            if(array_key_exists('notes',$payload)){
                $v=trim((string)$payload['notes']); if(($inv->notes??'')!==$v){$inv->notes=$v;$changes[]='Catatan kerja diperbarui'.($v?': '.$v:'');}
            }
            $inv->save();
            $this->appendHistory($inv,$actor,$inv->status,$inv->status,0,$changes?implode(' · ',$changes):'Pembaruan pekerjaan disimpan.');
            return ['success'=>true,'invoice'=>$this->toClient($inv->fresh(['picUser','accountingPic']))];
        });
    }

    public function transition(User $actor,string $id,string $action,string $note='',string $targetUsername=''): array
    {
        $action=strtoupper(trim($action)); $note=trim($note); $targetUsername=strtolower(trim($targetUsername));
        $rules=$this->transitionRules(); if(!isset($rules[$action])) throw new RuntimeException('Aksi workflow tidak valid.'); $rule=$rules[$action];
        $this->requireRole($actor,$rule['roles']);
        return DB::transaction(function() use($actor,$id,$action,$note,$targetUsername,$rule){
            $inv=Invoice::with(['picUser','accountingPic'])->lockForUpdate()->findOrFail($id);
            if(!$this->canSee($actor,$inv) && $actor->role!=='ADMIN') throw new RuntimeException('Invoice tidak dapat diakses.');
            $allowed=(array)$rule['from']; if(!in_array($inv->status,$allowed,true)) throw new RuntimeException('Aksi tidak sesuai tahap saat ini. Status invoice: '.$inv->status);
            if($actor->role==='USER' && (int)$inv->pic_user_id!==(int)$actor->id) throw new RuntimeException('Invoice ini bukan assignment Anda.');
            if($actor->role==='ACCOUNTING' && (int)$inv->accounting_pic_id!==(int)$actor->id) throw new RuntimeException('Invoice ini bukan assignment Accounting Anda.');
            if($action==='SEND_TO_USER' && !$inv->pic_user_id) throw new RuntimeException('Pilih User/PIC tujuan terlebih dahulu.');
            if(in_array($action,['SEND_TO_ACCOUNTING','DIRECT_TO_ACCOUNTING'],true)){
                $accounting=$targetUsername ? $this->userByUsername($targetUsername,'ACCOUNTING',true) : $inv->accountingPic;
                if(!$accounting) throw new RuntimeException('Pilih Accounting PIC tujuan terlebih dahulu.');
                $inv->accounting_pic_id=$accounting->id;
            }
            if($action==='RETURN_ACCOUNTING_TO_RECEPTION' && $note==='') throw new RuntimeException('Catatan alasan pengembalian wajib diisi.');
            if($action==='CANCEL' && $note==='') throw new RuntimeException('Alasan pembatalan wajib diisi.');
            if($action==='RETURN_TO_RECEPTION' && trim((string)$inv->missing_documents)!=='') throw new RuntimeException('Dokumen masih ditandai kurang. Lengkapi / hapus tanda kekurangan dokumen terlebih dahulu.');
            if(in_array($action,['SEND_TO_ACCOUNTING','DIRECT_TO_ACCOUNTING'],true) && trim((string)$inv->missing_documents)!=='') throw new RuntimeException('Masih ada kekurangan dokumen. Invoice hanya dapat dikirim ke Accounting jika dokumen sudah lengkap.');
            if($action==='RETURN_ACCOUNTING_TO_RECEPTION') $inv->notes=$this->appendNote($inv->notes,'[Accounting → Resepsionis] '.$note);
            if($action==='CANCEL') $inv->notes=$this->appendNote($inv->notes,'[Dibatalkan oleh Resepsionis] '.$note);
            $previous=$inv->status; $now=now(); $duration=$inv->status_updated_at ? round($inv->status_updated_at->diffInSeconds($now)/3600,2) : 0;
            $inv->status=$rule['to']; $inv->position=$this->positionForStatus($rule['to']); $inv->status_updated_at=$now; $inv->completed_at=$rule['to']==='Selesai'?$now:null; $inv->save();
            $this->appendHistory($inv,$actor,$previous,$rule['to'],$duration,$note?:$rule['default']);
            $this->tryNotify($inv->fresh(['picUser','accountingPic']),$action,$actor);
            return ['success'=>true,'invoice'=>$this->toClient($inv->fresh(['picUser','accountingPic']))];
        });
    }

    public function history(User $actor,string $id): array
    {
        $inv=Invoice::findOrFail($id); if(!$this->canSee($actor,$inv)) throw new RuntimeException('Invoice tidak ditemukan atau tidak dapat diakses.');
        return ActivityLog::where('invoice_id',$id)->latest()->get()->map(fn($a)=>[
            'id'=>$a->id,'time'=>$this->datetime($a->created_at),'actorUsername'=>$a->actor_username,'actorName'=>$a->actor_name,'role'=>$a->role,
            'fromStatus'=>$a->from_status,'toStatus'=>$a->to_status,'durationHours'=>(float)$a->duration_hours,'note'=>$a->note,
        ])->all();
    }

    public function recentActivity(User $actor,int $limit=30): array
    {
        $this->requireRole($actor,['ADMIN']); $limit=max(1,min(100,$limit));
        return ActivityLog::latest()->limit($limit)->get()->map(fn($a)=>[
            'id'=>$a->id,'invoiceId'=>$a->invoice_id,'invoiceNo'=>$a->invoice_no,'time'=>$this->datetime($a->created_at),'actorUsername'=>$a->actor_username,
            'actorName'=>$a->actor_name,'role'=>$a->role,'fromStatus'=>$a->from_status,'toStatus'=>$a->to_status,'durationHours'=>(float)$a->duration_hours,'note'=>$a->note,
        ])->all();
    }

    public function cancelled(User $actor): array
    {
        $this->requireRole($actor,['ADMIN']);
        return Invoice::with(['picUser','accountingPic'])->where('status','Batal')->latest('updated_at')->get()->map(function($inv){
            $row=$this->toClient($inv); $a=ActivityLog::where('invoice_id',$inv->id)->where('to_status','Batal')->latest()->first();
            return array_merge($row,['cancelledAt'=>$a?$this->datetime($a->created_at):$row['updatedAt'],'cancelledByUsername'=>$a?->actor_username?:'','cancelledBy'=>$a?->actor_name?:'','cancelledByRole'=>$a?->role?:'','reason'=>$a?->note?:'']);
        })->all();
    }

    public function adminUsers(User $actor): array
    {
        $this->requireRole($actor,['ADMIN']);
        return User::orderBy('username')->get()->map(fn($u)=>['username'=>$u->username,'name'=>$u->name,'role'=>$u->role,'email'=>$u->email?:'','active'=>(bool)$u->active])->all();
    }

    public function saveAdminUser(User $actor,array $payload): array
    {
        $this->requireRole($actor,['ADMIN']); $username=strtolower(trim((string)($payload['username']??''))); $name=trim((string)($payload['name']??''))?:$username; $role=strtoupper(trim((string)($payload['role']??'')));
        if(strlen($username)<3) throw new RuntimeException('Username minimal 3 karakter.'); if(!in_array($role,self::ROLES,true)) throw new RuntimeException('Role tidak valid.');
        $user=User::where('username',$username)->first(); $password=(string)($payload['password']??''); if(!$user && strlen($password)<4) throw new RuntimeException('Password user baru minimal 4 karakter.');
        if(!$user) $user=new User(['username'=>$username]);
        $user->name=$name; $user->role=$role; $user->email=trim((string)($payload['email']??''))?:null; $user->active=($payload['active']??true)!==false;
        if($password!=='') { if(strlen($password)<4) throw new RuntimeException('Password minimal 4 karakter.'); $user->password=$password; }
        $user->save(); return ['success'=>true,'user'=>['username'=>$user->username,'name'=>$user->name,'role'=>$user->role,'email'=>$user->email?:'','active'=>(bool)$user->active]];
    }

    public function delete(User $actor,string $id): array { $this->requireRole($actor,['ADMIN']); Invoice::findOrFail($id)->delete(); return ['success'=>true]; }

    public function toClient(Invoice $inv): array
    {
        $inv->loadMissing(['picUser','accountingPic']); $due=$this->dueState($inv->due_date,$inv->status);
        return ['id'=>$inv->id,'invoiceNo'=>$inv->invoice_no,'poNo'=>$inv->po_no,'supplier'=>$inv->supplier,'receivedDate'=>$this->date($inv->received_date),'amount'=>(float)$inv->amount,
            'receiptNo'=>$inv->receipt_no,'dueDate'=>$this->date($inv->due_date),'status'=>$inv->status,'missingDocuments'=>$inv->missing_documents?:'','notes'=>$inv->notes?:'',
            'dueStatus'=>$due['status'],'daysToDue'=>$due['daysToDue']===''?0:(int)$due['daysToDue'],'createdAt'=>$this->datetime($inv->created_at),'updatedAt'=>$this->datetime($inv->updated_at),
            'picUser'=>$inv->picUser?->username?:'','position'=>$inv->position?:$this->positionForStatus($inv->status),'statusUpdatedAt'=>$this->datetime($inv->status_updated_at),
            'completedAt'=>$this->datetime($inv->completed_at),'accountingPic'=>$inv->accountingPic?->username?:''];
    }

    public function positionForStatus(string $status): string { return ['Baru / Diterima'=>'Resepsionis','Menunggu User'=>'User / PIC','Sedang Diproses User'=>'User / PIC','Menunggu Diterima Resepsionis'=>'User / PIC','Kembali ke Resepsionis'=>'Resepsionis','Menunggu Accounting'=>'Accounting','Diproses Accounting'=>'Accounting','Selesai'=>'Selesai','Batal'=>'Batal'][$status]??'-'; }
    public function dueState($dueDate,string $status): array { if($status==='Selesai') return ['status'=>'Selesai','daysToDue'=>'']; if($status==='Batal') return ['status'=>'Batal','daysToDue'=>'']; if(!$dueDate) return ['status'=>'Belum Ada Jatuh Tempo','daysToDue'=>'']; $due=Carbon::parse($dueDate)->startOfDay(); $today=now()->startOfDay(); $diff=$today->diffInDays($due,false); if($diff<0)return ['status'=>'Terlambat','daysToDue'=>$diff]; if($diff===0)return ['status'=>'Jatuh Tempo Hari Ini','daysToDue'=>0]; return ['status'=>'Belum Jatuh Tempo','daysToDue'=>$diff]; }
    public function publicUser(User $u): array { return ['username'=>$u->username,'name'=>$u->name,'role'=>$u->role]; }
    private function publicAssignable(User $u): array { return ['username'=>$u->username,'name'=>$u->name,'role'=>$u->role,'active'=>(bool)$u->active]; }
    private function requireRole(User $u,array $roles): void { if(!in_array($u->role,$roles,true)) throw new RuntimeException('Role Anda tidak diizinkan melakukan aksi ini.'); }
    private function validateBase(array $d): void { if(trim((string)($d['invoiceNo']??''))==='')throw new RuntimeException('No Invoice wajib diisi.'); if(trim((string)($d['poNo']??''))==='')throw new RuntimeException('No PO wajib diisi.'); if(trim((string)($d['supplier']??''))==='')throw new RuntimeException('Supplier wajib diisi.'); if($this->number($d['amount']??0)<0)throw new RuntimeException('Amount tidak valid.'); }
    private function userByUsername(string $username,string $role,bool $required): ?User { $username=strtolower(trim($username)); if($username===''){ if($required)throw new RuntimeException($role==='USER'?'User/PIC tujuan tidak valid atau tidak aktif.':'Accounting PIC tujuan tidak valid atau tidak aktif.'); return null;} $u=User::where('username',$username)->where('role',$role)->where('active',true)->first(); if(!$u && $required)throw new RuntimeException($role==='USER'?'User/PIC tujuan tidak valid atau tidak aktif.':'Accounting PIC tujuan tidak valid atau tidak aktif.'); if(!$u)throw new RuntimeException($role==='USER'?'User/PIC tujuan tidak valid atau tidak aktif.':'Accounting PIC tujuan tidak valid atau tidak aktif.'); return $u; }
    private function nextReceiptNo(?Carbon $received): string { $date=($received?:now())->format('Ymd'); $prefix='TT-'.$date.'-'; $last=Invoice::where('receipt_no','like',$prefix.'%')->lockForUpdate()->orderByDesc('receipt_no')->value('receipt_no'); $n=$last?(int)substr($last,-3)+1:1; return $prefix.str_pad((string)$n,3,'0',STR_PAD_LEFT); }
    private function appendHistory(Invoice $inv,User $actor,string $from,string $to,float $duration,string $note): void { ActivityLog::create(['invoice_id'=>$inv->id,'invoice_no'=>$inv->invoice_no,'actor_user_id'=>$actor->id,'actor_username'=>$actor->username,'actor_name'=>$actor->name,'role'=>$actor->role,'from_status'=>$from,'to_status'=>$to,'duration_hours'=>$duration,'note'=>trim($note)]); }
    private function transitionRules(): array { return [
        'SEND_TO_USER'=>['from'=>'Baru / Diterima','to'=>'Menunggu User','roles'=>['ADMIN','RESEPSIONIS'],'default'=>'Invoice diserahkan ke User/PIC.'],
        'DIRECT_TO_ACCOUNTING'=>['from'=>'Baru / Diterima','to'=>'Menunggu Accounting','roles'=>['ADMIN','RESEPSIONIS'],'default'=>'Dokumen sudah lengkap. Invoice dikirim langsung dari Resepsionis ke Accounting tanpa melalui User/PIC.'],
        'START_USER_PROCESS'=>['from'=>'Menunggu User','to'=>'Sedang Diproses User','roles'=>['ADMIN','USER'],'default'=>'User/PIC mulai memeriksa dan melengkapi dokumen.'],
        'RETURN_TO_RECEPTION'=>['from'=>'Sedang Diproses User','to'=>'Menunggu Diterima Resepsionis','roles'=>['ADMIN','USER'],'default'=>'User/PIC menyatakan dokumen fisik telah diserahkan ke Resepsionis dan menunggu konfirmasi penerimaan.'],
        'CONFIRM_RECEPTION_RECEIPT'=>['from'=>'Menunggu Diterima Resepsionis','to'=>'Kembali ke Resepsionis','roles'=>['ADMIN','RESEPSIONIS'],'default'=>'Resepsionis mengonfirmasi dokumen fisik telah diterima.'],
        'SEND_TO_ACCOUNTING'=>['from'=>'Kembali ke Resepsionis','to'=>'Menunggu Accounting','roles'=>['ADMIN','RESEPSIONIS'],'default'=>'Resepsionis menyerahkan invoice ke Accounting.'],
        'START_ACCOUNTING'=>['from'=>'Menunggu Accounting','to'=>'Diproses Accounting','roles'=>['ADMIN','ACCOUNTING'],'default'=>'Accounting mulai verifikasi dan proses pembayaran.'],
        'RETURN_ACCOUNTING_TO_RECEPTION'=>['from'=>['Menunggu Accounting','Diproses Accounting'],'to'=>'Kembali ke Resepsionis','roles'=>['ADMIN','ACCOUNTING'],'default'=>'Accounting mengembalikan invoice ke Resepsionis untuk koreksi.'],
        'COMPLETE'=>['from'=>'Diproses Accounting','to'=>'Selesai','roles'=>['ADMIN','ACCOUNTING'],'default'=>'Proses invoice selesai.'],
        'CANCEL'=>['from'=>['Baru / Diterima','Kembali ke Resepsionis'],'to'=>'Batal','roles'=>['ADMIN','RESEPSIONIS'],'default'=>'Invoice dibatalkan oleh Resepsionis.'],
    ]; }
    private function normalizeDocs($value): string { $aliases=['gr'=>'GR','good receipt'=>'GR','goods receipt'=>'GR','po'=>'PO','purchase order'=>'PO','fp'=>'FP','faktur pajak'=>'FP','sj'=>'SJ','surat jalan'=>'SJ','inv'=>'INV','invoice'=>'INV','lainnya'=>'LAINNYA','lain-lain'=>'LAINNYA']; $items=is_array($value)?$value:preg_split('/[,;\n|]+/',(string)$value); $out=[]; foreach($items as $x){$x=trim(preg_replace('/\([^)]*\)/',' ',preg_replace('/^kurang\s+/i','',trim((string)$x)))); if($x==='')continue; $k=strtolower(preg_replace('/\s+/',' ',$x)); $v=$aliases[$k]??strtoupper($x); if(in_array($v,self::DOCUMENTS,true))$out[$v]=$v;} return implode(', ',array_values($out)); }
    private function normalizeSupplier($value): string { $s=trim(preg_replace('/\s+/',' ',(string)$value)); if($s==='')return ''; return implode(' ',array_map(function($w){$u=strtoupper($w); if(in_array($u,['PT','CV','UD'],true))return $u; if($u==='TBK')return 'Tbk'; return mb_convert_case(mb_strtolower($w),MB_CASE_TITLE,'UTF-8');},explode(' ',$s))); }
    private function parseDate($v): ?Carbon { if(!$v)return null; try{return Carbon::parse($v)->startOfDay();}catch(\Throwable){return null;} }
    private function number($v): float { if(is_numeric($v))return (float)$v; $s=preg_replace('/[^0-9,.-]/','',(string)$v); if(str_contains($s,'.')&&str_contains($s,','))$s=str_replace(',','.',str_replace('.','',$s)); elseif(preg_match('/^-?\d{1,3}(?:\.\d{3})+$/',$s))$s=str_replace('.','',$s); elseif(str_contains($s,','))$s=str_replace(',','.',$s); return is_numeric($s)?(float)$s:0; }
    private function appendNote(?string $old,string $line): string { $old=trim((string)$old); return $old===''?$line:$old."\n".$line; }
    private function date($v): string { return $v?Carbon::parse($v)->format('Y-m-d'):''; }
    private function datetime($v): string { return $v?Carbon::parse($v)->format('Y-m-d H:i:s'):''; }
    private function tryNotify(Invoice $inv,string $action,User $actor): void { try { $emails=[]; if($action==='SEND_TO_USER'&&$inv->picUser?->email)$emails[]=$inv->picUser->email; if(in_array($action,['SEND_TO_ACCOUNTING','DIRECT_TO_ACCOUNTING'],true)&&$inv->accountingPic?->email)$emails[]=$inv->accountingPic->email; $emails=array_values(array_unique(array_filter($emails))); if(!$emails)return; $subject='[Invoice Tracker] '.$inv->invoice_no.' — '.$inv->status; $body="Invoice: {$inv->invoice_no}\nSupplier: {$inv->supplier}\nStatus: {$inv->status}\nPosisi: {$inv->position}\nDiubah oleh: {$actor->name} ({$actor->role})"; Mail::raw($body,fn($m)=>$m->to($emails)->subject($subject)); } catch(\Throwable $e) { report($e); } }
}
