<?php

namespace App\Services;

use App\Models\GovAgency;
use App\Models\Implementation;
use App\Models\Municipality;
use App\Models\RcspBarangay;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImplanWorkbookExporter
{
    private const TEMPLATE = 'assets/IMPLANs/ELCAC-IMPLAN_BSC.xlsx';

    private const LOGO = 'assets/IMPLANs/ELCAC.png';

    public function download(Municipality $municipality, Collection $implans): StreamedResponse
    {
        $spreadsheet = $this->build($municipality, $implans);
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '-', $municipality->name) ?: 'Municipality';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, "IMPLAN-{$safeName}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function build(Municipality $municipality, Collection $implans): Spreadsheet
    {
        $templatePath = public_path(self::TEMPLATE);
        abort_unless(is_file($templatePath), 500, 'The official IMPLAN workbook template is missing.');

        $spreadsheet = IOFactory::createReader('Xlsx')->load($templatePath);
        $sheet = $spreadsheet->getSheet(0);

        while ($spreadsheet->getSheetCount() > 1) {
            $spreadsheet->removeSheetByIndex(1);
        }

        $sheet->setTitle($this->worksheetTitle($municipality->name));
        $this->prepareOfficialSheet($sheet, $municipality, max(1, $implans->count()));

        $areaNames = $this->areaNames($implans);
        $agenciesById = GovAgency::query()
            ->whereIn('id', $implans->flatMap(fn (Implementation $implan) => $implan->agencies ?? [])->unique())
            ->get()
            ->keyBy('id');
        foreach ($implans->values() as $index => $implan) {
            $this->writeImplanRow($sheet, 5 + $index, $implan, $areaNames, $agenciesById);
        }

        $lastRow = max(5, 4 + $implans->count());
        $sheet->getPageSetup()->setPrintArea("A1:K{$lastRow}");
        $sheet->setSelectedCell('A1');
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function prepareOfficialSheet(Worksheet $sheet, Municipality $municipality, int $rowCount): void
    {
        foreach ($sheet->getMergeCells() as $range) {
            $firstCell = explode(':', $range)[0];
            if ((int) preg_replace('/\D/', '', $firstCell) >= 5) {
                $sheet->unmergeCells($range);
            }
        }

        $sheet->insertNewColumnBefore('J', 1);
        $sheet->getColumnDimension('J')->setWidth($sheet->getColumnDimension('I')->getWidth());

        foreach (range('A', 'K') as $column) {
            $sheet->setCellValue("{$column}3", null);
            $sheet->setCellValue("{$column}4", null);
        }

        foreach (['A3:A4', 'B3:B4', 'C3:C4', 'D3:D4', 'E3:E4', 'F3:F4', 'H3:H4', 'I3:I4', 'J3:J4', 'K3:K4'] as $range) {
            if (! in_array($range, $sheet->getMergeCells(), true)) {
                $sheet->mergeCells($range);
            }
        }

        $headers = [
            'A3' => 'Issues and Concerns to be Addressed',
            'B3' => 'Program/Project/Activity',
            'C3' => 'Target Area',
            'D3' => 'Target Beneficiaries',
            'E3' => 'Expected Results/Outcome',
            'F3' => 'Responsible Agency',
            'G3' => 'Resources Needed',
            'G4' => '(Funding)',
            'H3' => 'Support Needed',
            'I3' => 'Duration',
            'J3' => 'Action Taken',
            'K3' => 'Remarks',
        ];

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $headerStyle = [
            'font' => ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F497D']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ];
        $sheet->getStyle('A3:K4')->applyFromArray($headerStyle);
        $sheet->getRowDimension(3)->setRowHeight(50.3);
        $sheet->getRowDimension(4)->setRowHeight(14.4);

        $this->createHeader($sheet, $municipality);

        $sheet->getStyle('A5:K5')->applyFromArray($this->bodyStyle());
        for ($row = 5; $row < 5 + $rowCount; $row++) {
            $sheet->duplicateStyle($sheet->getStyle('A5:K5'), "A{$row}:K{$row}");
            $sheet->getRowDimension($row)->setRowHeight(66);
        }
        $sheet->getStyle('A5:K1000')->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle('A5:K1000')->getAlignment()->setWrapText(true);
        $sheet->getStyle('A5:K1000')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        for ($row = 5; $row <= 1000; $row++) {
            foreach (range('A', 'K') as $column) {
                $sheet->setCellValue("{$column}{$row}", null);
            }
        }
        $sheet->getPageSetup()
            ->setOrientation('landscape')
            ->setPaperSize(9)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()->setLeft(0.25)->setRight(0.25)->setTop(0.75)->setBottom(0.75);
    }

    private function createHeader(Worksheet $sheet, Municipality $municipality): void
    {
        $sheet->mergeCells('B1:K2');
        $sheet->getStyle('A1:K2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('002060');
        $sheet->setCellValue('B1', "BASIC SERVICES CLUSTER IMPLEMENTATION PLAN\nMUNICIPALITY OF ".mb_strtoupper($municipality->name));
        $sheet->getStyle('B1')->applyFromArray([
            'font' => ['name' => 'Arial Narrow', 'size' => 20, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(15);
        $sheet->getRowDimension(2)->setRowHeight(63.8);

        $drawing = new Drawing();
        $drawing->setName('ELCAC Logo');
        $drawing->setDescription('Ending Local Communist Armed Conflict logo');
        $drawing->setPath(public_path(self::LOGO));
        $drawing->setHeight(75);
        $drawing->setCoordinates('A1');
        $drawing->setWorksheet($sheet);
    }

    private function writeImplanRow(
        Worksheet $sheet,
        int $row,
        Implementation $implan,
        Collection $areaNames,
        Collection $agenciesById
    ): void
    {
        $agencyNames = collect($implan->agencies ?? [])->map(function ($agencyId) use ($implan, $agenciesById): string {
            $response = $implan->responses->firstWhere('gov_agency_id', (int) $agencyId);
            $name = $response?->govAgency?->acronym
                ?? $agenciesById->get((int) $agencyId)?->acronym
                ?? "Agency #{$agencyId}";
            $status = ucfirst($response?->response_status ?? 'pending');

            return "{$name} ({$status})";
        })->implode("\n");

        $sheet->fromArray([
            $implan->issues,
            $implan->program,
            collect($implan->target_areas ?? [])->map(fn ($id) => $areaNames[(int) $id] ?? "Area #{$id}")->implode(', '),
            $implan->beneficiaries,
            $implan->outcome,
            $agencyNames,
            $implan->resources,
            $implan->support,
            $implan->duration,
            $this->responseValue($implan, 'action_taken'),
            $this->responseValue($implan, 'remarks'),
        ], null, "A{$row}");
    }

    private function responseValue(Implementation $implan, string $field): string
    {
        $values = collect();

        $responsesByAgency = $implan->responses->keyBy('gov_agency_id');

        foreach ($implan->agencies ?? [] as $agencyId) {
            $response = $responsesByAgency->get((int) $agencyId);

            if (! $response) {
                continue;
            }

            if (filled($response->{$field})) {
                $agency = $response->govAgency?->acronym ?? 'Agency';
                $values->push("{$agency} — {$response->{$field}}");
            }
        }

        return $values->implode("\n");
    }

    private function areaNames(Collection $implans): Collection
    {
        $ids = $implans->flatMap(fn (Implementation $implan) => $implan->target_areas ?? [])->unique()->values();

        return RcspBarangay::with('barangay')->whereIn('id', $ids)->get()
            ->mapWithKeys(fn ($row) => [(int) $row->id => $row->barangay?->name ?? "Barangay #{$row->barangay_id}"]);
    }

    private function bodyStyle(): array
    {
        return [
            'font' => ['name' => 'Arial', 'size' => 10, 'color' => ['rgb' => '000000']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E9EDF4']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
        ];
    }

    private function worksheetTitle(string $municipality): string
    {
        $title = preg_replace('~[\\\\/?*\[\]:]~', '-', $municipality) ?: 'IMPLAN';

        return mb_substr($title, 0, 31);
    }
}
