<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Database;
use PDO;
use PDOException;

/**
 * Reconexão do Database quando o servidor derruba a conexão parada.
 *
 * Reproduz o que aconteceu em produção: o cron ficou minutos montando um vídeo
 * sem falar com o banco, o servidor fechou a conexão e o próximo comando deu
 * "2006 MySQL server has gone away". Aqui a conexão é derrubada de verdade
 * (wait_timeout curto ou KILL por outra conexão) no banco helpdesk_on_test.
 */
final class DatabaseReconnectTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        $cfg = require BASE_PATH . '/config/database.php';
        if (($cfg['database'] ?? null) !== 'helpdesk_on_test') {
            $this->markTestSkipped('Banco de teste (helpdesk_on_test) não configurado.');
        }
        $this->db = Database::getInstance();
    }

    protected function tearDown(): void
    {
        // Deixa o singleton com uma conexão boa para os próximos testes.
        try { $this->db->fetch('SELECT 1 AS ok'); } catch (\Throwable $e) { $this->db->reconnect(); }
    }

    private function adminPdo(): PDO
    {
        $cfg = require BASE_PATH . '/config/database.php';
        return new PDO("mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']}", $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    private function connectionId(): int
    {
        return (int) $this->db->fetch('SELECT CONNECTION_ID() AS id')['id'];
    }

    private function killCurrentConnection(): int
    {
        $id = $this->connectionId();
        $this->adminPdo()->exec('KILL ' . $id);
        usleep(200000);
        return $id;
    }

    public function testConexaoDerrubadaPorInatividadeReconectaSozinha(): void
    {
        $antes = $this->connectionId();
        $this->db->query('SET SESSION wait_timeout = 1');
        sleep(3); // o servidor fecha a conexão parada

        $row = $this->db->fetch('SELECT 1 AS ok');
        $this->assertSame(1, (int) $row['ok']);
        $this->assertNotSame($antes, $this->connectionId(), 'deveria estar numa conexão nova');
    }

    public function testConexaoMortaPorKillReconectaEMantemOFuso(): void
    {
        $morta = $this->killCurrentConnection();

        $row = $this->db->fetch('SELECT @@session.time_zone AS tz, CONNECTION_ID() AS id');
        $this->assertNotSame($morta, (int) $row['id']);
        $this->assertSame('-03:00', $row['tz'], 'a conexão nova precisa do mesmo fuso');
    }

    public function testInsertDepoisDaQuedaGravaUmaVezSo(): void
    {
        $u = uniqid();
        $this->killCurrentConnection();
        $id = (int) $this->db->insert('users', [
            'name' => "Reconexao {$u}", 'email' => "reconexao_{$u}@example.test",
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'attendant',
        ]);
        try {
            $this->assertGreaterThan(0, $id);
            $n = $this->db->fetch('SELECT COUNT(*) AS n FROM users WHERE email = ?', ["reconexao_{$u}@example.test"]);
            $this->assertSame(1, (int) $n['n']);
        } finally {
            $this->db->delete('users', 'email = ?', ["reconexao_{$u}@example.test"]);
        }
    }

    public function testDentroDeTransacaoNaoReconectaENaoGravaPelaMetade(): void
    {
        $u = uniqid();
        $email = "transacao_{$u}@example.test";
        $pdo = $this->db->getConnection();
        $pdo->beginTransaction();
        $this->db->insert('users', [
            'name' => "Transacao {$u}", 'email' => $email,
            'password' => password_hash('x', PASSWORD_BCRYPT), 'role' => 'attendant',
        ]);
        $this->killCurrentConnectionFromOutside($pdo);

        try {
            $this->db->query('UPDATE users SET name = ? WHERE email = ?', ['Depois da queda', $email]);
            $this->fail('dentro da transação a queda precisa virar erro');
        } catch (PDOException $e) {
            $this->assertTrue(Database::isConnectionLost($e), $e->getMessage());
        }

        $this->db->reconnect();
        $n = $this->db->fetch('SELECT COUNT(*) AS n FROM users WHERE email = ?', [$email]);
        $this->assertSame(0, (int) $n['n'], 'a transação perdida não pode deixar registro');
    }

    /** KILL sem usar a conexão em transação (o SELECT CONNECTION_ID() ali é inofensivo). */
    private function killCurrentConnectionFromOutside(PDO $pdo): void
    {
        $id = (int) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
        $this->adminPdo()->exec('KILL ' . $id);
        usleep(200000);
    }

    public function testErroDeSqlNaoReconecta(): void
    {
        $antes = $this->connectionId();
        try {
            $this->db->query('SELECT * FROM tabela_que_nao_existe_reconexao');
            $this->fail('deveria dar erro de tabela');
        } catch (PDOException $e) {
            $this->assertFalse(Database::isConnectionLost($e));
        }
        $this->assertSame($antes, $this->connectionId(), 'erro comum não troca de conexão');
    }
}
