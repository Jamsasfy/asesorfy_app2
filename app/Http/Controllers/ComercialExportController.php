<?php

namespace App\Http\Controllers;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ComercialExportController extends Controller
{
    // ─────────────────────────────────────────────
    // ETIQUETAS DE COLUMNAS
    // ─────────────────────────────────────────────
    private array $labels = [
        'name'                 => 'Nombre',
        'email'                => 'Email',
        'num_reglas'           => 'Nº Reglas',
        'reglas_nombres'       => 'Reglas activas',
        'reglas_obligatorias'  => 'Reglas obligatorias',
        'minimo_mensual_total' => 'Mínimo mensual total (€)',
        'comision_media'       => 'Comisión media (%)',
    ];

    // ─────────────────────────────────────────────
    // EXCEL
    // ─────────────────────────────────────────────
    public function exportExcel(Request $request)
    {
        $token = $request->query('token');
        $data  = cache()->pull("comercial_export_{$token}");

        abort_unless($data, 404);

        $users    = $this->getUsers($data['ids']);
        $columnas = $data['columnas'];

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Comerciales');

        // ── Cabecera ──────────────────────────────
        $col = 1;
        foreach ($columnas as $key) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue("{$letter}1", $this->labels[$key] ?? $key);
            $col++;
        }

        $lastLetter = Coordinate::stringFromColumnIndex(count($columnas));
        $headerRange = "A1:{$lastLetter}1";

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['argb' => 'FFFFFFFF'],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1E40AF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        // ── Filas de datos ────────────────────────
        $row = 2;
        foreach ($users as $user) {
            $col = 1;
            foreach ($columnas as $key) {
                $letter = Coordinate::stringFromColumnIndex($col);
                $sheet->setCellValue("{$letter}{$row}", $this->valorColumna($user, $key));
                $col++;
            }
            $row++;
        }

        // ── Auto-ancho ────────────────────────────
        for ($i = 1; $i <= count($columnas); $i++) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }

        $filename = 'comerciales-reglas-' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control'       => 'max-age=0',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ─────────────────────────────────────────────
    // PDF
    // ─────────────────────────────────────────────
    public function exportPdf(Request $request)
    {
        $token = $request->query('token');
        $data  = cache()->pull("comercial_export_{$token}");

        abort_unless($data, 404);

        $users    = $this->getUsers($data['ids']);
        $columnas = $data['columnas'];
        $labels   = $this->labels;

        $pdf = Pdf::loadView('exports.comerciales-reglas-pdf', compact('users', 'columnas', 'labels'))
            ->setPaper('a4', 'landscape');

        $filename = 'comerciales-reglas-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    // ─────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────
    private function getUsers(array $ids): \Illuminate\Database\Eloquent\Collection
    {
        return User::whereIn('id', $ids)
            ->with('asignacionesReglas.regla')
            ->orderBy('name')
            ->get();
    }

    private function valorColumna(User $user, string $key): string|int|float
    {
        $activas      = $user->asignacionesReglas->where('activa', true);
        $obligatorias = $user->asignacionesReglas->where('es_obligatoria', true);

        return match ($key) {
            'name'                 => $user->name,
            'email'                => $user->email,
            'num_reglas'           => $user->asignacionesReglas->count(),
            'reglas_nombres'       => $activas->map(fn ($a) => $a->regla?->nombre)->filter()->implode(', ') ?: '—',
            'reglas_obligatorias'  => $obligatorias->map(fn ($a) => $a->regla?->nombre)->filter()->implode(', ') ?: '—',
            'minimo_mensual_total' => (float) $user->asignacionesReglas->sum(fn ($a) => $a->regla?->minimo_mensual ?? 0),
            'comision_media'       => $activas->isEmpty()
                ? 0
                : (float) round($activas->avg(fn ($a) => $a->regla?->porcentaje_comision ?? 0), 2),
            default => '',
        };
    }
}
