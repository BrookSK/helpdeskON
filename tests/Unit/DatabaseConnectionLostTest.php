<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Database;
use PDOException;

/**
 * Quais erros contam como "conexão perdida" (e permitem reconectar).
 * Regra pura: não abre conexão.
 */
final class DatabaseConnectionLostTest extends TestCase
{
    private function pdoError(string $sqlState, int $code, string $msg): PDOException
    {
        $e = new PDOException("SQLSTATE[{$sqlState}]: General error: {$code} {$msg}");
        $e->errorInfo = [$sqlState, $code, $msg];
        return $e;
    }

    public function testCodigosDeConexaoPerdida(): void
    {
        $this->assertTrue(Database::isConnectionLost($this->pdoError('HY000', 2006, 'MySQL server has gone away')));
        $this->assertTrue(Database::isConnectionLost($this->pdoError('HY000', 2013, 'Lost connection to MySQL server during query')));
        $this->assertTrue(Database::isConnectionLost($this->pdoError('HY000', 4031, 'The client was disconnected by the server because of inactivity.')));
        $this->assertTrue(Database::isConnectionLost($this->pdoError('70100', 1927, 'Connection was killed')));
    }

    public function testMensagemDoErroDeProducaoSemErrorInfo(): void
    {
        // Exatamente o texto do log de produção (13/11/2026).
        $e = new PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
        $this->assertTrue(Database::isConnectionLost($e));
    }

    public function testErrosComunsNaoReconectam(): void
    {
        $this->assertFalse(Database::isConnectionLost($this->pdoError('23000', 1062, "Duplicate entry 'x' for key 'uk_rec_token'")));
        $this->assertFalse(Database::isConnectionLost($this->pdoError('42S02', 1146, "Table 'x.y' doesn't exist")));
        $this->assertFalse(Database::isConnectionLost($this->pdoError('42000', 1064, 'You have an error in your SQL syntax')));
        // Too many connections é falha ao CONECTAR: reconectar só pioraria.
        $this->assertFalse(Database::isConnectionLost($this->pdoError('08004', 1040, 'Too many connections')));
        $this->assertFalse(Database::isConnectionLost(new \RuntimeException('qualquer outra coisa')));
    }
}
