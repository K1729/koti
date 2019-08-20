<?php
/**
 * DB class
 */
class DB {
    private $conn;
    private $database;
    private $output;
    private $pass;
    private $site;
    private $user;

    function __construct($config) {
        $this->user = $config["User"];
        $this->site = $config["Site"];
        $this->pass = $config["Pass"];
        $this->database = $config["Database"];
        $this->output = [];
    }

    function __destruct() {
        $this->conn = NULL;
        $this->user = NULL;
        $this->site = NULL;
        $this->pass = NULL;
        $this->database = NULL;
        $this->output = NULL;
    }
	
	/** getItem
	* getItem gets an item from database
	* Generate the code here and later turn it into a exterrior script
	*/
	public function GetItem($inputs, $lang = NULL): array {
		$this->output = [];
		if (!$this->connect()) return $this->output;
	
		//* Sanitize input
		$items = [];
		// Create assosiative array
		foreach ($inputs as $key => $value) $items[$this->conn->escape_string($key)] = $this->conn->escape_string($value);
		//*/
	
		// If table doesn't exist stop here
		if (!$this->checkTable($items["Table"])) {
			trigger_error("db.getItem: Table, ".$items["Table"]." , not found");
			return $this->output;
		}
	
		// Generate query
		$sql = "SELECT * FROM ".$items["Table"];
		$str = "";
		if (isset($lang)) {
			 $str = " WHERE Language='" . $lang . "'";
		}
		foreach ($items as $column=>$item) {
			if ($column != "Table") {
				if ($str != "") $str .= " AND";
				else $str .= " WHERE";
				$str .= " ".$column."='".$item."'";
			}
		}
		$sql .= $str." LIMIT 10";
	
		$results = $this->conn->query($sql);
		// If query fails stop here
		if ($results === FALSE) {
			trigger_error("db.getItem: ".$sql."; ".$this->conn->error);
			return $this->output;
		}
	
		// Fetch each row in associative form and pass it to output.
		while($row = $results->fetch_assoc()) $this->output[] = $row;
		$results->free();

		$this->conn->close();
		return $this->output;
	}
    
    /** SetItem
     * SetItem inserts data into a table.
     * Those using setItem should have special privileges
     */
	public function SetItem($table, $inputs) : bool{
        if (!$this->connect()) return false;
	
		//* Sanitize inputs
		$items = [];
		// Create assosiative array 
		foreach ($inputs as $key => $value) $items[$this->conn->escape_string($key)] = $this->conn->escape_string($value);
		//*/
	
		// Generate query
		$sql = "INSERT INTO ". $table ."(";
		$columns = [];
		$values = [];
		foreach ($items as $column=>$item) {
			$columns[] = $column;
			$values[] = "'" . $item . "'";
		}
		$sql .= implode(", ", $columns) . ") VALUES (" . implode(", ", $values) . ");";
	
		// If table does exist
		if (!$this->checkTable($table)) {
			trigger_error("db.setItem: Table, " . $table . " , not found");
			// Create the table
			$error = $this->createTable($this->conn, $table, $columns);
			// If creation failed table stop here
			if ($error != "") {
				trigger_error("db.setItem: " . $error);
				return false;
			}
		}
	
		// Query
		if ($this->conn->query($sql) !== TRUE) {
			trigger_error("db.setItem: ".$sql."<br>".$this->conn->error);
			return false;
		}

		$this->conn->close();
		return true;
	}
	
	/** RemoveItem
     * remove selected item
     */
	public function RemoveItem($table, $items) {
		
	}
	
	/** UpdateItem
     * update selected item
     */
	public function UpdateItem($table, $item) {
		$this->RemoveItem($table, $item);
		$this->SetItem($table, $item);
	}

	/**
	 * LogError saves errors to database
	 */
	public function LogError($errLvl, $errMsg, $errFile, $errLine, $errCon) {
		$table = "errors";
		$items = [
			"Level" => $errLvl,
			"Message" => $errMsg,
			"File" => $errFile,
			"Line" => $errLine,
			"Context" => $errCon,
		];
		ob_start();
		var_dump($errCon);
		$dump = ob_get_clean();
		ob_start();
		debug_print_backtrace();
		$trace = ob_get_clean();
		if ($errLvl == E_ERROR || $errLvl == E_USER_ERROR) {
			echo "<b>Fatal Error: </b> [$errLvl] '$errMsg' in $errFile line $errLine with values: <pre>".$dump."</pre><br>";
			die("Backtrace:<br><pre>$trace</pre>");
		} else {
			echo "<b>Error: </b> [$errLvl] '$errMsg' in $errFile line $errLine with values: <pre>".$dump."</pre><br>";
			echo "Backtrace:<br><pre>$trace</pre>";
		}
		if (!$this->SetItem($table, $items)) echo "Saving failed";
	}

	/** connect
	 * connects to database
     * returns true if successful
	 */
	private function connect(): bool {
		$this->conn = new \mysqli($this->site, $this->user, $this->pass, $this->database);
		if (!$this->conn) {
            $this->output["err"][] = mysqli_connect_error();
            return false;
		}
		return true;
	}

	/** checkTable
     * checkTable returns true if table exists
     */
	private function checkTable($items = "") {
		$result = $this->conn->query("SHOW TABLES LIKE '$items'");
		if ($result->num_rows < 1) {
			return false;
		}
		$result->free();
		return true;
	}
    
    /** createTable
     * createTitle creates table with given name and data.
     * First item in array will become primary key
     */
	private function createTable($table, $columns) {
		$sql = "CREATE TABLE ".$table." (";
		$items = [];
		$items[] = "id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY";
		// All this should be given in an array
		foreach($columns as $column) {
			if ($column == "Title" || $column == "Language" || $column == "PW" || $column == "UID") {
				$items[] = "$column VARCHAR(255) NOT NULL";
			} elseif ($column == "Auth" || $column == "Verified") {
				$items[] = "$column TINYINT NOT NULL";
			} elseif ($column == "Date") {
				$items[] = "$column BIGINT NOT NULL";
			} else {
				$items[] = "$column LONGTEXT NOT NULL";
			}
		}
		$sql .= implode(", ", $items);
		$sql .= ")";
		if ($this->conn->query($sql) !== TRUE) {
			return "db.createTable: ". $sql. ": " . $this->conn->error;
		}
		return "";
	}

	private function initReg($users) {
		if ($this->setItem("users", $users)) return false;
		return true;
	}
	
	private function initEditor() {
	   // A quick editor
	   $editor = [
			'Title' => 'Editori',
			'Content' => '<h1>Lisää </h1>
			<form action="/content" method="POST">
				<p><input type="text" name="Title" placeholder="Title for the content" required></p>
				<p><textarea name="Content" placeholder="Content in html form" required></textarea></p>
				<p><input type="text" name="Language" placeholder="Language in xx-XX form" required></p>
				<p><input type="text" name="Category" placeholder="Set the category" required></p>
				<p>Required level of authorization(0min and 3max): <input type="number" name="auth" min="0" max="3" required></p><br>
				<input type="submit">
			</form>
			<h1>Add Language</h1>
			<form action="/footer" method="POST">
				<p><textarea name="Content" placeholder="Text in footer" required></textarea></p><br>
				<p><input type="text" name="Language" placeholder="Language in xx-XX form" required></p><br>
				<input type="submit">
			</form>',
			'Category' => 'content',
			'Language' => 'fi-FI',
			'Auth' => 2,
			'Date' => time(),
		];
		// A quick editor
		$register = [
			'Title' => 'Rekisteröidy',
			'Content' => '<h1>Rekisteröidy</h1>
			<form action="/users" method="POST">
				<p><input type="text" name="uid" placeholder="Username" required></p>
				<p><input type="password" name="pw" placeholder="Password" required></p>
				<p><input type="text" name="name" placeholder="Your name(Not required)"></p>
				<p><input type="email" name="email" placeholder="Email" required></p>
				<input type="submit">
			</form>',
			'Category' => 'user',
			'Language' => 'fi-FI',
			'Auth' => 0,
			'Date' => time(),
		];
	
		// Upload editor UI
		if ($this->setItem("content", $editor) || $this->setItem("content", $register)) return false;
		return true;
	}
	
	private function initLang() {
		$lang = "fi-FI";
		$footer_text = '<p>Tein nämä sivut PHP:llä, '.
						'yrittäen noudattaa REST mallia. '.
						'Nämä sivut ovat minun testi sivut. '.
						'https://student.labranet.jamk.fi/~K1729 toimii minun CV:nä.</p>';
		$footer = [
			'Language' => $lang,
			'Content' => $footer_text,
		];
	
		if ($this->setItem("footer", $footer)) return false;
		return true;
	}
}
?>