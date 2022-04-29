var YKey=(function(){
	var YKeyObj, menuContent, frm, infoBox, sources, dc,ins;

	function YKey(){
		if(ins==null){
			ins = Object.create(YKey.prototype);
			/* reg:register-button; sgn:sign-in-button; logout:logout-buttons;  */
			frm = {fr: null,
			     login: null,
			     pass: null,
			     reg: null, sgn: null, logout: [],
			     keyRegBtn: null, hidden: null, isReg: false,
			     supports: false, credentials: false, credentialsAreSelected: false,
			     url: "", ssid: "", uid: -1};
			infoBox = {box: null,
			         print: (msg)=>{console.log(msg);},
			         msg: null,
			         printMessage: (msg, append, newLine)=>{console.log(msg);},
			         msgAppendDom: function(node){},
			         showMessage: ()=>{},
			         msgClose: null};

			YKeyObj = {regSteps: 0,
			           callbackFn: function(){},
			           challengeId: null,
			           appId: null,
			           ver: "",
			           signs: [],
			           counter: -1,
			           keyId: null, form: null, respInput: null};

			menuContent = {menu: [], content: []};

			sources={attempts:5, links:[]};
			dc=document;
		} else dc=false;
		return ins;
	}

	function setUp(args){
		if(!dc)
			return;
		let elm=dc.getElementById("register");
		if(elm){
			frm.fr = elm;
			elm=frm.fr.getElementsByTagName("input");
		} else
			elm = [];

		let i;
		for(i=elm.length-1; i>=0; i--){
			if(elm[i].type=="text")
				frm.login=elm[i];
			else if(elm[i].type=="password"){
				frm.pass=elm[i];
			} else if(elm[i].type=="button"){
				if(elm[i].name === "yubikey")
					frm.keyRegBtn = elm[i];
				else
					frm.reg=elm[i];
			} else if(elm[i].type=="submit"){
				if(!/logout/i.test(elm[i].value) )
					frm.sgn=elm[i];
				else
					frm.logout.push(elm[i]);
			} else if(elm[i].type=="hidden" && elm[i].name=="ssid"){
				frm.ssid = elm[i].value;
			}
		}

		elm=dc.getElementById("info");
		if(elm){
			infoBox.box=elm;
			infoBox.print=(message)=>{
				clearDm(infoBox.box);
				infoBox.box.appendChild(dc.createTextNode(message) );
			};
		}

		if(u2f || navigator.credentials){
			let str = "";
			if(u2f && navigator.credentials){
				str = "This browser supports U2F  and  \"credentials\".";
				frm.supports=true;
				frm.credentials = navigator.credentials;
				createFidoAuthenticationOptions();
			} else if(u2f){
				str = "This browser supports U2F.";
				frm.supports=true;
			} else {
				str = "This browser supports  \"credentials\"";
				frm.credentials = navigator.credentials;
			}
			infoBox.print(str);
			setUpYKey(args);
		} else {
			infoBox.print("This browser does not support U2F!");
		}

		elm = window.location.href.split('.php');
		if(elm.length > 0){
			frm.url = elm[0];
			if(elm.length > 1) frm.url = elm[0] + ".php";
		}

		elm = dc.getElementsByClassName("message");
		if(elm.length > 0){
			let cnt = elm[0].getElementsByClassName("content");
			if(cnt.length > 0)
				infoBox.msg = cnt[0];
			elm = elm[0].getElementsByClassName("close");
			if(elm.length > 0) infoBox.msgClose = elm[0];
		}
		if(infoBox.msg){
			infoBox.printMessage = (msg, append, newLine) => {
				if(typeof append === 'undefined' || !append)
					clearDm(infoBox.msg);

				infoBox.msg.appendChild(dc.createTextNode(msg) );

				if(typeof newLine !== 'undefined' || !newLine)
					infoBox.msg.appendChild(dc.createElement("br") );
			};
			infoBox.showMessage = function(){
				let elm = parentMessage(infoBox.msg);
				if(elm) elm.style.display = "";
			};

			infoBox.msgAppendDom = function(node){
				infoBox.msg.appendChild(node);
			};
		}

		setUpMenuLinks();
	}

	function setUpMenuLinks(){
		var cnt = dc.getElementsByClassName("menu"), cnts, i;
		if(cnt.length > 0){
			cnt = cnt[0];
			cnts = cnt.getElementsByTagName("a");
			for(i = 0; i < cnts.length; i++)
				menuContent.menu.push(cnts[i]);
		}
		cnt = dc.getElementsByClassName("main");
		if(cnt.length > 0){
			cnt = cnt[0];
			cnts = cnt.getElementsByClassName("cell");
			for(i = 0; i < cnts.length; i++){
				menuContent.content.push(cnts[i])
				if(i > 0)
					cnts[i].style.display = "none";
			}
		}
	}

	function createFidoAuthenticationOptions(){
		if( !(frm.fr && u2f && navigator.credentials) || frm.pass == null )
			return;

		let methodSelect, elem;
		elem = dc.createElement("span");
		elem.appendChild(dc.createTextNode("Method of FIDO authentication: ") );
		elem.style.color = "#FFF";
		elem.style.paddingRight = "1em";
		frm.fr.appendChild(dc.createElement("br") );
		frm.fr.appendChild(elem);

		methodSelect = dc.createElement("select");
		elem = dc.createElement("option");
		elem.value = "u2f";
		elem.appendChild(dc.createTextNode("u2f") );
		methodSelect.appendChild(elem);
		elem = dc.createElement("option");
		elem.value = "credentials";
		elem.appendChild(dc.createTextNode("credentials") );
		methodSelect.appendChild(elem);
		frm.fr.appendChild(methodSelect);

		methodSelect.addEventListener("change", function(){
			if(!this.options)
				return;
			let index = this.options.selectedIndex;
			frm.credentialsAreSelected = this.options[index].value === "credentials";
		}, false);
	}

	function setUpJsonLinks(){
		let dataBlock = dc.getElementById("data_json");
		if(!dataBlock){
			if(sources.attempts > 0){
				sources.attempts = sources.attempts-1;
				setTimeout(setUpJsonLinks, 30);
			}
			return;
		}

		if(!infoBox.box)
			return;

		try {
			sources.links = JSON.parse(dataBlock.textContent);
		} catch(e){
			sources.links=[];
		}
		if(sources.links.length < 1)
			return;

		var showLnk = dc.createElement("a");
		showLnk.href="#";
		showLnk.appendChild(dc.createTextNode("Show helpful links") );
		showLnk.addEventListener("click", showLinks, false);
		infoBox.box.parentNode.appendChild(showLnk);
	}

	function setUpYKey(args){
		if(frm.supports !== true)
			return;

		if(args.hasOwnProperty("check") && args.check){
			frm.uid = parseInt(args.check, 10);
			YKeyObj.callbackFn = checkingSteps;
			setTimeout(checkingSteps, 300);
		} else
			YKeyObj.callbackFn = registerSteps;
	}

	function registerSteps(){
		if(!infoBox.msg){
			infoBox.print("Watch console to see all steps!");
		}

		let elm;
		if(YKeyObj.regSteps < 1){
			YKeyObj.form = YKeyObj.respInput = null;

			infoBox.printMessage("Do you own security hardware key?", false, true);
			elm = dc.createElement("img");
			elm.style.width="40%";
			elm.src = frm.url.substring(0, frm.url.lastIndexOf('/')) + "/static/images/yubiKey.jpg";
			infoBox.msgAppendDom(elm);

			infoBox.msgAppendDom(dc.createElement("br") );
			infoBox.printMessage("Prepare your key.", true, true);
			infoBox.showMessage();

			/* todo: if(frm.credentialsAreSelected){} else { */
			sendXML("", null, receiveChallengeAndKeys, "register_check=1", displayResponse);
			/* } */
		} else {
			infoBox.printMessage("Register your security key.", false, true);

			elm = dc.createElement("ol");
			let ptm = dc.createElement("li");
			ptm.appendChild(dc.createTextNode("Insert your security key into USB.") );
			elm.appendChild(ptm);
			ptm=dc.createElement("li");
			ptm.appendChild(dc.createTextNode("Press button on a key.") );
			elm.appendChild(ptm);
			infoBox.msgAppendDom(elm);

			createRegisterForm();
			infoBox.msgAppendDom(YKeyObj.form);
			YKeyObj.respInput.focus();
			YKeyObj.respInput.select();

			infoBox.showMessage();

			let regRequest = [{version: YKeyObj.ver, challenge: YKeyObj.challengeId, appId: YKeyObj.appId, attestation: 'direct'}];
			u2f.register(YKeyObj.appId, regRequest, YKeyObj.signs, function(data){
				if(data.errorCode && data.errorCode != 0){
					let message = "Registration failed (" + u2fErrorsToText(data);

					infoBox.printMessage(message + "; errno: " + data.errorCode + ")", false);
					infoBox.showMessage();
					console.log("Error occured during registration:");
					console.log(data);
					console.log("YKey object: ", YKeyObj);
					return;
				}

				let objResponse = {keyData: data, appId: YKeyObj.appId, challenge: YKeyObj.challengeId, version: YKeyObj.ver, keyId: ""};
				if(YKeyObj.keyId)
					objResponse.keyId = YKeyObj.keyId;
				let registerState="register_finish=1", jsonResp = "keyResponse=" + encodeURIComponent(JSON.stringify(objResponse) );

				sendXML("", null, receiveRegistrationResponse, jsonResp + '&' + registerState, displayResponse);
			});

			YKeyObj.regSteps = 0;
		}
	}

	function receiveChallengeAndKeys(response){
		if(!response.hasOwnProperty("challenge") && !response.hasOwnProperty("signs") ){
			infoBox.printMessage("Required properties of keys are missing!", false);
			infoBox.showMessage();
			console.log(response);
			return;
		}

		YKeyObj.challengeId = response['challenge'];
		YKeyObj.appId=response['appId'];

		if(response.hasOwnProperty("version") )
			YKeyObj.ver = response['version'];

		if(Array.isArray(response['signs']) )
			YKeyObj.signs = response['signs'];
		let elm = dc.createElement("a");
		elm.href="#";
		elm.onclick = function(e){
			var e2=(!e)?window.event:e;
			e2.preventDefault();
			YKeyObj.callbackFn();
		};
		elm.appendChild(dc.createTextNode("Next") );
		infoBox.msgAppendDom(elm);
		YKeyObj.regSteps = 1;
	}
	function receiveRegistrationResponse(response){
		let message = "Unknown result";
		if(response.hasOwnProperty('message') )
			message = response['message'];
		else if(response.hasOwnProperty('error') )
			message = response['error'];

		if(YKeyObj.form){
			YKeyObj.form.parentNode.removeChild(YKeyObj.form);
			YKeyObj.form = YKeyObj.respInput = null;
		}

		if(response.hasOwnProperty('exception') ){
			infoBox.printMessage(message, false, true);
			infoBox.printMessage("(" + response['exception'] + ")", true);
		} else
			infoBox.printMessage(message, false);

		infoBox.showMessage();
		YKeyObj.regSteps = 0;
	}

	function checkingSteps(){
		if(!infoBox.msg){
			infoBox.print("Watch console to see all steps!");
		}

		if(YKeyObj.regSteps < 1){
			/* first step to check user by YubiKey; */
			infoBox.printMessage("You will be asked to verify yourself by hardware key.", false, true);

			elm = dc.createElement("img");
			elm.style.width="40%";
			elm.src = frm.url.substring(0, frm.url.lastIndexOf('/')) + "/static/images/yubiKey.jpg";
			infoBox.msgAppendDom(elm);

			infoBox.msgAppendDom(dc.createElement("br") );
			infoBox.showMessage();

			let values = ["key_check=1"];
			values.push("ssid=" + encodeURIComponent(frm.ssid));
			values.push("user_id=" + encodeURIComponent(frm.uid));

			sendFetch("", null, receiveChallengeAndKeys, values.join('&'), checkingKeysFailed);// sendXML();
		} else {
			if(YKeyObj.signs.length < 1){
				infoBox.printMessage("Registered keys for authentication are missing!", false);
				infoBox.showMessage();
				return;
			}

			infoBox.printMessage("Touch your key or move it closer to your cell phone.", false, true);
			infoBox.showMessage();
//debugger
console.time();
			YKeyObj.challengeId = YKeyObj.signs[0].challenge;
			u2f.sign(YKeyObj.appId, YKeyObj.challengeId, YKeyObj.signs, function(data){
				if(data.errorCode && data.errorCode != 0){
					let message = "Checking key/-s failed:";

					infoBox.printMessage(message, false, true);
					message = u2fErrorsToText(data) + " (#" + data.errorCode + ").";
					infoBox.printMessage(message, true);
					infoBox.showMessage();
					console.log("Error occured during checking:");
					console.log(data);
					console.log("YKey object: ", YKeyObj);
					setTimeout(() => {window.location = frm.url + "/logout/";}, 1500);
					return;
				}

				let values = ["finish_checking=1"], objResponse;
				values.push("ssid=" + encodeURIComponent(frm.ssid));
				values.push("user_id=" + encodeURIComponent(frm.uid));

				objResponse = {keyData: data, challenge: YKeyObj.challengeId, appId: YKeyObj.appId};
				values.push("keyResponse=" + encodeURIComponent(JSON.stringify(objResponse) ) );

				sendFetch("", null, verificationSuccess, values.join('&'), checkingKeysFailed);
console.timeEnd();
			});
			YKeyObj.regSteps = 0;
		}
	}

	function verificationSuccess(response){
		let message = "Verified";
		if(response.hasOwnProperty("message") )
			message = response['message'];

		infoBox.printMessage(message, false, true);

		var link = dc.createElement("a");
		link.href = frm.url;
		link.appendChild(dc.createTextNode("Click to go to account.") );
		infoBox.msgAppendDom(link);
		infoBox.showMessage();
	}

	function checkingKeysFailed(response){
		infoBox.printMessage("Checking keys failed!", false, true);

		if(response.hasOwnProperty("message") ){
			infoBox.printMessage(response['message'], true, true);
		}
		if(response.hasOwnProperty("error") ){
			infoBox.printMessage(response['error'], true, true);
		}
		infoBox.showMessage();

		setTimeout(()=>{window.location = frm.url;}, 2000);
	}

	/**
	 *	Creates text message based of the error code returned from FIDO key;
	 * @return  {string}: the message of that error code;
	 */
	function u2fErrorsToText(data){
		if( !data.hasOwnProperty("errorCode") || data.errorCode == 0)
			return "";
		let message = "";
		if(data.errorCode == 1)
			return "other error";
		else if(data.errorCode == 2)
			return "bad request, wrong App ID or parameters in wrong order";
		else if(data.errorCode == 3)
			return "configuration not supported";
		else if(data.errorCode == 4)
			return "presented device is not eligible for this request";
		else if(data.errorCode == 5)
			return "timeout error";
	}

	function displayResponse(msg){
		infoBox.printMessage(msg, false);
		infoBox.showMessage();
	}

	function clearDm(node){
		while(node.firstChild){
			node.removeChild(node.firstChild);
		}
	}

	/**
	 *	Creates hidden form before user will be assked to touch his FIDO key;
	 * but the form is not appended as a child yet;
	 */
	function createRegisterForm(){
		if(YKeyObj.form)
			YKeyObj.form.parentNode.removeChild(YKeyObj.form);

		YKeyObj.form = dc.createElement("form");
		YKeyObj.respInput = dc.createElement("input");
		YKeyObj.respInput.type = "text";
		YKeyObj.respInput.name = "key";
		YKeyObj.form.appendChild(YKeyObj.respInput);
		YKeyObj.form.style.position = "absolute";
		YKeyObj.form.style.visibility = "hidden";

		YKeyObj.form.onsubmit = function(e){
			let e2 = (!e)?window.event:e;
			e2.preventDefault();

			if(YKeyObj.respInput.value.length < 1)
				return;

			YKeyObj.keyId = YKeyObj.respInput.value.substring(0, 12);
			console.log(YKeyObj);/* console.dir(DOMnode); console.assert(); console.table(); input is empty when the key is touched; */
		}
	}

	function parentMessage(node){
		let cnt = node.parentNode;
		while(cnt && cnt.className !== "message")
			cnt = cnt.parentNode;
		return cnt;
	}

	function hideMessage(e){
		var e2=(!e)?window.event:e, elem, cnt;
		if(e2.target) elem=e2.target;
		else elem=e2.srcElement;
		cnt = parentMessage(elem);
		if(cnt) cnt.style.display="none";
		YKeyObj.regSteps = 0;
	}

	function checkInputs(e){
		var inputsCase = (frm.login && frm.pass)?true:false,
			e2=(!e)?window.event:e;
		if(frm.login && frm.login.value.length < 1)
			inputsCase= false;
		else if(frm.pass && frm.pass.value.length < 1)
			inputsCase=false;

		if(!inputsCase)
			e2.preventDefault();
		return inputsCase;
	}

	function addApiKey(inputName){
		if(frm.hidden){
			frm.fr.removeChild(frm.hidden);
		}
		frm.hidden = dc.createElement("input");
		frm.hidden.type = "hidden";
		frm.hidden.name = inputName;
		frm.hidden.value = 1;
		frm.fr.appendChild(frm.hidden);
	}
	function clickSign(e){
		frm.fr.action=frm.url;
		let inputsCase = checkInputs(e);
		addApiKey("signin");
		if(inputsCase)
			frm.fr.submit();
	}
	function clickRegister(e){
		frm.fr.action=frm.url;
		let inputsCase = checkInputs(e);
		addApiKey("register");
		if(inputsCase)
			frm.fr.submit();
	}
	function clickLogout(e){
		frm.fr.action=frm.url;
		var e2=(!e)?window.event:e;
		e2.preventDefault();
		addApiKey("logout");
		frm.fr.submit();
	}

	function sendXML(method, endpoint, callbackFn, content, callbackError){
		var xhr = new XMLHttpRequest();
		var methodCp="POST", endpointCp=frm.url, useContent = true, callbackErrorCp = (response)=>{console.log(response);};

		if(method==="GET" || method==="PATCH")
			methodCp = method;
		if(typeof endpoint === 'string' || endpoint instanceof String)
			endpointCp = frm.url + endpoint;

		if(content !== false && content !== null)
			useContent = true;

		if(typeof callbackError === 'function')
			callbackErrorCp = callbackError;

		xhr.onreadystatechange = function(){
			if(xhr.readyState == 4 && xhr.status >= 200 && xhr.status < 300){
				let result = xhr.responseText;
				try {
					result = JSON.parse(result);
				}catch(e){
					callbackErrorCp(result);
				}
				callbackFn(result);
			} else if(xhr.readyState == 4){
				callbackErrorCp("Bad status id: " + xhr.status);
			}
		};

		xhr.open(methodCp, endpointCp, true);
		xhr.setRequestHeader("Accept", "application/json");
		xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");/* problem in PHP when "application/json:; */
		xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
		if(useContent)
			xhr.send(content);
		else
			xhr.send();
	}

	/**
	 *	The same task like that one above but for variety (educational purpose);
	 */
	function sendFetch(method, endpoint, callbackFn, content, callbackError){
		var endpointCp=frm.url, callbackErrorCp = (response)=>{console.log(response);};
		let fetchProps = {method: "POST", headers: {}, body: null};

		fetchProps.headers = {"Accept":"application/json", "Content-Type":"application/x-www-form-urlencoded", "X-Requested-With": "XMLHttpRequest"};

		if(method==="GET" || method==="PATCH")
			fetchProps.method = method;
		if(typeof endpoint === 'string' || endpoint instanceof String)
			endpointCp = frm.url + endpoint;

		if(content !== false && content !== null)
			fetchProps.body = content;// after  JSON.stringify(...);

		if(typeof callbackError === 'function')
			callbackErrorCp = callbackError;

		// fetchProps.signal = new AbortController().signal;

		fetch(endpointCp, fetchProps).then(res => {
				console.log(res);
				if(!res.ok){
					//callbackErrorCp();
					throw Error("Error in fetch response!");
				}
				return res.json();
			}).then(data => {
				callbackFn(data);
			}).catch(function(err){
				// if(err.name === "AbortError") return;
				callbackErrorCp(err);// err.message
			});
	}

	function switchContentBlocks(e){
		var e2=(!e)?window.event:e;
		e2.preventDefault();
		if(menuContent.menu.length < 2 || menuContent.content.length < 2)
			return;

		let clickElem, i, ind;
		if(e2.target) clickElem = e2.target;
		else clickElem = e2.srcElement;
		ind = menuContent.menu.indexOf(clickElem);
		if(ind >= menuContent.content.length)
			ind = menuContent.content.length - 1;

		for(i = menuContent.content.length - 1; i >= 0; i--){
			menuContent.content[i].style.display = "none";
		}
		menuContent.content[ind].style.display = "";
	}

	function showLinks(e){
		var e2=(!e)?window.event:e;
		e2.preventDefault();
		if(sources.links.length < 1)
			return;

		var i, link, count = sources.links.length, dsc, elem;
		clearDm(infoBox.msg);
		for(i=0; i < count; i++){
			elem = sources.links[i];
			if(!elem.hasOwnProperty('url') )
				continue;
			dsc = elem['url'].trim();
			if(elem.hasOwnProperty("desc") )
				dsc = elem['desc'].trim();

			if(i>0) infoBox.msg.appendChild(dc.createElement("br") );

			link = dc.createElement("a");
			link.href=elem['url'].trim();
			link.appendChild(dc.createTextNode(dsc) );
			infoBox.msg.appendChild(link);

		}

		link = parentMessage(infoBox.msg);
		if(link) link.style.display = "";
	}

	function run(){
		if(!frm.fr)
			return;

		frm.fr.addEventListener("submit", checkInputs, false);
		if(frm.sgn) frm.sgn.addEventListener("click", clickSign, false);
		if(frm.reg) frm.reg.addEventListener("click", clickRegister, false);
		let i = frm.logout.length-1;
		for(; i >= 0; i--){
			frm.logout[i].addEventListener("click", clickLogout, false);
		}

		if(frm.keyRegBtn && frm.supports)
			frm.keyRegBtn.addEventListener("click", registerSteps, false);

		if(infoBox.msgClose)
			infoBox.msgClose.addEventListener("click", hideMessage, false);
		var elems = dc.getElementsByClassName("close");
		for(i=elems.length-1; i >= 0; i--){
			if(elems[i]===infoBox.msgClose)
				continue;
			elems[i].addEventListener("click", hideMessage, false);
		}

		if(menuContent.menu.length > 1 && menuContent.content.length > 1){
			for(i = menuContent.menu.length - 1; i >= 0; i--)
				menuContent.menu[i].addEventListener("click", switchContentBlocks, false);
		}
	}

	return {
		getInstance: function() {
			return new YKey();
		},
		ini:function(){
			var ins2=ins;
			if(ins==null) ins2=new YKey();
			let args = Array().slice.call(arguments), firstArg = args.length > 0 ? args[0] : {};
			setUp(firstArg);
			run();

			setUpJsonLinks();
			return ins2;
		}
	}
})();

Object.freeze(YKey);

