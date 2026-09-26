<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['username','name','role','email','password','active'];
    protected $hidden = ['password','remember_token'];
    protected function casts(): array { return ['active'=>'boolean','password'=>'hashed']; }

    public function picInvoices() { return $this->hasMany(Invoice::class, 'pic_user_id'); }
    public function accountingInvoices() { return $this->hasMany(Invoice::class, 'accounting_pic_id'); }
}
