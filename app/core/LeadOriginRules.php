<?php
/**
 * Regras puras (sem banco/HTTP) de dono, origem e "dados do lead" no CRM.
 *
 *  - Dono do lead captado pela prospecção automática: quem toca a campanha
 *    (Responsável da campanha → quem criou a campanha → Super Admin). Antes ia
 *    sempre para o primeiro Super Admin, e a pessoa que fazia a prospecção não
 *    via os próprios leads.
 *  - Origem do card: prospecção automática, prospecção manual (Apollo) ou
 *    importação de planilha, com rótulo para mostrar no CRM.
 *  - Dados do lead (cargo, empresa, LinkedIn): colunas próprias; para leads
 *    antigos, lidos do texto das observações ("Cargo: X | Empresa: Y | ...").
 */
class LeadOriginRules
{
    public const AUTO = 'prospeccao_auto';
    public const MANUAL = 'prospeccao_manual';
    public const SHEET = 'planilha';

    /** Dono de um lead NOVO captado por uma campanha automática. */
    public static function campaignLeadOwner(array $camp, ?int $superAdminId): ?int
    {
        foreach (['assigned_to', 'created_by'] as $k) {
            if (!empty($camp[$k]) && (int) $camp[$k] > 0) return (int) $camp[$k];
        }
        return $superAdminId ?: null;
    }

    /**
     * Rótulo da origem do card.
     * @return array{label:string,short:string,icon:string,color:string}|null
     */
    public static function originBadge(?string $origin, ?string $detail = null): ?array
    {
        $detail = trim((string) $detail);
        switch ($origin) {
            case self::AUTO:
                return ['label' => 'Prospecção automática' . ($detail !== '' ? ' · ' . $detail : ''), 'short' => 'Automática', 'icon' => 'robot', 'color' => '#0d6efd'];
            case self::MANUAL:
                return ['label' => 'Prospecção manual (Apollo)' . ($detail !== '' ? ' · ' . $detail : ''), 'short' => 'Manual', 'icon' => 'hand-index', 'color' => '#6f42c1'];
            case self::SHEET:
                return ['label' => 'Importado de planilha' . ($detail !== '' ? ' · ' . $detail : ''), 'short' => 'Planilha', 'icon' => 'file-earmark-spreadsheet', 'color' => '#198754'];
            default:
                return null;
        }
    }

    /**
     * Lê "Cargo: X | Empresa: Y | LinkedIn: Z" das observações do briefing
     * (formato gravado pela captação Apollo antes das colunas próprias).
     * @return array{job_title:?string,company:?string,linkedin:?string}
     */
    public static function parseNotes(?string $notes): array
    {
        $out = ['job_title' => null, 'company' => null, 'linkedin' => null];
        $map = ['cargo' => 'job_title', 'empresa' => 'company', 'linkedin' => 'linkedin'];
        foreach (preg_split('/\s*\|\s*|\R/u', (string) $notes) as $part) {
            if (!preg_match('/^\s*(cargo|empresa|linkedin)\s*:\s*(.+?)\s*$/iu', $part, $m)) continue;
            $key = $map[mb_strtolower($m[1], 'UTF-8')];
            if ($out[$key] === null && $m[2] !== '') $out[$key] = $m[2];
        }
        return $out;
    }

    /**
     * Dados do lead para o card: prioriza as colunas do contato, completa com o
     * staging do Apollo e, por último, com o texto das observações.
     *
     * @param array $contact  lead_email, phone, job_title, company_name, linkedin_url
     * @param array $apollo   title, organization_name, organization_website, linkedin_url, city, state, country
     * @param string|null $notes observações do briefing
     * @return array<string,?string> email, phone, job_title, company, linkedin, website, location
     */
    public static function profile(array $contact, array $apollo = [], ?string $notes = null): array
    {
        $fromNotes = self::parseNotes($notes);
        $pick = function (...$vals) {
            foreach ($vals as $v) {
                $v = trim((string) $v);
                if ($v !== '') return $v;
            }
            return null;
        };
        $location = implode(', ', array_filter([
            trim((string) ($apollo['city'] ?? '')), trim((string) ($apollo['state'] ?? '')), trim((string) ($apollo['country'] ?? '')),
        ], fn($v) => $v !== ''));
        return [
            'email' => $pick($contact['lead_email'] ?? null, self::realEmail($apollo['email'] ?? null)),
            'phone' => $pick($contact['phone'] ?? null, $apollo['phone'] ?? null),
            'job_title' => $pick($contact['job_title'] ?? null, $apollo['title'] ?? null, $fromNotes['job_title']),
            'company' => $pick($contact['company_name'] ?? null, $apollo['organization_name'] ?? null, $fromNotes['company']),
            'linkedin' => $pick($contact['linkedin_url'] ?? null, $apollo['linkedin_url'] ?? null, $fromNotes['linkedin']),
            'website' => $pick($apollo['organization_website'] ?? null),
            'location' => $location !== '' ? $location : null,
        ];
    }

    /** E-mail utilizável (descarta o marcador do Apollo "email_not_unlocked@..."). */
    private static function realEmail($email): ?string
    {
        $email = trim((string) $email);
        if ($email === '' || stripos($email, 'email_not_unlocked') !== false) return null;
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
