<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use SocialAccount;
use SocialSnapshot;
use BufferData;
use Database;

/**
 * Testes de integração de Métricas Sociais contra o banco helpdesk_on_test.
 * Cobre SocialAccount (upsert, snapshot de seguidores, crescimento 7/30/90d,
 * closest), SocialSnapshot (upsert por source+entity+data) e BufferData
 * (posts + métricas agregadas, sumMetric/timeline/topPosts).
 */
final class SocialMetricsTest extends TestCase
{
    private Database $db;
    private SocialAccount $social;
    private SocialSnapshot $snapshot;
    private BufferData $buffer;
    private int $accountId;
    private string $entityKey;
    private string $bufferPostId;
    private string $channelId;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
        $this->social = new SocialAccount();
        $this->snapshot = new SocialSnapshot();
        $this->buffer = new BufferData();

        $u = uniqid();
        $this->entityKey = 'ig_' . $u;
        $this->accountId = (int) $this->social->upsert('meta_instagram', $this->entityKey, [
            'display_name' => 'Conta IG Teste',
            'username' => 'ig_teste_' . $u,
            'followers' => 1000,
        ]);

        $this->bufferPostId = 'bp_' . $u;
        $this->channelId = 'ch_' . $u;
    }

    protected function tearDown(): void
    {
        // followers_history sai via CASCADE do social_accounts
        try { $this->db->delete('social_followers_history', 'account_id = ?', [$this->accountId]); } catch (\Throwable $e) {}
        try { $this->db->delete('social_posts', 'account_id = ?', [$this->accountId]); } catch (\Throwable $e) {}
        try { $this->db->delete('social_accounts', 'id = ?', [$this->accountId]); } catch (\Throwable $e) {}
        try { $this->db->delete('social_snapshots', 'entity_key = ?', [$this->entityKey]); } catch (\Throwable $e) {}
        try { $this->db->delete('buffer_post_metrics', 'buffer_post_id = ?', [$this->bufferPostId]); } catch (\Throwable $e) {}
        try { $this->db->delete('buffer_posts', 'buffer_post_id = ?', [$this->bufferPostId]); } catch (\Throwable $e) {}
    }

    // ===== SocialAccount: upsert e métricas =====

    public function testUpsertNaoDuplicaEAtualiza(): void
    {
        // Segundo upsert com o mesmo provider+external_id atualiza, não cria outro
        $id2 = (int) $this->social->upsert('meta_instagram', $this->entityKey, ['followers' => 1500]);
        $this->assertSame($this->accountId, $id2);
        $acc = $this->social->findById($this->accountId);
        $this->assertSame(1500, (int) $acc['followers']);
    }

    // ===== Histórico de seguidores / crescimento =====

    public function testSnapshotDeSeguidoresEUpsertPorDia(): void
    {
        $this->social->saveFollowersSnapshot($this->accountId, 1000, null, null, '2026-01-01');
        // Mesmo dia → atualiza (ON DUPLICATE KEY)
        $this->social->saveFollowersSnapshot($this->accountId, 1100, null, null, '2026-01-01');

        $rows = $this->db->fetchAll(
            "SELECT * FROM social_followers_history WHERE account_id = ? AND snapshot_date = ?",
            [$this->accountId, '2026-01-01']
        );
        $this->assertCount(1, $rows);
        $this->assertSame(1100, (int) $rows[0]['followers']);
    }

    public function testGetFollowersClosestPegaAnteriorOuIgual(): void
    {
        $this->social->saveFollowersSnapshot($this->accountId, 900, null, null, '2026-01-01');
        $this->social->saveFollowersSnapshot($this->accountId, 1000, null, null, '2026-01-10');

        $closest = $this->social->getFollowersClosest($this->accountId, '2026-01-05');
        $this->assertSame(900, (int) $closest['followers']); // o de 01/01 (<= 05/01)

        $closest2 = $this->social->getFollowersClosest($this->accountId, '2026-01-15');
        $this->assertSame(1000, (int) $closest2['followers']);
    }

    public function testGetFollowersGrowthCalculaVariacao(): void
    {
        $hoje = date('Y-m-d');
        $ref7 = date('Y-m-d', strtotime('-7 days'));
        // 7 dias atrás: 800; hoje: 1000 → +200 (+25%)
        $this->social->saveFollowersSnapshot($this->accountId, 800, null, null, $ref7);
        $this->social->saveFollowersSnapshot($this->accountId, 1000, null, null, $hoje);

        $growth = $this->social->getFollowersGrowth($this->accountId);
        $this->assertSame(1000, (int) $growth['current']);
        $this->assertSame(200, (int) $growth['7d']['diff']);
        $this->assertEqualsWithDelta(25.0, (float) $growth['7d']['pct'], 0.01);
    }

    public function testGetFollowersGrowthNaoQuebraSemHistorico(): void
    {
        // Conta sem nenhum snapshot: current null, pct null (guarda de divisão)
        $growth = $this->social->getFollowersGrowth($this->accountId);
        $this->assertNull($growth['current']);
        $this->assertNull($growth['7d']['pct']);
        $this->assertNull($growth['7d']['diff']);
    }

    // ===== SocialSnapshot =====

    public function testSnapshotSaveUpsertPorSourceEntityData(): void
    {
        $this->snapshot->save('direct', 'instagram', $this->entityKey, 'Conta IG', [
            'followers' => 1000, 'likes' => 50, 'engagement_rate' => 2.5,
        ], '2026-02-01');
        // mesmo (source, entity_key, date) → atualiza
        $this->snapshot->save('direct', 'instagram', $this->entityKey, 'Conta IG', [
            'followers' => 1200, 'likes' => 80,
        ], '2026-02-01');

        $rows = $this->db->fetchAll(
            "SELECT * FROM social_snapshots WHERE source = 'direct' AND entity_key = ? AND snapshot_date = ?",
            [$this->entityKey, '2026-02-01']
        );
        $this->assertCount(1, $rows);
        $this->assertSame(1200, (int) $rows[0]['followers']);

        $hist = $this->snapshot->history($this->entityKey);
        $this->assertNotEmpty($hist);
    }

    // ===== BufferData: posts e métricas =====

    public function testSavePostEMetricasAgregadas(): void
    {
        $this->buffer->savePost([
            'buffer_post_id' => $this->bufferPostId,
            'channel_id' => $this->channelId,
            'service' => 'instagram',
            'text' => 'Post de teste',
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
        ]);

        $this->buffer->saveMetric($this->bufferPostId, ['type' => 'reactions', 'name' => 'Reações', 'value' => 120, 'unit' => 'count']);
        $this->buffer->saveMetric($this->bufferPostId, ['type' => 'comments', 'name' => 'Comentários', 'value' => 15, 'unit' => 'count']);
        // upsert: re-salvar a mesma métrica atualiza o valor
        $this->buffer->saveMetric($this->bufferPostId, ['type' => 'reactions', 'name' => 'Reações', 'value' => 130, 'unit' => 'count']);

        $metrics = $this->buffer->getMetricsForPost($this->bufferPostId);
        $tipos = array_column($metrics, 'metric_value', 'metric_type');
        $this->assertEqualsWithDelta(130.0, (float) $tipos['reactions'], 0.01);
        $this->assertEqualsWithDelta(15.0, (float) $tipos['comments'], 0.01);

        // sumMetric soma no período
        $hoje = date('Y-m-d');
        $soma = $this->buffer->sumMetric('reactions', $hoje, $hoje);
        $this->assertGreaterThanOrEqual(130.0, $soma);
    }

    public function testMetricTimelineOrdenadaNoTempo(): void
    {
        $this->buffer->savePost([
            'buffer_post_id' => $this->bufferPostId,
            'channel_id' => $this->channelId,
            'service' => 'instagram',
            'text' => 'Post timeline',
            'status' => 'sent',
            'sent_at' => date('Y-m-d H:i:s'),
        ]);
        $this->buffer->saveMetric($this->bufferPostId, ['type' => 'impressions', 'name' => 'Impressões', 'value' => 500, 'unit' => 'count']);

        $hoje = date('Y-m-d');
        $timeline = $this->buffer->metricTimeline('impressions', $hoje, $hoje);
        $this->assertNotEmpty($timeline);
    }
}
