<?php
namespace sksso;

use PDO;

class Configs {
	private static $env;
	private static $syncTable;
	private static $tableInfo;
	private $pdo;
    private const SESSION_NAMESPACE = 'sksso_sdk_sess';

    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION[self::SESSION_NAMESPACE])) {
            $_SESSION[self::SESSION_NAMESPACE] = [];
        }
    }

    public function setDBConn($host, $user, $pass, $db) {
		$options = [
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES   => false,
		];
		
        $dsn = "pgsql:host=$host;port=5432;dbname=$db;user=$user;password=$pass";
        // print_r($dsn);die();
		try {
			$this->pdo = new PDO($dsn, null, null, $options);
			return $this->pdo;
		} catch (\PDOException $e) {
			throw new \PDOException($e->getMessage(), (int)$e->getCode());
		}
    }

    public function setSyncTable($data) {
		$paramSSO = [
	        "uid_sso" => "VARCHAR(50)",
	        "sesi_sso" => "VARCHAR(255)",
	        "sesi_app_sso" => "VARCHAR(255)",
	        "tiket_sso" => "VARCHAR(255)",
	        "token_sso" => "TEXT",
	        "token_created_sso" => "TIMESTAMP",
	        "token_expired_sso" => "TIMESTAMP",
	    ];

	    $newField = [];
	    $stmt = $this->pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = :table");
	    $stmt->bindParam(':table', $data["nama_table"], PDO::PARAM_STR);
	    $stmt->execute();
	    $table_fields = $stmt->fetchAll(PDO::FETCH_COLUMN);
	    // print_r($table_fields);die();

	    foreach ($paramSSO as $key => $value) {
	        if (!in_array($key, $data["field_table"])) {
	            if (!in_array($key, $table_fields)) {
	                $newField[] = "ADD $key $value";
	            }
	            $data["field_table"][$key] = $key;
	        }
	    }
	    // print_r($data);die();
		if (!empty($newField)) {
		    $sql = "ALTER TABLE ".$data["nama_table"]." ".implode(", ", $newField);
	        $stmt = $this->pdo->prepare($sql);
	        $stmt->execute();
	    }

	    $_SESSION[self::SESSION_NAMESPACE]['sync_table_data'] = $data;
		self::$syncTable = $data;

		// $sql = "SELECT column_name, data_type, column_default, is_nullable, character_maximum_length, column_key FROM information_schema.columns WHERE table_name = :table";
		// $sql = "SELECT information_schema.columns.column_name, data_type, column_default, is_nullable, character_maximum_length
        // FROM information_schema.columns
        // LEFT JOIN information_schema.key_column_usage 
        // ON information_schema.columns.column_name = information_schema.key_column_usage.column_name
        // WHERE information_schema.columns.table_name = :table";
        $sql = "SELECT 
			a.table_name, 
			a.column_name, 
			a.udt_name data_type,
			a.character_maximum_length length,
			a.is_nullable,
			a.column_default,
			c.constraint_type,
			CASE 
				WHEN 
					LEFT(column_default,7) ='nextval' 
				THEN
					'auto_increment'
				ELSE
					NULL
			END extra
		FROM 
		information_schema.columns a
		LEFT JOIN
			information_schema.key_column_usage b ON a.table_name = b.table_name AND a.column_name = b.column_name
		LEFT JOIN
			information_schema.table_constraints c ON a.table_name = c.table_name AND b.constraint_name = c.constraint_name
		WHERE 
		a.table_name = :table;";
		$stmt = $this->pdo->prepare($sql);
    	$stmt->bindParam(':table', $data["nama_table"], PDO::PARAM_STR);
		$stmt->execute();
		$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$tbInfo = [];
		foreach ($columns as $row) {
			$fieldName = $row['column_name'];
			unset($row['column_name']);
			$tbInfo[$fieldName] = $row;
		}
		// print_r($tbInfo);die();
		$_SESSION[self::SESSION_NAMESPACE]['table_info_data'] = $tbInfo;
		self::$tableInfo = $tbInfo;

	    return;
    }

	public static function getSyncTable() 
	{
        if (!self::$syncTable && isset($_SESSION[self::SESSION_NAMESPACE]['sync_table_data'])) {
            self::$syncTable = $_SESSION[self::SESSION_NAMESPACE]['sync_table_data']; // Ambil dari sesi dengan namespace
        }
		return self::$syncTable;
	}

	public static function getTableInfo($key) 
	{
        if (!self::$tableInfo && isset($_SESSION[self::SESSION_NAMESPACE]['table_info_data'])) {
            self::$tableInfo = $_SESSION[self::SESSION_NAMESPACE]['table_info_data']; // Ambil dari sesi dengan namespace
        }
		return self::$tableInfo[$key];
	}	

    public function setEnv($aliasID, $secretKeyBody, $secretKeyUrl) {
		// https://sso.banyuwangikab.go.id/
		$data = [
			'SSO_HOST' => 'https://sso.banyuwangikab.go.id/',
			'REDIRECT_LOGIN_PAGE' => 'https://sso.banyuwangikab.go.id/user/login/0?as='.$aliasID,
			'SECRET_KEY_BODY' => $secretKeyBody,
			'SECRET_KEY_URL' => $secretKeyUrl,
			'ALIAS_ID' => $aliasID
		];
        $_SESSION[self::SESSION_NAMESPACE]['env_data'] = $data; // Menyimpan data ke sesi dengan namespace
		self::$env = $data;
		return $data;
    }

	public static function getEnv() 
	{
        if (!self::$env && isset($_SESSION[self::SESSION_NAMESPACE]['env_data'])) {
            self::$env = $_SESSION[self::SESSION_NAMESPACE]['env_data']; // Ambil dari sesi dengan namespace
        }
		return self::$env;
	}	
}
