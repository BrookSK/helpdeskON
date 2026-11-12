<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use WhatsappMentionRules as R;

/**
 * Regras puras das menções (@) em grupos de WhatsApp.
 * Os formatos de payload seguem o observado na Evolution v2 (grupos em LID).
 */
final class WhatsappMentionRulesTest extends TestCase
{
    private const OWNER_PHONE = '5517988093160';
    private const OWNER_LID = '156079530967127@lid';

    // ---------- extractMentionedJids ----------

    public function testExtraiMencoesDoTextoEstendido(): void
    {
        $msg = ['message' => ['extendedTextMessage' => [
            'text' => 'oi @156079530967127 e @58935558037626',
            'contextInfo' => ['mentionedJid' => ['156079530967127@lid', '58935558037626@lid']],
        ]]];
        $this->assertSame(['156079530967127@lid', '58935558037626@lid'], R::extractMentionedJids($msg));
    }

    public function testExtraiMencoesDaLegendaDeImagemEDoTopo(): void
    {
        $msg = [
            'contextInfo' => ['mentionedJid' => ['5511999990000@s.whatsapp.net']],
            'message' => ['imageMessage' => [
                'caption' => '@5511999990000 olha',
                'contextInfo' => ['mentionedJid' => ['5511999990000@s.whatsapp.net', '5521988887777@s.whatsapp.net']],
            ]],
        ];
        // Sem duplicata, na ordem em que apareceram
        $this->assertSame(
            ['5511999990000@s.whatsapp.net', '5521988887777@s.whatsapp.net'],
            R::extractMentionedJids($msg)
        );
    }

    public function testExtraiMencoesDeMensagemEmbrulhada(): void
    {
        $msg = ['message' => ['ephemeralMessage' => ['message' => ['extendedTextMessage' => [
            'text' => '@1',
            'contextInfo' => ['mentionedJid' => ['111@lid']],
        ]]]]];
        $this->assertSame(['111@lid'], R::extractMentionedJids($msg));
    }

    public function testSemMencaoRetornaVazio(): void
    {
        $this->assertSame([], R::extractMentionedJids(['message' => ['conversation' => 'bom dia @todos']]));
        $this->assertSame([], R::extractMentionedJids([]));
        $this->assertSame([], R::extractMentionedJids(['message' => ['extendedTextMessage' => [
            'contextInfo' => ['mentionedJid' => [null, '', 123], 'stanzaId' => 'ABC'],
        ]]]));
    }

    // ---------- mentionsOwner ----------

    public function testMencaoPorLidDoNossoNumero(): void
    {
        $this->assertTrue(R::mentionsOwner(['58935558037626@lid', '156079530967127@lid'], self::OWNER_PHONE, self::OWNER_LID));
    }

    public function testMencaoDeOutraPessoaNaoConta(): void
    {
        $this->assertFalse(R::mentionsOwner(['58935558037626@lid', '5511999990000@s.whatsapp.net'], self::OWNER_PHONE, self::OWNER_LID));
    }

    public function testMencaoPorTelefoneComESemNonoDigitoEComAparelho(): void
    {
        $this->assertTrue(R::mentionsOwner(['5517988093160@s.whatsapp.net'], self::OWNER_PHONE, null));
        $this->assertTrue(R::mentionsOwner(['551788093160@s.whatsapp.net'], self::OWNER_PHONE, null));   // sem o 9
        $this->assertTrue(R::mentionsOwner(['5517988093160:12@s.whatsapp.net'], '551788093160', null)); // aparelho
    }

    public function testLidNuncaEComparadoComoTelefone(): void
    {
        // Um LID cujos dígitos "parecem" o nosso telefone não é o nosso número
        $this->assertFalse(R::mentionsOwner(['5517988093160@lid'], self::OWNER_PHONE, null));
    }

    public function testSemIdentidadeDaInstanciaNaoMarca(): void
    {
        $this->assertFalse(R::mentionsOwner(['156079530967127@lid'], null, null));
        $this->assertFalse(R::mentionsOwner(['156079530967127@lid'], '', ''));
        // LID desconhecido: a menção por LID não pode ser confirmada só pelo telefone
        $this->assertFalse(R::mentionsOwner(['156079530967127@lid'], self::OWNER_PHONE, null));
    }

    public function testNeedsOwnerLid(): void
    {
        $this->assertTrue(R::needsOwnerLid(['156079530967127@lid'], null));
        $this->assertFalse(R::needsOwnerLid(['156079530967127@lid'], self::OWNER_LID));
        $this->assertFalse(R::needsOwnerLid(['5517988093160@s.whatsapp.net'], null));
        $this->assertFalse(R::needsOwnerLid([], null));
    }

    // ---------- textMentionsOwner (reserva) ----------

    public function testTextoComNossoLidOuTelefone(): void
    {
        $this->assertTrue(R::textMentionsOwner('@156079530967127 consegue ver?', self::OWNER_PHONE, self::OWNER_LID));
        $this->assertTrue(R::textMentionsOwner('fala @5517988093160', self::OWNER_PHONE, null));
        $this->assertFalse(R::textMentionsOwner('@58935558037626 consegue?', self::OWNER_PHONE, self::OWNER_LID));
        // Prefixo do nosso LID não é o nosso LID
        $this->assertFalse(R::textMentionsOwner('@1560795309671270', self::OWNER_PHONE, self::OWNER_LID));
        // E-mail não é menção
        $this->assertFalse(R::textMentionsOwner('contato5517988093160@empresa.com', self::OWNER_PHONE, null));
        $this->assertFalse(R::textMentionsOwner('sem arroba', self::OWNER_PHONE, self::OWNER_LID));
    }

    // ---------- identidade da instância ----------

    public function testTelefoneDaInstanciaV2(): void
    {
        $resp = [['name' => 'helpdesk-lrv', 'ownerJid' => '5517988093160@s.whatsapp.net', 'connectionStatus' => 'open']];
        $this->assertSame('5517988093160', R::ownerPhoneFromInstanceInfo($resp, 'helpdesk-lrv'));
    }

    public function testTelefoneDaInstanciaEscolheAInstanciaCerta(): void
    {
        $resp = [
            ['name' => 'outra', 'ownerJid' => '5511900000000@s.whatsapp.net'],
            ['name' => 'helpdesk-lrv', 'ownerJid' => '5517988093160@s.whatsapp.net'],
        ];
        $this->assertSame('5517988093160', R::ownerPhoneFromInstanceInfo($resp, 'helpdesk-lrv'));
        $this->assertSame('', R::ownerPhoneFromInstanceInfo($resp, 'inexistente'));
    }

    public function testTelefoneDaInstanciaV1EErro(): void
    {
        $v1 = [['instance' => ['instanceName' => 'x', 'owner' => '5511987654321@s.whatsapp.net']]];
        $this->assertSame('5511987654321', R::ownerPhoneFromInstanceInfo($v1, 'x'));
        $this->assertSame('', R::ownerPhoneFromInstanceInfo(['error' => true, 'message' => 'x'], 'x'));
        $this->assertSame('', R::ownerPhoneFromInstanceInfo(null, 'x'));
        $this->assertSame('', R::ownerPhoneFromInstanceInfo([['name' => 'x', 'ownerJid' => null]], 'x'));
    }

    public function testAchaNossoLidNosParticipantes(): void
    {
        $resp = ['participants' => [
            ['id' => '236932122603562@lid', 'phoneNumber' => '5517991253062@s.whatsapp.net', 'admin' => 'admin'],
            ['id' => '156079530967127@lid', 'phoneNumber' => '5517988093160@s.whatsapp.net', 'admin' => 'superadmin'],
        ]];
        $this->assertSame('156079530967127@lid', R::findOwnLidInParticipants($resp, self::OWNER_PHONE));
        // Telefone salvo sem o 9º dígito também casa
        $this->assertSame('156079530967127@lid', R::findOwnLidInParticipants($resp, '551788093160'));
    }

    public function testLidNoModoTelefoneVemNoCampoLid(): void
    {
        $resp = [['id' => '5517988093160@s.whatsapp.net', 'lid' => '156079530967127@lid']];
        $this->assertSame('156079530967127@lid', R::findOwnLidInParticipants($resp, self::OWNER_PHONE));
    }

    public function testLidNaoEncontrado(): void
    {
        $resp = ['participants' => [['id' => '236932122603562@lid', 'phoneNumber' => '5517991253062@s.whatsapp.net']]];
        $this->assertSame('', R::findOwnLidInParticipants($resp, self::OWNER_PHONE));
        $this->assertSame('', R::findOwnLidInParticipants($resp, ''));
        $this->assertSame('', R::findOwnLidInParticipants(['error' => true], self::OWNER_PHONE));
        // Nosso número sem LID (grupo antigo): não inventa LID
        $this->assertSame('', R::findOwnLidInParticipants([['id' => '5517988093160@s.whatsapp.net']], self::OWNER_PHONE));
    }

    // ---------- buildMentionCandidates ----------

    private function participantes(): array
    {
        return ['participants' => [
            ['id' => '236932122603562@lid', 'phoneNumber' => '5517991253062@s.whatsapp.net', 'admin' => 'admin'],
            ['id' => '58935558037626@lid', 'phoneNumber' => '5517991190528@s.whatsapp.net', 'name' => null],
            ['id' => '156079530967127@lid', 'phoneNumber' => '5517988093160@s.whatsapp.net', 'admin' => 'superadmin'],
            ['id' => '134973977849932@lid', 'name' => 'Punta Cana para Brasileiros'],
            ['id' => '999000111@lid'],                                  // sem nome e sem telefone
            ['id' => '5511987650001@s.whatsapp.net', 'notify' => 'Zé'], // grupo em modo telefone
            ['id' => '120363396240081249@g.us'],                        // lixo: grupo não é pessoa
            ['id' => '58935558037626@lid'],                             // duplicado
        ]];
    }

    public function testCandidatosExcluemNossoNumeroEResolvemNomes(): void
    {
        $out = R::buildMentionCandidates(
            $this->participantes(),
            ['58935558037626@lid' => 'Lucas Vacari'],                 // visto nas mensagens
            ['551791253062' => 'Ana Cliente'],                        // salvo no CRM (phoneKey)
            self::OWNER_PHONE,
            self::OWNER_LID
        );

        $tokens = array_column($out['participants'], 'token');
        $this->assertNotContains('156079530967127', $tokens, 'o nosso número não aparece na lista');
        $this->assertNotContains('120363396240081249', $tokens);
        $this->assertSame(count($tokens), count(array_unique($tokens)), 'sem duplicatas');
        $this->assertContains('156079530967127', $out['self_tokens']);
        $this->assertContains('5517988093160', $out['self_tokens']);

        $byToken = array_column($out['participants'], null, 'token');
        $this->assertSame('Ana Cliente', $byToken['236932122603562']['name']);
        $this->assertSame('236932122603562@lid', $byToken['236932122603562']['jid']);
        $this->assertSame('+55 (17) 99125-3062', $byToken['236932122603562']['phone']);
        $this->assertSame('Lucas Vacari', $byToken['58935558037626']['name']);
        $this->assertSame('Punta Cana para Brasileiros', $byToken['134973977849932']['name']);
        $this->assertSame('Zé', $byToken['5511987650001']['name']);
        $this->assertSame('5511987650001@s.whatsapp.net', $byToken['5511987650001']['jid']);
        // Sem nome nenhum: rótulo é o próprio token, e vai para o fim da lista
        $this->assertSame('999000111', $byToken['999000111']['name']);
        $this->assertFalse($byToken['999000111']['has_name']);
        $this->assertSame('999000111', end($out['participants'])['token']);
    }

    public function testCandidatosOrdenadosPorNomeSemAcento(): void
    {
        $resp = [
            ['id' => '1001@lid', 'name' => 'Óscar'],
            ['id' => '1002@lid', 'name' => 'bruna'],
            ['id' => '1003@lid', 'name' => 'Ana'],
        ];
        $out = R::buildMentionCandidates($resp, [], [], null, null);
        $this->assertSame(['Ana', 'bruna', 'Óscar'], array_column($out['participants'], 'name'));
        $this->assertSame([], $out['self_tokens']);
    }

    public function testNomeSemQuebraDeLinhaNemArroba(): void
    {
        $out = R::buildMentionCandidates([['id' => '1001@lid', 'name' => "Maria\n@ Loja"]], [], [], null, null);
        $this->assertSame('Maria Loja', $out['participants'][0]['name']);
    }

    public function testCandidatosComRespostaDeErro(): void
    {
        $out = R::buildMentionCandidates(['error' => true, 'message' => 'x'], [], [], self::OWNER_PHONE, self::OWNER_LID);
        $this->assertSame([], $out['participants']);
    }

    // ---------- sanitizeOutgoingMentions ----------

    public function testSoAceitaQuemEstaNoTexto(): void
    {
        $text = "*Atendente*\nOi @58935558037626, pode ver? cc @5511987650001";
        $jids = ['58935558037626@lid', '5511987650001@s.whatsapp.net', '236932122603562@lid'];
        $this->assertSame(['58935558037626@lid', '5511987650001@s.whatsapp.net'], R::sanitizeOutgoingMentions($text, $jids));
    }

    public function testAceitaJsonEDeduplica(): void
    {
        $this->assertSame(
            ['58935558037626@lid'],
            R::sanitizeOutgoingMentions('@58935558037626 @58935558037626', '["58935558037626@lid","58935558037626@lid"]')
        );
        $this->assertSame([], R::sanitizeOutgoingMentions('@1', 'não é json'));
    }

    public function testRejeitaJidInvalido(): void
    {
        $text = '@120363396240081249 @123 @58935558037626';
        $jids = ['120363396240081249@g.us', 'status@broadcast', '58935558037626@lid<script>', ['x'], 5, '58935558037626'];
        $this->assertSame([], R::sanitizeOutgoingMentions($text, $jids));
    }

    public function testTokenPrecisaSerExato(): void
    {
        // "@589355580376260" não é "@58935558037626"; e-mail também não
        $this->assertSame([], R::sanitizeOutgoingMentions('@589355580376260', ['58935558037626@lid']));
        $this->assertSame([], R::sanitizeOutgoingMentions('x58935558037626@lid.com', ['58935558037626@lid']));
        $this->assertSame([], R::sanitizeOutgoingMentions('', ['58935558037626@lid']));
    }

    public function testLimiteDeMencoes(): void
    {
        $jids = [];
        $text = '';
        for ($i = 0; $i < R::MAX_MENTIONS + 10; $i++) {
            $tok = (string) (100000 + $i);
            $jids[] = $tok . '@lid';
            $text .= '@' . $tok . ' ';
        }
        $this->assertCount(R::MAX_MENTIONS, R::sanitizeOutgoingMentions($text, $jids));
    }

    // ---------- auxiliares ----------

    public function testJidUserEDominio(): void
    {
        $this->assertSame('5517988093160', R::jidUser('5517988093160:3@s.whatsapp.net'));
        $this->assertSame('lid', R::jidDomain('1@LID'));
        $this->assertSame('', R::jidDomain('5511'));
        $this->assertSame('', R::phoneKeyOf('156079530967127@lid'));
        $this->assertSame('551788093160', R::phoneKeyOf('5517988093160'));
        $this->assertSame('', R::phoneDigits('120363396240081249@g.us'));
    }
}
