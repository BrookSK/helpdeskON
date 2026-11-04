<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Contract;
use ContractSigning;

/**
 * Contrato da change 02 (tarefas 3.1, 3.3, 3.4 e 3.5): dados do cliente no
 * modelo, assinatura única pelo webhook e pelo upload, PDF assinado guardado
 * fora de public/ e download da ClickSign com nova tentativa.
 */
final class ContractSigningTest extends TestCase
{
    use CommercialFixtures;

    private Contract $model;
    private int $userId;
    private string $root;

    protected function setUp(): void
    {
        $this->requireTestDb();
        $this->model = new Contract();
        $this->userId = $this->newUser();
        $this->root = sys_get_temp_dir() . '/hd-contract-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->trackWhere('notifications', 'user_id = ?', [$this->userId]);
        foreach ($this->created as [$table, $id]) {
            if ($table === 'contracts') $this->trackWhere('finance_projects', 'contract_id = ?', [$id]);
        }
        $this->cleanupCreated();
        if (is_dir($this->root)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
            rmdir($this->root);
        }
    }

    private function signing($clicksign = null): ContractSigning
    {
        return new ContractSigning($this->model, $this->root, $clicksign);
    }

    private function pdf(int $bytes = 2000): string
    {
        $path = tempnam(sys_get_temp_dir(), 'hdpdf');
        file_put_contents($path, '%PDF-1.4' . str_repeat('x', max(0, $bytes - 8)));
        return $path;
    }

    private function financeCount(int $contractId): int
    {
        return (int)$this->db->fetch('SELECT COUNT(*) n FROM finance_projects WHERE contract_id = ?', [$contractId])['n'];
    }

    public function testContratoGeradoComDocumentoItensECondicao(): void
    {
        $company = $this->newCompany(['document' => '12.345.678/0001-90']);
        $pid = $this->newProposal($this->userId, [
            'status' => 'accepted', 'company_id' => $company,
            'content' => json_encode(['payment_method' => '40% na assinatura e 60% na entrega']),
        ], [['Loja virtual', 8000, 'once'], ['Suporte', 400, 'monthly']]);
        $tpl = ['id' => null, 'body' => '<p>{{cliente_documento}}</p>{{proposta_itens}}<p>{{condicao_pagamento}}</p><p>{{cliente_endereco}}</p>'];

        $id = $this->track('contracts', $this->model->createFromProposal((new \Proposal())->findById($pid), $tpl, $this->userId));
        $c = $this->model->findById($id);
        $this->assertSame('12.345.678/0001-90', $c['client_document']);
        $this->assertStringContainsString('<p>12.345.678/0001-90</p>', $c['body']);
        $this->assertStringContainsString('Loja virtual', $c['body']);
        $this->assertStringContainsString('R$ 400,00/mês', $c['body']);
        $this->assertStringContainsString('40% na assinatura e 60% na entrega', $c['body']);

        // Endereço preenchido no rascunho entra ao recarregar o modelo.
        $this->model->update($id, ['client_address' => 'Av. Brasil, 100']);
        $v = $this->model->valuesForContract($this->model->findById($id));
        $this->assertSame('Av. Brasil, 100', $v['cliente_endereco']);
        $this->assertSame('12.345.678/0001-90', $v['cliente_documento']);
    }

    public function testCondicaoSemTextoNaPropostaUsaOPadraoConfigurado(): void
    {
        $this->setSetting('payment_condition_default', 'Condição padrão de teste');
        $pid = $this->newProposal($this->userId, ['status' => 'accepted', 'content' => null]);
        $id = $this->track('contracts', $this->model->createFromProposal((new \Proposal())->findById($pid), ['body' => '{{condicao_pagamento}}'], $this->userId));
        $this->assertSame('Condição padrão de teste', $this->model->findById($id)['body']);
        $this->setSetting('payment_condition_default', '');
    }

    public function testWebhookDuplicadoCriaUmProjetoFinanceiro(): void
    {
        $id = $this->newContract($this->userId, ['status' => 'awaiting_signature', 'clicksign_doc_key' => 'doc-' . uniqid()]);
        $s = $this->signing();
        $a = $s->onContractSigned($id, ContractSigning::ORIGIN_CLICKSIGN);
        $b = $s->onContractSigned($id, ContractSigning::ORIGIN_CLICKSIGN);
        $this->assertTrue($a['newly_signed']);
        $this->assertFalse($b['newly_signed']);
        $this->assertSame($a['finance_id'], $b['finance_id']);
        $this->assertSame(1, $this->financeCount($id));
        $c = $this->model->findById($id);
        $this->assertSame('signed', $c['status']);
        $this->assertSame('clicksign', $c['signed_via']);
    }

    public function testUploadDepoisDoWebhookGuardaOArquivoSemDuplicar(): void
    {
        $id = $this->newContract($this->userId, ['status' => 'awaiting_signature', 'clicksign_doc_key' => 'doc-' . uniqid()]);
        $s = $this->signing();
        $s->onContractSigned($id, ContractSigning::ORIGIN_CLICKSIGN);
        $r = $s->uploadSigned($id, $this->pdf(), $this->userId, 'Comercial');
        $this->assertTrue($r['ok'], $r['error'] ?? '');
        $this->assertFalse($r['newly_signed']);
        $this->assertSame(1, $this->financeCount($id));
        $c = $this->model->findById($id);
        $this->assertSame('stored', $c['signed_file_status']);
        $this->assertFileExists($s->absolutePath($c['signed_file_path']));
        $this->assertStringStartsWith('contracts/' . $id . '/', $c['signed_file_path']);
    }

    public function testWebhookDepoisDoUpload(): void
    {
        $id = $this->newContract($this->userId, ['status' => 'approved', 'clicksign_doc_key' => 'doc-' . uniqid()]);
        $s = $this->signing();
        $r = $s->uploadSigned($id, $this->pdf(), $this->userId, 'Comercial');
        $this->assertTrue($r['ok'], $r['error'] ?? '');
        $this->assertTrue($r['newly_signed']);
        $w = $s->onContractSigned($id, ContractSigning::ORIGIN_CLICKSIGN);
        $s->markFilePending($id);
        $this->assertFalse($w['newly_signed']);
        $this->assertSame(1, $this->financeCount($id));
        $c = $this->model->findById($id);
        $this->assertSame('upload', $c['signed_via']);
        $this->assertSame('stored', $c['signed_file_status'], 'o webhook não volta o arquivo para pendente');
        $ev = $this->db->fetch("SELECT * FROM contract_events WHERE contract_id = ? AND event_type = 'signed_file'", [$id]);
        $this->assertStringContainsString('por Comercial', $ev['description']);
    }

    public function testUploadRecusaArquivoFalsoGrandeEContratoNaoAprovado(): void
    {
        $id = $this->newContract($this->userId, ['status' => 'approved']);
        $s = $this->signing();

        $fake = tempnam(sys_get_temp_dir(), 'hdfake');
        file_put_contents($fake, "PK\x03\x04 não é pdf");
        $r = $s->uploadSigned($id, $fake, $this->userId);
        $this->assertSame(400, $r['code']);
        $this->assertSame('O arquivo não é um PDF.', $r['error']);

        $big = $this->pdf(10);
        $h = fopen($big, 'r+'); ftruncate($h, 20 * 1024 * 1024 + 1); fclose($h);
        $r = $s->uploadSigned($id, $big, $this->userId);
        $this->assertSame('O arquivo passa de 20 MB.', $r['error']);
        @unlink($fake); @unlink($big);

        $this->assertSame('approved', $this->model->findById($id)['status']);
        $this->assertSame(0, $this->financeCount($id));
        $this->assertDirectoryDoesNotExist($this->root . '/contracts/' . $id);

        $draft = $this->newContract($this->userId, ['status' => 'draft']);
        $this->assertSame(409, $s->uploadSigned($draft, $this->pdf(), $this->userId)['code']);
        $this->assertSame('draft', $this->model->findById($draft)['status']);
    }

    public function testDownloadDaClickSignFalhaEDepoisBaixa(): void
    {
        $id = $this->newContract($this->userId, ['status' => 'awaiting_signature', 'clicksign_doc_key' => 'doc-' . uniqid()]);
        $fake = new FakeClickSign();
        $s = $this->signing($fake);
        $s->onContractSigned($id, ContractSigning::ORIGIN_CLICKSIGN);
        $s->markFilePending($id);

        $fake->fail = true;
        $r = $s->fetchSignedFile($id);
        $this->assertFalse($r['ok']);
        $c = $this->model->findById($id);
        $this->assertSame('signed', $c['status'], 'a falha no download não mexe no status');
        $this->assertSame('pending', $c['signed_file_status']);
        $this->assertSame(1, (int)$c['signed_file_attempts']);
        $this->assertStringContainsString('HTTP 503', $c['signed_file_error']);

        $fake->fail = false;
        $stats = $s->retryPending();
        $this->assertGreaterThanOrEqual(1, $stats['stored']);
        $c = $this->model->findById($id);
        $this->assertSame('stored', $c['signed_file_status']);
        $this->assertSame($fake->pdf, file_get_contents($s->absolutePath($c['signed_file_path'])));
        $this->assertSame(['doc' => $c['clicksign_doc_key']], ['doc' => $fake->lastKey]);
    }

    public function testDesisteDepoisDoLimiteDeTentativas(): void
    {
        $id = $this->newContract($this->userId, [
            'status' => 'signed', 'clicksign_doc_key' => 'doc-' . uniqid(),
            'signed_file_status' => 'pending', 'signed_file_attempts' => ContractSigning::MAX_DOWNLOAD_ATTEMPTS - 1,
        ]);
        $fake = new FakeClickSign();
        $fake->fail = true;
        $this->assertSame('failed', $this->signing($fake)->fetchSignedFile($id)['status']);
    }

    public function testClickSignSemConfiguracaoContaTentativa(): void
    {
        $id = $this->newContract($this->userId, ['status' => 'signed', 'clicksign_doc_key' => 'doc-' . uniqid(), 'signed_file_status' => 'pending']);
        $fake = new FakeClickSign();
        $fake->configured = false;
        $r = $this->signing($fake)->fetchSignedFile($id);
        $this->assertSame('ClickSign não configurada.', $r['error']);
        $this->assertSame(0, $fake->calls);
    }
}

/** ClickSign falsa: getDocument devolve o link e downloadFile o PDF ou erro. */
final class FakeClickSign
{
    public bool $configured = true;
    public bool $fail = false;
    public int $calls = 0;
    public ?string $lastKey = null;
    public string $pdf = "%PDF-1.7\nassinado";

    public function isConfigured(): bool { return $this->configured; }

    public function getDocument(string $key): array
    {
        $this->calls++;
        $this->lastKey = $key;
        return ['success' => true, 'data' => ['document' => ['key' => $key, 'status' => 'closed',
            'downloads' => ['signed_file_url' => 'https://example.test/signed.pdf']]]];
    }

    public function downloadFile(string $url): array
    {
        return $this->fail
            ? ['success' => false, 'body' => null, 'error' => 'Download HTTP 503']
            : ['success' => true, 'body' => $this->pdf, 'error' => null];
    }
}
