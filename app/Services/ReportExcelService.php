<?php

namespace App\Services;

use App\Models\IncomingLetter;
use App\Models\InternshipCertificate;
use App\Models\OutgoingLetter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

class ReportExcelService
{
    private const STATUS_LABELS = [
        'baru_diterima' => 'Baru Diterima',
        'menunggu_pemeriksaan' => 'Menunggu Pemeriksaan',
        'selesai' => 'Selesai',
    ];

    private const PRIORITY_LABELS = [
        'biasa' => 'Biasa',
        'segera' => 'Segera',
    ];

    /**
     * @param  iterable<int, IncomingLetter>  $letters
     * @param  array{total: int, baru_diterima: int, menunggu_pemeriksaan: int, selesai: int}  $summary
     * @param  array{period: string, division: string, exported_by: string, exported_at: string}  $metadata
     */
    public function writeIncoming(iterable $letters, array $summary, array $metadata): string
    {
        $headerRow = 15;
        $firstDataRow = $headerRow + 1;
        $options = $this->options([7, 18, 22, 18, 24, 40, 24, 14, 26]);
        $writer = new Writer($options);
        $path = $this->temporaryPath();

        try {
            $writer->openToFile($path);
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Surat Masuk');
            $sheet->setSheetView((new SheetView)->setFreezeRow($firstDataRow));
            $sheet->setAutoFilter(new AutoFilter(0, $headerRow, 8, max($headerRow, $headerRow + $summary['total'])));

            $this->writeHeading($writer, 'LAPORAN SURAT MASUK', $metadata, 9);
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['RINGKASAN'], $this->sectionStyle()));
            $writer->addRow(Row::fromValues(['Total Surat', $summary['total']], $this->informationStyle()));
            $writer->addRow(Row::fromValues(['Baru Diterima', $summary['baru_diterima']], $this->informationStyle()));
            $writer->addRow(Row::fromValues(['Menunggu Pemeriksaan', $summary['menunggu_pemeriksaan']], $this->informationStyle()));
            $writer->addRow(Row::fromValues(['Selesai', $summary['selesai']], $this->informationStyle()));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([
                'No',
                'Agenda',
                'Nomor Surat',
                'Tanggal Diterima',
                'Pengirim',
                'Perihal',
                'Divisi Tujuan',
                'Prioritas',
                'Status',
            ], $this->tableHeaderStyle()));

            foreach ($letters as $index => $letter) {
                $writer->addRow(Row::fromValues([
                    $index + 1,
                    $letter->agenda_number,
                    $letter->letter_number ?: '-',
                    $letter->received_date?->format('d-m-Y') ?? '-',
                    $letter->sender_name,
                    $letter->subject,
                    $letter->destinationDivision?->name ?? 'Belum Ditentukan',
                    self::PRIORITY_LABELS[$letter->priority] ?? $letter->priority,
                    self::STATUS_LABELS[$letter->status] ?? $letter->status,
                ], $this->tableBodyStyle()));
            }

            $writer->close();

            return $path;
        } catch (Throwable $exception) {
            $this->closeQuietly($writer);
            (new Filesystem)->delete($path);

            throw $exception;
        }
    }

    /**
     * @param  iterable<int, OutgoingLetter>  $letters
     * @param  array{total: int, division_count: int}  $summary
     * @param  array{period: string, division: string, exported_by: string, exported_at: string}  $metadata
     */
    public function writeOutgoing(iterable $letters, array $summary, array $metadata): string
    {
        $headerRow = 12;
        $firstDataRow = $headerRow + 1;
        $options = $this->options([7, 19, 23, 18, 30, 42, 24, 24]);
        $writer = new Writer($options);
        $path = $this->temporaryPath();

        try {
            $writer->openToFile($path);
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Surat Keluar');
            $sheet->setSheetView((new SheetView)->setFreezeRow($firstDataRow));
            $sheet->setAutoFilter(new AutoFilter(0, $headerRow, 7, max($headerRow, $headerRow + $summary['total'])));

            $this->writeHeading($writer, 'LAPORAN SURAT KELUAR', $metadata, 8);
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues(['RINGKASAN'], $this->sectionStyle()));
            $writer->addRow(Row::fromValues(['Total Surat Keluar', $summary['total']], $this->informationStyle()));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([
                'No',
                'Kode Sistem',
                'Nomor Surat',
                'Tanggal Surat',
                'Tujuan',
                'Perihal',
                'Divisi',
                'Dicatat Oleh',
            ], $this->tableHeaderStyle()));

            foreach ($letters as $index => $letter) {
                $writer->addRow(Row::fromValues([
                    $index + 1,
                    $letter->reference_code,
                    $letter->letter_number,
                    $letter->letter_date?->format('d-m-Y') ?? '-',
                    $letter->recipient_name,
                    $letter->subject,
                    $letter->division?->name ?? '-',
                    $letter->creator?->name ?? '-',
                ], $this->tableBodyStyle()));
            }

            $writer->close();

            return $path;
        } catch (Throwable $exception) {
            $this->closeQuietly($writer);
            (new Filesystem)->delete($path);

            throw $exception;
        }
    }

    /**
     * @param  iterable<int, InternshipCertificate>  $certificates
     * @param  array{total: int}  $summary
     * @param  array{year: string, search: string, exported_by: string, exported_at: string}  $metadata
     */
    public function writeCertificates(iterable $certificates, array $summary, array $metadata): string
    {
        $headerRow = 12;
        $firstDataRow = $headerRow + 1;
        $options = $this->options([7, 28, 32, 32, 18, 18]);
        $writer = new Writer($options);
        $path = $this->temporaryPath();

        try {
            $writer->openToFile($path);
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Sertifikat');
            $sheet->setSheetView((new SheetView)->setFreezeRow($firstDataRow));
            $sheet->setAutoFilter(new AutoFilter(0, $headerRow, 5, max($headerRow, $headerRow + $summary['total'])));

            $this->writeCertificateHeading($writer, $summary, $metadata);
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([
                'No',
                'Nama Peserta',
                'Asal Institusi',
                'Program Studi / Jurusan',
                'Tanggal Mulai',
                'Tanggal Selesai',
            ], $this->tableHeaderStyle()));

            foreach ($certificates as $index => $certificate) {
                $writer->addRow(Row::fromValues([
                    $index + 1,
                    $certificate->participant_name,
                    $certificate->institution_name,
                    $certificate->major_name,
                    $certificate->start_date?->format('d/m/Y') ?? '-',
                    $certificate->end_date?->format('d/m/Y') ?? '-',
                ], $this->tableBodyStyle()));
            }

            $writer->close();

            return $path;
        } catch (Throwable $exception) {
            $this->closeQuietly($writer);
            (new Filesystem)->delete($path);

            throw $exception;
        }
    }

    /**
     * @param  array{total: int}  $summary
     * @param  array{year: string, search: string, exported_by: string, exported_at: string}  $metadata
     */
    private function writeCertificateHeading(Writer $writer, array $summary, array $metadata): void
    {
        $writer->addRow(Row::fromValues(['SIRAPI'], $this->brandStyle()));
        $writer->addRow(Row::fromValues(['Sistem Arsip Jawa Pos Radar Kediri'], $this->informationStyle()));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['LAPORAN ARSIP SERTIFIKAT'], $this->titleStyle()));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Tahun', $metadata['year']], $this->informationStyle()));
        $writer->addRow(Row::fromValues(['Pencarian', $metadata['search']], $this->informationStyle()));
        $writer->addRow(Row::fromValues(['Total Data', $summary['total']], $this->informationStyle()));
        $writer->addRow(Row::fromValues(['Diekspor Oleh', $metadata['exported_by']], $this->informationStyle()));
        $writer->addRow(Row::fromValues(['Tanggal Export', $metadata['exported_at']], $this->informationStyle()));

        $options = $writer->getOptions();
        $options->mergeCells(0, 1, 5, 1);
        $options->mergeCells(0, 2, 5, 2);
        $options->mergeCells(0, 4, 5, 4);
    }

    /**
     * @param  array{period: string, division: string, exported_by: string, exported_at: string}  $metadata
     */
    private function writeHeading(Writer $writer, string $title, array $metadata, int $columnCount): void
    {
        $writer->addRow(Row::fromValues(['RADAR KEDIRI'], $this->brandStyle()));
        $writer->addRow(Row::fromValues([$title], $this->titleStyle()));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Periode', $metadata['period']], $this->informationStyle()));
        $writer->addRow(Row::fromValues(['Divisi', $metadata['division']], $this->informationStyle()));
        $writer->addRow(Row::fromValues(['Diekspor Oleh', $metadata['exported_by']], $this->informationStyle()));
        $writer->addRow(Row::fromValues(['Tanggal Export', $metadata['exported_at']], $this->informationStyle()));

        $options = $writer->getOptions();
        $lastColumn = $columnCount - 1;
        $options->mergeCells(0, 1, $lastColumn, 1);
        $options->mergeCells(0, 2, $lastColumn, 2);
        $options->mergeCells(0, 9, $lastColumn, 9);
    }

    /** @param array<int, float|int> $widths */
    private function options(array $widths): Options
    {
        $options = new Options;
        $options->SHOULD_CREATE_NEW_SHEETS_AUTOMATICALLY = false;

        foreach ($widths as $column => $width) {
            $options->setColumnWidth((float) $width, $column + 1);
        }

        return $options;
    }

    private function temporaryPath(): string
    {
        $directory = storage_path('app/private');
        (new Filesystem)->ensureDirectoryExists($directory);

        return $directory.DIRECTORY_SEPARATOR.'report-'.Str::uuid().'.xlsx';
    }

    private function brandStyle(): Style
    {
        return (new Style)
            ->setFontBold()
            ->setFontSize(13)
            ->setFontColor('FF1F4E78')
            ->setCellAlignment(CellAlignment::CENTER);
    }

    private function titleStyle(): Style
    {
        return (new Style)
            ->setFontBold()
            ->setFontSize(17)
            ->setFontColor('FF17365D')
            ->setCellAlignment(CellAlignment::CENTER);
    }

    private function informationStyle(): Style
    {
        return (new Style)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText();
    }

    private function sectionStyle(): Style
    {
        return (new Style)
            ->setFontBold()
            ->setFontColor('FF17365D')
            ->setBackgroundColor('FFD9EAF7');
    }

    private function tableHeaderStyle(): Style
    {
        return (new Style)
            ->setFontBold()
            ->setFontColor(Color::WHITE)
            ->setBackgroundColor('FF1F4E78')
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText()
            ->setBorder($this->tableBorder());
    }

    private function tableBodyStyle(): Style
    {
        return (new Style)
            ->setCellVerticalAlignment(CellVerticalAlignment::TOP)
            ->setShouldWrapText()
            ->setBorder($this->tableBorder());
    }

    private function tableBorder(): Border
    {
        return new Border(
            new BorderPart(Border::TOP, 'FFD6DEE8', Border::WIDTH_THIN),
            new BorderPart(Border::RIGHT, 'FFD6DEE8', Border::WIDTH_THIN),
            new BorderPart(Border::BOTTOM, 'FFD6DEE8', Border::WIDTH_THIN),
            new BorderPart(Border::LEFT, 'FFD6DEE8', Border::WIDTH_THIN),
        );
    }

    private function closeQuietly(Writer $writer): void
    {
        try {
            $writer->close();
        } catch (Throwable) {
            // The writer may not have opened yet.
        }
    }
}
