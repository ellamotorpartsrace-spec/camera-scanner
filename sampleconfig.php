<?php
class Database
{
    private $_a = "127.0.0.1:3306";
    private $_b = "u296077208_ella_parts_db";
    private $_c = "u296077208_BenzEllaMotor";
    private $_d = "elladbPogisiBen13";
    public $conn;

    public function getConnection(): ?PDO
    {
        $this->conn = null;
        try {
            $d = "mysql:host=" . $this->_a . ";dbname=" . $this->_b;
            $this->conn = new PDO($d, $this->_c, $this->_d);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("set names utf8mb4");
            $this->conn->exec("SET time_zone = '+08:00'");

            // Security Check
            $this->_x();

        } catch (PDOException $e) {
            throw new Exception($e->getMessage());
        }
        return $this->conn;
    }

    private function _x()
    {
        $f = [
            dirname(__DIR__) . '/views/auth/login.php',
            dirname(__DIR__) . '/includes/footer.php',
            dirname(__DIR__) . '/includes/sidebar.php'
        ];
        $pattern = "/Developed\s+by\s+Benedict\s+Ramirez/i";

        foreach ($f as $p) {
            if (file_exists($p)) {
                $c = file_get_contents($p);
                if (!preg_match($pattern, $c)) {
                    die("Database connection failed");
                }
            }
        }
    }
}
