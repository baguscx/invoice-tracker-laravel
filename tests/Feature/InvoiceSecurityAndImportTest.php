<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class InvoiceSecurityAndImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_tracking_requires_the_random_access_token(): void
    {
        $invoice = $this->invoice();

        $this->get('/?code='.urlencode($invoice->invoice_no))
            ->assertOk()
            ->assertSee('Kode akses tidak valid')
            ->assertDontSee($invoice->receipt_no);

        $this->get(route('tracking.show', $invoice->public_tracking_token))
            ->assertOk()
            ->assertSee($invoice->receipt_no);

        $this->get(route('tracking.qr', $invoice->public_tracking_token))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertSee('<svg', false);
    }

    public function test_user_cannot_open_another_users_invoice_history(): void
    {
        $owner = $this->user('owner', 'USER');
        $stranger = $this->user('stranger', 'USER');
        $invoice = $this->invoice(['pic_user_id' => $owner->id]);

        $this->actingAs($stranger)
            ->getJson(route('invoices.history', $invoice->id))
            ->assertNotFound();
    }

    public function test_receptionist_can_import_csv_atomically(): void
    {
        $receptionist = $this->user('reception', 'RESEPSIONIS');
        $csv = "No Invoice *,No PO *,Supplier *,Tanggal Masuk,Amount,Jatuh Tempo\nINV-101,PO-101,PT Contoh,2026-09-26,150000,2026-10-15\nINV-102,PO-102,CV Demo,26/09/2026,250000,15/10/2026\n";

        $response = $this->actingAs($receptionist)->postJson(route('invoices.import'), [
            'file' => UploadedFile::fake()->createWithContent('invoice.csv', $csv),
        ]);

        $response->assertOk()->assertJsonPath('count', 2);
        $this->assertDatabaseCount('invoices', 2);
        $this->assertDatabaseCount('activity_logs', 2);
        $this->assertNotNull(Invoice::first()->public_tracking_token);
    }

    public function test_invalid_import_rolls_back_every_row(): void
    {
        $receptionist = $this->user('reception', 'RESEPSIONIS');
        $csv = "No Invoice *,No PO *,Supplier *,Username User/PIC\nINV-201,PO-201,PT Valid,\nINV-202,PO-202,PT Tidak Valid,ghost-user\n";

        $this->actingAs($receptionist)->postJson(route('invoices.import'), [
            'file' => UploadedFile::fake()->createWithContent('invoice.csv', $csv),
        ])->assertUnprocessable()->assertJsonFragment(['message' => 'Baris 3: User/PIC tujuan tidak valid atau tidak aktif.']);

        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_receptionist_can_download_a_valid_excel_template(): void
    {
        $receptionist = $this->user('reception', 'RESEPSIONIS');
        $response = $this->actingAs($receptionist)->get(route('invoices.import-template'));

        $response->assertOk()->assertDownload('template-import-invoice.xlsx');
        $workbook = IOFactory::load($response->baseResponse->getFile()->getPathname());
        $this->assertSame('No Invoice *', $workbook->getSheetByName('Invoice')->getCell('A1')->getValue());
        $this->assertSame('Petunjuk impor invoice', $workbook->getSheetByName('Petunjuk')->getCell('A1')->getValue());
        $workbook->disconnectWorksheets();
    }

    private function user(string $username, string $role): User
    {
        return User::create([
            'username' => $username,
            'name' => ucfirst($username),
            'role' => $role,
            'password' => 'secret123',
            'active' => true,
        ]);
    }

    private function invoice(array $overrides = []): Invoice
    {
        return Invoice::create(array_merge([
            'invoice_no' => 'INV-SECRET-001',
            'po_no' => 'PO-SECRET-001',
            'supplier' => 'PT Rahasia',
            'amount' => 100000,
            'receipt_no' => 'TT-20260926-001',
            'status' => 'Baru / Diterima',
            'position' => 'Resepsionis',
            'status_updated_at' => now(),
        ], $overrides));
    }
}
