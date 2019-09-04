<?php
namespace Saitdarom\Mutmarket\Parser\Api;

class ServerApi
{
    private $domen='https://00.mutmarket.ru';

    public function get_tree($category_id){
        $url=$this->domen.'/parsers/server/getTree.php?id='.$category_id;
        return json_decode(file_get_contents($url));
    }

    public function get_category($category_id){
        $url=$this->domen.'/parsers/server/getCategory.php?id='.$category_id;
        return json_decode(file_get_contents($url));
    }

    public function search_category($name){
        $url=$this->domen.'/parsers/server/getCategory.php?name='.$name;
        return json_decode(file_get_contents($url));
    }

    public function get_product($product_id){
        $url=$this->domen.'/parsers/server/getProduct.php?id='.$product_id;
        return json_decode(file_get_contents($url));
    }
    public function search_product($name){
        $url=$this->domen.'/parsers/server/getProduct.php?name='.$name;
        return json_decode(file_get_contents($url));
    }

    public function get_products($category_id,$page){
        $url=$this->domen.'/parsers/server/getProducts.php?id='.$category_id.'&page='.$page;
        return json_decode(file_get_contents($url));
    }

    public function get_seo_title($host,$productName,$server_id){
        $productName=urlencode($productName);
        $url=$this->domen.'/parsers/server/getSeoTitle.php?host='.$host.'&server_id='.$server_id.'&product='.$productName;
        return json_decode(file_get_contents($url));
    }

    public function get_seo_desc($host,$productName,$server_id){
        $productName=urlencode($productName);
        $url=$this->domen.'/parsers/server/getSeoDesc.php?host='.$host.'&server_id='.$server_id.'&product='.$productName;
        return json_decode(file_get_contents($url));
    }

    public function get_seo_body($host,$productName,$server_id){
        $productName=urlencode($productName);
        $url=$this->domen.'/parsers/server/getSeoBody.php?host='.$host.'&server_id='.$server_id.'&product='.$productName;
        return json_decode(file_get_contents($url));
    }
}
