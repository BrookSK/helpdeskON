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
            'cliente_documento'=> 'CPF ou CNPJ do cliente',
            'cliente_endereco' => 'Endereço do cliente',
            'proposta_itens'   => 'Tabela com os itens da proposta aceita (descrição, quantidade e valor)',
            'condicao_pagamento' => 'Condição de pagamento (da proposta ou o padrão das configurações)',
            'proposta_titulo'  => 'Título da proposta',
            'proposta_total'   => 'Valor total da proposta (R$)',
            'proposta_total_liquido' => 'Total do 1º ano já com desconto (R$)',
            'proposta_pontual' => 'Valor pontual/implantação com desconto (R$)',
            'proposta_mensalidade' => 'Mensalidade com desconto (R$)',
            'proposta_desconto' => 'Desconto comercial aplicado (R$)',
            'plano_aceito'     => 'Plano aceito pelo cliente (quando houver opções)',
            'proposta_validade'=> 'Data de validade da proposta',
            'tipo_contratacao' => 'Tipo de contratação (ex.: Desenvolvimento do zero)',
            'data_hoje'        => 'Data de hoje (dd/mm/aaaa)',
            'empresa_prestador'=> 'Nome da sua empresa (do sistema)',
        ];
    }

    /** Rótulo amigável do plano aceito. */
    private static function planLabel(?string $plan): string
    {
        $map = ['plan_3' => 'Plano 3 meses', 'plan_6' => 'Plano 6 meses', 'plan_12' => 'Plano 12 meses'];
        return $plan && isset($map[$plan]) ? $map[$plan] : '';
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

    /**
     * Condição de pagamento do contrato: o texto da proposta; sem ele, o padrão
     * das configurações; sem os dois, "a combinar".
     */
    public static function paymentCondition(array $proposal, ?string $default = null): string
    {
        $content = class_exists('ProposalRules') ? ProposalRules::decodeContent($proposal['content'] ?? null) : [];
        $text = trim((string)($content['payment_method'] ?? ''));
        if ($text === '') $text = trim((string)$default);
        return $text !== '' ? $text : 'a combinar';
    }

    /** Tabela HTML dos itens (descrição, quantidade e valor). Vazia sem itens. */
    public static function itemsTable(array $items): string
    {
        $rows = '';
        foreach ($items as $it) {
            $desc = trim((string)($it['description'] ?? ''));
            if ($desc === '') continue;
            $qty = (float)($it['quantity'] ?? 1) ?: 1.0;
            $qtyTxt = rtrim(rtrim(number_format($qty, 2, ',', '.'), '0'), ',');
            $value = self::money($it['amount'] ?? 0) . (($it['billing'] ?? '') === 'monthly' ? '/mês' : '');
            $rows .= '<tr><td>' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="text-align:center">' . $qtyTxt . '</td>'
                . '<td style="text-align:right">' . $value . '</td></tr>';
        }
        if ($rows === '') return '';
        return '<table style="width:100%;border-collapse:collapse" border="1" cellpadding="6">'
            . '<thead><tr><th>Descrição</th><th>Quantidade</th><th>Valor</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>';
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
     * @param array $items itens da proposta (versão aceita)
     * @param string|null $paymentDefault condição de pagamento padrão (Settings)
     * @return array<string,string>
     *
     * client_document e client_address vêm do contrato quando o chamador os
     * junta à proposta.
     */
    public static function valuesFromProposal(array $proposal, ?string $prestador = null, ?string $empresaCliente = null, array $items = [], ?string $paymentDefault = null): array
    {
        // Totais comerciais (com desconto) via fonte única, quando disponível.
        $comm = null;
        if (class_exists('ProposalRules')) {
            $comm = ProposalRules::commercialTotals($items, $proposal['discounts'] ?? []);
        }
        $totalNet = $comm ? $comm['year']['net'] : (float)($proposal['total'] ?? 0);
        $onceNet = $comm ? $comm['once']['net'] : 0.0;
        $monthlyNet = $comm ? $comm['monthly']['net'] : 0.0;
        $discount = $comm ? $comm['year']['discount'] : 0.0;

        return [
            'cliente_nome'      => (string)($proposal['client_name'] ?? ''),
            'cliente_email'     => (string)($proposal['client_email'] ?? ''),
            'cliente_telefone'  => (string)($proposal['client_phone'] ?? ''),
            'empresa_nome'      => (string)($empresaCliente ?? ''),
            'cliente_documento' => (string)($proposal['client_document'] ?? ''),
            'cliente_endereco'  => (string)($proposal['client_address'] ?? ''),
            'proposta_itens'    => self::itemsTable($items),
            'condicao_pagamento'=> self::paymentCondition($proposal, $paymentDefault),
            'proposta_titulo'   => (string)($proposal['title'] ?? ''),
            'proposta_total'    => self::money($proposal['total'] ?? 0),
            'proposta_total_liquido' => self::money($totalNet),
            'proposta_pontual'  => self::money($onceNet),
            'proposta_mensalidade' => self::money($monthlyNet),
            'proposta_desconto' => self::money($discount),
            'plano_aceito'      => self::planLabel($proposal['accepted_plan'] ?? null),
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
