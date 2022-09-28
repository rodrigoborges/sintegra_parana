<?php

require 'spider/sintegra/Parana.php';

use Spider\Sintegra\Parana;

echo "iniciando teste\n";

$spider = new Parana();

$retorno = $spider->pesquisar_cnpj("00080160000198");

var_dump($retorno);

echo "finalizando teste \n";
