<?php

/**
 * Variáveis (placeholders) dos modelos de contrato (esteira comercial).
 *
 * O usuário escreve o modelo com marcadores no formato {{chave}} e, ao gerar o
 * contrato a partir da proposta (ou ao carregar o modelo no editor), as chaves
 * são substituídas pelos dados reais. Regra pura e testável.
 *
 * Formato do marcador: {{cliente_nome}}. Chaves desconhecidas são deixadas como
 * estão (para não apagar texto por engano) — a UI lista as válidas.
 */
class ContractTemplateVars
{
    /**
     * Catálogo das variáveis disponíveis: chave => descrição (para a UI).
     * @return array<string,string>
     */
    public static function catalog(): array
    {
        return [
            'cliente_nome'     => 'Nome do cliente',
            'cliente_email'    => 'E-mail do cliente',
            'cliente_telefone' => 'Telefone do cliente',
            'empresa_nome'     => 'Nome da empresa (cliente), quando houver',
            'proposta_titulo'  => 'Título da proposta',
            'proposta_total'   => 'Valor total da proposta (R$)',
            'proposta_validade'=> 'Data de validade da proposta',
            'tipo_contratacao' => 'Tipo de contratação (ex.: Desenvolvimento do zero)',
            'data_hoje'        => 'Data de hoje (dd/mm/aaaa)',
            'empresa_prestador'=> 'Nome da sua empresa (do sistema)',
        ];
    }

    /** Rótulos amigáveis dos tipos de contratação (espelha ProposalRules). */
    private static function contractTypeLabel(?string $ct): string
    {
        $map = [
            'dev_zero' => 'Desenvolvimento do zero',
            'dev_manutencao' => 'Desenvolvimento/manutenção',
            'suporte' => 'Suporte',
            'dev_suporte' => 'Desenvolvimento + suporte',
            'outro' => 'Outro',
        ];
        return $ct && isset($map[$ct]) ? $map[$ct] : '';
    }

    private static function money($v): string
    {
        return 'R$ ' . number_format((float)$v, 2, ',', '.');
    }

    private static function dateBr(?string $d): string
    {
        $ts = $d ? strtotime($d) : false;
        return $ts ? date('d/m/Y', $ts) : '';
    }

    /**
     * Monta o mapa de valores a partir de uma proposta (linha de proposals) e
     * do nome da empresa prestadora (Settings app_name).
     *
     * @param array $proposal
     * @param string|null $prestador Nome da empresa dona do sistema (app_name)
     * @param string|null $empresaCliente Nome da empresa do cliente (companies.name)
     * @return array<string,string>
     */
    public static function valuesFromProposal(array $proposal, ?string $prestador = null, ?string $empresaCliente = null): array
    {
        return [
            'cliente_nome'      => (string)($proposal['client_name'] ?? ''),
            'cliente_email'     => (string)($proposal['client_email'] ?? ''),
            'cliente_telefone'  => (string)($proposal['client_phone'] ?? ''),
            'empresa_nome'      => (string)($empresaCliente ?? ''),
            'proposta_titulo'   => (string)($proposal['title'] ?? ''),
            'proposta_total'    => self::money($proposal['total'] ?? 0),
            'proposta_validade' => self::dateBr($proposal['validity_date'] ?? null),
            'tipo_contratacao'  => self::contractTypeLabel($proposal['contract_type'] ?? null),
            'data_hoje'         => date('d/m/Y'),
            'empresa_prestador' => (string)($prestador ?? ''),
        ];
    }

    /**
     * Substitui os marcadores {{chave}} do corpo pelos valores informados.
     * Aceita espaços dentro das chaves: {{ cliente_nome }}. Chaves sem valor no
     * mapa são mantidas intactas.
     *
     * @param string $body
     * @param array<string,string> $values
     */
    public static function render(string $body, array $values): string
    {
        if ($body === '') return '';
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($values) {
            $key = strtolower($m[1]);
            return array_key_exists($key, $values) ? (string)$values[$key] : $m[0];
        }, $body);
    }
}
