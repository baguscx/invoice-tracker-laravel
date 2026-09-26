<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;
use DateTimeImmutable;

class InvoiceSpreadsheetService
{
    private const MAX_ROWS = 1000;

    private const COLUMNS = [
        'invoiceNo' => 'No Invoice *',
        'poNo' => 'No PO *',
        'supplier' => 'Supplier *',
        'receivedDate' => 'Tanggal Masuk',
        'amount' => 'Amount',
        'receiptNo' => 'No Tanda Terima',
        'dueDate' => 'Jatuh Tempo',
        'picUser' => 'Username User/PIC',
        'accountingPic' => 'Username Accounting PIC',
        'missingDocuments' => 'Kekurangan Dokumen',
        'notes' => 'Catatan',
    ];

    private const HEADER_ALIASES = [
        'no invoice' => 'invoiceNo', 'invoice no' => 'invoiceNo', 'invoice number' => 'invoiceNo',
        'no po' => 'poNo', 'po no' => 'poNo', 'po number' => 'poNo',
        'supplier' => 'supplier', 'vendor' => 'supplier',
        'tanggal masuk' => 'receivedDate', 'received date' => 'receivedDate',
        'amount' => 'amount', 'nominal' => 'amount', 'nilai' => 'amount',
        'no tanda terima' => 'receiptNo', 'receipt no' => 'receiptNo',
        'jatuh tempo' => 'dueDate', 'due date' => 'dueDate',
        'username user/pic' => 'picUser', 'user/pic' => 'picUser', 'pic user' => 'picUser',
        'username accounting pic' => 'accountingPic', 'accounting pic' => 'accountingPic',
        'kekurangan dokumen' => 'missingDocuments', 'missing documents' => 'missingDocuments',
        'catatan' => 'notes', 'notes' => 'notes',
    ];

    public function import(User $actor, UploadedFile $file, InvoiceTrackerService $tracker): array
    {
        $this->requireImporter($actor);
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, ['xlsx', 'xls'], true) && !extension_loaded('zip')) {
            throw new RuntimeException('Ekstensi PHP zip belum aktif. Aktifkan extension=zip di php.ini lalu restart Laragon.');
        }

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestDataRow();
            $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

            if ($highestRow < 2) {
                throw new RuntimeException('Spreadsheet belum berisi data invoice.');
            }
            if ($highestRow - 1 > self::MAX_ROWS) {
                throw new RuntimeException('Maksimal '.self::MAX_ROWS.' baris invoice per unggahan.');
            }

            $headers = [];
            for ($column = 1; $column <= $highestColumn; $column++) {
                $name = $this->normalizeHeader((string) $sheet->getCell([$column, 1])->getValue());
                if ($name !== '') {
                    if (in_array($name, $headers, true)) {
                        throw new RuntimeException('Header spreadsheet duplikat: '.self::COLUMNS[$name].'.');
                    }
                    $headers[$column] = $name;
                }
            }

            foreach (['invoiceNo', 'poNo', 'supplier'] as $required) {
                if (!in_array($required, $headers, true)) {
                    throw new RuntimeException('Kolom wajib tidak ditemukan: '.self::COLUMNS[$required].'. Gunakan template yang tersedia.');
                }
            }

            $rows = [];
            for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
                $data = [];
                foreach ($headers as $column => $key) {
                    $cell = $sheet->getCell([$column, $rowNumber]);
                    $value = $cell->getValue();
                    if (in_array($key, ['receivedDate', 'dueDate'], true)) {
                        $value = $this->dateValue($value, $rowNumber, self::COLUMNS[$key]);
                    }
                    $data[$key] = is_string($value) ? trim($value) : $value;
                }

                if ($this->isBlankRow($data)) {
                    continue;
                }
                foreach (['invoiceNo', 'poNo', 'supplier'] as $required) {
                    if (trim((string) ($data[$required] ?? '')) === '') {
                        throw new RuntimeException("Baris {$rowNumber}: ".self::COLUMNS[$required].' wajib diisi.');
                    }
                }
                $rows[] = ['number' => $rowNumber, 'data' => $data];
            }

            if ($rows === []) {
                throw new RuntimeException('Tidak ada baris invoice yang dapat diimpor.');
            }

            $created = DB::transaction(function () use ($rows, $actor, $tracker) {
                $created = [];
                foreach ($rows as $row) {
                    try {
                        $created[] = $tracker->create($actor, $row['data'])['invoice'];
                    } catch (Throwable $e) {
                        throw new RuntimeException("Baris {$row['number']}: {$e->getMessage()}", 0, $e);
                    }
                }
                return $created;
            });

            return [
                'success' => true,
                'count' => count($created),
                'invoices' => $created,
                'message' => count($created).' invoice berhasil diimpor.',
            ];
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw new RuntimeException('File tidak dapat dibaca. Pastikan formatnya XLSX, XLS, atau CSV yang valid.');
        } finally {
            if (isset($spreadsheet)) {
                $spreadsheet->disconnectWorksheets();
            }
        }
    }

    public function createTemplate(User $actor): string
    {
        $this->requireImporter($actor);
        if (!extension_loaded('zip')) {
            throw new RuntimeException('Ekstensi PHP zip belum aktif. Aktifkan extension=zip di php.ini lalu restart Laragon.');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Invoice');
        $sheet->fromArray(array_values(self::COLUMNS), null, 'A1');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:K1');
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1D4ED8']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);
        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setWidth(in_array($column, ['C', 'K'], true) ? 28 : 20);
        }
        $sheet->getStyle('D2:D1001')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle('G2:G1001')->getNumberFormat()->setFormatCode('yyyy-mm-dd');
        $sheet->getStyle('E2:E1001')->getNumberFormat()->setFormatCode('#,##0.00');

        $help = $spreadsheet->createSheet();
        $help->setTitle('Petunjuk');
        $help->fromArray([
            ['Petunjuk impor invoice'],
            ['Satu baris mewakili satu invoice. Jangan mengubah nama header pada sheet Invoice.'],
            ['Kolom bertanda * wajib diisi. Tanggal gunakan format YYYY-MM-DD, misalnya 2026-09-26.'],
            ['No Tanda Terima boleh dikosongkan agar dibuat otomatis oleh sistem.'],
            ['Username User/PIC dan Accounting PIC harus sama dengan username aktif di aplikasi.'],
            ['Kekurangan Dokumen dapat berisi GR, PO, FP, SJ, INV, atau LAINNYA; pisahkan dengan koma.'],
            ['Maksimal 1.000 baris per unggahan. Jika satu baris gagal, seluruh impor dibatalkan.'],
        ], null, 'A1');
        $help->getStyle('A1')->getFont()->setBold(true)->setSize(15);
        $help->getColumnDimension('A')->setWidth(110);
        $help->getStyle('A1:A7')->getAlignment()->setWrapText(true);

        $path = tempnam(sys_get_temp_dir(), 'invoice-template-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function normalizeHeader(string $header): string
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', str_replace(['*', '_'], [' ', ' '], $header))));
        return self::HEADER_ALIASES[$normalized] ?? '';
    }

    private function dateValue(mixed $value, int $row, string $label): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        try {
            if (is_numeric($value)) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->format('Y-m-d');
            }
            $text = trim((string) $value);
            foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
                $date = DateTimeImmutable::createFromFormat('!'.$format, $text);
                $errors = DateTimeImmutable::getLastErrors();
                if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && $date->format($format) === $text) {
                    return $date->format('Y-m-d');
                }
            }
        } catch (Throwable) {
        }
        throw new RuntimeException("Baris {$row}: {$label} tidak valid. Gunakan format YYYY-MM-DD.");
    }

    private function isBlankRow(array $data): bool
    {
        foreach ($data as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    private function requireImporter(User $actor): void
    {
        if (!in_array($actor->role, ['ADMIN', 'RESEPSIONIS'], true)) {
            throw new RuntimeException('Role Anda tidak diizinkan mengimpor invoice.');
        }
    }
}
