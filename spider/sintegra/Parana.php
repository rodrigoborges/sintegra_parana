<?php

namespace Spider\Sintegra;

use Exception;
use DOMDocument;
use DomXPath;

class Parana {

    private const PATH_CAPTCHA = "spider/sintegra/captcha/captcha.jpeg";

    public function __construct()
    {

    }

    private function validar_cnpj($cnpj)
    {
        return preg_match("/^\d{2}\.?\d{3}\.?\d{3}\/?\d{4}\-?\d{2}$/", $cnpj);
    }

    private function request_captcha($cookie)
    {
        $curl = curl_init();

        $fp = fopen($this::PATH_CAPTCHA, "wb");

        $options = array(CURLOPT_FILE => $fp,
                        CURLOPT_HEADER => 0,
                        CURLOPT_FOLLOWLOCATION => 1,
                        CURLOPT_URL => "http://www.sintegra.fazenda.pr.gov.br/sintegra/captcha",
                        CURLOPT_HTTPHEADER => ["Host: www.sintegra.fazenda.pr.gov.br",
                                                "Cookie: CAKEPHP={$cookie['CAKEPHP']}; path=/sintegra",
                                                "Connection: keep-alive",
                                                "Upgrade-Insecure-Requests: 1",
                                                "Content-Type: image/jpeg",
                                                "Accept: */*"],
                        CURLOPT_TIMEOUT => 60);

        curl_setopt_array($curl, $options);

        curl_exec($curl);

        curl_close($curl);

        fclose($fp);

        return $this::PATH_CAPTCHA;
    }

    private function request_consultar_empresa($cnpj, $captcha, $cookie)
    {

        $form = [
            "_method" => "POST",
            "data[Sintegra1][CodImage]" => $captcha,
            "data[Sintegra1][Cnpj]" => $cnpj,
            "empresa" => "Consultar Empresa",
            "data[Sintegra1][Cadicms]" => "",
            "data[Sintegra1][CadicmsProdutor]" => "",
            "data[Sintegra1][CnpjCpfProdutor]" => "",
        ];

        $payload = http_build_query($form);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "http://www.sintegra.fazenda.pr.gov.br/sintegra/");
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 0);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "Content-Type: application/x-www-form-urlencoded",
            "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language: pt-BR,pt;q=0.9",
            "Host: www.sintegra.fazenda.pr.gov.br",
            "Origin: http://www.sintegra.fazenda.pr.gov.br",
            "Connection: keep-alive",
            "Upgrade-Insecure-Requests: 1",
            "Referer: http://www.sintegra.fazenda.pr.gov.br/sintegra/sintegra1",
            "Content-Length: " . strlen($payload),
            "Cookie: CAKEPHP={$cookie["CAKEPHP"]}; path=/sintegra"
        ]);

        $response = curl_exec($curl);

        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response,  $match_found);

        #TODO: tratar valor não encotrado.
        parse_str($match_found[1][1], $cookie);

        return $this->obter_valores_response($response, $cookie);
    }

    private function obter_valores_response($response, $cookie)
    {
        $dom = new DOMDocument;
        $dom->loadHtml($response);

        $valores_identificacao = $this->obter_valores_identificacao($dom);
        $valores_endereco = $this->obter_valores_endereco($dom);
        $valores_complementares = $this->obter_valores_complementares($dom);

        $retorno[] = array_merge($valores_identificacao, $valores_endereco, $valores_complementares);

        $retorno[] = $this->obter_valores_outras_inscricoes_estadual($dom, $cookie);

        return $retorno;
    }

    private function obter_valores_outras_inscricoes_estadual($dom, $cookie)
    {
        $xpath = new DomXPath($dom);

        $token_sintegra = "";

        $sintegra_campo_anterior = $xpath->query('//*[@id="Sintegra1CampoAnterior"]/attribute::value');

        if (sizeof($sintegra_campo_anterior)) {
            $token_sintegra = $sintegra_campo_anterior->item(0)->nodeValue;
        }

        if (!$token_sintegra) return [];

        $form = [
            "_method" => "POST",
            "data[Sintegra1][campoAnterior]" => $token_sintegra,
            "consultar" => ""
        ];

        $payload = http_build_query($form);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "http://www.sintegra.fazenda.pr.gov.br/sintegra/sintegra1/consultar");
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 0);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "Content-Type: application/x-www-form-urlencoded",
            "Cookie: CAKEPHP={$cookie["CAKEPHP"]}; path=/sintegra; Domain=www.sintegra.fazenda.pr.gov.br"
        ]);

        $response = curl_exec($curl);

        return $this->obter_valores_response($response, $cookie);
    }

    private function obter_valores_identificacao($dom)
    {
        $xpath = new DomXPath($dom);

        $index = [
            'cnpj' => 'CNPJ:',
            'ie' => 'Inscrição Estadual:',
            'razao_social' => 'Nome Empresarial:'
        ];

        $content = [];
        $retorno = [];

        foreach ($xpath->query("//*[@id='Sintegra1ConsultarForm']/table[2]") as $tabela)
        {
            foreach ($tabela->childNodes as $tbody)
            {
                foreach ($tbody->childNodes as $key=>$tr) {

                    foreach ($tr->childNodes as $td) {

                        $content[] = $td->textContent;

                    }
                }
            }
        }

        //criando o retorno com os valores na posição correta
        $retorno = $this->ajustar_array_retorno($index, $content);

        return $retorno;
    }

    private function obter_valores_endereco($dom)
    {

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

        $xpath = new DomXPath($dom);

        $retorno = [];
        $content = [];

        foreach ($xpath->query ("//*[@id='Sintegra1ConsultarForm']/table[4]") as $tabela)
        {
            foreach ($tabela->childNodes as $tbody)
            {
                foreach ($tbody->childNodes as $tr) {

                    foreach ($tr->childNodes as $td) {

                        $content[] = $td->textContent;
                    }
                }
            }
        }

        //criando o retorno com os valores na posição correta
        $retorno = $this->ajustar_array_retorno($index, $content);

        return $retorno;
    }

    private function ajustar_array_retorno($index, $content)
    {

        $content = (array)$content;

        $retorno = [];

        foreach ($index as  $tag=>$campo) {
            $chave = (int)array_search($campo, $content);

            $chave_proximo = $chave + 1;

            $valor = "";

            if ($chave_proximo <= sizeof($content)) {
                $valor = $content[$chave_proximo];
            }

            $ehCampo= (boolean)(array_search($valor, $index));

            if ($ehCampo) {
                $retorno[$tag] = '';
            } else {
                $retorno[$tag] = $valor;
            }

        }
        return $retorno;
    }

    private function obter_valores_complementares($dom)
    {
        $xpath = new DomXPath($dom);

        $index = [
            'atividade_principal' => 'Atividade Econômica Principal:',
            'data_inicio' => 'Início das Atividades:',
            'situacao_atual' => 'Situação Atual:'
        ];

        $content = [];
        $retorno = [];

        foreach ($xpath->query ("//*[@id='Sintegra1ConsultarForm']/table[6]") as $tabela)
        {
            foreach ($tabela->childNodes as $tbody)
            {
                foreach ($tbody->childNodes as $tr) {

                    foreach ($tr->childNodes as $td) {

                        $content[] = $td->textContent;
                    }
                }
            }
        }

        //criando o retorno com os valores na posição correta
        $retorno = $this->ajustar_array_retorno($index, $content);

        $retorno['hora'] = date("H:i:s");
        $retorno['data'] = date("d/m/Y");

        return $retorno;
    }

    private function request_cookie()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "http://www.sintegra.fazenda.pr.gov.br/sintegra/");
        curl_setopt($curl, CURLOPT_TIMEOUT, 0);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($curl);

        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response,  $match_found);

        #TODO: tratar valor não encotrado.
        parse_str($match_found[1][0], $cookie);

        return $cookie;
    }

    public function pesquisar_cnpj(String $cnpj)
    {
        if (!$this->validar_cnpj($cnpj)) throw new Exception('CNPJ informado é inválido.');

        $cookie = $this->request_cookie();

        $imagem_captcha = $this->request_captcha($cookie);

        $processId = shell_exec("open {$imagem_captcha}");

        $captcha = readline("Digite o captcha da imagem aberta: ");

        #TODO: validar captcha

        $retorno = $this->request_consultar_empresa($cnpj, $captcha, $cookie);

        var_dump($retorno);


    }

}