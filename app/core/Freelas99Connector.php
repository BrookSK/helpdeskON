<?php

/**
 * Conector da fonte 99Freelas (única fonte implementada nesta entrega).
 * Contrato: source, buildUrl(term, page), collect({term, page}).
 * Não conhece banco, UI nem scheduler.
 */
class Freelas99Connector
{
    const SOURCE = 'freelas99';
    const BASE = 'https://www.99freelas.com.br/projects';

    private $http;

    public function __construct(HttpCollector $http = null)
    {
        $this->http = $http ?: new HttpCollector();
    }

    public function source()
    {
        return self::SOURCE;
    }

    /**
     * Monta a URL de busca. term vazio => listagem geral. page>1 => &page=N.
     */
    public function buildUrl($term, $page = 1)
    {
        $params = [];
        if (!empty($term)) $params['q'] = $term;
        if ($page > 1) $params['page'] = (int) $page;
        $qs = http_build_query($params);
        return self::BASE . ($qs ? '?' . $qs : '');
    }

    /**
     * Coleta uma página. Retorna:
     *   ['raw'=>RawProject[], 'cardsDetected'=>int, 'meta'=>httpResult]
     * @throws \RuntimeException em erro HTTP (status != 200)
     */
    public function collect($term, $page = 1)
    {
        $url = $this->buildUrl($term, $page);
        $res = $this->http->get($url);

        if ($res['status'] !== 200) {
            throw new \RuntimeException('HTTP ' . $res['status'] . ' em ' . $url . ($res['error'] ? ' — ' . $res['error'] : ''));
        }

        $cardsDetected = Freelas99Parser::countProjectLinks($res['body']);
        $raw = Freelas99Parser::parse($res['body']);

        return ['raw' => $raw, 'cardsDetected' => $cardsDetected, 'meta' => $res, 'url' => $url];
    }
}
