<?php
// config/database.php

class Database {
    private $host = "bzlwnzdfwf8n1tct7ebf-mysql.services.clever-cloud.com";
    private $db_name = "bzlwnzdfwf8n1tct7ebf";
    private $username = "uiewshfkax9viaaw"; // Tus credenciales reales
    private $password = "ecxBIcUMIBgaN3SX0h6X"; // Tus credenciales reales
    private $port = "3306";
    private $conn = null; // Inicializar a null

    public function getConnection() {
        try {
            if ($this->conn === null) {
                $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8"; // Añadir charset para UTF-8
                
                $this->conn = new PDO(
                    $dsn,
                    $this->username,
                    $this->password,
                    array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC // Para que fetchAll devuelva arrays asociativos por defecto
                    )
                );
            }
            return $this->conn;
        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            // En un entorno de producción, es mejor lanzar una excepción genérica o mostrar un mensaje amigable.
            throw new Exception("Database connection failed: " . $e->getMessage()); // Mostrar el mensaje para depuración
        }
    }
}
?>