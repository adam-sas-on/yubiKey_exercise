<?php

class DBsession implements SessionHandlerInterface/*, SessionUpdateTimestampHandlerInterface*/ {
	private $dbConnection;
	protected $_row_exists = FALSE;
	private $tableName;
	private $userAgent = "";
	private $_session_id;

	public function __construct(array $params=array(), $server=array() ){
		$hostname = $params['hostname'];
		$username = $params['username'];
		$password = $params['password'];
		$db_name = $params['database'];

		$this->tableName = ( !empty($params['session_table']) )? $params['session_table'] : "yubikey_session";
		$this->dbConnection = new mysqli($hostname,$username,$password, $db_name);

		if( !empty($server['HTTP_USER_AGENT']) )
			$this->userAgent = $server['HTTP_USER_AGENT'];
	}

	public function open($savePath, $sessionName){
		if ($this->dbConnection) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	/**
	 *
	 * @param $sessionId  {string};
	 * @return  string of saved data;
	 */
	public function read($sessionId){
		try {
			$sql = "SELECT data, user_agent FROM ".$this->tableName." WHERE session_id = ?";
			$stmt = $this->dbConnection->prepare($sql);
			$stmt->bind_param("s", $sessionId);

			$sessionData = array();

			$stmt->execute();
			$results = $stmt->get_result();
			$row = $results->fetch_assoc();
			$stmt->close();

			if($row){
				$this->_row_exists = TRUE;
				$sessionData = $row['data'];
				return $sessionData;
			} else {
				$this->_row_exists = FALSE;
				return '';
			}

		} catch (Exception $e) {
			$this->_row_exists = FALSE;
			return '';
		}
	}

	/**
	 *
	 * @param $sessionId  {string};
	 * @param $sessionData  {string};
	 * @return  {bool}: TRUE if success;
	 */
	public function write($sessionId, $sessionData){
		try {
			if($this->_session_id !== $sessionId){
				$this->_row_exists = FALSE;
				$this->_session_id = $sessionId;

				$sql = "SELECT 1 FROM ".$this->tableName." WHERE `session_id` = ?";
				$stmt = $this->dbConnection->prepare($sql);
				$stmt->bind_param("s", $sessionId);

				$stmt->execute();
				$stmt->bind_result($exists);
				$exists = $stmt->fetch();
				$stmt->close();

				$this->_row_exists = ($exists)? TRUE : FALSE;
			}

			//$sql = "REPLACE INTO ".$this->tableName."(`session_id`, `lifetime`, `data`) VALUES(?, ?, ?)";
			if($this->_row_exists === FALSE){
				$sql = "INSERT INTO ".$this->tableName." (`session_id`, `lifetime`, `data`, `user_agent`) VALUES (?, ?, ?, ?)";
			} else {
				$sql = "UPDATE ".$this->tableName." SET `lifetime` = ?, `data` = ? WHERE `session_id` = ?";
			}
			$stmt = $this->dbConnection->prepare($sql);
			$time = time();
			$browser = $this->userAgent;

			if($this->_row_exists === FALSE){
				$stmt->bind_param("sibs", $sessionId, $time, $sessionData, $browser);
			} else {
				$stmt->bind_param("iss", $time, $sessionData, $sessionId);
			}

			$stmt->execute();
			$stmt->close();
			$this->_row_exists = TRUE;

			return TRUE;
		} catch (Exception $e) {
			return FALSE;
		}
	}

	public function destroy($sessionId){
		try {
			$sql = "DELETE FROM ".$this->tableName." WHERE session_id = ?";
			$stmt = $this->dbConnection->prepare($sql);
			$stmt->bind_param("s", $sessionId);
			$stmt->execute();
			$stmt->close();

			return TRUE;
		} catch (Exception $e) {
			return FALSE;
		}
	}

	public function gc($maxlifetime){
		$past = time() - $maxlifetime;

		try {
			$sql = "DELETE FROM ".$this->tableName." WHERE `lifetime` < ?";
			$stmt = $this->dbConnection->prepare($sql);
			$stmt->bind_param("i", $past);
			$stmt->execute();
			$stmt->close();

			return TRUE;
		} catch (Exception $e) {
			return FALSE;
		}
	}

	public function close(){
		return TRUE;
	}
}

