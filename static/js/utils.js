

export function getSessionIdFromInput(){
	let inputs, i;
	inputs = document.getElementsByTagName("input");

	for(i = inputs.length - 1; i >= 0; i--){
		if(inputs[i].type === "hidden" && inputs[i].name === "ssid")
			return inputs[i].value;
	}

	return "";
}

export function clearTable(tableNode){
	if(!tableNode.rows)
		return;

	let i, count = tableNode.rows.length;
	for(i = 0; i < count; i++)
		tableNode.deleteRow(-1);
}

export function insertCol(tableRow, colTitle, colValue){
	let col = tableRow.insertCell();
	col.style.padding = "0.5em";
	col.appendChild(document.createTextNode(colTitle) );

	col = tableRow.insertCell();
	col.style.padding = "0.5em";
	if(tableRow.rowIndex > 0)
		col.style.borderTop = "1px solid black";
	if(colValue)
		col.appendChild(document.createTextNode(colValue) );

	return col;
}

/**
 *	Split string info many separated by whitespace
 * or by defined length if no whitespace is found;
 *
 * @param str  {string}: string to break;
 * @param defaultLength  (number}: default length of returned string in array element;
 * @return  {array}: list of strings;
 */
export function splitString(str, defaultLength){
	let stringRows;
	stringRows = str.split(/(\s+)/).filter(elm => elm.trim().length > 0);
	if(stringRows.length < 2 && str.length > defaultLength){
		stringRows = str.match(new RegExp(".{1," + defaultLength + "}", 'g') );
	}
	return stringRows;
}

/**
 *	Object of ajax connection;
 */
const ajax = {
	url: "",

	setUrl: function(){
		let hrf = window.location.href.split('.php');
		if(hrf.length > 0){
			this.url = hrf[0];
			if(hrf.length > 1) this.url = hrf[0] + ".php";
		}

	},
	setDefaults: function(method, endpoint, callbackFn, content, callbackError){
		let result = {method: "POST", endpoint: "", callbackFn: null, content: "", useContent: false, callbackError: null};

		if(method==="GET" || method==="PATCH")
			result.method = method;
		if(typeof endpoint === 'string' || endpoint instanceof String)
			result.endpoint = this.url + endpoint;

		if(content !== false && content !== null){
			result.content = content;
			result.useContent = true;
		}

		if(typeof callbackError === 'function')
			result.callbackError = callbackError;
		else
			result.callbackError = (response)=>{console.log(response);};
		return result;
	},

	xhr: (method, endpoint, callbackFn, content, callbackError) => {
		var xhr = new XMLHttpRequest(), ajaxSets = ajax.setDefaults(method, endpoint, callbackFn, content, callbackError);

		xhr.onreadystatechange = function(){
			if(xhr.readyState == 4 && xhr.status >= 200 && xhr.status < 300){
				let result = xhr.responseText;
				try {
					result = JSON.parse(result);
				}catch(e){
					ajaxSets.callbackError(result);
				}
				callbackFn(result);
			} else if(xhr.readyState == 4){
				ajaxSets.callbackError("Bad status id: " + xhr.status);
			}
		};

		xhr.open(ajaxSets.method, ajaxSets.endpoint, true);
		xhr.setRequestHeader("Accept", "application/json");
		xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");/* problem in PHP when "application/json:; */
		xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
		if(ajaxSets.useContent)
			xhr.send(ajaxSets.content);
		else
			xhr.send();
	},
	fetch: (method, endpoint, callbackFn, content, callbackError) => {
		var ajaxSets = ajax.setDefaults(method, endpoint, callbackFn, content, callbackError);
		let fetchProps = {method: ajaxSets.method, headers: {}, body: null};

		fetchProps.headers = {"Accept":"application/json", "Content-Type":"application/x-www-form-urlencoded", "X-Requested-With": "XMLHttpRequest"};

		if(ajaxSets.useContent)
			fetchProps.body = ajaxSets.content;
		// fetchProps.signal = new AbortController().signal;

		fetch(ajaxSets.endpoint, fetchProps).then(res => {
				if(!res.ok){
					//callbackErrorCp();
					throw Error("Error in fetch response!");
				}
				return res.json();
			}).then(data => {
				callbackFn(data);
			}).catch(function(err){
				// if(err.name === "AbortError") return;
				ajaxSets.callbackError(err);// err.message
			});
	}
};

export default ajax;

