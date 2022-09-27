<?php

$content = [
  0=>
  "Logradouro:",
  1=>
  "RUA ARACI DE ALMEIDA",
  2=>
  "Número:",
  3=>
  "45",
  4=>
  "Complemento:",
  5=>
  "Bairro:",
  6=>
  "CJ VIVI XAVIER",
  7=>
  "Município:",
  8=>
  "LONDRINA",
  9=>
  "UF:",
  10=>
  "PR",
  11=>
  "CEP:",
  12=>
  "86.082-040",
  13=>
  "Telefone:",
  14=>"(43)99849-0012",
  15=> "E-mail:",
  16=> "R.O.ALIMENTOS@HOTMAIL.COM",
];


        $index = [
            'logradouro' => 'Logradouro:',
            'numero' => 'Número:',
            'complemento' => 'Complemento:',
            'bairro' => 'Bairro:',
            'municipio' => 'Município:',
            'uf' => 'UF:',
            'cep' => 'CEP:',
            'telefone' => 'Telefone:',
            'email' => 'E-mail:'
        ];


$retorno = [];

foreach ($index as  $campo) {
    $chave = array_search($campo, $content);

    $valor = $content[$chave+1];

    $ehCampo= (boolean)(array_search($valor, $index));

    if ($ehCampo) {
        $retorno[$campo] = '';
    } else {
        $retorno[$campo] = $valor;
    }

}

var_dump($retorno);


len($retorno);