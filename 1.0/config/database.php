<?php
class Database {
    private $host = "bzlwnzdfwf8n1tct7ebf-mysql.services.clever-cloud.com";
    private $db_name = "bzlwnzdfwf8n1tct7ebf";
    private $username = "uiewshfkax9viaaw";
    private $password = "ecxBIcUMIBgaN3SX0h6X";
    private $conn = null;

    public function getConnection() {
        try {
            if ($this->conn === null) {
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                    $this->username,
                    $this->password,
                    array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
                );
            }
            return $this->conn;
        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
}
?>