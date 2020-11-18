<?php

namespace Saitdarom\Mutmarket\Parser\Api;

class ServerApi
{
    private $domen = 'https://00.mutmarket.ru';

    public function get_tree($category_id)
    {
        $url = $this->domen . '/parsers/server/getTree.php?id=' . $category_id;
        $temp = json_decode(file_get_contents($url));
        return $temp->return;
    }

    public function get_category($category_id)
    {
        $url = $this->domen . '/parsers/server/getCategory.php?id=' . $category_id;
        $temp = json_decode(file_get_contents($url));
        return $temp->return;
    }

    public function search_category($name)
    {
        $url = $this->domen . '/parsers/server/getCategory.php?name=' . $name;
        $temp = json_decode(file_get_contents($url));
        return $temp->return;
    }

    public function get_product($product_id)
    {
        $url = $this->domen . '/parsers/server/getProduct.php?id=' . $product_id;
        $temp = json_decode(file_get_contents($url));
        return $temp->return;
    }

    public function search_product($name)
    {
        $url = $this->domen . '/parsers/server/getProduct.php?name=' . $name;
        $temp = json_decode(file_get_contents($url));
        return $temp->return;
    }

    public function get_products($category_id, $page)
    {
        $url = $this->domen . '/parsers/server/getProducts.php?id=' . $category_id . '&page=' . $page;
        $temp = json_decode(file_get_contents($url));
        return $temp->return;
    }

    public function get_seo_title($host, $productName, $server_id,$category='')
    {
        $url = $this->domen . '/parsers/server/getSeoTitle.php';
        return json_decode($this->post($url, ['host' => $host, 'server_id' => $server_id, 'product' => $productName,'category'=>$category]));
    }

    public function get_seo_desc($host, $productName, $server_id,$category='')
    {
        $url = $this->domen . '/parsers/server/getSeoDesc.php';
        return json_decode($this->post($url, ['host' => $host, 'server_id' => $server_id, 'product' => $productName,'category'=>$category]));
    }

    public function get_seo_body($host, $productName, $server_id, $arr = [])
    {
        $url = $this->domen . '/parsers/server/getSeoBody.php';
        return json_decode($this->post($url, array_merge(['host' => $host, 'server_id' => $server_id, 'product' => $productName], $arr)));
    }

    public function post($url, $postdata)
    {
        $postdata = http_build_query($postdata);

        $opts = [
            'http' => [
                'method' => 'POST',
//                              'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => $postdata
            ],
            'ssl' => [
                "verify_peer" => false,
                "verify_peer_name" => false,
            ]
        ];

        $context = stream_context_create($opts);

        return file_get_contents($url, false, $context);
    }
}
