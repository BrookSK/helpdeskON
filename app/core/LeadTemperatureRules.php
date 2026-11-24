<?php
/**
 * Regras puras da TEMPERATURA do lead (frio / morno / quente).
 *
 * A temperatura é o engajamento do lead com os contatos que fizemos, somado
 * automaticamente: abertura de e-mail +1, clique em link +3, resposta +5,
 * e-mail que voltou (bounce) −5. Também pode ser ajustada à mão no briefing.
 *
 * Bug corrigido: a captação automática somava o score de PERFIL (0–100) nesse
 * placar, então o lead já nascia "quente" sem nenhuma interação, e a pessoa não
 * tinha como saber de onde vinha a informação. Agora só interações contam, e a
 * tela explica a regra.
 */
class LeadTemperatureRules
{
    public const W_OPEN = 1;
    public const W_CLICK = 3;
    public const W_REPLY = 5;
    public const W_BOUNCE = -5;

    /** Classificação interna do score (o CRM mostra 'engajado' como morno). */
    public static function classify(int $score): string
    {
        if ($score >= 5) return 'quente';
        if ($score >= 3) return 'engajado';
        if ($score >= 1) return 'morno';
        return 'frio';
    }

    /** Temperatura exibida no CRM (frio / morno / quente). */
    public static function temperature(int $score): string
    {
        return ['frio' => 'frio', 'morno' => 'morno', 'engajado' => 'morno', 'quente' => 'quente'][self::classify($score)];
    }

    /** Regra em uma linha, para legendas. */
    public static function legend(): string
    {
        return 'Temperatura = engajamento do lead com os nossos contatos, calculado sozinho: '
            . 'e-mail aberto +1, clique em link +3, resposta +5, e-mail que voltou −5. '
            . 'Frio: 0 ponto · Morno: 1 a 4 · Quente: 5 ou mais. Pode ser ajustada à mão no briefing.';
    }

    /** Motivo curto, visível também no celular: "6 pts" | "0 pt" | "à mão". */
    public static function shortReason(?string $temp, ?int $score): string
    {
        if ($score === null) return 'à mão';
        if ($temp !== null && $temp !== '' && $temp !== self::temperature($score)) return 'à mão';
        return $score . ($score === 1 || $score === 0 ? ' pt' : ' pts');
    }

    /**
     * Texto do "por quê" de um lead específico (tooltip).
     * @param string|null $temp  temperatura gravada no briefing
     * @param int|null    $score pontos de engajamento (lead_score), null se não houver
     */
    public static function explain(?string $temp, ?int $score): string
    {
        $labels = ['frio' => 'Frio', 'morno' => 'Morno', 'quente' => 'Quente'];
        $label = $labels[$temp ?? ''] ?? 'Sem temperatura';
        if ($score === null) {
            return $label . ': definida à mão no briefing (ainda sem interações registradas).';
        }
        $pts = $score . ' ponto' . ($score === 1 ? '' : 's') . ' de engajamento';
        if ($temp !== null && $temp !== '' && $temp !== self::temperature($score)) {
            return $label . ': ajustada à mão no briefing (pelas interações seria ' . $labels[self::temperature($score)] . ', ' . $pts . ').';
        }
        if ($score <= 0) return $label . ': o lead ainda não abriu, clicou nem respondeu nenhum contato (0 ponto).';
        return $label . ': ' . $pts . ' (aberturas, cliques e respostas aos nossos contatos).';
    }
}
