<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasUuids;
    protected $fillable = ['invoice_id','invoice_no','actor_user_id','actor_username','actor_name','role','from_status','to_status','duration_hours','note'];
    protected function casts(): array { return ['duration_hours'=>'float']; }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }
}
