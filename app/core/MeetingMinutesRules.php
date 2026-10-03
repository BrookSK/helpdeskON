<?php

/**
 * Regras puras (sem banco/HTTP/IA) para a MINUTA/ATA automática de reuniões.
 *
 * A partir da transcrição de uma reunião gravada, o sistema gera automaticamente:
 *  - transcrição (Whisper)  -> já existente
 *  - resumo (chat)          -> já existente
 *  - MINUTA/ATA estruturada -> esta etapa (Fase 2)
 *
 * A minuta é uma ata objetiva do que aconteceu: contexto, participantes, pontos
 * discutidos, decisões tomadas, valores/condições acordados, pendências e próximos
 * passos. É editável pela equipe e, depois, enviada em PDF ao cliente e à equipe.
 *
 * Esta classe centraliza, de forma testável:
 *  - decidir se há material suficiente para gerar a minuta;
 *  - montar as mensagens (system/user) do prompt de geração;
 *  - normalizar/sanitizar o conteúdo editado antes de salvar.
 */
class MeetingMinutesRules
{
    /** Estados da geração da minuta (espelha a coluna minutes_status). */
    public const STATUS_NONE = 'none';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DONE = 'done';
    public const STATUS_ERROR = 'error';

    public const STATUSES = [
        self::STATUS_NONE,
        self::STATUS_PROCESSING,
        self::STATUS_DONE,
        self::STATUS_ERROR,
    ];

    /** Limite de caracteres da transcrição enviada ao modelo (controle de custo/tokens). */
    public const TRANSCRIPT_LIMIT = 48000;

    /** Normaliza um status; valor desconhecido vira 'none'. */
    public static function normalizeStatus($value): string
    {
        return in_array($value, self::STATUSES, true) ? $value : self::STATUS_NONE;
    }

    /**
     * Há material suficiente para gerar a minuta? Exige uma transcrição com algum
     * conteúdo real (não só espaços/pontuação). Sem transcrição, não há ata.
     */
    public static function canGenerate(?string $transcript): bool
    {
        if (!is_string($transcript)) return false;
        $stripped = preg_replace('/[\p{P}\p{Z}\s]+/u', '', $transcript);
        return is_string($stripped) && $stripped !== '';
    }

    /**
     * Mensagem de sistema do prompt da minuta. Define o papel e o formato fixo
     * da ata, em português do Brasil. Mantida como fonte única (testável) para o
     * formato não divergir entre pontos de chamada.
     */
    public static function systemPrompt(): string
    {
        return 'Você redige ATAS (minutas) de reuniões em português do Brasil, de forma objetiva, '
            . 'profissional e fiel ao que foi dito — sem inventar informação. Produza a ata em Markdown '
            . 'com as seções, nesta ordem: '
            . '1) **Contexto da reunião** (data/assunto, de forma breve); '
            . '2) **Participantes** (quem falou, se identificável); '
            . '3) **Pontos discutidos** (bullets objetivos); '
            . '4) **Decisões tomadas** (o que ficou acordado); '
            . '5) **Valores e condições** (preços, prazos, formas de pagamento mencionados — '
            . 'se não houver, escreva "Não foram tratados valores."); '
            . '6) **Pendências / o que ficou em aberto**; '
            . '7) **Próximos passos** (tarefas e responsáveis quando citados). '
            . 'Não repita a transcrição literal; sintetize. Se algo não foi tratado, diga explicitamente.';
    }

    /**
     * Mensagem do usuário: contexto da reunião + transcrição (truncada ao limite).
     *
     * @param string      $transcript Transcrição com marcas [mm:ss].
     * @param string|null $title      Título/assunto da reunião, se houver.
     * @param string|null $dateLabel  Data legível da reunião, se houver.
     */
    public static function userPrompt(string $transcript, ?string $title = null, ?string $dateLabel = null): string
    {
        $header = 'Gere a ATA da reunião a seguir.';
        if ($title !== null && trim($title) !== '') {
            $header .= ' Assunto: ' . trim($title) . '.';
        }
        if ($dateLabel !== null && trim($dateLabel) !== '') {
            $header .= ' Data: ' . trim($dateLabel) . '.';
        }
        $body = mb_substr($transcript, 0, self::TRANSCRIPT_LIMIT);
        return $header . "\n\nTranscrição (formato [mm:ss] texto):\n\n" . $body;
    }

    /**
     * Monta o array de mensagens (role/content) para o endpoint de chat.
     *
     * @return array<int,array{role:string,content:string}>
     */
    public static function buildMessages(string $transcript, ?string $title = null, ?string $dateLabel = null): array
    {
        return [
            ['role' => 'system', 'content' => self::systemPrompt()],
            ['role' => 'user', 'content' => self::userPrompt($transcript, $title, $dateLabel)],
        ];
    }

    /**
     * Normaliza o conteúdo da minuta recebido para salvar: converte CRLF/CR em LF,
     * remove espaços à direita e limita o tamanho total (defesa). Retorna string
     * limpa (pode ser vazia — o chamador decide se vazio é aceitável).
     */
    public static function sanitizeContent(?string $content, int $maxLen = 200000): string
    {
        if (!is_string($content)) return '';
        $c = str_replace(["\r\n", "\r"], "\n", $content);
        $c = rtrim($c);
        if (mb_strlen($c) > $maxLen) {
            $c = mb_substr($c, 0, $maxLen);
        }
        return $c;
    }
}
