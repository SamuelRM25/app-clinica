<?php
// config/database.php
date_default_timezone_set('America/Guatemala');

// Buscar .env en múltiples ubicaciones (local, Hostinger, etc.)
$envPaths = [
    __DIR__ . '/../.env',          // Local XAMPP (project root)
    __DIR__ . '/../../.env',       // Hostinger: public_html/
    __DIR__ . '/../../../.env',    // Hostinger: fuera de public_html/
];
$envFile = null;
foreach ($envPaths as $path) {
    if (file_exists($path)) {
        $envFile = $path;
        break;
    }
}

if ($envFile) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        putenv(trim($line));
    }
}

// También leer variables de entorno reales (Hostinger hPanel)
// getenv() ya las buscará automáticamente

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port = "3306";
    private $conn = null;

    public function __construct() {
        $this->host = getenv('DB_HOST') ?: "localhost";
        $this->db_name = getenv('DB_NAME') ?: "clinica_db";
        $this->username = getenv('DB_USER') ?: "root";
        $this->password = getenv('DB_PASS') ?: "";
        $this->port = getenv('DB_PORT') ?: "3306";
    }

    public function getConnection() {
        try {
            if ($this->conn === null) {
                $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4";

                $this->conn = new PDO(
                    $dsn,
                    $this->username,
                    $this->password,
                    array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    )
                );
                // Pin MySQL session timezone to Guatemala (UTC-6) so any TIMESTAMP
                // columns are interpreted correctly regardless of the host OS TZ.
                $this->conn->exec("SET time_zone = '-06:00'");
            }
            return $this->conn;
        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            throw new Exception("Error de conexión a la base de datos.");
        }
    }
}
?>