<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SimpleXlsxWriter;
use ZipArchive;

/**
 * Gerador mínimo de .xlsx: estrutura do pacote, XML válido e conteúdo como texto.
 */
final class SimpleXlsxWriterTest extends TestCase
{
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        if (!SimpleXlsxWriter::isSupported()) {
            $this->markTestSkipped('ext-zip não disponível.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $f) @unlink($f);
    }

    private function open(string $bin): ZipArchive
    {
        $f = tempnam(sys_get_temp_dir(), 'xlsxtest');
        $this->tmpFiles[] = $f;
        file_put_contents($f, $bin);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($f) === true, 'arquivo deve ser um zip válido');
        return $zip;
    }

    public function testColumnLetter(): void
    {
        $this->assertSame('A', SimpleXlsxWriter::columnLetter(0));
        $this->assertSame('Z', SimpleXlsxWriter::columnLetter(25));
        $this->assertSame('AA', SimpleXlsxWriter::columnLetter(26));
        $this->assertSame('AZ', SimpleXlsxWriter::columnLetter(51));
        $this->assertSame('BA', SimpleXlsxWriter::columnLetter(52));
    }

    public function testPacoteTemPartesObrigatoriasEXmlValido(): void
    {
        $zip = $this->open(SimpleXlsxWriter::build(['Grupo', 'Nome'], [['G1', 'Ana']], 'Contatos'));
        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml'] as $part) {
            $xml = $zip->getFromName($part);
            $this->assertNotFalse($xml, "parte ausente: $part");
            $this->assertNotFalse(@simplexml_load_string($xml), "XML inválido: $part");
        }
        $this->assertStringContainsString('name="Contatos"', $zip->getFromName('xl/workbook.xml'));
        $zip->close();
    }

    public function testConteudoComAcentoCaracteresEspeciaisEFormula(): void
    {
        $rows = [
            ['Família & Amigos <3', 'José "Zé"', '+55 (11) 98765-4321'],
            ['=HYPERLINK("x")', "Linha\x01ctrl", ''],
        ];
        $zip = $this->open(SimpleXlsxWriter::build(['Grupo', 'Nome', 'Telefone'], $rows));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $xml = simplexml_load_string($sheet);
        $this->assertNotFalse($xml);
        $xml->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $texts = array_map('strval', $xml->xpath('//m:c/m:is/m:t'));
        $this->assertSame(['Grupo', 'Nome', 'Telefone', 'Família & Amigos <3', 'José "Zé"', '+55 (11) 98765-4321', '=HYPERLINK("x")', 'Linhactrl', ''], $texts);

        // Nenhuma célula é fórmula nem número: tudo texto
        $this->assertCount(0, $xml->xpath('//m:c/m:f'));
        foreach ($xml->xpath('//m:c') as $c) {
            $this->assertSame('inlineStr', (string) $c['t']);
        }
        $this->assertSame('A1:C3', (string) $xml->autoFilter['ref']);
    }

    public function testPlanilhaSoComCabecalho(): void
    {
        $zip = $this->open(SimpleXlsxWriter::build(['A', 'B'], []));
        $xml = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->close();
        $this->assertCount(1, $xml->sheetData->row);
        $this->assertSame('A1:B1', (string) $xml->autoFilter['ref']);
    }

    public function testNomeDaAbaSanitizado(): void
    {
        $zip = $this->open(SimpleXlsxWriter::build(['A'], [], 'Grupos: [teste]/2026 com um nome bem comprido demais'));
        $wb = $zip->getFromName('xl/workbook.xml');
        $zip->close();
        $xml = simplexml_load_string($wb);
        $name = (string) $xml->sheets->sheet['name'];
        $this->assertLessThanOrEqual(31, mb_strlen($name));
        $this->assertDoesNotMatchRegularExpression('~[\[\]:*?/\\\\]~', $name);
    }
}
