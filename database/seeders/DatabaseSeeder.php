<?php
namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Seeder;
class DatabaseSeeder extends Seeder { public function run(): void { foreach([
 ['admin','Administrator','ADMIN'],['resepsionis','Resepsionis','RESEPSIONIS'],['pic01','User PIC 01','USER'],['acc01','Accounting 01','ACCOUNTING'],['acc02','Accounting 02','ACCOUNTING']
 ] as [$username,$name,$role]) User::updateOrCreate(['username'=>$username],['name'=>$name,'role'=>$role,'email'=>null,'password'=>'1234','active'=>true]); } }
