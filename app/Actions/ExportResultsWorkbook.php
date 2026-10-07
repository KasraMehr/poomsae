<?php

namespace App\Actions;

use DOMDocument;
use DOMXPath;
use RuntimeException;
use Throwable;
use ZipArchive;

class ExportResultsWorkbook
{
    private const SPREADSHEET_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /** @param array{headers: list<string>, rows: list<list<int|string>>} $table */
    public function create(array $table): string
    {
        $path = tempnam(sys_get_temp_dir(), 'poomsae-results-');
        if ($path === false) {
            throw new RuntimeException('Unable to prepare the results workbook.');
        }
        $archive = new ZipArchive;
        $isOpen = false;
        try {
            if (! copy(resource_path('exports/results-template.xlsx'), $path)) {
                throw new RuntimeException('Unable to prepare the results workbook.');
            }
            if ($archive->open($path) !== true) {
                throw new RuntimeException('Unable to open the results workbook.');
            }
            $isOpen = true;
            $sheet = $this->document($archive, 'xl/worksheets/sheet1.xml');
            $xpath = new DOMXPath($sheet);
            $xpath->registerNamespace('s', self::SPREADSHEET_NAMESPACE);
            $sheetData = $xpath->query('/s:worksheet/s:sheetData')->item(0);
            $styles = [];
            foreach ($xpath->query('/s:worksheet/s:sheetData/s:row/s:c') as $cell) {
                $styles[$cell->getAttribute('r')] = $cell->getAttribute('s');
            }
            while ($sheetData->firstChild !== null) {
                $sheetData->removeChild($sheetData->firstChild);
            }
            $rows = [$table['headers'], ...($table['rows'] ?: [array_fill(0, count($table['headers']), '')])];
            foreach ($rows as $index => $values) {
                $rowNumber = $index + 1;
                $row = $sheet->createElementNS(self::SPREADSHEET_NAMESPACE, 'row');
                $row->setAttribute('r', (string) $rowNumber);
                $row->setAttribute('ht', $index === 0 ? '44' : '40');
                $row->setAttribute('customHeight', '1');
                foreach ($values as $column => $value) {
                    $letter = chr(65 + $column);
                    $styleRow = $index === 0 ? 1 : ($index % 2 === 1 ? 2 : 3);
                    $cell = $sheet->createElementNS(self::SPREADSHEET_NAMESPACE, 'c');
                    $cell->setAttribute('r', $letter.$rowNumber);
                    $cell->setAttribute('s', $styles[$letter.$styleRow] ?? '0');
                    $numeric = $index > 0 && $value !== '' && in_array($column, [0, 4, 9, 11, 12, 13], true) && is_numeric($value);
                    if ($numeric) {
                        $cell->appendChild($sheet->createElementNS(self::SPREADSHEET_NAMESPACE, 'v', (string) $value));
                    } else {
                        $cell->setAttribute('t', 'inlineStr');
                        $inline = $sheet->createElementNS(self::SPREADSHEET_NAMESPACE, 'is');
                        $text = $sheet->createElementNS(self::SPREADSHEET_NAMESPACE, 't');
                        $text->appendChild($sheet->createTextNode(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $value)));
                        $inline->appendChild($text);
                        $cell->appendChild($inline);
                    }
                    $row->appendChild($cell);
                }
                $sheetData->appendChild($row);
            }
            $reference = 'A1:P'.count($rows);
            $xpath->query('/s:worksheet/s:dimension')->item(0)?->setAttribute('ref', $reference);
            foreach ($xpath->query('/s:worksheet/s:sheetViews/s:sheetView') as $sheetView) {
                $sheetView->setAttribute('rightToLeft', '1');
                $sheetView->setAttribute('zoomScale', '85');
            }
            $this->writeDocument($archive, 'xl/worksheets/sheet1.xml', $sheet);

            $filterTable = $this->document($archive, 'xl/tables/table1.xml');
            $filterTable->documentElement->setAttribute('ref', $reference);
            foreach ($filterTable->getElementsByTagNameNS(self::SPREADSHEET_NAMESPACE, 'autoFilter') as $filter) {
                $filter->setAttribute('ref', $reference);
            }
            $this->writeDocument($archive, 'xl/tables/table1.xml', $filterTable);
            if (! $archive->close()) {
                throw new RuntimeException('Unable to save the results workbook.');
            }
            $isOpen = false;

            return $path;
        } catch (Throwable $exception) {
            if ($isOpen) {
                $archive->close();
            }
            if (is_file($path)) {
                unlink($path);
            }
            throw $exception;
        }
    }

    private function document(ZipArchive $archive, string $name): DOMDocument
    {
        $contents = $archive->getFromName($name);
        $document = new DOMDocument;
        if ($contents === false || ! $document->loadXML($contents, LIBXML_NONET)) {
            throw new RuntimeException('Invalid results workbook template.');
        }

        return $document;
    }

    private function writeDocument(ZipArchive $archive, string $name, DOMDocument $document): void
    {
        if (! $archive->addFromString($name, $document->saveXML())) {
            throw new RuntimeException('Unable to write the results workbook.');
        }
    }
}
