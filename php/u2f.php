<?php
include dirname(__FILE__)."/constants.php";

class U2F_example {
	private $appId;
	private $facetIds;
	private $userId;

	private $dbConnection;
	private $databaseTable;
	private const VERSION = "U2F_V2";

	public function __construct($userId, $databaseParams, $http_host = NULL, $isHttps = TRUE){
		$https = ($isHttps)? "https://" : "http://";
		if($http_host !== NULL)
			$this->appId = $https . $http_host;
		else
			$this->appId = $https . "localhost";

		$this->facetIds = [$this->appId];

		$this->userId = $userId;

		$this->databaseTable = "";

		$hostname = ( !empty($databaseParams['hostname']) )? $databaseParams['hostname'] : "localhost";
		$username = $databaseParams['username'];
		$password = $databaseParams['password'];
		$db_name = $databaseParams['database'];

		$this->databaseTable = ( !empty($databaseParams['table_name']) )? $databaseParams['table_name'] : "hardware_key";
		$this->dbConnection = new mysqli($hostname,$username,$password, $db_name);
	}

	/**
	 *	Creates u2f object with random challenge id
	 * to register new u2f FIDO key or to check registered one;
	 *
	 * @return: u2f default object with challenge id;
	 */
	public function getFirstRegister(){
		$challenge = random_bytes(32);
		$challenge = trim(strtr(base64_encode($challenge), '+/', '-_'), '=');

		$request = array(
					'appId' => $this->appId,
					'challenge' => $challenge,
					'version' => U2F_example::VERSION,
					'signs' => array()
				);
		return $request;
	}

	/**
	 *	Creates u2f object with random challenge id
	 * and includes registered keys with another random challenge id
	 * if $userTable is defined;
	 *
	 * @param  $userTable  {string}: name of database table which stores registered users;
	 * @return: u2f object with default challenge id and registered keys if $userTable is defined;
	 */
	public function getRegisters($userTable = NULL){
		$registerData = $this->getFirstRegister();
		if($this->databaseTable === NULL)
			return $registerData;

		$sql_query = "SELECT COUNT(*) AS count_keys FROM " . $this->databaseTable . " WHERE user_id = ?";
		$sql_statement = $this->dbConnection->prepare($sql_query);

		if($sql_statement === FALSE){// problems with data collection? No problem, just send no signatures;
			return $registerData;
		}

		$sql_statement->bind_param("i", $this->userId);
		$sql_statement->execute();
		$result = $sql_statement->get_result();
		if($result === FALSE)
			return $registerData;

		$count_keys = $result->fetch_assoc();
		$count_keys = $count_keys['count_keys'];
		$sql_statement->close();

		if($count_keys < 1)
			return $registerData;

		$registerData['signs'] = $this->getKeys($userTable);
		return $registerData;
	}

	/**
	 *	Gets keys of user with defined id from database 
	 *
	 * @param $userTable  {string}: if is defined then update challenge value on user table;
	 * @return: array of keys needed for u2f on a browser side;
	 */
	private function getKeys($userTable = NULL){
		if($this->databaseTable === NULL)
			return array();

		$challenge = random_bytes(32);
		$challenge = trim(strtr(base64_encode($challenge), '+/', '-_'), '=');
		$signs = array();

		$sql_query = "SELECT key_handle FROM " . $this->databaseTable . " WHERE user_id = ? AND is_active = 1";
		$sql_statement = $this->dbConnection->prepare($sql_query);

		if($sql_statement === FALSE){// problems with data collection? No problem, just send no signatures;
			return $signs;
		}

		$sql_statement->bind_param("i", $this->userId);
		$sql_statement->execute();

		$results = $sql_statement->get_result();
		while($row = $results->fetch_assoc() ){
			$registeredKey = array('appId' => $this->appId, 'keyHandle' => $row['key_handle'], 'challenge' => $challenge, 'version' => U2F_example::VERSION);
			$signs[] = $registeredKey;
		}

		$sql_statement->close();

		if(!empty($userTable) )
			$this->saveLastChallenge($challenge, $userTable);

		return $signs;
	}

	/**
	 *	Select all keys by a key_handle which was sent into backend server when user touched his key;
	 *
	 * @param $keyHandle: value retrieved from the data get from users key;
	 * @return FALSE|array: list of keys when success or FALSE;
	 */
	private function getKeysByKeyHandle($keyHandle){
		$sql_query = "SELECT id, key_handle, public_key, usage_counter FROM " . $this->databaseTable . " WHERE key_handle = ? AND user_id = ? AND is_active = 1";
		$sql_statement = $this->dbConnection->prepare($sql_query);
		if($sql_statement === FALSE){
			return FALSE;
		}

		$sql_statement->bind_param("si", $keyHandle, $this->userId);
		$sql_statement->execute();

		$results = $sql_statement->get_result();
		$keys = array();
		while($row = $results->fetch_assoc() ){
			$registeredKey = array('id' => $row['id'], 'key_handle' => $row['key_handle'], 'public_key' => $row['public_key'], 'usage_counter' => $row['usage_counter'], 'pem' => "");
			$registeredKey['pem'] = base64_decode(strtr($row['public_key'], '-_', '+/'));
			$registeredKey['pem'] = $this->pubKeyToPem($registeredKey['pem']);
			$keys[] = $registeredKey;
		}

		$sql_statement->close();

		if(count($keys) < 1)
			return FALSE;
		return $keys;
	}

	/**
	 *	Updates counter of registered key with $id which belongs to $this->userId;
	 * value of $newCounter  comes from user's yubiKey. 
	 *
	 * @param $id:  id of key in database;
	 * @param $newCounter: new value of usage counter;
	 * @return  {bool}
	 */
	private function updateCounter($id, $newCounter){
		$sql_query = "UPDATE ".$this->databaseTable." SET `usage_counter` = ?, last_used = NOW() WHERE `id` = ? AND `user_id` = ?";
		$sql_statement = $this->dbConnection->prepare($sql_query);
		if($sql_statement === FALSE){
			return FALSE;
		}

		$sql_statement->bind_param("iii", $newCounter, $id, $this->userId);
		$sql_statement->execute();
		$sql_statement->close();
		return TRUE;
	}

	/**
	 *	Checks if touched users key and its data corresponds with those keys registered by user with $this->userId;
	 *
	 * @param $u2fSignData: data from callback of u2f.sign();
	 * @return  {bool}: TRUE when authentication successed, FALSE otherwise;
	 * @throws Exception
	 */
	public function authenticate($u2fSignData, $sentChallenge, $requestedAppId){
		if( !is_object($u2fSignData) )
			throw new InvalidArgumentException('Authentication method requires object type (#1).');

		$clientDataAsJson = base64_decode(strtr($u2fSignData->clientData, '-_', '+/'));
		$clientData = json_decode($clientDataAsJson);

		if(isset($clientData->typ) && $clientData->typ !== 'navigator.id.getAssertion')
			throw new Exception('Client data type is invalid', ERR_BAD_TYPE);

		if($clientData->challenge !== $sentChallenge)
			throw new Exception('Client data does not match required "challenge"', ERR_NO_MATCHING_REQUEST);

		if(isset($clientData->origin) && !in_array($clientData->origin, $this->facetIds, true))
            throw new Exception('App ID does not match the origin.', ERR_NO_MATCHING_ORIGIN);


		$keys = $this->getKeysByKeyHandle($u2fSignData->keyHandle);
		if($keys === FALSE)
			throw new Exception("No matching key found!", ERR_NO_MATCHING_REGISTRATION);

		$key = NULL;
		foreach($keys as $key){
			if($key['pem'] !== NULL)
				break;

			$key = NULL;
		}

		if($key === NULL)
			throw new Exception("Decoding of public key failed!", ERR_PUBKEY_DECODE);


		$signData = base64_decode(strtr($u2fSignData->signatureData, '-_', '+/'));
		$dataToVerify  = hash('sha256', $requestedAppId, TRUE);
		$dataToVerify .= substr($signData, 0, 5);
		$dataToVerify .= hash('sha256', $clientDataAsJson, TRUE);
		$signature = substr($signData, 5);

		if(openssl_verify($dataToVerify, $signature, $key['pem'], 'sha256') === 1){
			$upb = unpack("Cupb", substr($signData, 0, 1) );
			if($upb['upb'] !== 1)
				throw new Error('User presence byte value is invalid', ERR_BAD_USER_PRESENCE);

			$upb = unpack("Nctr", substr($signData, 1, 4) );
			$counter = $upb['ctr'];
			if($counter <= $key['usage_counter'])
				throw new Exception('Authentication failed due to counter', ERR_COUNTER_TOO_LOW);
				// what if yubiKey counter restarts from 0;

			$this->updateCounter($key['id'], $counter);
			return TRUE;
		} else
			throw new Exception('Authentication failed!', ERR_AUTHENTICATION_FAILURE);

		return FALSE;
	}

	/**
	 * @param $request: u2f key register request (typeof dictionary array);
	 * @param $u2fRegisterData: data responded from u2f.register in JavaScript
	 *                          as argument in callback function (typeof dictionary array);
	 * @return  {bool}
	 * @throws Exception
	 */
	public function registerKey($request, $u2fRegisterData, $doIncludeCert = TRUE){
		if( !is_object($request) ){
			throw new InvalidArgumentException('Argument has to be an object (#1).');
		}
		// todo: add exceptions
		$rawRegistrationData = base64_decode(strtr($u2fRegisterData->registrationData, '-_', '+/'));
		$rawData = array_values(unpack('C*', $rawRegistrationData));// 'C' stands for unsigned char;
		$clientDataAsJson = base64_decode(strtr($u2fRegisterData->clientData, '-_', '+/'));
		$clientData = json_decode($clientDataAsJson);

		if($request->challenge !== $clientData->challenge)
			throw new Exception('Registration challenge does not match', ERR_UNMATCHED_CHALLENGE);

		if(isset($clientData->typ) && $clientData->typ !== 'navigator.id.finishEnrollment') // REQUEST_TYPE_REGISTER;
			throw new Exception('ClientData type is invalid', ERR_BAD_TYPE);

		if(isset($clientData->origin) && !in_array($clientData->origin, $this->facetIds, true) )
			throw new Exception('App ID does not match the origin', ERR_NO_MATCHING_ORIGIN);

		$registration = array('key_id' => '',
		                      'key_handle' => '',
		                      'public_key' => '',
		                      'certificate' => '',
		                      'counter' => 0,
		                      'signature' => '', 'client_data' => '', 'registration_data' => '');
		if($rawRegistrationData !== FALSE)
			$registration['registration_data'] = chunk_split(base64_encode($rawRegistrationData), 64, " ");// or unpack('C*', $rawRegistrationData) where  'C' stands for unsigned char;
		if($clientDataAsJson !== FALSE)
			$registration['client_data'] = $clientDataAsJson;

		$offset = 1;
		$pubKey = substr($rawRegistrationData, $offset, 65);// PUBKEY_LEN;
		$offset += 65;
		if(strlen($pubKey) !== 65 || $pubKey[0] !== "\x04") // PUBKEY_LEN;
			throw new Exception('Decoding of public key failed', ERR_PUBKEY_DECODE);

		$registration['public_key'] = base64_encode($pubKey);
		$length = $rawData[$offset++];
		$keyHandle = substr($rawRegistrationData, $offset, $length);
		$offset += $length;
		$registration['key_handle'] = trim(strtr(base64_encode($keyHandle), '+/', '-_'), '=');

		$length  = 4;
		$length += ($rawData[$offset + 2] << 8);
		$length += $rawData[$offset + 3];

		$rawCert = $this->fixSignatureFromUnusedBits(substr($rawRegistrationData, $offset, $length) );
		$offset += $length;

		$pemCert  = "-----BEGIN CERTIFICATE-----\r\n";
		$pemCert .= chunk_split(base64_encode($rawCert), 64);
		$pemCert .= "-----END CERTIFICATE-----";
		if($doIncludeCert){
			$registration['certificate'] = base64_encode($rawCert);
		}

		if(!openssl_pkey_get_public($pemCert) )
			throw new Exception('Decoding of public key failed', ERR_PUBKEY_DECODE);

		$registration['signature'] = substr($rawRegistrationData, $offset);
		$verificationData  = pack('C', 0);
		$verificationData .= hash('sha256', $request->appId, TRUE);
		$verificationData .= hash('sha256', $clientDataAsJson, TRUE);
		$verificationData .= $keyHandle;
		$verificationData .= $pubKey;

		if(openssl_verify($verificationData, $registration['signature'], $pemCert, 'sha256') )
			return $this->saveKey($registration);// -> add to database;
		else
			throw new Exception('Attestation signature does not match', ERR_ATTESTATION_SIGNATURE);

		return FALSE;
	}

	/**
	 * @param $registration: array object with properties/keys respective to model properties:
	 *                       'key_id', 'key_handle', 'public_key', 'certificate', 'counter', 'signature', 'client_data' and 'registration_data';
	 * @return  {bool}: TRUE if savev successfully, FALSE otherwise;
	 * @throws Exception
	 */
	private function saveKey($registration){
		$sql_query  = "INSERT INTO " . $this->databaseTable;
		$sql_query .= " (key_id, key_handle, public_key, certificate, usage_counter, raw_key_response_client, raw_key_response_data, user_id)";
		$sql_query .= " VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

		$sql_statement = $this->dbConnection->prepare($sql_query);
		if($sql_statement === FALSE)
			throw new Exception($this->dbConnection->error, $this->dbConnection->errno);// $this->dbConnection->errno, $this->dbConnection->error;

		$sql_statement->bind_param("ssssissi", $registration['key_id'],
		                                       $registration['key_handle'],
		                                       $registration['public_key'],
		                                       $registration['certificate'],
		                                       $registration['counter'],
		                                       $registration['client_data'],
		                                       $registration['registration_data'],
		                                       $this->userId);
		$sql_statement->execute();
		$success = FALSE;
		if($sql_statement->affected_rows > 0)
			$success = TRUE;
		else {
			$sql_statement->close();
			throw new Exception("No affected rows (#1).");
		}

		$sql_statement->close();
		return $success;
	}


	/**
	 *	Convert the public key to binary DER format first
	 * Using the ECC SubjectPublicKeyInfo OIDs from RFC 5480
	 *
	 * @param $key: base64 (public) key;
	 * @return NULL|string: pem version ok key;
	 */
	private function pubKeyToPem($key){
		if(strlen($key) !== 65 || $key[0] !== "\x04") // PUBKEY_LEN
			return NULL;

		/**
		 *  SEQUENCE(2 elem)                        30 59
		 *  SEQUENCE(2 elem)                        30 13
		 *    OID1.2.840.10045.2.1 (id-ecPublicKey) 06 07 2a 86 48 ce 3d 02 01
		 *    OID1.2.840.10045.3.1.7 (secp256r1)    06 08 2a 86 48 ce 3d 03 01 07
		 *  BIT STRING(520 bit)                     03 42  ..key..
		 */
		$der  = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01";
		$der .= "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42";
		$der .= "\0".$key;

		$pem  = "-----BEGIN PUBLIC KEY-----\r\n";
		$pem .= chunk_split(base64_encode($der), 64);
		$pem .= "-----END PUBLIC KEY-----";

		return $pem;
	}

	private function fixSignatureFromUnusedBits($cert){
		$unusedBits = array('349bca1031f8c82c4ceca38b9cebf1a69df9fb3b94eed99eb3fb9aa3822d26e8',
		                    'dd574527df608e47ae45fbba75a2afdd5c20fd94a02419381813cd55a2a3398f',
		                    '1d8764f0f7cd1352df6150045c8f638e517270e8b5dda1c63ade9c2280240cae',
		                    'd0edc9a91a1677435a953390865d208c55b3183c6759c9b5a7ff494c322558eb',
		                    '6073c436dcd064a48127ddbf6032ac1a66fd59a0c24434f070d4e564c124c897',
		                    'ca993121846c464d666096d35f13bf44c1b05af205f9b4a1e00cf6cc10c5e511');

		if(in_array(hash('sha256', $cert), $unusedBits, TRUE)) {
			$cert[strlen($cert) - 257] = "\0";
		}
		return $cert;
	}

	/**
	 * @param $challenge: string of challenge ID to save into DB on backend side (typically 44 chars);
	 * @param $userTable: table of users in database which is expected to have 'last_challenge_id' property;
	 * @return  {bool}: TRUE if success or FALSE otherwise;
	 */
	private function saveLastChallenge($challenge, $userTable){
		$sql_query = "UPDATE " . $userTable . " SET last_challenge_id = ? WHERE id = ?";

		$sql_statement = $this->dbConnection->prepare($sql_query);
		if($sql_statement === FALSE){
			return FALSE;
		}

		$sql_statement->bind_param("si", $challenge, $this->userId);
		$sql_statement->execute();
		$sql_statement->close();
		return TRUE;
	}

}

