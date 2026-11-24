<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use LeadImportRules;

/**
 * Importação de leads por planilha (LeadImportRules): planilha modelo,
 * exportação de contatos do Apollo, CSV do Excel (";" e Windows-1252) e .xlsx.
 */
final class LeadImportRulesTest extends TestCase
{
    public function testPlanilhaModeloTemCabecalhosEAbreNoExcel(): void
    {
        $csv = LeadImportRules::templateCsv();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'BOM para o Excel reconhecer UTF-8');
        $rows = LeadImportRules::parseCsv($csv);
        $this->assertSame(LeadImportRules::TEMPLATE_HEADERS, $rows[0]);
        // A própria linha de exemplo do modelo é um lead válido.
        $r = LeadImportRules::extract($rows);
        $this->assertCount(1, $r['leads']);
        $this->assertSame('Maria Souza', $r['leads'][0]['name']);
        $this->assertSame('41999990000', $r['leads'][0]['phone']);
        $this->assertSame('Diretora Comercial', $r['leads'][0]['job_title']);
    }

    public function testExportacaoDoApolloSemMudarNada(): void
    {
        $csv = "First Name,Last Name,Title,Company,Email,Email Status,Mobile Phone,Corporate Phone,Person Linkedin Url,Website,City,State,Country\n"
             . "João,Silva,CEO,Clínica Boa Saúde,joao@boasaude.com.br,Verified,,+55 41 3333-4444,http://www.linkedin.com/in/joaosilva,http://boasaude.com.br,Curitiba,Paraná,Brazil\n"
             . "Ana,Lima,Sócia,\"Lima, Costa & Cia\",,,+55 41 98888-7777,,,,,,\n";
        $r = LeadImportRules::extract(LeadImportRules::parseCsv($csv));
        $this->assertSame([], $r['errors']);
        $this->assertCount(2, $r['leads']);
        $j = $r['leads'][0];
        $this->assertSame('João Silva', $j['name']);
        $this->assertSame('joao@boasaude.com.br', $j['email']);
        $this->assertSame('554133334444', $j['phone'], 'sem celular, usa o telefone da empresa');
        $this->assertSame('CEO', $j['job_title']);
        $this->assertSame('Clínica Boa Saúde', $j['company']);
        $this->assertSame('http://www.linkedin.com/in/joaosilva', $j['linkedin']);
        $this->assertSame('Curitiba', $j['city']);
        $a = $r['leads'][1];
        $this->assertSame('5541988887777', $a['phone'], 'celular tem prioridade');
        $this->assertSame('Lima, Costa & Cia', $a['company'], 'vírgula entre aspas não quebra a coluna');
        $this->assertNull($a['email']);
    }

    public function testCsvDoExcelEmPortuguesComPontoEVirgulaEWindows1252(): void
    {
        $csv = mb_convert_encoding("Nome;E-mail;Telefone;Empresa;Cargo;Observações\r\nJosé Araújo;jose@x.com.br;(41) 99999-1111;Padaria São João;Proprietário;Indicação do Pedro\r\n", 'Windows-1252', 'UTF-8');
        $this->assertFalse(mb_check_encoding($csv, 'UTF-8'));
        $r = LeadImportRules::extract(LeadImportRules::parseCsv($csv));
        $this->assertCount(1, $r['leads']);
        $this->assertSame('José Araújo', $r['leads'][0]['name']);
        $this->assertSame('Padaria São João', $r['leads'][0]['company']);
        $this->assertSame('Proprietário', $r['leads'][0]['job_title']);
        $this->assertSame('Indicação do Pedro', $r['leads'][0]['notes']);
    }

    public function testLinhasInvalidasViramErroComNumeroDaLinha(): void
    {
        $csv = "Nome;E-mail;Telefone;LinkedIn\n"
             . ";sem.nome@x.com;;\n"           // linha 2: sem nome
             . "Sem Contato;;;\n"              // linha 3: sem contato
             . "Email Ruim;nao-e-email;;\n"    // linha 4: e-mail inválido e nada mais
             . "Email Ruim Com Fone;nao-e-email;41999998888;\n" // linha 5: importa sem e-mail (aviso)
             . ";;;\n"                          // linha 6: vazia, ignorada
             . "Só LinkedIn;;;linkedin.com/in/x\n"; // linha 7: ok
        $r = LeadImportRules::extract(LeadImportRules::parseCsv($csv));
        $this->assertSame([2, 3, 4], array_column($r['errors'], 'line'));
        $this->assertSame('Sem nome.', $r['errors'][0]['message']);
        $this->assertStringContainsString('Sem e-mail, telefone ou LinkedIn', $r['errors'][1]['message']);
        $this->assertSame([5, 7], array_column($r['leads'], 'line'));
        $this->assertNull($r['leads'][0]['email']);
        $this->assertSame(5, $r['warnings'][0]['line']);
    }

    public function testPlanilhaSemColunasObrigatorias(): void
    {
        $r = LeadImportRules::extract(LeadImportRules::parseCsv("Empresa;Cidade\nAcme;Curitiba\n"));
        $this->assertTrue($r['missing_headers']);
        $r = LeadImportRules::extract(LeadImportRules::parseCsv("Nome;Cidade\nAna;Curitiba\n"));
        $this->assertTrue($r['missing_headers'], 'sem coluna de contato');
        $this->assertFalse(LeadImportRules::extract([])['missing_headers']);
    }

    public function testCabecalhosSaoReconhecidosSemAcentoEMaiusculas(): void
    {
        $map = LeadImportRules::mapHeaders(['NOME', 'e-mail', 'Telefone ', 'CARGO', 'Observações', 'Coluna estranha']);
        $this->assertSame([0], $map['name']);
        $this->assertSame([1], $map['email']);
        $this->assertSame([2], $map['phone']);
        $this->assertSame([3], $map['job_title']);
        $this->assertSame([4], $map['notes']);
        $this->assertArrayNotHasKey('company', $map);
        $this->assertSame("\t", LeadImportRules::detectDelimiter("Nome\tEmail"));
        $this->assertSame(',', LeadImportRules::detectDelimiter('a,b,c'));
        $this->assertSame(';', LeadImportRules::detectDelimiter('a'));
    }

    public function testLimiteDeLinhas(): void
    {
        $rows = [['Nome', 'Telefone']];
        for ($i = 0; $i < LeadImportRules::MAX_ROWS + 5; $i++) $rows[] = ['Lead ' . $i, '4199999' . str_pad((string) $i, 4, '0', STR_PAD_LEFT)];
        $r = LeadImportRules::extract($rows);
        $this->assertCount(LeadImportRules::MAX_ROWS, $r['leads']);
        $this->assertStringContainsString('Limite', end($r['errors'])['message']);
    }

    public function testLeXlsx(): void
    {
        $path = $this->makeXlsx([
            ['Nome', 'E-mail', 'Cargo', 'Telefone'],
            ['Carla Dias', 'carla@x.com', 'Diretora', '41988887777'],
            ['Bruno', '', '', '41977776666'],
        ]);
        try {
            $rows = LeadImportRules::parseXlsx($path);
            $this->assertSame(['Nome', 'E-mail', 'Cargo', 'Telefone'], $rows[0]);
            $r = LeadImportRules::extract($rows);
            $this->assertCount(2, $r['leads']);
            $this->assertSame('Carla Dias', $r['leads'][0]['name']);
            $this->assertSame('Diretora', $r['leads'][0]['job_title']);
            $this->assertSame('41977776666', $r['leads'][1]['phone']);
        } finally {
            @unlink($path);
        }
        $bad = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($bad, 'não é zip');
        $this->assertNull(LeadImportRules::parseXlsx($bad));
        @unlink($bad);
    }

    public function testColumnIndexEBriefingNotes(): void
    {
        $this->assertSame(0, LeadImportRules::columnIndex('A'));
        $this->assertSame(25, LeadImportRules::columnIndex('Z'));
        $this->assertSame(26, LeadImportRules::columnIndex('AA'));
        $this->assertSame(
            'Cargo: CEO | Empresa: Acme | LinkedIn: in/x | Local: Curitiba/PR | Veio do evento',
            LeadImportRules::briefingNotes(['job_title' => 'CEO', 'company' => 'Acme', 'linkedin' => 'in/x', 'city' => 'Curitiba', 'state' => 'PR', 'notes' => 'Veio do evento'])
        );
        $this->assertNull(LeadImportRules::briefingNotes([]));
    }

    /** Monta um .xlsx mínimo (strings compartilhadas + célula numérica). */
    private function makeXlsx(array $rows): string
    {
        $strings = [];
        $sheetRows = '';
        foreach ($rows as $ri => $row) {
            $cells = '';
            foreach ($row as $ci => $v) {
                if ($v === '') continue;
                $ref = chr(65 + $ci) . ($ri + 1);
                if (ctype_digit((string) $v)) {
                    $cells .= "<c r=\"$ref\"><v>$v</v></c>";
                } else {
                    $strings[] = htmlspecialchars($v, ENT_XML1);
                    $cells .= "<c r=\"$ref\" t=\"s\"><v>" . (count($strings) - 1) . "</v></c>";
                }
            }
            $sheetRows .= '<row r="' . ($ri + 1) . '">' . $cells . '</row>';
        }
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Leads" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . implode('', array_map(fn($s) => "<si><t>$s</t></si>", $strings)) . '</sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $sheetRows . '</sheetData></worksheet>');
        $zip->close();
        return $path;
    }
}
