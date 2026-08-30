<?php

// namespace Ceaser\Core;

// use mysqli;

class Database
{
    private $host;
    private $username;
    private $password;
    private $database;
    public $connection;

    public function __construct($database = null)
    {
        $this->host     = $_ENV['DB_HOST']     ?? 'localhost';
        $this->username = $_ENV['DB_USER']     ?? 'root';
        $this->password = $_ENV['DB_PASSWORD'] ?? '';
        $this->database = $database;
        $this->connect();
    }

    public function setDatabase($database)
    {
        if ($this->connection) {
            $this->connection->close();
        }
        $this->database = $database;
        $this->connect();
    }

    private function connect()
    {
        $this->connection = new mysqli($this->host, $this->username, $this->password, $this->database);
        if ($this->connection->connect_error) {
            throw new RuntimeException("Connection failed: " . $this->connection->connect_error);
        }
    }


    public function query($query)
    {
        $result = $this->connection->query($query);
        if (!$result) {
            throw new RuntimeException("Query error: " . $this->connection->error);
        }
        return $result;
    }

    public function prepare($string)
    {
        $stmt = $this->connection->prepare($string);
        if (!$stmt) {
            throw new RuntimeException("Prepare failed: " . $this->connection->error);
        }
        return $stmt;
    }

    public function escapeString($string)
    {
        return $this->connection->real_escape_string($string);
    }

    public function close()
    {
        $this->connection->close();
    }
}
