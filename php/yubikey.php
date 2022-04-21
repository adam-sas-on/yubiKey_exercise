<?php
include dirname(__FILE__)."/u2f.php";

class YubiKey {
	private $dbConnection;
	private $dbParams;
	private $userTable;
	private $keyTable;
	private $isDB_opened;
	private $headerProtocol;
	private $ssId;

	private $isSSL;
	private $host;
	private $isAjax;
	private $userId;
	private $U2F;

	private $isSuperuser;
	private $displayContent;
	private $userData;
	private $taskName;

	public function __construct($server, array $params){
		$this->dbParams = array('hostname' => $params['hostname'],
		                  'username' => $params['username'],
		                  'password' => $params['password'],
		                  'database' => $params['database']);

		$this->userTable = ( !empty($params['user_table']) )? $params['user_table'] : "yubikey_user";
		$this->keyTable = ( !empty($params['key_table']) )? $params['key_table'] : "hardware_key";

		$this->isAjax = ( !empty($server['HTTP_X_REQUESTED_WITH']) && strtolower($server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')?TRUE:FALSE;

		$this->isSSL = FALSE;
		if ( !empty($server['HTTPS']) && strtolower($server['HTTPS']) !== 'off'){
			$this->isSSL = TRUE;
		} elseif (isset($server['HTTP_X_FORWARDED_PROTO']) && strtolower($server['HTTP_X_FORWARDED_PROTO']) === 'https'){
			$this->isSSL = TRUE;
		} elseif ( !empty($server['HTTP_FRONT_END_HTTPS']) && strtolower($server['HTTP_FRONT_END_HTTPS']) !== 'off')
			$this->isSSL = TRUE;

		$this->host = $server['HTTP_HOST'];
		$this->ssId = session_id();

		$this->setHeaderProtocol($server);


		$this->userId = (isset($_SESSION['user_id']) )? $_SESSION['user_id'] : -1;
		$this->isSuperuser = NULL;
		$this->taskName = NULL;

		if($this->isAjax && $this->userId != -1){
			$this->U2F = new U2F_example($this->userId, $this->dbParams, $this->host, $this->isSSL);

			$this->setAjaxTaskNameByPost();
			$this->isDB_opened = FALSE;
		} else {
			$this->U2F = NULL;

			if( isset($_POST['signin']) || (!empty($server['PATH_INFO']) && $server['PATH_INFO']==='/signin/') )
				$this->taskName = "signin";
			else if( isset($_POST['register']) )
				$this->taskName = "register";
			else if( isset($_POST['logout']) )
				$this->taskName = "logout";


			$this->displayContent = array('logged_in' => isset($_SESSION['logged_in']), 'session_id' => "", 'message' => array(), 'keep_form_values' => FALSE);
			$this->userData = array('id' => $this->userId, 'login' => "", 'password' => "", 'hash' => '', 'has_keys' => FALSE);

			if($this->taskName === "logout"){
				unset($_SESSION['logged_in']);
				unset($_SESSION['user_id']);
				$this->displayContent['logged_in'] = FALSE;
				$this->userId = -1;
			} else {
				$this->dbConnection = new mysqli($this->dbParams['hostname'], $this->dbParams['username'], $this->dbParams['password'], $this->dbParams['database']);
				if($this->dbConnection->connect_error){
					$this->displayContent['message'][] = "Problem with opening database! (" . $this->dbConnection->connect_error . ")";// mysqli_connect_errno()
					$this->isDB_opened = FALSE;
				} else {
					$this->isDB_opened = TRUE;
					$this->dbConnection->set_charset("utf-8");
				}
			}

		}
	}

	private function setHeaderProtocol($server){
		$phpSapiName = substr(php_sapi_name(), 0, 3);
		if($phpSapiName == 'cgi' || $phpSapiName == 'fpm'){
			$this->headerProtocol = "Status:";// header('Status: 403 Forbidden');
		} else {
			$this->headerProtocol = isset($server['SERVER_PROTOCOL']) ? $server['SERVER_PROTOCOL'] : 'HTTP/1.0';// header($protocol.' 403 Forbidden');
		}
	}

	private function setAjaxTaskNameByPost(){
		$this->taskName = NULL;

		if( isset($_POST['key_check']) ){
			$this->taskName = "key_check";
		} else if( isset($_POST['finish_checking']) ){
			$this->taskName = "finish_checking";
		} else if( isset($_POST['register_check']) ){
			$this->taskName = "register_check";
		} else if( isset($_POST['register_finish']) ){
			$this->taskName = "register_finish";
		}
	}

	public function getContent(){
		return $this->displayContent;
	}

	public function getUserData(){
		return $this->userData;
	}

	// public function isRegistrationRequested(){
	//	return $this->taskName === "register";
	// }

	public function getCurrentUserAndKeys(){
		if($this->isDB_opened === FALSE || $this->userId < 1)
			throw new Exception("User is not set or database connection error occured!");

		$sql_query = "SELECT * FROM " . $this->userTable . " WHERE id = ?";
		// if( !empty($this->keyTable) ){
		//	$sql_query = "SELECT * FROM " . $this->keyTable . " JOIN " . $this->userTable . " ON " . $this->userTable . ".id = user_id WHERE user_id = ?";
		// }

		$sql_statement = $this->dbConnection->prepare($sql_query);

		if($sql_statement === FALSE){
			$this->displayContent['message'][] = "Something's wrong with statement: " . $this->dbConnection->error . " (" . $this->dbConnection->errno . ")";
			return FALSE;
		}

		$sql_statement->bind_param("i", $this->userId);
		$sql_statement->execute();

		$results = $sql_statement->get_result();

		$user = $results->fetch_assoc();
		$sql_statement->close();

		$this->userData['login'] = $user['username'];
		$this->userData['has_keys'] = FALSE;
		$this->isSuperuser = ( $user['is_superuser'] ) ? TRUE : FALSE;
		$user['keys'] = array();

		if( empty($this->keyTable) ){
			return $user;
		}


		$sql_query = "SELECT * FROM " . $this->keyTable . " WHERE user_id = ?";
		$sql_statement = $this->dbConnection->prepare($sql_query);

		if($sql_statement === FALSE){
			$this->displayContent['message'][] = "Problem with getting keys occured: " . $this->dbConnection->error . " (" . $this->dbConnection->errno . ")";
			return $user;
		}

		$sql_statement->bind_param("i", $this->userId);
		$sql_statement->execute();

		$results = $sql_statement->get_result();
		$keys = array();

		while($row = $results->fetch_assoc() ){
			$keys[] = $row;
		}

		$sql_statement->close();

		if(count($keys) > 0)
			$this->userData['has_keys'] = TRUE;

		$user['keys'] = $keys;
		return $user;
	}

	public function getOtherUsers(){
		if($this->isDB_opened === FALSE || $this->userId < 1)
			throw new Exception("User is not set or database connection error occured!");

		if(!$this->isSuperuser)
			return FALSE;

		$sql_query = "SELECT * FROM yubikey_user WHERE NOT id = ?";
		$sql_statement = $this->dbConnection->prepare($sql_query);

		if($sql_statement === FALSE){
			$message = "Something's wrong with statement for all other users: ";
			$this->displayContent['message'][] = $message . $this->dbConnection->error . " (" . $this->dbConnection->errno . ")";
			return FALSE;
		}


		$sql_statement->bind_param("i", $this->userId);
		$sql_statement->execute();
		$results = $sql_statement->get_result();

		$users = array();

		while($row = $results->fetch_assoc() )
			$users[] = $row;

		$sql_statement->close();

		return $users;
	}

	/**
	 *	Checks if page is requested for logging in or registration.
	 * If it is then appropriate action is taken.
	 *
	 * @return  {bool}
	 */
	public function signInOrRegisterUser($post){
		if( !isset($post['login']) || empty($post['login']) || !isset($post['pswd']) || empty($post['pswd']) )
			return FALSE;

		if($this->taskName !== "signin" && $this->taskName !== "register")
			return FALSE;

		if($this->taskName === "signin"){
			$success = $this->getUserByLoginAndPassword($post['login'], $post['pswd']);
		} else {
			$success = $this->registerUser($post['login'], $post['pswd']);
		}

		return $success;
	}

	/**
	 *	Checks if user with defined login and password exists;
	 * it also sets userData and displayContent properties of this class;
	 *
	 * @param  $login  {string}: login or e-mail of registered user;
	 * @param  $password  {string}: his password as plain text (e.g. string from <input type="password" />);
	 * @return  {bool}: TRUE for success, FALSE otherwise;
	 */
	private function getUserByLoginAndPassword($login, $password){
		if($this->isDB_opened === FALSE || $this->userId > 0)
			throw new Exception("User was already received or database connection error occured!");

		$this->userData['login'] = $this->dbConnection->real_escape_string($login);
		$this->userData['password'] = $password;
		$this->userData['hash'] = password_hash($password, PASSWORD_BCRYPT);


		$sql_query = "SELECT *";
		if( !empty($this->keyTable) ){
			$sql_query = $sql_query . ", (SELECT COUNT(*) FROM " . $this->keyTable . " WHERE " . $this->keyTable . ".user_id=" . $this->userTable . ".id) AS count_keys";
		} else
			$sql_query = $sql_query . ", 0 AS count_keys";
		$sql_query = $sql_query . " FROM " . $this->userTable . " WHERE (username = ? OR email = ?) AND is_active = 1";

		$sql_statement = $this->dbConnection->prepare($sql_query);
		$exists = FALSE;
		$user = NULL;
		if($sql_statement === FALSE){
			$this->displayContent['message'][] = "Something's wrong: " . $this->dbConnection->error . " (" . $this->dbConnection->errno . ")";
			return FALSE;
		}

		$sql_statement->bind_param("ss", $this->userData['login'], $this->userData['login']);

		$sql_statement->execute();
		$results = $sql_statement->get_result();
		if($results !== FALSE){
			$user = $results->fetch_assoc();
			$exists = TRUE;
		}
		$sql_statement->close();

		if($exists === FALSE || $user === NULL){
			$this->displayContent['message'][] = "Not such user in a system! Click register if you want to add a new one.";
			$this->displayContent['keep_form_values'] = TRUE;
			return FALSE;
		}

		$exists = password_verify($this->userData['password'], $user['password']);
		if(!$exists){
			$this->displayContent['message'][] = "Password mismatch!";
			return FALSE;
		}

		$_SESSION['user_id'] = $user['id'];
		$this->userData['id'] = $user['id'];
		if($user['count_keys'] > 0){
			// user has key/-s  ->  use them for verification;
			$this->userData['has_keys'] = TRUE;
			$this->displayContent['keep_form_values'] = TRUE;
			return TRUE;
		}

		$this->displayContent['message'][] = "Welcome " . $this->userData['login'];
		$_SESSION['logged_in'] = $this->displayContent['logged_in'] = TRUE;
		$this->updateLastLogin($user['id']);
		return TRUE;
	}

	// public function getKeys(){
	//	if($this->isDB_opened === FALSE || $this->userId < 0)
	//		return FALSE;

	// }

	/**
	 *	Updates last_login field for $userId in a database;
	 * it uses an id from its argument when user is logged at once or
	 * $this->userId when user is logged in after hardware key steps;
	 *
	 * @param $user_id: an id of user to whom last_login time has to be updated;
	 */
	private function updateLastLogin($user_id = NULL){
		$user_id_copy = ($user_id != NULL)? $user_id : $this->userId;

		if(!$this->isDB_opened){
			$this->dbConnection = new mysqli($this->dbParams['hostname'], $this->dbParams['username'], $this->dbParams['password'], $this->dbParams['database']);

			if($this->dbConnection->connect_error){
				$this->isDB_opened = FALSE;
			} else {
				$this->isDB_opened = TRUE;
				$this->dbConnection->set_charset("utf-8");
			}
		}

		$sql_query = "UPDATE " . $this->userTable . " SET last_login = now() WHERE id = ?";

		$sql_statement = $this->dbConnection->prepare($sql_query);
		if($sql_statement === FALSE)
			return;// don't bother with update;

		$sql_statement->bind_param("i", $user_id_copy);

		$sql_statement->execute();
		$sql_statement->close();
	}


	/**
	 *	Reacts for the request of AJAX according to taskName.
	 *
	 * @return array: associative array with key-value properties  or  NULL if request is not an AJAX;
	 */
	public function ajaxResponse(){
		if( !$this->isAjax )
			return NULL;

		if($this->taskName === "register_check")
			return $this->U2F->getRegisters();

		$responseAjax = array();
		if($this->taskName === "key_check")
			$responseAjax = $this->u2f_keyCheck();
		else if($this->taskName === "finish_checking")
			$responseAjax = $this->u2f_keyCheckFinish();
		else if($this->taskName === "register_finish")
			$responseAjax = $this->u2f_finishRegister();

		return $responseAjax;
	}

	/**
	 *	Receives u2f key data from front-end and adds into database table of keys;
	 *
	 * @return array
	 */
	private function u2f_finishRegister(){
		if(!isset($_POST['keyResponse']) || empty($_POST['keyResponse']) )
			return array('error' => "Key data is missing!");

		// expected: {keyData: data, appId: YKeyObj.appId, challenge: YKeyObj.challengeId, version: YKeyObj.ver};
		$keyResponse = json_decode($_POST['keyResponse']);

		$keyData = $keyResponse->keyData;
		$request = array('challenge' => '', 'appId' => '');
		$request['challenge'] = $keyResponse->challenge;
		$request['appId'] = $keyResponse->appId;
		$success = FALSE;

		try {
			$success = $this->U2F->registerKey((object)$request, $keyData);
		} catch(Exception $e){
			$responseAjax = array('exception' => $e->getMessage() );
		}

		$responseAjax['message'] = ($success) ? "Registration key accomplished" : "Registration key failed!";
		return $responseAjax;
	}

	/**
	 *	Gets keys from database and sends them to front-end to check if
	 * one which is tested is correct.
	 *
	 * @return array
	 */
	private function u2f_keyCheck(){
		if( !isset($_POST['ssid']) || empty($_POST['ssid']) || !isset($_POST['user_id']) ){
			return array('error' => "Expected properties are missing!");
		}

		$responseAjax = array();

		if($_POST['ssid'] !== $this->ssId || !isset($_SESSION['user_id']) || (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $_POST['user_id']) ){
			$responseAjax['header'] = $this->headerProtocol . " 403 Forbidden";// header($responseAjax['header']);

			unset($_SESSION['logged_in']);
			unset($_SESSION['user_id']);

			$responseAjax['error'] = "Forbidden request!";
			return $responseAjax;
		}

		$responseAjax = $this->U2F->getRegisters($this->userTable);
		if(count($responseAjax['signs']) < 1){
			$responseAjax = array('error' => "Missing keys!", 'header' => $this->headerProtocol . " 404 No keys found.");

			unset($_SESSION['logged_in']);
			unset($_SESSION['user_id']);

			return $responseAjax;
		}

		$sign = $responseAjax['signs'][0];
		$responseAjax['challenge'] = $sign['challenge'];
		return $responseAjax;
	}

	/**
	 *	Checks if tested key which was sent as a response is correct.
	 *
	 * @return array: ajax response with message about success or failure and header of response to send back;
	 */
	private function u2f_keyCheckFinish(){
		if( !isset($_POST['ssid']) || empty($_POST['ssid']) || !isset($_POST['user_id']) )
			return array('error' => "Expected properties are missing!");

		if($_POST['ssid'] !== $this->ssId ||
		   !isset($_SESSION['user_id']) ||
		   (isset($_SESSION['user_id']) && $_SESSION['user_id'] != $_POST['user_id']) ||
		   !isset($_POST['keyResponse']) ||
		   empty($_POST['keyResponse'])
		){
			$responseAjax = array('error' => "Forbidden request!", 'header' => $this->headerProtocol . " 403 Forbidden");

			unset($_SESSION['logged_in']);
			unset($_SESSION['user_id']);

			return $responseAjax;
		}

		$success = FALSE;
		$keyResponse = json_decode($_POST['keyResponse']);

		$keyData = $keyResponse->keyData;
		$sentChallenge  = $keyResponse->challenge;
		$requestedAppId = $keyResponse->appId;

		try {
			$success = $this->U2F->authenticate($keyData, $sentChallenge, $requestedAppId);
		} catch(Exception $e){
			// todo: what if counter restarts
			$responseAjax = array('exception' => $e->getMessage() );
		}
			
		if($success){
			$responseAjax = array('success' => TRUE, 'message' => "Congratulation! Key confirmed your identity");
			$_SESSION['logged_in'] = TRUE;

			// $this->updateLastLogin();
			// header('Location: ') to reload page; $consts['ssl'] ? "https://" : "http://" . $_SERVER['HTTP_HOST']
		} else {
			unset($_SESSION['logged_in']);
			unset($_SESSION['user_id']);
		}

		return $responseAjax;
	}
	// - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -

	private function registerUser($login, $password){
		if($this->isDB_opened === FALSE)
			throw new Exception("Database connection error occured!");

		if($this->taskName !== "register")
			throw new Exception("Registration a new user was not requested!");

		if(strlen($password) < 6 || strlen($login) < 1){
			if(strlen($login) < 1)
				$this->displayContent['message'][] = "Login of user has to be defined!";
			if(strlen($password) < 6)
				$this->displayContent['message'][] = "Password has to have minimum 6 characters!";
			return FALSE;
		}


		$this->userData['login'] = $this->dbConnection->real_escape_string($login);
		$this->userData['password'] = $password;

		$sql_query = "SELECT 1 FROM " . $this->userTable . " WHERE username = ? OR email = ?";
		$sql_statement = $this->dbConnection->prepare($sql_query);
		if($sql_statement === FALSE){
			$this->displayContent['message'][] = "Something's wrong: " . $this->dbConnection->error . " (" . $this->dbConnection->errno . ")";
			return FALSE;
		}

		$sql_statement->bind_param("ss", $this->userData['login'], $this->userData['login']);

		$sql_statement->execute();
		$sql_statement->bind_result($exists);
		$exists = $sql_statement->fetch();
		$sql_statement->close();
		// $exists = ($exists)? TRUE : FALSE;
		if($exists){
			$this->displayContent['message'][] = "User already exists in a system!";
			return FALSE;
		}

		$this->userData['hash'] = password_hash($password, PASSWORD_BCRYPT);

		$sql_query = "INSERT INTO " . $this->userTable . " (username, password, last_login) VALUES (?, ?, now())";
		$sql_statement = $this->dbConnection->prepare($sql_query);
		if($sql_statement === FALSE){
			$this->displayContent['message'][] = "Something's wrong with statement: " . $this->dbConnection->error . " (" . $this->dbConnection->errno . ")";
			return FALSE;
		}

		$exists = TRUE;
		$sql_statement->bind_param("ss", $this->userData['login'], $this->userData['hash']);
		$sql_statement->execute();
		if($sql_statement->affected_rows > 0){
			$_SESSION['logged_in'] = $displayContent['logged_in'] = TRUE;
			$this->userId = $sql_statement->insert_id;
			$_SESSION['user_id'] = $this->userId;
			$this->userData['id'] = $this->userId;
		} else {
			$this->displayContent['message'][] = "Registration failed!";
			$exists = FALSE;
		}
		$sql_statement->close();

		return $exists;
	}

	function __destruct(){
		if($this->isDB_opened)
			$this->dbConnection->close();
	}

}

