<?php

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        $configFile = BASE_PATH . '/config/database.php';
        if (!file_exists($configFile)) {
            die('Arquivo de configuração do banco não encontrado. Crie config/database.php');
        }
        $config = require $configFile;

        try {
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
        } catch (PDOException $e) {
            die('Erro de conexão com o banco: ' . $e->getMessage());
        }
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
        $stmt = $this->pdo->prepare($sql);
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
