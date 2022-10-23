<?php

//configurações
$emojiMentiu = '🤥';
$emojiOk = '🟢';
$colunas = 7;

//1 para explicitar tudo que está fazendo durante o crawler
//0 para não mostrar nada
define('DEBUG',1);

include('functions.php');

//checa se tem diretórios necessários
if(!is_dir('./cache')){
	mkdir("./cache", 0777);
}

//coisas relacionadas ao crawler
$host = 'www.aosfatos.org';
$urlStart = 'https://www.aosfatos.org/todas-as-declara%C3%A7%C3%B5es-de-bolsonaro/';
$urlSearchBase = 'https://www.aosfatos.org/todas-as-declara%C3%A7%C3%B5es-de-bolsonaro/?page=';
$countPageResults = 0;
$countCacheInvalidado = 0;
$arrOutput = array();

echo 'Baixando dados. Isso vai demorar alguns minutos...'."\n";

//pega página inicial para pegar cookies, etc
$contents1 = get_web_page($urlStart,$host);

//busca páginas até não encontrar mais fatos
for($page = 1; true ; $page++){

	//pega página de resultado
	$urlSearch = $urlSearchBase.$page;
	$contents2 = get_web_page($urlSearch,$host,$contents1['cookies'],$urlStart);
	//se não usou cache, espera um pouco para não sobrecarregar servidor
	if($contents2['cache'] == false){
		sleep(rand(1,2));
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

			//extrai data
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

			$errosBastaInvalidarCache = array();
			$errosBastaInvalidarCache[] = (false !== strstr($contents2['content'], '<p>Please try again in a few minutes.</p>'));
			$errosBastaInvalidarCache[] = (false !== strstr($contents2['content'], 'Could not resolve host: www.aosfatos.org'));
			$errosBastaInvalidarCache[] = (isset($contents2['errmsg']) && false !== strstr($contents2['errmsg'], 'Could not resolve host'));
			$errosBastaInvalidarCache[] = (isset($contents2['errmsg']) && false !== strstr($contents2['errmsg'], 'handshake'));
			$errosBastaInvalidarCache[] = (false !== strstr($contents2['content'], 'Web server is returning an unknown error</title>'));
			$errosBastaInvalidarCache[] = (false !== strstr($contents2['content'], '502: Bad gateway</title>'));
			
			//se não tratou desse erro
			if (false === in_array(true, $errosBastaInvalidarCache)) {
				echo 'Erro nao tratado:'."\n";
				var_dump(str_replace("\n",'',$contents2['content']));
				die();
			}

			limpaCache($urlSearch,$host,$urlStart);
			$countCacheInvalidado++;

		}
	}

	//se não achou, acaba aqui
	if(count($arrFactsParts) == 0){
		echo 'Não há mais facts para processar. Para o crawler.'."\n";
		break;
	}

}

echo 'Páginas processadas: '.$countPageResults."\n\n";

//caso tenha invalidado algum cache (as vezes uma página de erro do servidor, algo assim...)
if($countCacheInvalidado > 0){
	echo $countCacheInvalidado.' arquivos de cache foram invalidados.'."\n".'Rode novamente o script.'."\n";
	die();
}

//deixa em ordem cronológica crescente (do mais antigo para o mais recente)
$arrOutput = array_reverse($arrOutput);

//guarda menor e maior timestamps
$menor = 999999999999999;
$maior = 0;
foreach($arrOutput as $arrItem){
    if($arrItem['timestamp'] < $menor){
        $menor = $arrItem['timestamp'];
    }
    if($arrItem['timestamp'] > $maior){
        $maior = $arrItem['timestamp'];
    }
}

//popula um array de dias com contagem de quantas mentiras em cada dia
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
//corrige os índices
ksort($arrDias);

//total de mentiras
$mentirasTotal = array_sum($arrDias);

//output inicial
echo "De:  ".date('d/m/Y',$menor)."\n";
echo "Até: ".date('d/m/Y',$maior)."\n\n";
echo $mentirasTotal." mentiras ou distorções.\n\n";
echo "legenda:\n";
echo 'Dias em que Bolsonaro mentiu ou distorceu informação publicamente: '.$emojiMentiu."\n";
echo 'Dias em que não fez isto: '.$emojiOk."\n\n";

//output dos emojis
$numeroDiaDaSemanaQueComeca = intval(date('N', $menor));

for($c=0;$c<$numeroDiaDaSemanaQueComeca;$c++){
	echo '  ';
}

foreach($arrDias as $index => $quantas){
	$index += $numeroDiaDaSemanaQueComeca;
    if($quantas == 0){
        echo $emojiOk;
    }else{
        echo $emojiMentiu;
    }
	if($index % $colunas == $colunas - 1){
		echo "\n";
	}

}

//output da fonte
echo "\n\nDados: https://www.aosfatos.org/todas-as-declara%C3%A7%C3%B5es-de-bolsonaro/\n\n";