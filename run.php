<?php

//configurações
$emojiMentiu = '🤥';
$emojiOk = '✅';
$colunas = 10;

//1 para explicitar tudo que está fazendo durante o crawler
//0 para não mostrar nada
define('DEBUG',0);

include('functions.php');

//checa se tem diretórios necessários
if(!is_dir('./cache')){
	mkdir("./cache", 0777);
}

//tem permissão de escrita?
// if(!isWritable('./cache')){
// 	echo 'Pasta ./cache nao tem permissao de escrita.'."\n";
// 	die();
// }

//coisas relacionadas ao crawler
$host = 'www.aosfatos.org';
$page = 1;
$strPagePlaceholder = '||PAGE||';
$urlStart = 'https://www.aosfatos.org/todas-as-declara%C3%A7%C3%B5es-de-bolsonaro/';
$urlSearchBase = 'https://www.aosfatos.org/todas-as-declara%C3%A7%C3%B5es-de-bolsonaro/?page=||PAGE||';
$countPageResults = 0;
$countCacheInvalidado = 0;
$arrOutput = array();

//Baixando dados
echo 'Baixando dados. Isso vai demorar alguns minutos...'."\n";

//pega página inicial para pegar cookies, etc
$contents1 = get_web_page($urlStart,$host);

//busca páginas até não encontrar mais fatos
while(true){

	//pega página de resultado
	$urlSearch = str_replace('||PAGE||',$page,$urlSearchBase);
	$contents2 = get_web_page($urlSearch,$host,$contents1['cookies'],$urlStart);
	//se não usou cache, espera um pouco para não sobrecarregar servidor
	if($contents2['cache'] == false){
		sleep(rand(1,3));
	}
	$countPageResults++;

	//cria array com html (ainda sujo) de cada fato
	$conteudo = $contents2['content'];
	$arrFactsParts = explode('<p class="w600">',$conteudo);
	if(isset($arrFactsParts[0])){
		unset($arrFactsParts[0]);
	}

	//para cada html de fato
	foreach($arrFactsParts as $strFactsPart){
		try {
			$arrItem = array();

			//extrai titulo
			$arrParts = explodeByArray(array('<h4>','</h4>'),$strFactsPart);
			if(!isset($arrParts[1])){
				throw new Exception('Nao encontrou titulo.');
			}
			$arrItem['title'] = trim(strip_tags($arrParts[1]));

			//extrai texto corrido
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

			//trata possíveis erros
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

	//se não achou, acaba aqui
	if(count($arrFactsParts) == 0){
		echo 'Não há mais facts para processar. Para o crawler.'."\n";
		break;
	}

	$page++;
}

echo 'Páginas processadas: '.$countPageResults."\n";

//caso tenha invalidado algum cache (as vezes uma página de erro do servidor, algo assim...)
if($countCacheInvalidado > 0){
	//precisa rodar de novo
	echo $countCacheInvalidado.' arquivos de cache foram invalidados.'."\n".'Rode novamente o script.'."\n";
	die();
}
echo "\n";
echo '---------------------------------------';
echo "\n";

//saída dos emojis

//deixa em ordem cronológica crescente (do mais antigo para o mais recente)
$arrOutput = array_reverse($arrOutput);

//guarda menor e maior timestamps
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

//popula um array de dias com contagem de quantas mentiras naquele dia
$umDiaEmSegundos = 24 * 60 * 60;
$maiorDia = 0;
$arrDias = array();
foreach($arrOutput as $arrItem){
    $diaIndex = floor(($arrItem['timestamp'] - $menor) / $umDiaEmSegundos);
    if($diaIndex > $maiorDia){
        $maiorDia = $diaIndex;
    }
    if(!isset($arrDias[$diaIndex])){
		$arrDias[$diaIndex] = 0;
    }
	$arrDias[$diaIndex]++;
}

//completa o array com zero para os dias em que não teve mentira
for($c=0;$c<$maiorDia;$c++){
    if(false === isset($arrDias[$c])){
        $arrDias[$c] = 0;
    }
}
//acerta os índices
ksort($arrDias);

//total de mentiras
$mentirasTotal = 0;
foreach($arrDias as $count){
	$mentirasTotal += $count;
}

//output inicial
echo "\n";
echo "De:  ".date('d/m/Y',$menor)."\n";
echo "Até: ".date('d/m/Y',$maior)."\n";
echo "\n";
echo $mentirasTotal." mentiras ou distorções.";
echo "\n";
echo "\n";
echo "legenda:\n";
echo 'Bolsonaro mentiu: '.$emojiMentiu."\n";
echo 'não mentiu: '.$emojiOk."\n";

//output dos emojis
foreach($arrDias as $index => $quantas){
    if($quantas == 0){
        echo $emojiOk;
    }else{
        echo $emojiMentiu;
    }
    if($index % $colunas == $colunas-1){
        echo "\n";
    }
}

//output da fonte
echo "\n";
echo "\n";
echo 'Dados: https://www.aosfatos.org/todas-as-declara%C3%A7%C3%B5es-de-bolsonaro/';
echo "\n";
echo "\n";