<?php

/**
 * Gerador mínimo de planilha .xlsx (Office Open XML) sem dependências
 * externas — usa apenas ZipArchive (ext-zip).
 *
 * Todas as células são gravadas como texto (inlineStr), então valores como
 * "+55 (11) ..." ou "=SOMA()" NUNCA viram fórmula/número no Excel.
 * A primeira linha (cabeçalho) fica em negrito, congelada e com autofiltro.
 */
class SimpleXlsxWriter
{
    /** Disponível no ambiente? (ext-zip carregada) */
    public static function isSupported(): bool
    {
        return class_exists('ZipArchive');
    }

    /**
     * Gera o .xlsx e retorna o conteúdo binário.
     *
     * @param array  $headers   lista de títulos das colunas
     * @param array  $rows      lista de linhas (cada linha = lista de valores)
     * @param string $sheetName nome da aba (máx. 31 caracteres, sem []:*?/\)
     */
    public static function build(array $headers, array $rows, string $sheetName = 'Planilha'): string
    {
        if (!self::isSupported()) {
            throw new RuntimeException('Extensão zip do PHP não está disponível.');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            throw new RuntimeException('Não foi possível criar o arquivo da planilha.');
        }

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::rootRels());
        $zip->addFromString('xl/workbook.xml', self::workbook(self::sheetName($sheetName)));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet($headers, $rows));
        $zip->close();

        $bin = file_get_contents($tmp);
        @unlink($tmp);
        return $bin;
    }

    /** Converte índice 0-based em letra de coluna (0 => A, 26 => AA). */
    public static function columnLetter(int $index): string
    {
        $s = '';
        $n = $index + 1;
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m) . $s;
            $n = intdiv($n - 1, 26);
        }
        return $s;
    }

    /** Escapa texto para XML, removendo caracteres de controle inválidos. */
    public static function xmlText($value): string
    {
        $v = (string) $value;
        if (!mb_check_encoding($v, 'UTF-8')) {
            $v = mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1');
        }
        $v = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $v) ?? '';
        // Limite de caracteres por célula no Excel
        if (mb_strlen($v) > 32767) $v = mb_substr($v, 0, 32767);
        return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function sheetName(string $name): string
    {
        $name = trim(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $name));
        if ($name === '') $name = 'Planilha';
        return mb_substr($name, 0, 31);
    }

    private static function sheet(array $headers, array $rows): string
    {
        $colCount = max(1, count($headers));
        foreach ($rows as $r) $colCount = max($colCount, count($r));

        // Largura das colunas estimada pelo maior conteúdo (limitada)
        $widths = array_fill(0, $colCount, 10);
        foreach (array_merge([$headers], $rows) as $r) {
            foreach (array_values($r) as $i => $v) {
                $len = mb_strlen((string) $v);
                $widths[$i] = min(60, max($widths[$i], $len + 2));
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<cols>';
        foreach ($widths as $i => $w) {
            $xml .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $xml .= '</cols><sheetData>';

        $xml .= self::row(1, $headers, 1);
        $rowNum = 2;
        foreach ($rows as $r) {
            $xml .= self::row($rowNum++, array_values($r), 0);
        }
        $xml .= '</sheetData>';

        $lastRow = max(1, count($rows) + 1);
        $xml .= '<autoFilter ref="A1:' . self::columnLetter($colCount - 1) . $lastRow . '"/>';
        $xml .= '</worksheet>';
        return $xml;
    }

    private static function row(int $num, array $values, int $style): string
    {
        $xml = '<row r="' . $num . '">';
        foreach (array_values($values) as $i => $v) {
            $ref = self::columnLetter($i) . $num;
            $s = $style ? ' s="' . $style . '"' : '';
            $xml .= '<c r="' . $ref . '" t="inlineStr"' . $s . '><is><t xml:space="preserve">' . self::xmlText($v) . '</t></is></c>';
        }
        return $xml . '</row>';
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbook(string $sheetName): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . self::xmlText($sheetName) . '" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    private static function styles(): string
    {
        // Estilo 0 = normal; estilo 1 = cabeçalho (negrito, fundo verde claro)
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9F2E3"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }
}
