<?php
/**
 * Regras puras da importação de leads por planilha (sem banco/HTTP).
 *
 * Aceita:
 *  - a planilha modelo do sistema (cabeçalhos em português);
 *  - a exportação de contatos do próprio Apollo (cabeçalhos em inglês:
 *    "First Name", "Title", "Company", "Email", "Mobile Phone",
 *    "Person Linkedin Url", "Website", "City"...);
 *  - CSV separado por ";" (Excel em português), "," ou tabulação, em UTF-8 ou
 *    no padrão do Excel do Windows (Windows-1252), e arquivos .xlsx.
 *
 * Cada linha precisa de um nome e de pelo menos um meio de contato
 * (e-mail, telefone ou LinkedIn).
 */
class LeadImportRules
{
    public const MAX_ROWS = 2000;

    /** Colunas da planilha modelo, na ordem. */
    public const TEMPLATE_HEADERS = ['Nome', 'Sobrenome', 'E-mail', 'Telefone', 'Empresa', 'Cargo', 'LinkedIn', 'Site', 'Cidade', 'Estado', 'Observações'];

    /**
     * Campo => cabeçalhos aceitos (já normalizados: minúsculo, sem acento,
     * só letras/números). A ordem define a prioridade quando a planilha traz
     * mais de uma coluna para o mesmo campo (ex.: vários telefones do Apollo).
     */
    private const ALIASES = [
        'name' => ['nome', 'nomecompleto', 'name', 'fullname', 'contato', 'lead'],
        'first_name' => ['primeironome', 'firstname'],
        'last_name' => ['sobrenome', 'lastname', 'ultimonome'],
        'email' => ['email', 'emailcorporativo', 'emailaddress', 'workemail', 'emailprofissional'],
        'phone' => ['celular', 'whatsapp', 'mobilephone', 'telefone', 'fone', 'phone', 'firstphone', 'workdirectphone', 'corporatephone', 'companyphone', 'homephone', 'otherphone'],
        'company' => ['empresa', 'company', 'companyname', 'organizacao', 'organization', 'razaosocial', 'nomedaempresa'],
        'job_title' => ['cargo', 'title', 'jobtitle', 'funcao', 'posicao'],
        'linkedin' => ['linkedin', 'personlinkedinurl', 'linkedinurl', 'linkedinpessoa'],
        'website' => ['site', 'website', 'companywebsite', 'sitedaempresa', 'url'],
        'city' => ['cidade', 'city'],
        'state' => ['estado', 'uf', 'state'],
        'notes' => ['observacoes', 'observacao', 'obs', 'notas', 'notes', 'anotacoes'],
    ];

    /** CSV da planilha modelo (com BOM, separado por ";" para abrir certo no Excel). */
    public static function templateCsv(): string
    {
        $rows = [
            self::TEMPLATE_HEADERS,
            ['Maria', 'Souza', 'maria.souza@empresa.com.br', '(41) 99999-0000', 'Empresa Exemplo Ltda', 'Diretora Comercial', 'https://www.linkedin.com/in/maria-souza', 'https://empresa.com.br', 'Curitiba', 'PR', 'Apague esta linha de exemplo antes de importar'],
        ];
        $out = "\xEF\xBB\xBF";
        foreach ($rows as $r) {
            $out .= implode(';', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $r)) . "\r\n";
        }
        return $out;
    }

    /** Normaliza um cabeçalho: minúsculo, sem acento, só letras e números. */
    public static function normalizeHeader(string $h): string
    {
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
        $h = mb_strtolower(trim($h), 'UTF-8');
        $h = strtr($h, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'ê' => 'e', 'è' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n']);
        return preg_replace('/[^a-z0-9]/', '', $h);
    }

    /**
     * Mapeia os cabeçalhos para os campos.
     * @return array<string,int[]> campo => índices das colunas (em ordem de prioridade)
     */
    public static function mapHeaders(array $headers): array
    {
        $norm = array_map(fn($h) => self::normalizeHeader((string) $h), $headers);
        $map = [];
        $used = [];
        foreach (self::ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                foreach ($norm as $i => $h) {
                    if ($h === $alias && !isset($used[$i])) { $map[$field][] = $i; $used[$i] = true; }
                }
            }
        }
        return $map;
    }

    /** Converte o conteúdo para UTF-8 (Excel do Windows salva CSV em Windows-1252). */
    public static function toUtf8(string $content): string
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        return $content;
    }

    /** Detecta o separador pela primeira linha: ";", "," ou tabulação. */
    public static function detectDelimiter(string $firstLine): string
    {
        $counts = [';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ','), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);
        $best = array_key_first($counts);
        return $counts[$best] > 0 ? $best : ';';
    }

    /**
     * Lê um CSV inteiro em linhas (respeita aspas e quebras de linha dentro de células).
     * @return array<int,array<int,string>>
     */
    public static function parseCsv(string $content): array
    {
        $content = self::toUtf8($content);
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $firstLine = strtok($content, "\n") ?: '';
        $delim = self::detectDelimiter($firstLine);

        $fh = fopen('php://temp', 'w+');
        fwrite($fh, $content);
        rewind($fh);
        $rows = [];
        while (($r = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
            if ($r === [null]) continue; // linha vazia
            $rows[] = array_map(fn($v) => trim((string) $v), $r);
        }
        fclose($fh);
        return $rows;
    }

    /**
     * Lê a primeira aba de um .xlsx (zip + XML), sem bibliotecas externas.
     * @return array<int,array<int,string>>|null null se o arquivo não puder ser lido
     */
    public static function parseXlsx(string $path): ?array
    {
        if (!class_exists('ZipArchive')) return null;
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) return null;

        $shared = [];
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss !== false) {
            $x = @simplexml_load_string($ss);
            if ($x) {
                foreach ($x->si as $si) {
                    if (isset($si->t)) { $shared[] = (string) $si->t; continue; }
                    $txt = '';
                    foreach ($si->r as $run) $txt .= (string) $run->t;
                    $shared[] = $txt;
                }
            }
        }

        // Primeira aba: segue o workbook.xml -> rels; cai em sheet1.xml.
        $sheetPath = 'xl/worksheets/sheet1.xml';
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb !== false && $rels !== false) {
            $wbx = @simplexml_load_string($wb);
            $rx = @simplexml_load_string($rels);
            if ($wbx && $rx && isset($wbx->sheets->sheet[0])) {
                $rid = (string) $wbx->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                foreach ($rx->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $t = ltrim((string) $rel['Target'], '/');
                        $sheetPath = strpos($t, 'xl/') === 0 ? $t : 'xl/' . $t;
                        break;
                    }
                }
            }
        }
        $sheet = $zip->getFromName($sheetPath);
        $zip->close();
        if ($sheet === false) return null;
        $sx = @simplexml_load_string($sheet);
        if (!$sx || !isset($sx->sheetData)) return null;

        $rows = [];
        foreach ($sx->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $col = self::columnIndex(preg_replace('/\d+/', '', $ref));
                $type = (string) $c['t'];
                if ($type === 's') $v = $shared[(int) $c->v] ?? '';
                elseif ($type === 'inlineStr') $v = (string) ($c->is->t ?? '');
                else $v = (string) ($c->v ?? '');
                $cells[$col] = trim($v);
            }
            if (!$cells) continue;
            $max = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= $max; $i++) $line[] = $cells[$i] ?? '';
            if (implode('', $line) === '') continue;
            $rows[] = $line;
        }
        return $rows;
    }

    /** "A" => 0, "B" => 1, "AA" => 26 */
    public static function columnIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) $n = $n * 26 + (ord($ch) - 64);
        return max(0, $n - 1);
    }

    /**
     * Converte as linhas da planilha em leads validados.
     *
     * @param array $rows primeira linha = cabeçalhos
     * @return array{leads:array<int,array>,errors:array<int,array{line:int,message:string}>,warnings:array<int,array{line:int,message:string}>,missing_headers:bool}
     */
    public static function extract(array $rows): array
    {
        $out = ['leads' => [], 'errors' => [], 'warnings' => [], 'missing_headers' => false];
        if (!$rows) return $out;
        $map = self::mapHeaders(array_shift($rows));
        $hasName = !empty($map['name']) || !empty($map['first_name']);
        $hasContact = !empty($map['email']) || !empty($map['phone']) || !empty($map['linkedin']);
        if (!$hasName || !$hasContact) {
            $out['missing_headers'] = true;
            return $out;
        }

        $get = function (array $row, string $field) use ($map) {
            foreach ($map[$field] ?? [] as $i) {
                $v = trim((string) ($row[$i] ?? ''));
                if ($v !== '') return $v;
            }
            return '';
        };

        foreach ($rows as $idx => $row) {
            $line = $idx + 2; // linha 1 = cabeçalho
            if (implode('', array_map('strval', $row)) === '') continue;
            if (count($out['leads']) + count($out['errors']) >= self::MAX_ROWS) {
                $out['errors'][] = ['line' => $line, 'message' => 'Limite de ' . self::MAX_ROWS . ' linhas por importação. As demais foram ignoradas.'];
                break;
            }

            $name = $get($row, 'name');
            $first = $get($row, 'first_name');
            $last = $get($row, 'last_name');
            if ($name === '') $name = trim($first . ' ' . $last);
            elseif ($last !== '' && $first === '' && mb_stripos($name, $last) === false) $name = trim($name . ' ' . $last);

            $email = mb_strtolower($get($row, 'email'), 'UTF-8');
            $phone = preg_replace('/\D/', '', $get($row, 'phone'));
            $linkedin = $get($row, 'linkedin');

            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                if ($phone === '' && $linkedin === '') {
                    $out['errors'][] = ['line' => $line, 'message' => "E-mail inválido ({$email}) e sem telefone/LinkedIn."];
                    continue;
                }
                $out['warnings'][] = ['line' => $line, 'message' => "E-mail inválido ({$email}): importado sem e-mail."];
                $email = '';
            }
            if ($phone !== '' && strlen($phone) < 8) {
                $out['warnings'][] = ['line' => $line, 'message' => 'Telefone muito curto: importado sem telefone.'];
                $phone = '';
            }
            if ($name === '') {
                $out['errors'][] = ['line' => $line, 'message' => 'Sem nome.'];
                continue;
            }
            if ($email === '' && $phone === '' && $linkedin === '') {
                $out['errors'][] = ['line' => $line, 'message' => 'Sem e-mail, telefone ou LinkedIn.'];
                continue;
            }

            $out['leads'][] = [
                'line' => $line,
                'name' => mb_substr($name, 0, 190),
                'email' => $email ?: null,
                'phone' => $phone ?: null,
                'company' => mb_substr($get($row, 'company'), 0, 190) ?: null,
                'job_title' => mb_substr($get($row, 'job_title'), 0, 190) ?: null,
                'linkedin' => $linkedin ?: null,
                'website' => $get($row, 'website') ?: null,
                'city' => $get($row, 'city') ?: null,
                'state' => $get($row, 'state') ?: null,
                'notes' => $get($row, 'notes') ?: null,
            ];
        }
        return $out;
    }

    /** Texto das observações do briefing para um lead importado (mesmo padrão da captação). */
    public static function briefingNotes(array $lead): ?string
    {
        $parts = [];
        if (!empty($lead['job_title'])) $parts[] = 'Cargo: ' . $lead['job_title'];
        if (!empty($lead['company'])) $parts[] = 'Empresa: ' . $lead['company'];
        if (!empty($lead['linkedin'])) $parts[] = 'LinkedIn: ' . $lead['linkedin'];
        if (!empty($lead['website'])) $parts[] = 'Site: ' . $lead['website'];
        $loc = implode('/', array_filter([$lead['city'] ?? null, $lead['state'] ?? null]));
        if ($loc !== '') $parts[] = 'Local: ' . $loc;
        if (!empty($lead['notes'])) $parts[] = $lead['notes'];
        return $parts ? implode(' | ', $parts) : null;
    }
}
