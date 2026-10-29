<?php

/**
 * Regras puras (sem banco/HTTP) da EXPORTAÇÃO do relatório consolidado de RDO
 * em PDF (capa + resumo executivo por IA + detalhamento + fotos).
 *
 * Fonte única e testável para:
 *  - quem pode exportar (controle de acesso por papel);
 *  - validação/normalização do período (data inicial/final);
 *  - montagem do prompt da IA a partir EXCLUSIVAMENTE dos dados reais dos RDOs
 *    (o modelo não pode inventar números, clima ou fatos ausentes);
 *  - rótulos/contadores para a capa do PDF.
 *
 * A orquestração (buscar no banco, chamar a OpenAI, renderizar o PDF) fica no
 * RdoController; aqui só mora a lógica pura.
 */
class RdoReportRules
{
    /** Papéis autorizados a exportar o relatório consolidado. Só super_admin. */
    public const EXPORT_ROLES = ['super_admin'];

    /**
     * Teto do intervalo de exportação, em dias (inclusivo). Evita um PDF
     * gigantesco e um prompt que estoure o limite de tokens da IA.
     */
    public const MAX_RANGE_DAYS = 366;

    /** Título fixo do documento. */
    public const DOCUMENT_TITLE = 'RELATÓRIO DIÁRIO CONSOLIDADO';

    // =========================================================================
    // Acesso
    // =========================================================================

    /** O papel informado pode exportar o relatório consolidado? */
    public static function canExport(?string $role): bool
    {
        return $role !== null && in_array($role, self::EXPORT_ROLES, true);
    }

    // =========================================================================
    // Período
    // =========================================================================

    /**
     * Valida e normaliza o período informado na exportação.
     *
     * Regras:
     *  - ambas as datas são obrigatórias e devem estar no formato Y-m-d válido;
     *  - a data inicial não pode ser posterior à final;
     *  - o intervalo não pode exceder MAX_RANGE_DAYS dias.
     *
     * @return array{ok:bool, from?:string, to?:string, error?:string}
     */
    public static function normalizePeriod($from, $to): array
    {
        $from = self::parseDate($from);
        $to   = self::parseDate($to);

        if ($from === null || $to === null) {
            return ['ok' => false, 'error' => 'Informe a data inicial e a data final (formato válido).'];
        }
        if ($from > $to) {
            return ['ok' => false, 'error' => 'A data inicial não pode ser posterior à data final.'];
        }
        if (self::rangeDays($from, $to) > self::MAX_RANGE_DAYS) {
            return [
                'ok'    => false,
                'error' => 'O período é muito longo (máximo de ' . self::MAX_RANGE_DAYS . ' dias). Selecione um intervalo menor.',
            ];
        }
        return ['ok' => true, 'from' => $from, 'to' => $to];
    }

    /**
     * Converte uma string em data Y-m-d válida, ou null se inválida. Rejeita
     * datas impossíveis (ex.: 2026-02-31) comparando com a reconstrução.
     */
    public static function parseDate($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $v = trim($value);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
            return null;
        }
        [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if (!checkdate($mo, $d, $y)) {
            return null;
        }
        return $v;
    }

    /** Número de dias no intervalo [from, to], inclusivo (ambas Y-m-d válidas). */
    public static function rangeDays(string $from, string $to): int
    {
        $a = new \DateTimeImmutable($from . ' 00:00:00');
        $b = new \DateTimeImmutable($to . ' 00:00:00');
        return (int) $a->diff($b)->days + 1;
    }

    // =========================================================================
    // Rótulos / contadores para a capa
    // =========================================================================

    /** "08/10/2026 a 23/10/2026" a partir de duas datas Y-m-d. */
    public static function periodLabel(string $from, string $to): string
    {
        return self::dateBr($from) . ' a ' . self::dateBr($to);
    }

    /** Converte Y-m-d em d/m/Y (sem validar; use parseDate antes se preciso). */
    public static function dateBr(string $ymd): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m)) {
            return "{$m[3]}/{$m[2]}/{$m[1]}";
        }
        return $ymd;
    }

    // =========================================================================
    // Prompt da IA (somente dados reais)
    // =========================================================================

    /**
     * Monta as mensagens (system + user) para o resumo executivo da IA.
     *
     * O texto do usuário é construído SOMENTE a partir dos campos reais de cada
     * RDO recebido. A instrução do sistema proíbe explicitamente inventar dados
     * (números, clima, fatos não mencionados). Se um campo está vazio, ele
     * simplesmente não entra no texto — nunca é preenchido com suposição.
     *
     * @param array  $reports Lista de RDOs (como vindos de DailyReport::getList,
     *                        opcionalmente com 'collaborators' já anexados).
     * @param string $from    Data inicial Y-m-d (para contexto do período).
     * @param string $to      Data final    Y-m-d.
     * @return array<int, array{role:string, content:string}>
     */
    public static function buildSummaryMessages(array $reports, string $from, string $to): array
    {
        $system = 'Você é um analista que redige o RESUMO EXECUTIVO de um relatório '
            . 'consolidado de atividades (RDO). Escreva em português do Brasil, de forma '
            . 'objetiva e profissional. REGRA ABSOLUTA: baseie-se EXCLUSIVAMENTE nas '
            . 'informações fornecidas abaixo. Não invente dados, números, percentuais, '
            . 'datas, nomes, condições climáticas ou qualquer fato que não esteja '
            . 'explicitamente no conteúdo. Se uma informação não foi fornecida, não a '
            . 'mencione. Não use formatação Markdown (sem **, #, listas com -); use '
            . 'apenas parágrafos de texto corrido, podendo separar por linhas em branco.';

        $user = "Período do relatório: " . self::periodLabel($from, $to) . ".\n"
            . "Total de RDOs no período: " . count($reports) . ".\n\n"
            . "Conteúdo dos relatórios (use apenas o que está aqui):\n\n"
            . self::reportsAsText($reports)
            . "\nRedija um resumo executivo coeso do período, destacando as principais "
            . "atividades, ocorrências e pendências efetivamente registradas acima.";

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ];
    }

    /**
     * Serializa os RDOs em texto plano para o prompt, incluindo apenas os
     * campos preenchidos. Pública para ser exercitada diretamente em testes.
     */
    public static function reportsAsText(array $reports): string
    {
        $blocks = [];
        foreach ($reports as $r) {
            $lines = [];
            $date    = trim((string) ($r['report_date'] ?? ''));
            $company = trim((string) ($r['company_name'] ?? ''));
            $head = 'RDO';
            if ($date !== '')    $head .= ' de ' . self::dateBr($date);
            if ($company !== '') $head .= ' — ' . $company;
            $lines[] = $head;

            $map = [
                'title'         => 'Título',
                'activities'    => 'Atividades',
                'occurrences'   => 'Ocorrências',
                'pending_tasks' => 'Pendências',
                'next_day_plan' => 'Plano para o próximo dia',
            ];
            foreach ($map as $key => $label) {
                $val = trim((string) ($r[$key] ?? ''));
                if ($val !== '') {
                    $lines[] = $label . ': ' . $val;
                }
            }

            $collabs = $r['collaborators'] ?? [];
            if (is_array($collabs) && $collabs) {
                $names = [];
                foreach ($collabs as $c) {
                    $name = trim((string) ($c['collaborator_name'] ?? ''));
                    if ($name !== '') $names[] = $name;
                }
                if ($names) {
                    $lines[] = 'Colaboradores/Prestadores: ' . implode(', ', $names);
                }
            }

            $blocks[] = implode("\n", $lines);
        }
        return implode("\n\n", $blocks);
    }

    /** O anexo é uma imagem (para entrar nos registros fotográficos)? */
    public static function isImageAttachment(array $attachment): bool
    {
        $type = strtolower(trim((string) ($attachment['file_type'] ?? '')));
        if (strncmp($type, 'image/', 6) === 0) {
            return true;
        }
        // Fallback por extensão quando o file_type veio vazio/genérico.
        $name = strtolower((string) ($attachment['file_name'] ?? $attachment['file_path'] ?? ''));
        return (bool) preg_match('/\.(jpe?g|png|gif|webp|bmp)$/', $name);
    }

    /** Nome sugerido do arquivo PDF para o período. */
    public static function fileName(string $from, string $to): string
    {
        return 'relatorio-diario-' . $from . '-a-' . $to . '.pdf';
    }
}
