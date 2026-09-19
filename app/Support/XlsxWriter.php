<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;
use XMLWriter;
use ZipArchive;

/**
 * كاتب ملفات Excel (.xlsx) خفيف بلا أي مكتبة خارجية — يُنشئ حزمة OOXML
 * دنيا (ورقة واحدة بسلاسل مضمّنة) مع دعم الاتجاه من اليمين لليسار.
 *
 * كل صف = مصفوفة قيم؛ الأرقام تُكتب كأرقام والنصوص كسلاسل UTF-8.
 */
final class XlsxWriter
{
    /**
     * @param  array<int, array<int, string|int|float|null>>  $rows
     * @param  array<int, float>  $columnWidths  عرض كل عمود بالحروف
     */
    public static function download(string $filename, array $rows, array $columnWidths = []): StreamedResponse
    {
        $content = self::build($rows, $columnWidths);

        return response()->streamDownload(function () use ($content) {
            echo $content;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Length' => (string) strlen($content),
        ]);
    }

    /**
     * @param  array<int, array<int, string|int|float|null>>  $rows
     * @param  array<int, float>  $columnWidths
     */
    public static function build(array $rows, array $columnWidths = [], string $sheetName = 'كشف'): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'xlsx');

        $zip = new ZipArchive;
        $zip->open($temp, ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbook($sheetName));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet($rows, $columnWidths));

        $zip->close();

        $content = (string) file_get_contents($temp);
        @unlink($temp);

        return $content;
    }

    /** @param array<int, array<int, string|int|float|null>> $rows */
    private static function sheet(array $rows, array $columnWidths): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8', 'yes');
        $xml->startElement('worksheet');
        $xml->writeAttribute('xmlns', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $xml->startElement('sheetViews');
        $xml->startElement('sheetView');
        $xml->writeAttribute('rightToLeft', '1');
        $xml->writeAttribute('workbookViewId', '0');
        $xml->endElement();
        $xml->endElement();

        if ($columnWidths !== []) {
            $xml->startElement('cols');

            foreach ($columnWidths as $index => $width) {
                $xml->startElement('col');
                $xml->writeAttribute('min', (string) ($index + 1));
                $xml->writeAttribute('max', (string) ($index + 1));
                $xml->writeAttribute('width', (string) $width);
                $xml->writeAttribute('customWidth', '1');
                $xml->endElement();
            }

            $xml->endElement();
        }

        $xml->startElement('sheetData');

        foreach ($rows as $rowIndex => $row) {
            $xml->startElement('row');
            $xml->writeAttribute('r', (string) ($rowIndex + 1));

            foreach (array_values($row) as $columnIndex => $value) {
                $xml->startElement('c');
                $xml->writeAttribute('r', self::columnLetter($columnIndex).($rowIndex + 1));

                if (is_int($value) || is_float($value)) {
                    $xml->writeAttribute('t', 'n');
                    $xml->writeElement('v', (string) $value);
                } else {
                    $xml->writeAttribute('t', 'inlineStr');
                    $xml->startElement('is');
                    $xml->startElement('t');
                    $xml->writeAttribute('xml:space', 'preserve');
                    $xml->text((string) ($value ?? ''));
                    $xml->endElement();
                    $xml->endElement();
                }

                $xml->endElement();
            }

            $xml->endElement();
        }

        $xml->endElement();
        $xml->endElement();

        return $xml->outputMemory();
    }

    private static function columnLetter(int $index): string
    {
        $letter = '';

        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $letter = chr($i % 26 + 65).$letter;
        }

        return $letter;
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private static function workbook(string $sheetName): string
    {
        $name = htmlspecialchars($sheetName, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$name.'" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>';
    }
}
