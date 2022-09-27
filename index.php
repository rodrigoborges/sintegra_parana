<?php

require 'spider/sintegra/Parana.php';

use Spider\Sintegra\Parana;

echo "iniciando teste\n";

$spider = new Parana();

$spider->pesquisar_cnpj("00.063.744/0001-55");

echo "finalizando teste \n";
