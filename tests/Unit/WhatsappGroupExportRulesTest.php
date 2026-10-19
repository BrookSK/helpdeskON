<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use WhatsappGroupExportRules as R;

/**
 * Regras puras da exportação dos participantes dos grupos de WhatsApp.
 */
final class WhatsappGroupExportRulesTest extends TestCase
{
    // ---------- phoneFromJid ----------

    public function testTelefoneDeJidDePessoa(): void
    {
        $this->assertSame('5511987654321', R::phoneFromJid('5511987654321@s.whatsapp.net'));
        $this->assertSame('5511987654321', R::phoneFromJid('5511987654321@c.us'));
        $this->assertSame('5511987654321', R::phoneFromJid('5511987654321:12@s.whatsapp.net'), 'remove sufixo de dispositivo');
        $this->assertSame('5511987654321', R::phoneFromJid('5511987654321'));
    }

    public function testJidSemTelefoneRetornaVazio(): void
    {
        $this->assertSame('', R::phoneFromJid('123456789012345@lid'), '@lid não é telefone');
        $this->assertSame('', R::phoneFromJid('120363012345678@g.us'), 'grupo não é pessoa');
        $this->assertSame('', R::phoneFromJid('status@broadcast'));
        $this->assertSame('', R::phoneFromJid(''));
        $this->assertSame('', R::phoneFromJid('123'), 'curto demais');
    }

    // ---------- parseParticipant ----------

    public function testParticipanteComLidUsaPhoneNumber(): void
    {
        $p = R::parseParticipant(['id' => '999@lid', 'phoneNumber' => '5521912345678@s.whatsapp.net', 'admin' => 'admin']);
        $this->assertSame('5521912345678', $p['phone']);
        $this->assertSame('5521912345678@s.whatsapp.net', $p['jid']);
        $this->assertSame('Sim', $p['admin']);
    }

    public function testParticipanteSomenteLidFicaSemTelefone(): void
    {
        $p = R::parseParticipant(['id' => '999@lid', 'admin' => null]);
        $this->assertSame('', $p['phone']);
        $this->assertSame('999@lid', $p['jid']);
        $this->assertSame('Não', $p['admin']);
    }

    public function testSuperadminViraCriador(): void
    {
        $this->assertSame('Criador', R::parseParticipant(['id' => '5511987654321@s.whatsapp.net', 'admin' => 'superadmin'])['admin']);
    }

    // ---------- parseGroups ----------

    public function testParseGroupsListaDiretaOrdenadaEDeduplicada(): void
    {
        $api = [
            ['id' => '2@g.us', 'subject' => 'Zeta', 'participants' => [
                ['id' => '5511987654321@s.whatsapp.net', 'admin' => null],
                ['id' => '5511987654321@s.whatsapp.net', 'admin' => null], // duplicado
                ['id' => '777@lid'],
            ]],
            ['id' => '1@g.us', 'subject' => 'alfa', 'participants' => []],
            ['subject' => 'Sem JID'], // ignorado
            'lixo',
        ];
        $g = R::parseGroups($api);
        $this->assertCount(2, $g);
        $this->assertSame('alfa', $g[0]['subject']);
        $this->assertSame('Zeta', $g[1]['subject']);
        $this->assertCount(2, $g[1]['participants']);
    }

    public function testParseGroupsEmbrulhadoEmGroupsESemNome(): void
    {
        $g = R::parseGroups(['groups' => [['id' => '120363@g.us', 'participants' => ['5511987654321@s.whatsapp.net']]]]);
        $this->assertSame('120363', $g[0]['subject'], 'sem subject usa o número do grupo');
        $this->assertSame('5511987654321', $g[0]['participants'][0]['phone'], 'participante como string');
    }

    public function testParseGroupsFiltraPorJidsSelecionados(): void
    {
        $api = [
            ['id' => '111@g.us', 'subject' => 'A', 'participants' => []],
            ['id' => '222@g.us', 'subject' => 'B', 'participants' => []],
            ['id' => '333@g.us', 'subject' => 'C', 'participants' => []],
        ];
        // Seleção com e sem sufixo @g.us deve casar o mesmo grupo
        $g = R::parseGroups($api, ['111@g.us', '333']);
        $this->assertSame(['A', 'C'], array_column($g, 'subject'));

        // Lista vazia ou nula = todos os grupos
        $this->assertCount(3, R::parseGroups($api, []));
        $this->assertCount(3, R::parseGroups($api, null));

        // JID selecionado inexistente não traz nada
        $this->assertSame([], R::parseGroups($api, ['999@g.us']));

        // Só JIDs inválidos (sem dígitos) = trata como "todos"
        $this->assertCount(3, R::parseGroups($api, ['', '@g.us']));
    }

    public function testNormalizeGroupJid(): void
    {
        $this->assertSame('120363', R::normalizeGroupJid('120363@g.us'));
        $this->assertSame('120363', R::normalizeGroupJid('120363'));
        $this->assertSame('5511987654321', R::normalizeGroupJid('5511987654321@s.whatsapp.net'));
        $this->assertSame('', R::normalizeGroupJid(''));
        $this->assertSame('', R::normalizeGroupJid('@g.us'));
    }

    public function testParseGroupsRespostaDeErroOuInvalida(): void
    {
        $this->assertSame([], R::parseGroups(['error' => true, 'message' => 'x']));
        $this->assertSame([], R::parseGroups(null));
        $this->assertSame([], R::parseGroups('texto'));
        $this->assertSame([], R::parseGroups([]));
    }

    // ---------- telefone ----------

    public function testFormatPhone(): void
    {
        $this->assertSame('+55 (11) 98765-4321', R::formatPhone('5511987654321'));
        $this->assertSame('+55 (11) 8765-4321', R::formatPhone('551187654321'));
        $this->assertSame('+14155552671', R::formatPhone('14155552671'));
        $this->assertSame('', R::formatPhone(''));
    }

    public function testPhoneKeyCasaComESemNonoDigito(): void
    {
        $this->assertSame(R::phoneKey('5511987654321'), R::phoneKey('551187654321'));
        $this->assertSame(R::phoneKey('5511987654321'), R::phoneKey('+55 (11) 98765-4321'));
        $this->assertNotSame(R::phoneKey('5511987654321'), R::phoneKey('5521987654321'), 'DDD diferente não casa');
    }

    // ---------- parseProfile ----------

    public function testParseProfileBusiness(): void
    {
        $p = R::parseProfile([
            'wuid' => '5511987654321@s.whatsapp.net',
            'name' => 'Loja X',
            'status' => ['status' => 'Atendemos 9h-18h', 'setAt' => '2024-01-01'],
            'isBusiness' => true,
            'email' => 'contato@lojax.com.br',
            'description' => 'Moda. Insta: @lojax',
            'website' => ['https://lojax.com.br'],
            'category' => 'Loja de roupas',
            'address' => 'Rua A, 10',
        ]);
        $this->assertSame('Loja X', $p['name']);
        $this->assertSame('Atendemos 9h-18h', $p['status']);
        $this->assertSame(['https://lojax.com.br'], $p['websites']);
        $this->assertTrue($p['is_business']);
        $this->assertSame('Loja de roupas', $p['category']);
    }

    public function testParseProfileErroOuVazio(): void
    {
        $p = R::parseProfile(['error' => true]);
        $this->assertSame('', $p['name']);
        $this->assertSame([], $p['websites']);
        $this->assertNull($p['is_business']);
        $this->assertSame('', R::parseProfile(null)['status']);
        $this->assertSame(['http://a.com'], R::parseProfile(['website' => 'http://a.com'])['websites'], 'website string');
    }

    // ---------- extractLinks ----------

    public function testExtractLinksClassificaEDeduplica(): void
    {
        $l = R::extractLinks(
            ['https://lojax.com.br', 'lojax.com.br/', 'https://www.instagram.com/lojax'],
            ['Siga facebook.com/lojax e linkedin.com/in/fulano ou wa.me/5511987654321.']
        );
        $this->assertSame('https://lojax.com.br', $l['site'], 'mesmo site com/sem https e barra final conta uma vez');
        $this->assertSame('https://www.instagram.com/lojax', $l['instagram']);
        $this->assertSame('https://facebook.com/lojax', $l['facebook']);
        $this->assertSame('https://linkedin.com/in/fulano', $l['linkedin']);
        $this->assertSame('https://wa.me/5511987654321', $l['outros'], 'pontuação final removida');
    }

    public function testExtractLinksArrobaInstagramSoComContexto(): void
    {
        $this->assertSame('https://instagram.com/minhaloja', R::extractLinks([], ['Insta: @minhaloja'])['instagram']);
        $this->assertSame('', R::extractLinks([], ['fale com @joao amanhã'])['instagram'], 'sem mencionar insta não vira link');
        $this->assertSame('', R::extractLinks([], ['instagram contato@empresa.com'])['instagram'], 'e-mail não vira @handle');
    }

    public function testExtractLinksVazio(): void
    {
        $l = R::extractLinks([], []);
        $this->assertSame(['site' => '', 'instagram' => '', 'facebook' => '', 'linkedin' => '', 'outros' => ''], $l);
        $this->assertSame('', R::extractLinks(['', 'nao-e-url'], ['texto sem link'])['site']);
    }

    // ---------- buildRow ----------

    public function testBuildRowCompleto(): void
    {
        $group = ['subject' => 'Clientes VIP', 'jid' => '1@g.us'];
        $part = R::parseParticipant(['id' => '5511987654321@s.whatsapp.net', 'admin' => 'admin']);
        $local = ['contact_name' => 'Maria (CRM)', 'lead_email' => 'maria@crm.com', 'linkedin_url' => 'https://linkedin.com/in/maria'];
        $profile = R::parseProfile(['name' => 'Maria Perfil', 'website' => ['https://maria.dev'], 'description' => 'insta @maria.dev', 'isBusiness' => true]);

        $row = R::buildRow($group, $part, $local, $profile, 'Comercial');
        $this->assertCount(count(R::headers()), $row, 'uma célula por cabeçalho');
        $this->assertSame('Clientes VIP', $row[0]);
        $this->assertSame('Maria (CRM)', $row[1], 'nome salvo no CRM tem prioridade');
        $this->assertSame('+55 (11) 98765-4321', $row[2]);
        $this->assertSame('Sim', $row[3]);
        $this->assertSame('maria@crm.com', $row[4], 'sem e-mail no perfil usa o do CRM');
        $this->assertSame('https://maria.dev', $row[5]);
        $this->assertSame('https://instagram.com/maria.dev', $row[6]);
        $this->assertSame('https://linkedin.com/in/maria', $row[8]);
        $this->assertSame('Sim', $row[14]);
        $this->assertSame('Comercial', $row[15]);
    }

    public function testBuildRowSemDadosExtrasELid(): void
    {
        $part = R::parseParticipant(['id' => '999@lid']);
        $row = R::buildRow(['subject' => 'G'], $part, ['sender_name' => 'Zé do Grupo'], []);
        $this->assertSame('Zé do Grupo', $row[1], 'cai para o nome visto nas mensagens');
        $this->assertSame('Oculto pelo WhatsApp', $row[2]);
        $this->assertSame('', $row[4]);
        $this->assertSame('', $row[14], 'Business desconhecido fica vazio');
        $this->assertSame('999@lid', $row[16]);
    }

    // ---------- csvSafe / fileName ----------

    public function testCsvSafeNeutralizaFormulas(): void
    {
        $this->assertSame("'=1+1", R::csvSafe('=1+1'));
        $this->assertSame("'+55 (11) 9", R::csvSafe('+55 (11) 9'));
        $this->assertSame("'@cmd", R::csvSafe('@cmd'));
        $this->assertSame('Maria', R::csvSafe('Maria'));
        $this->assertSame('', R::csvSafe(null));
    }

    public function testFileName(): void
    {
        $ts = mktime(14, 5, 9, 3, 2, 2026);
        $this->assertSame('contatos_grupos_whatsapp_2026-03-02_140509.xlsx', R::fileName('xlsx', $ts));
    }
}
