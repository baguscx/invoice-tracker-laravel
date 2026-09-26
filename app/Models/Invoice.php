<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasUuids;
    protected $fillable = ['invoice_no','po_no','supplier','received_date','amount','receipt_no','due_date','status','missing_documents','notes','pic_user_id','position','status_updated_at','completed_at','accounting_pic_id'];
    protected function casts(): array { return ['received_date'=>'date','due_date'=>'date','amount'=>'decimal:2','status_updated_at'=>'datetime','completed_at'=>'datetime']; }
    public function picUser(): BelongsTo { return $this->belongsTo(User::class, 'pic_user_id'); }
    public function accountingPic(): BelongsTo { return $this->belongsTo(User::class, 'accounting_pic_id'); }
    public function activities(): HasMany { return $this->hasMany(ActivityLog::class)->orderByDesc('created_at'); }
}
