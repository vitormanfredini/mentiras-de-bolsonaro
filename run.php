<?php

//configurações
$emojiMentiu = '🤥';
$emojiOk = '✅';
$colunas = 10;

define('DEBUG',1);
include('functions.php');

//checa se tem diretórios necessários
if(!is_dir('./cache')){
	mkdir("./cache", 0777);
}

if(!isWritable('./cache')){
	echo 'Pasta ./cache nao tem permissao de escrita.'."\n";
	die();
}

//CRAWLER PART
//CRAWLER PART
//CRAWLER PART

$host = 'www.aosfatos.org';
$page = 1;
$strPagePlaceholder = '||PAGE||';

$urlStart = 'https://www.aosfatos.org/todas-as-declara%C3%A7%C3%B5es-de-bolsonaro/';
$urlSearchBase = 'https://www.aosfatos.org/todas-as-declara%C3%A7%C3%B5es-de-bolsonaro/?page=||PAGE||';

$contents1 = get_web_page($urlStart,$host);

$countPageResults = 0;
$countCacheInvalidado = 0;

$arrOutput = array();

$countMatches = 0;

while(true){

	//get search results
	$urlSearch = str_replace('||PAGE||',$page,$urlSearchBase);
	$contents2 = get_web_page($urlSearch,$host,$contents1['cookies'],$urlStart);
	if($contents2['cache'] == false){
		sleep(rand(1,3));
	}
	$countPageResults++;

	$conteudo = $contents2['content'];
	$arrFactsParts = explode('<p class="w600">',$conteudo);
	if(isset($arrFactsParts[0])){
		unset($arrFactsParts[0]);
	}

	foreach($arrFactsParts as $strFactsPart){
		try {
			$arrItem = array();

			//titulo
			$arrParts = explodeByArray(array('<h4>','</h4>'),$strFactsPart);
			if(!isset($arrParts[1])){
				throw new Exception('Nao encontrou titulo.');
			}
			$arrItem['title'] = trim(strip_tags($arrParts[1]));

			//texto corrido
			// $arrParts = explodeByArray(array('neuton fs20 w300">','</p>'),$strFactsPart);
			// if(!isset($arrParts[1])){
			// 	throw new Exception('Nao encontrou texto corrido.');
			// }
			// $textCorrido = trim(strip_tags($arrParts[1]));
			// $textCorrido = mb_strtolower($textCorrido);

			//data
			$arrParts = explode('<a',$strFactsPart);
			if(!isset($arrParts[1])){
				throw new Exception('Nao encontrou data.');
			}
			$data = trim(strip_tags($arrParts[0]));
			$arrItem['timestamp'] = strDataToTimestamp($data);
			
			//guarda no array
			$arrOutput[] = $arrItem;

		} catch (Exception $e){

			//trata erros
			echo 'Erro: '.$e->getMessage()."\n";

			if(false !== strstr($contents2['content'], '<p>Please try again in a few minutes.</p>')){
				limpaCache($urlSearch,$host,$urlStart);
				$countCacheInvalidado++;
			}elseif(false !== strstr($contents2['content'], 'Could not resolve host: www.aosfatos.org')){
				limpaCache($urlSearch,$host,$urlStart);
				$countCacheInvalidado++;
			}elseif(isset($contents2['errmsg']) && false !== strstr($contents2['errmsg'], 'Could not resolve host')){
				limpaCache($urlSearch,$host,$urlStart);
				$countCacheInvalidado++;
			}elseif(isset($contents2['errmsg']) && false !== strstr($contents2['errmsg'], 'handshake')){
				limpaCache($urlSearch,$host,$urlStart);
				$countCacheInvalidado++;
			}elseif(false !== strstr($contents2['content'], 'Web server is returning an unknown error</title>')){
				echo 'Erro tratado: bad gateway cloudflare'."\n";
				limpaCache($urlSearch,$host,$urlStart);
				$countCacheInvalidado++;
			}elseif(false !== strstr($contents2['content'], '502: Bad gateway</title>')){
				echo 'Erro tratado: bad gateway'."\n";
				limpaCache($urlSearch,$host,$urlStart);
				$countCacheInvalidado++;
			}else{
				echo 'Erro nao tratado:'."\n";
				var_dump(str_replace("\n",'',$contents2['content']));
				die();
			}

		}
	}

	if(count($arrFactsParts) == 0){
		echo 'Não encontrou mais facts. Para o crawler.'."\n";
		break;
	}

	$page++;
}

echo 'Páginas processadas: '.$countPageResults."\n";

if($countCacheInvalidado > 0){
	echo $countCacheInvalidado.' arquivos de cache foram invalidados.'."\n".'Rode novamente o script.'."\n";
	die();
}
echo "\n";
echo '---------------------------------------';
echo "\n";

//OUTPUT PART
//OUTPUT PART
//OUTPUT PART

$arrOutput = array_reverse($arrOutput);

$menor = 999999999999999999;
$maior = 0;
foreach($arrOutput as $arrItem){
    if($arrItem['timestamp'] < $menor){
        $menor = $arrItem['timestamp'];
    }
    if($arrItem['timestamp'] > $maior){
        $maior = $arrItem['timestamp'];
    }
}

$umDiaEmSegundos = 24 * 60 * 60;
$maiorDia = 0;
$arrDias = array();
$max = 0;
foreach($arrOutput as $arrItem){
    $dia = floor(($arrItem['timestamp'] - $menor) / $umDiaEmSegundos);
    if($dia > $maiorDia){
        $maiorDia = $dia;
    }
    if(!isset($arrDias[$dia])){
		$arrDias[$dia] = 0;
    }
	$arrDias[$dia]++;
    if($arrDias[$dia] > $max){
        $max = $arrDias[$dia];
    }
}

for($c=0;$c<$maiorDia;$c++){
    if(false === isset($arrDias[$c])){
        $arrDias[$c] = 0;
    }
}

ksort($arrDias);

echo "\n";

echo "De:  ".date('d/m/Y',$menor);
echo "\n";
echo "Até: ".date('d/m/Y',$maior);

echo "\n";
echo "\n";

echo 'Dia em que Bolsonaro mentiu: '.$emojiMentiu;
echo "\n";
echo 'Dia em que não mentiu: '.$emojiOk;

echo "\n";
echo "\n";

foreach($arrDias as $index => $quantas){
    // $porc = $quantas / $max;
    // $qualChar = ceil($porc * (strlen($strChars)-1));
    // echo substr($strChars,$qualChar,1);
    if($quantas == 0){
        echo $emojiOk;
    }else{
        echo $emojiMentiu;
    }
    if($index % $colunas == $colunas-1){
        echo "\n";
    }
}

echo "\n";
echo "\n";

echo 'Dados: https://www.aosfatos.org/todas-as-declara%C3%A7%C3%B5es-de-bolsonaro/';
echo "\n";
echo "\n";
