<?php

namespace Saitdarom\Mutmarket\Parser;

require_once(__DIR__ . '/../../../../api/Simpla.php');

use \Saitdarom\Mutmarket\Parser\Api\ServerApi;
use \Saitdarom\Mutmarket\Parser\Api\ServerClient;


class Server
{
    public $simpla;
    public $serverApi;
    public $serverClient;
    public $host;
    public $notes;


    public function __construct($setting = [])
    {
        $this->simpla = new \Simpla();
        $this->serverApi = new ServerApi();
        $this->serverClient = new ServerClient();
        $host = '';
        if (isset($_SERVER['HTTP_HOST'])) $host = $_SERVER['HTTP_HOST'];
        if (isset($setting['host'])) $host = $setting['host'];
        if (!$host) die('задайте хост');
        $this->host = $host;
    }

    public function setSeo()
    {
        foreach ($this->simpla->products->get_products(['limit' => 100000]) as $product) {

            if ($product->meta_title == $product->name) $product->meta_title = '';
            if ($product->meta_description == $product->name) $product->meta_description = '';
            if ($product->meta_title && $product->meta_description) continue;


            $catTemp = reset($this->simpla->categories->get_categories(['product_id' => $product->id]));
            if (!$catTemp) continue;
            $catTemp = $catTemp->path;
            $category = array_shift($catTemp);
            if (!$category->name) continue;

            var_dump($product->name);

            $title = json_decode($this->serverApi->post('https://00.mutmarket.ru/parsers/server/getSeoTitle.php', ['host' => $this->host, 'category' => $category->name, 'product' => $product->name]));
            $description = json_decode($this->serverApi->post('https://00.mutmarket.ru/parsers/server/getSeoDesc.php', ['host' => $this->host, 'category' => $category->name, 'product' => $product->name]));

            if (!$product->meta_title && $title) $this->simpla->products->update_product($product->id, ['meta_title' => $title]);
            if (!$product->meta_description && $description) $this->simpla->products->update_product($product->id, ['meta_description' => $description]);

        }
    }


    public function start()
    {
        if (!$this->simpla->settings->parser_status) {
            var_dump('парсер отключен');
            return 0;
        }
//        foreach ($this->serverClient->get_products_all() as $product) {
//            $this->simpla->products->delete_product((int)$product->id);
//        }
//        die();
//        $this->deleteOldCategories();
        $this->addNewCatalogsAndProducts();
        $this->updateProducts();//здесь же и удаляем
//        $this->deleteOldProducts();
        $this->send();
    }

    public function send()
    {
        var_dump('send');
        if (!$this->notes) return 0;
        mail($this->simpla->settings->parser_email, "Обновление товаров " . $this->host, implode("\n<br>", $this->notes), "MIME-Version: 1.0\r\n" . "Content-type: text/html; charset=utf-8\r\n");
    }

    public function addNewCatalogsAndProducts()
    {
        var_dump('addNewCatalogsAndProducts');
        foreach ($this->serverClient->get_categories() as $category) {
            $this->clearSetting();
            $this->getSettingByCategory($category);
            if (!$this->setting['server_add']) continue;
            foreach ($this->serverApi->get_tree($category->server_id) as $path => $server_id) {
                $category_id = $this->addCategories($category, $path, $server_id);
                $this->addProducts($server_id, $category_id);
            }
        }
    }


    public function addCategories($category, $path)
    {
        $parent_id = $category->id;
        foreach (explode('.', $path) as $key => $catServer_id) {
            if (!$key) continue;
            $catServer = $this->serverApi->get_category((int)$catServer_id);
            $parent_id = $this->serverClient->add_category($catServer->name, $parent_id, $catServer->id);
        }
        return $parent_id;
    }

    public function addProducts($server_id, $category_id)
    {
        $this->clearSetting();
        $this->getSettingByCategory($category = $this->simpla->categories->get_category((int)$category_id));
        if (!$this->setting['server_add']) return 0;
        $page = 0;
        while (1) {
            $productsServer = $this->serverApi->get_products($server_id, $page);
            foreach ($productsServer as $productServer) {
                if (!$this->serverClient->get_product_id($productServer->id)) $this->notes['add' . $productServer->id] = '';
                $product_id = $this->serverClient->add_product($productServer, $this->setting['server_replace']);
                $this->serverClient->add_product_category($product_id, $category_id);
                if (isset($this->notes['add' . $productServer->id])) {
                    $obj = $this->simpla->products->get_product((int)$product_id);
                    $this->notes['add' . $productServer->id] = 'Добавлен продукт ' . $obj->name . ' -> ' . $this->host . '/products/' . $obj->url . '';
                    var_dump('add ' . $productServer->name);
                }
            }

            if (!$productsServer) break;
            $page++;
        }
    }


    public function updateProducts()
    {
        var_dump('updateProducts');
        foreach ($this->serverClient->get_products_all() as $product) {
            $productServer = $this->serverApi->get_product($product->server_id);
            $category = 0;
            if ($catTemp = $this->simpla->categories->get_categories(['product_id' => (int)$product->id])) {
                $catTemp = reset($this->simpla->categories->get_categories(['product_id' => (int)$product->id]));
                $catTemp = $catTemp->path;
                $category = array_pop($catTemp);
            }
            $this->clearSetting();
            $this->getSettingByProduct($product);
            if ($category) $this->getSettingByCategory($category);

            if (!$productServer) {
                if ($this->setting['server_delete']) {
                    $this->simpla->products->delete_product((int)$product->id);
                    $this->notes['delete' . $productServer->id] = 'Удален продукт ' . $product->name;
                    var_dump('delete ' . $productServer->id);
                }
                continue;
            }

            if ($this->setting['server_status_edit'] && !$this->setting['server_edit_update']) continue;
            $this->updateProduct($product, $productServer);
            var_dump('updateProduct ' . $product->name);
        }

    }

    public function updateProduct($product, $productServer)
    {
        $body = $productServer->body;
        if ($body) $body .= '<p>' . $this->serverApi->get_seo_body($this->host, $productServer->name, $productServer->id) . '</p>';
        $this->simpla->products->update_product((int)$product->id, [
            'body'      => $body,
            'server_id' => $productServer->id,
        ]);
        if (!$product->name)
            $this->simpla->products->update_product((int)$product->id, [
                'name' => $productServer->name,
            ]);

        if (!$product->meta_title && ($metaTitle = $this->serverApi->get_seo_title($this->host, $productServer->name, $productServer->id)))
            $this->simpla->products->update_product((int)$product->id, [
                'meta_title' => $metaTitle,
            ]);
        if (!$product->meta_description && ($metaDesc = $this->serverApi->get_seo_desc($this->host, $productServer->name, $productServer->id)))
            $this->simpla->products->update_product((int)$product->id, [
                'meta_description' => $metaDesc,
            ]);

        if (!$product->images = $this->simpla->products->get_images(['product_id' => (int)$product->id]))
            $this->serverClient->add_product_images($product->id, $productServer);

    }


    public function deleteOldCategories()
    {
        var_dump('deleteOldCategories');
        foreach ($this->serverClient->get_categories() as $category) {
            if (!$this->serverApi->get_tree($category->server_id)) {
                $this->simpla->categories->delete_category([(int)$category->id]);
                $this->notes['deleteCat' . $category->id] = 'Удалена категория ' . $category->name;
                var_dump('deleteCat ' . $category->id);
            }
        }
    }

//################################################################################################################
//################################################################################################################
//################################################################################################################
//################################################################################################################


    //если NULL то насторойка будет проигнорирована
    //есди настройка не найдется и все будет проигнорировано, то по умолчанию выставится setDefSetting
    private $setting = [
        'server_edit_update' => NULL,
        'server_status_edit' => NULL,
        'server_delete'      => NULL,
        'server_add'         => NULL,
        'server_replace'     => NULL,
    ];


    public function clearSetting()
    {
        $this->setting = [
            'server_edit_update' => NULL,
            'server_status_edit' => NULL,
            'server_delete'      => NULL,
            'server_add'         => NULL,
            'server_replace'     => NULL,
        ];
    }

    public function setDefSetting()
    {
        if (is_null($this->setting['server_edit_update'])) $this->setting['server_edit_update'] = 0;
        if (is_null($this->setting['server_status_edit'])) $this->setting['server_status_edit'] = 0;
        if (is_null($this->setting['server_delete'])) $this->setting['server_delete'] = 0;
        if (is_null($this->setting['server_add'])) $this->setting['server_add'] = 1;
        if (is_null($this->setting['server_replace'])) $this->setting['server_replace'] = 0;
    }

    public function getSettingByCategory($category)
    {
        if (!$category->path) {
            var_dump($category);
            die('no path');
        }
        foreach (array_reverse($category->path) as $categoryPath) {
            $setting = [
                'server_edit_update' => $categoryPath->server_edit_update,
                'server_delete'      => $categoryPath->server_delete,
                'server_add'         => $categoryPath->server_add,
                'server_replace'     => $categoryPath->server_replace,
            ];
            $this->setSetting($setting);
        }
        $this->setDefSetting();
    }

    public function getSettingByProduct($product)
    {
        $setting = [
            'server_edit_update' => $product->server_edit_update,
            'server_status_edit' => $product->server_status_edit,
        ];
        $this->setSetting($setting);
    }

    public function setSetting($setting)
    {
        foreach ($setting as $key => $val) {
            if (!is_null($val) && is_null($this->setting[$key])) $this->setting[$key] = (int)$val;
        }
    }


}
