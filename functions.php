<?php

function isWritable($cacheDir){
	$filenameTest = 'test-'.rand(1,99999999).'.txt';
	@file_put_contents($cacheDir.'/'.$filenameTest,rand(1,999999));
	if(file_exists($cacheDir.'/'.$filenameTest)){
		@unlink($cacheDir.'/'.$filenameTest);
		return true;
	}
	return false;
}

function strDataToTimestamp($strData){
	$arrParts = explode('.',$strData);
	$arrParts[1] = strMesToNumberMes($arrParts[1]);
	if($arrParts[1] === false){
		return false;
	}
	return strtotime(implode('-',array_reverse($arrParts)));
}

function strMesToNumberMes($strMes){
	switch ($strMes) {
		case 'jan':
			return 1;
		case 'fev':
			return 2;
		case 'mar':
			return 3;
		case 'abr':
			return 4;
		case 'mai':
			return 5;
		case 'jun':
			return 6;
		case 'jul':
			return 7;
		case 'ago':
			return 8;
		case 'set':
			return 9;
		case 'out':
			return 10;
		case 'nov':
			return 11;
		case 'dez':
			return 12;

	}
	return false;
}

function limpaCache($url, $host, $referer = ''){
	$cacheFilename = './cache/'.base64_encode(md5($url.$host.$referer)).'.txt';
	if(DEBUG > 0){
		echo 'Excluindo arquivo de cache: '.$cacheFilename."\n";
		unlink($cacheFilename);
	}
}

function get_web_page( $url, $host, $cookiesIn = '', $referer = '' ){

	$cacheFilename = './cache/'.base64_encode(md5($url.$host.$referer)).'.txt';

	if(DEBUG > 0) echo 'Crawling: '.$url.' | ';
	if(DEBUG > 0) echo 'Cache file: '.$cacheFilename.' ... ';

	if(file_exists($cacheFilename)){
		if(DEBUG > 0) echo 'Found in cache.'."\n";
		$arrRetornar = unserialize(file_get_contents($cacheFilename));
		$arrRetornar['cache'] = true;
		$arrRetornar['cacheFilename'] = $cacheFilename;
		return $arrRetornar;
	}

	if($referer != ''){
		$referer = 'Referer: '.$referer;
	}
	$headers = explode("\n",
'Host: '.$host.'
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36
Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8
Accept-Language: en-US,en;q=0.5
Accept-Encoding: gzip, deflate
'.$referer.'
Connection: keep-alive
Upgrade-Insecure-Requests: 1
Pragma: no-cache
Cache-Control: no-cache');

        $options = array(
		CURLOPT_RETURNTRANSFER => true,     // return web page
		CURLOPT_HEADER         => true,     //return headers in addition to content
		CURLOPT_FOLLOWLOCATION => true,     // follow redirects
		CURLOPT_ENCODING       => "utf-8",       // handle all encodings
		CURLOPT_AUTOREFERER    => true,     // set referer on redirect
		CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
		CURLOPT_TIMEOUT        => 120,      // timeout on response
		CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
		CURLINFO_HEADER_OUT    => true,
		CURLOPT_SSL_VERIFYPEER => true,     // Validate SSL Certificates
		CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
		CURLOPT_COOKIE         => $cookiesIn,
		CURLOPT_HTTPHEADER     => $headers,
		//CURLOPT_USERAGENT      => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:61.0) Gecko/20100101 Firefox/61.0'
		CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36'
        );

        $ch      = curl_init( $url );
        curl_setopt_array( $ch, $options );
        $rough_content = curl_exec( $ch );
        $err     = curl_errno( $ch );
        $errmsg  = curl_error( $ch );
        $header  = curl_getinfo( $ch );
        curl_close( $ch );

        $header_content = substr($rough_content, 0, $header['header_size']);
        $body_content = trim(str_replace($header_content, '', $rough_content));
        $pattern = "#Set-Cookie:\\s+(?<cookie>[^=]+=[^;]+)#m";
        preg_match_all($pattern, $header_content, $matches);
        $cookiesOut = implode("; ", $matches['cookie']);

        $header['errno']   = $err;
        $header['errmsg']  = $errmsg;
        $header['headers']  = $header_content;
        $header['content'] = $body_content;
        $header['cookies'] = $cookiesOut;

	if(file_put_contents($cacheFilename,serialize($header))){
		if(DEBUG > 0) echo 'Ok!'."\n";
		$header['cache'] = false;
		return $header;
	}else{
		echo 'Nao foi possivel salvar cache. ('.$cacheFilename.')'."\n";
		die();
		return false;
	}
}

function explodeByArray($arrDelimiters,$string){
	return explode($arrDelimiters[0], str_replace($arrDelimiters, $arrDelimiters[0], $string) );
}

