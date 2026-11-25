<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use EmailThreadRules;

/**
 * Histórico de e-mails do lead no card do CRM: texto da resposta sem a citação,
 * junção de email_messages + email_prospections sem duplicar espelhos e
 * agrupamento em conversas.
 */
final class EmailThreadRulesTest extends TestCase
{
    public function testRespostaGmailCortaCitacaoMantendoQuebras(): void
    {
        $raw = "Oi Evelyn,\r\n\r\nTenho interesse sim.\r\nPode me ligar amanhã?\r\n\r\n\r\n\r\nAbs\r\n"
             . "Em qua., 24 de nov. de 2026 às 10:00, Evelyn <evelyn@on.com.br>\r\nescreveu:\r\n> Olá Carla\r\n> tudo bem?";
        $this->assertSame("Oi Evelyn,\n\nTenho interesse sim.\nPode me ligar amanhã?\n\nAbs", EmailThreadRules::replyText($raw));
    }

    public function testRespostaOutlookEEmIngles(): void
    {
        $outlook = "Podemos falar sexta.\n\nDe: Evelyn <evelyn@on.com.br>\nEnviado: quarta-feira, 24 de novembro\nAssunto: Proposta";
        $this->assertSame('Podemos falar sexta.', EmailThreadRules::replyText($outlook));
        $this->assertSame('Sure!', EmailThreadRules::replyText("Sure!\nOn Wed, Nov 24, 2026 at 10:00 AM Evelyn <e@x.com> wrote:\n> hi"));
        $this->assertSame('Ok', EmailThreadRules::replyText("Ok\n-----Mensagem original-----\nDe: x"));
        $this->assertSame('Ok', EmailThreadRules::replyText("Ok\n________________________________\nDe: x"));
    }

    public function testTudoCitacaoDevolveTextoInteiroETextoNormalNaoCorta(): void
    {
        $this->assertSame('> só citação', EmailThreadRules::replyText('> só citação'));
        $txt = "Em resumo, gostei da proposta.\nDe acordo com o combinado, seguimos.";
        $this->assertSame($txt, EmailThreadRules::replyText($txt), '"Em ..." e "De ..." sem cabeçalho de citação não cortam');
    }

    public function testHtmlViraTextoComParagrafosECitacaoMarcada(): void
    {
        $html = '<div dir="ltr">Olá,<br>quero <b>sim</b>&nbsp;a proposta.</div><p>Abs</p>'
              . '<div class="gmail_quote"><blockquote>Texto antigo</blockquote></div><style>p{color:red}</style>';
        $text = EmailThreadRules::htmlToText($html);
        $this->assertStringContainsString("Olá,\nquero sim a proposta.\nAbs", $text);
        $this->assertStringNotContainsString('color:red', $text);
        $this->assertSame("Olá,\nquero sim a proposta.\nAbs", EmailThreadRules::replyText($text), 'blockquote vira citação e é cortado');
    }

    public function testAssuntoSemPrefixosDeResposta(): void
    {
        $this->assertSame('proposta on', EmailThreadRules::normalizeSubject('RE: Res: Fwd:  Proposta   ON'));
        $this->assertSame('proposta', EmailThreadRules::normalizeSubject('ENC: RES[2]: proposta'));
        $this->assertSame('', EmailThreadRules::normalizeSubject(null));
        $this->assertSame('rede de clínicas', EmailThreadRules::normalizeSubject('Rede de clínicas'), '"Re" no começo de palavra não é prefixo');
    }

    private function out(int $id, string $subject, string $at, array $extra = []): array
    {
        return array_merge(['id' => $id, 'direction' => 'outbound', 'origin' => 'sequence', 'subject' => $subject,
            'status' => 'sent', 'sent_at' => $at, 'created_at' => $at, 'body' => "<p>Corpo {$id}</p>",
            'message_id' => "<seq-tok{$id}@on.com.br>", 'sender_name' => 'Evelyn', 'sequence_name' => 'Clínicas'], $extra);
    }

    private function in(int $id, string $subject, string $at, array $extra = []): array
    {
        return array_merge(['id' => $id, 'direction' => 'inbound', 'origin' => 'sequence', 'subject' => $subject,
            'status' => 'received', 'received_at' => $at, 'created_at' => $at, 'recipient_email' => 'carla@x.com'], $extra);
    }

    public function testEspelhoDaProspeccaoNaoDuplicaMasRespostaDaCaixaEntra(): void
    {
        $messages = [$this->out(1, 'Proposta', '2026-11-20 09:00:00', ['origin' => 'manual'])];
        $prospections = [
            // Espelho do envio acima (mesmo assunto, 40s depois): descartado
            ['id' => 10, 'subject' => 'Proposta', 'status' => 'sent', 'sent_at' => '2026-11-20 09:00:40', 'body' => '<p>x</p>', 'sender_name' => 'Evelyn'],
            // Resposta mandada pela Caixa de entrada: entra
            ['id' => 11, 'subject' => 'Re: Proposta', 'status' => 'sent', 'sent_at' => '2026-11-21 14:00:00', 'body' => '<p>Claro, ligo amanhã</p>', 'sender_name' => 'Evelyn'],
            // Rascunho nunca aparece
            ['id' => 12, 'subject' => 'Rascunho', 'status' => 'draft', 'sent_at' => null, 'created_at' => '2026-11-22 10:00:00', 'body' => 'x'],
        ];
        $items = EmailThreadRules::merge($messages, $prospections);
        $this->assertSame(['m1', 'p11'], array_column($items, 'key'));
        $this->assertSame('inbox_reply', $items[1]['source']);
        $this->assertSame('Claro, ligo amanhã', $items[1]['preview']);
        $this->assertSame('manual', $items[0]['source']);
    }

    public function testConversaCompletaRespostaDoLeadNossaRespostaENovaResposta(): void
    {
        $messages = [
            $this->out(1, 'Proposta', '2026-11-20 09:00:00', ['open_count' => 2, 'replied_at' => '2026-11-20 11:00:00']),
            $this->out(2, 'Outro assunto', '2026-11-22 09:00:00'),
            // Resposta casada por cabeçalho (In-Reply-To = Message-ID do envio 1), mesmo com assunto diferente
            $this->in(3, 'Resposta rápida', '2026-11-20 11:00:00', ['in_reply_to' => '<SEQ-TOK1@on.com.br>', 'body' => "Tenho interesse.\nMe liga?", 'reply_snippet' => 'Tenho interesse. Me liga?']),
            // Segunda resposta do lead (respondeu a nossa resposta da Caixa): junta pelo assunto
            $this->in(4, 'RE: Re: Proposta', '2026-11-21 16:00:00', ['body' => 'Pode ser às 15h.']),
        ];
        $prospections = [
            ['id' => 11, 'subject' => 'Re: Proposta', 'status' => 'sent', 'sent_at' => '2026-11-21 14:00:00', 'body' => '<p>Claro!</p>', 'sender_name' => 'Evelyn'],
        ];
        $r = EmailThreadRules::conversations(EmailThreadRules::merge($messages, $prospections));

        $this->assertCount(2, $r['conversations']);
        // Conversa mais recente primeiro
        $this->assertSame('Outro assunto', $r['conversations'][0]['subject']);
        $conv = $r['conversations'][1];
        $this->assertSame('Proposta', $conv['subject']);
        $this->assertSame(['m1', 'm3', 'p11', 'm4'], array_column($conv['items'], 'key'), 'ordem cronológica');
        $this->assertSame(['out', 'in', 'out', 'in'], array_column($conv['items'], 'direction'));
        $this->assertSame("Tenho interesse.\nMe liga?", $conv['items'][1]['body_text'], 'texto completo, com quebras');
        $this->assertSame(2, $conv['items'][0]['opens']);
        $this->assertTrue($conv['has_reply']);
        $this->assertNull($conv['reply_without_text_at']);
        $this->assertArrayNotHasKey('message_id', $conv['items'][0], 'cabeçalhos internos não vão para a tela');

        $this->assertSame(['total' => 5, 'sent' => 3, 'failed' => 0, 'replies' => 2,
            'last_reply_at' => '2026-11-21 16:00:00', 'last_at' => '2026-11-22 09:00:00', 'bounced' => 0], $r['summary']);
    }

    public function testRespostaAntigaSemTextoEFalhaDeEnvio(): void
    {
        $messages = [
            $this->out(1, 'Oi', '2026-11-20 09:00:00', ['replied_at' => '2026-11-20 12:00:00']),
            $this->out(2, 'Tentativa', '2026-11-21 09:00:00', ['status' => 'failed', 'sent_at' => null, 'error_message' => 'SMTP recusou']),
            // Resposta antiga gravada só com o snippet: usa o snippet como texto
            $this->in(3, 'Re: Tentativa', '2026-11-21 10:00:00', ['reply_snippet' => 'Recebi sim']),
        ];
        $r = EmailThreadRules::conversations(EmailThreadRules::merge($messages, []));
        $byKey = [];
        foreach ($r['conversations'] as $c) $byKey[$c['items'][0]['key']] = $c;

        $this->assertSame('2026-11-20 12:00:00', $byKey['m1']['reply_without_text_at'], 'resposta detectada sem texto guardado');
        $this->assertTrue($byKey['m1']['has_reply']);
        $this->assertSame('failed', $byKey['m2']['items'][0]['status']);
        $this->assertSame('SMTP recusou', $byKey['m2']['items'][0]['error']);
        $this->assertSame('Recebi sim', $byKey['m2']['items'][1]['body_text']);
        $this->assertSame(1, $r['summary']['sent']);
        $this->assertSame(1, $r['summary']['failed']);
        $this->assertSame(2, $r['summary']['replies']);
    }

    public function testSemEmails(): void
    {
        $r = EmailThreadRules::conversations(EmailThreadRules::merge([], []));
        $this->assertSame([], $r['conversations']);
        $this->assertSame(0, $r['summary']['total']);
        $this->assertNull($r['summary']['last_reply_at']);
    }

    public function testPreviewCurtoEmUmaLinha(): void
    {
        $this->assertSame('a b c', EmailThreadRules::preview("a\n\n b\tc"));
        $p = EmailThreadRules::preview(str_repeat('x', 300));
        $this->assertSame(EmailThreadRules::PREVIEW_LEN, mb_strlen($p));
        $this->assertStringEndsWith('…', $p);
    }
}
