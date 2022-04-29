import ajax, { getSessionIdFromInput, clearTable, insertCol, splitString } from "./utils.js";


function getBlocks(){
	let blocks = [null, null];
	blocks[0] = document.getElementById("keys");
	blocks[1] = document.getElementById("users");
	return blocks;
}

function keySelector(node){
	let sel = node.getElementsByTagName("select"), result = {select: null, button: null};
	if(sel.length < 1)
		return result;

	let i, j, selForm, btn;
	for(i = sel.length - 1; i >= 0; i--){
		selForm = sel[i].parentNode;

		btn = selForm.getElementsByTagName("input");
		j = btn.length - 1;
		for(; j >= 0; j--){
			if(btn[j].type === "button" && btn[j].value === "Show key"){
				result.select = sel[i];
				result.button = btn[j];
				return result;
			}
		}
	}

	return result;
}


var Account = (function(){
	var keysBlock, usersBlock, keyTable, keySelect, linkTableElements;
	var ssid, instance;

	/**
	 *	Remove all DOM nodes from parent nodes
	 * which are included in linkTableElements object;
	 */
	function clearTableElements(){
		if(linkTableElements.link)
			linkTableElements.link.parentNode.removeChild(linkTableElements.link);
		if(linkTableElements.inputText)
			linkTableElements.inputText.parentNode.removeChild(linkTableElements.inputText);
		if(linkTableElements.button)
			linkTableElements.button.parentNode.removeChild(linkTableElements.button);
		if(linkTableElements.linkCert)
			linkTableElements.linkCert.parentNode.removeChild(linkTableElements.linkCert);
	}

	function requestKey(){
		if(keySelect === null || keySelect.select === null)
			return;

		let index = keySelect.select.selectedIndex;
		if(index < 1)
			return;

		let values = ["key_detail=1"], keyId = keySelect.select.options[index].value;
		values.push("ssid=" + encodeURIComponent(ssid));
		values.push("key_id=" + encodeURIComponent(keyId));

		ajax.xhr("", null, showKey, values.join('&'), showKey);
	}

	function showKey(response){
		if(!keyTable)
			return;

		clearTableElements();
		clearTable(keyTable);

		if(response.hasOwnProperty('id') )
			linkTableElements.keyId = response['id'];

		let row, text;

		text = (response.hasOwnProperty('name') && response['name'])? response['name'] : "N.A.";
		row = keyTable.insertRow();
		createNameLink(text);
		insertCol(row, "Name: ").appendChild(linkTableElements.link);

		text = (response.hasOwnProperty('key_id') && response['key_id'])? response['key_id'] : "N.A.";
		row = keyTable.insertRow();
		insertCol(row, "Key id: ", text);

		text = (response.hasOwnProperty('usage_counter') && response['usage_counter'])? response['usage_counter'] : "N.A.";
		row = keyTable.insertRow();
		insertCol(row, "Usage counter: ", text);


		row = keyTable.insertRow();
		if(response.hasOwnProperty('raw_key_response_client') && response['raw_key_response_client']){
			let parsed = true;
			try {
				text = JSON.parse(response['raw_key_response_client']);
			}catch(e){
				parsed = false;
				insertCol(row, "Raw key response client: ", response['raw_key_response_client']);
			}

			if(parsed){
				let col = insertCol(row, "Raw key response client: ");
				Object.keys(text).forEach((key, index) => {
					if(index > 0)
						col.appendChild(document.createElement("br") );
					col.appendChild(document.createTextNode(key) );
					col.appendChild(document.createTextNode(": ") );
					col.appendChild(document.createTextNode(text[key]) );
				});
			}
		} else
			insertCol(row, "Raw key response client: ", "unknown");


		text = (response.hasOwnProperty('public_key') && response['public_key'])? response['public_key'] : "unknown";
		row = keyTable.insertRow();
		insertCol(row, "Public key: ", text);

		text = (response.hasOwnProperty('key_handle') && response['key_handle'])? response['key_handle'] : "unknown";
		row = keyTable.insertRow();
		insertCol(row, "Key handle: ", text);

		row = keyTable.insertRow();
		linkTableElements.linkCert = createDisplayLink("Click to show", sendCertificateRequest);
		insertCol(row, "Certificate: ").appendChild(linkTableElements.linkCert);

		row = keyTable.insertRow();
		linkTableElements.linkRawData = createDisplayLink("Click to show", sendRawKeyResponseDataRequest);
		insertCol(row, "Raw response data: ").appendChild(linkTableElements.linkRawData);

		if(response.hasOwnProperty('is_active') ){
			row = keyTable.insertRow();
			linkTableElements.activeCheckbox = document.createElement("input");
			linkTableElements.activeCheckbox.type = "checkbox";
			linkTableElements.activeCheckbox.checked = (response['is_active'])? true : false;
			/*linkTableElements.activeCheckbox.addEventListener("click", enableOrDisableKey, false); https://stackoverflow.com/questions/5575338/what-the-difference-between-click-and-change-on-a-checkbox */
			insertCol(row, "Is active: ").appendChild(linkTableElements.activeCheckbox);
		}

		text = "";
		if(response.hasOwnProperty('last_used_date') || response.hasOwnProperty('last_used_time') ){
			text = "";
			if(response.hasOwnProperty('last_used_date') ){
				text += response['last_used_date'];
			}

			if(response.hasOwnProperty('last_used_time') ){
				if(text.length > 0)
					text += ", ";
				text += response['last_used_time'];
			}
		} else if(response.hasOwnProperty('last_used') )
			text = response['last_used'];

		if(text.length > 0){
			row = keyTable.insertRow();
			insertCol(row, "Last used: ", text);
		}
	}

	function createNameLink(name){
		linkTableElements.value = name;

		linkTableElements.link = document.createElement("a");
		linkTableElements.link.appendChild(document.createTextNode(name) );
		linkTableElements.link.href = "#";
		linkTableElements.link.addEventListener("click", createFormName, false);
	}

	function createDisplayLink(text, onClickFun){
		let elem = document.createElement("a");

		elem.appendChild(document.createTextNode(text) );
		elem.href = "#";
		elem.addEventListener("click", onClickFun, false);
		return elem;
	}

	/**
	 *	Create form to rename the key;
	 */
	function createFormName(e){
		var e2=(!e)?window.event:e;
		e2.preventDefault();

		linkTableElements.inputText = document.createElement("input");
		linkTableElements.inputText.type = "text";
		linkTableElements.inputText.value = linkTableElements.value;

		linkTableElements.button = document.createElement("input");
		linkTableElements.button.type = "button";
		linkTableElements.button.value = "Save";

		let col = linkTableElements.link.parentNode;
		col.removeChild(linkTableElements.link);
		linkTableElements.link = null;
		col.appendChild(linkTableElements.inputText);
		col.appendChild(document.createElement("br") );
		col.appendChild(linkTableElements.button);
		linkTableElements.button.addEventListener("click", sendChangeNameRequest, false);

		linkTableElements.inputText.focus();
	}

	/**
	 *	Methods to rename key: request and response;
	 */
	function sendChangeNameRequest(){
		linkTableElements.button.disabled = true;

		let values = ["rename=1"];
		values.push("ssid=" + encodeURIComponent(ssid));
		values.push("key_id=" + encodeURIComponent(linkTableElements.keyId));
		values.push("name=" + encodeURIComponent(linkTableElements.inputText.value));

		ajax.xhr("", null, receiveChangeName, values.join('&'), receiveChangeName);
	}

	function receiveChangeName(response){
		if(response.hasOwnProperty('error') || !response.hasOwnProperty('success') ){
			console.log(response['error']);
			if(typeof response['error'] === 'string' || response['error'] instanceof String)
				alert(response['error']);
			linkTableElements.button.disabled = false;
			return;
		}

		if(!response['success']){
			console.log(response);
			linkTableElements.button.disabled = false;
			if(response.hasOwnProperty('message') && (typeof response['message'] === 'string' || response['message'] instanceof String) )
				alert(response['message']);
			return;
		}

		createNameLink(linkTableElements.inputText.value);

		let col = linkTableElements.inputText.parentNode;
		while(col.firstChild)
			col.removeChild(col.firstChild);

		linkTableElements.inputText = null;
		linkTableElements.button = null;
		col.appendChild(linkTableElements.link);
	}

	/**
	 *	Methods to enable or disable key and its response;
	 */
	function sendEnableOrDisableKey(){
		if(!linkTableElements.activeCheckbox)
			return;

		let values = ["active=1"];
		values.push("ssid=" + encodeURIComponent(ssid));
		values.push("key_id=" + encodeURIComponent(linkTableElements.keyId));

	}

	function receiveEnableOrDisableKey(response){
		console.log(response);
	}

	/**
	 *	Methods to receive the certificate of the key;
	 */
	function sendCertificateRequest(e){
		var e2=(!e)?window.event:e;
		e2.preventDefault();

		linkTableElements.linkCert.removeEventListener("click", sendCertificateRequest);

		let values = ["cert=1"];
		values.push("ssid=" + encodeURIComponent(ssid));
		values.push("key_id=" + encodeURIComponent(linkTableElements.keyId));

		ajax.xhr("", null, receiveCertificateRequest, values.join('&'), receiveCertificateRequest);
	}

	function receiveCertificateRequest(response){
		if(response.hasOwnProperty('error') ){
			console.log(response['error']);
			if(typeof response['error'] === 'string' || response['error'] instanceof String)
				alert(response['error']);

			linkTableElements.linkCert.addEventListener("click", sendCertificateRequest, false);
			return;
		} else if(!response.hasOwnProperty('cert') ){
			alert("Missing proper key for response of certificate!");
			linkTableElements.linkCert.addEventListener("click", sendCertificateRequest, false);
			return
		}

		let count, certBlocks, col;
		certBlocks = splitString(response['cert'], 64);

		col = linkTableElements.linkCert.parentNode;
		while(col.firstChild)
			col.removeChild(col.firstChild);
		linkTableElements.linkCert = null;

		count = certBlocks.length;
		let i, br;
		col.appendChild(document.createTextNode(certBlocks[0]) );
		for(i = 1; i < count; i++){
			br = document.createElement("br");
			col.appendChild(br);
			col.appendChild(document.createTextNode(certBlocks[i]) );
		}
	}

	/**
	 *	Methods to receive the raw data of the key;
	 */
	function sendRawKeyResponseDataRequest(e){
		var e2=(!e)?window.event:e;
		e2.preventDefault();

		linkTableElements.linkRawData.removeEventListener("click", sendRawKeyResponseDataRequest);

		let values = ["response_data=1"];
		values.push("ssid=" + encodeURIComponent(ssid));
		values.push("key_id=" + encodeURIComponent(linkTableElements.keyId));

		ajax.xhr("", null, receiveRawKeyResponseDataRequest, values.join('&'), receiveRawKeyResponseDataRequest);
	}

	function receiveRawKeyResponseDataRequest(response){
		if(response.hasOwnProperty('error') ){
			console.log(response['error']);
			if(typeof response['error'] === 'string' || response['error'] instanceof String)
				alert(response['error']);

			linkTableElements.linkRawData.addEventListener("click", sendRawKeyResponseDataRequest, false);
			return;
		} else if(!response.hasOwnProperty('key_data') ){
			alert("Missing proper key for response of raw data!");
			linkTableElements.linkRawData.addEventListener("click", sendRawKeyResponseDataRequest, false);
			return
		}

		let count, dataBlocks = splitString(response['key_data'], 64), col;

		col = linkTableElements.linkRawData.parentNode;
		while(col.firstChild)
			col.removeChild(col.firstChild);
		linkTableElements.linkRawData = null;

		count = dataBlocks.length;
		let i, br;
		col.appendChild(document.createTextNode(dataBlocks[0]) );
		for(i = 1; i < count; i++){
			br = document.createElement("br");
			col.appendChild(br);
			col.appendChild(document.createTextNode(dataBlocks[i]) );
		}
	}

	function run(){
		if(keySelect != null && keySelect.select && keySelect.button){
			keySelect.select.addEventListener("change", () => {keySelect.button.disabled = keySelect.select.selectedIndex < 1;}, false);
			keySelect.button.addEventListener("click", requestKey, false);
		}
	}

	return {
		init: function(){
			let obj = getBlocks();
			keysBlock = obj[0];
			usersBlock = obj[1];
			ssid = getSessionIdFromInput();

			keyTable = document.getElementById("key_detail");
			keySelect = keySelector(keysBlock);
			run();

			ajax.setUrl();
			linkTableElements = {keyId: -1, link: null, value: "", inputText: null, button: null, linkCert: null, linkRawData: null, activeCheckbox: null};
		}
	}
})();

export default Account;

