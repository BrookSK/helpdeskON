<?php

class Database
{
    private static $instance = null;
    private $pdo;

    /**
     * Códigos do cliente/servidor MySQL/MariaDB que indicam conexão perdida:
     * 2006 server has gone away, 2013 lost connection, 4031 desconectado por
     * inatividade (MySQL 8), 1927 connection was killed (MariaDB).
     */
    private const CONNECTION_LOST_CODES = [2006, 2013, 4031, 1927];

    private function __construct()
    {
        try {
            $this->connect();
        } catch (PDOException $e) {
            die('Erro de conexão com o banco: ' . $e->getMessage());
        }
    }

    /** Abre (ou reabre) a conexão PDO. */
    private function connect(): void
    {
        $configFile = BASE_PATH . '/config/database.php';
        if (!file_exists($configFile)) {
            die('Arquivo de configuração do banco não encontrado. Crie config/database.php');
        }
        $config = require $configFile;

        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        // Alinha o fuso da SESSÃO MySQL ao horário do Brasil (UTC-03:00), o
        // mesmo usado pelo PHP (date_default_timezone_set). Assim, colunas
        // preenchidas pelo banco (ex.: CURRENT_TIMESTAMP em sequence_executions)
        // ficam no mesmo fuso das datas geradas pelo PHP, evitando horários
        // deslocados/"voltando no tempo" no acompanhamento. Brasil não usa mais
        // horário de verão desde 2019, então o offset fixo é estável.
        try {
            $this->pdo->exec("SET time_zone = '-03:00'");
        } catch (\Throwable $e) { /* se o servidor recusar, mantém o padrão */ }
    }

    /**
     * Fecha a conexão atual e abre outra. Quem guardou o objeto Database
     * (models) passa a usar a nova; quem guardou o PDO de getConnection() não.
     */
    public function reconnect(): void
    {
        $this->pdo = null;
        $this->connect();
    }

    /** A exceção indica que o servidor derrubou a conexão? */
    public static function isConnectionLost(\Throwable $e): bool
    {
        $code = null;
        if ($e instanceof PDOException && isset($e->errorInfo[1]) && is_numeric($e->errorInfo[1])) {
            $code = (int) $e->errorInfo[1];
        } elseif (preg_match('/General error:\s*(\d+)/', $e->getMessage(), $m)) {
            $code = (int) $m[1];
        }
        if ($code !== null && in_array($code, self::CONNECTION_LOST_CODES, true)) return true;

        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'server has gone away') || str_contains($msg, 'lost connection to');
    }

    /**
     * Prepara o comando. Se a conexão caiu enquanto o processo estava parado
     * (ex.: cron montando um vídeo grande por vários minutos, além do
     * wait_timeout do servidor), reconecta e tenta de novo uma única vez.
     *
     * É seguro repetir porque a falha acontece no prepare, antes de qualquer
     * execução. Dentro de uma transação não reconecta: a transação já se
     * perdeu no servidor e continuar numa conexão nova gravaria pela metade.
     */
    private function prepare(string $sql): PDOStatement
    {
        try {
            return @$this->pdo->prepare($sql);
        } catch (PDOException $e) {
            if (!self::isConnectionLost($e) || $this->inTransactionSafe()) throw $e;
            if (class_exists('Logger', false)) {
                Logger::warning('Database: conexão perdida, reconectando', ['error' => $e->getMessage()]);
            }
            $this->reconnect();
            return $this->pdo->prepare($sql);
        }
    }

    private function inTransactionSafe(): bool
    {
        try { return $this->pdo->inTransaction(); } catch (\Throwable $e) { return true; }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->pdo;
    }

    public function query($sql, $params = [])
    {
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch($sql, $params = [])
    {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll($sql, $params = [])
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert($table, $data)
    {
        $keys = array_keys($data);
        // Protege os nomes de coluna com crases: evita erro de sintaxe quando
        // a coluna usa uma palavra reservada do MySQL/MariaDB (ex.: `limit`).
        $fields = implode(', ', array_map([$this, 'quoteIdentifier'], $keys));
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
        $this->query($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = [])
    {
        $set = implode(', ', array_map(fn($k) => $this->quoteIdentifier($k) . ' = ?', array_keys($data)));
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
        $params = array_merge(array_values($data), $whereParams);
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Protege um identificador (nome de coluna) com crases, escapando crases
     * internas. Só aceita o nome de uma coluna simples (sem ponto/função); se
     * vier algo fora do padrão de identificador, devolve como está para não
     * mascarar um uso intencional.
     */
    private function quoteIdentifier(string $name): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            return $name;
        }
        return '`' . str_replace('`', '``', $name) . '`';
    }

    public function delete($table, $where, $params = [])
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params)->rowCount();
    }
}
