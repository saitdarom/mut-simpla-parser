<?php
namespace Saitdarom\Mutmarket\Parser;

require_once(__DIR__ . '/../api/Simpla.php');
require_once(__DIR__ . '/../api/ServerApi.php');
require_once(__DIR__ . '/../api/ServerClient.php');


class Server
{
    private $simpla;
    private $serverApi;
    private $serverClient;
    private $host;


    public function __construct()
    {
        $this->simpla = new Simpla();
        $this->serverApi = new ServerApi();
        $this->serverClient = new ServerClient();
        $this->host = $_SERVER['HTTP_HOST'];
    }


    public function start()
    {
        $this->deleteOldCategories();
        $this->addNewCatalogsAndProducts();
        $this->updateProducts();//здесь же и удаляем
//        $this->deleteOldProducts();
    }

    public function addNewCatalogsAndProducts()
    {

        foreach ($this->serverClient->get_categories() as $category) {
            $this->clearSetting();
            $this->getSettingByCategory($category);
            if (!$this->setting['server_add']) continue;
            foreach ($this->serverApi->get_tree($category->server_id) as $path => $server_id) {
                $category_id = $this->addCategories($category, $path, $server_id);
                $this->addProducts($server_id, $category_id);
            }
        }
        var_dump('addNewCatalogsAndProducts end', $this->setting);
        die;
    }


    public function addCategories($category, $path)
    {
        $parent_id = $category->id;
        foreach (explode('.', $path) as $key => $catServer_id) {
            if (!$key) continue;
            $catServer = $this->serverApi->get_category($catServer_id);
            $parent_id = $this->serverClient->add_category($catServer->name, $parent_id, $catServer_id);
        }
        return $parent_id;
    }

    public function addProducts($server_id, $category_id)
    {
        $this->clearSetting();
        $this->getSettingByCategory($category = $this->simpla->categories->get_category($category_id));
        if (!$this->setting['server_add']) return 0;
        $page = 0;
        while (1) {
            $productsServer = $this->serverApi->get_products($server_id, $page);
            foreach ($productsServer as $productServer) {
                $product_id = $this->serverClient->add_product($productServer, $this->setting['server_replace'], $category_id);
                $this->serverClient->add_product_category($product_id, $category_id);
            }

            if (!$productsServer) break;
            $page++;
        }
    }


    public function updateProducts()
    {

        foreach ($this->serverClient->get_products_all() as $product) {
            $productServer = $this->serverApi->get_product($product->server_id);
            $category = 0;
            if ($catTemp = $this->simpla->categories->get_categories(['product_id' => $product->id])) {
                $catTemp = reset($this->simpla->categories->get_categories(['product_id' => $product->id]));
                $catTemp = $catTemp->path;
                $category = array_pop($catTemp);
            }
            $this->clearSetting();
            $this->getSettingByProduct($product);
            if ($category) $this->getSettingByCategory($category);

            if (!$productServer) {
                if ($this->setting['server_delete']) {
                    $this->simpla->products->delete_product($product->id);
                }
                continue;
            }

            if ($this->setting['server_status_edit'] && !$this->setting['server_edit_update']) continue;
            $this->updateProduct($product, $productServer);

        }

    }

    public function updateProduct($product, $productServer)
    {
        if (!$product->meta_title && ($metaTitle = $this->serverApi->get_seo_title($this->host, $product->name, $product->server_id)))
            $this->simpla->products->update_product($product->id, [
                'meta_title' => $metaTitle,
            ]);
        if (!$product->meta_description && ($metaDesc = $this->serverApi->get_seo_desc($this->host, $product->name, $product->server_id)))
            $this->simpla->products->update_product($product->id, [
                'meta_description' => $metaDesc,
            ]);

        if (!$product->images = $this->simpla->products->get_images(['product_id' => $product->id]))
            $this->serverClient->add_product_images($product->id, $productServer);

        if ($productServer->body) $productServer->body .= '<p>' . $this->serverApi->get_seo_body($this->host, $product->name, $product->server_id) . '</p>';

        $this->simpla->products->update_product($product->id, [
            'name' => $productServer->name,
            'body' => $productServer->body,
        ]);
    }


    public function deleteOldCategories()
    {
        foreach ($this->serverClient->get_categories() as $category) {
            if (!$this->serverApi->get_tree($category->server_id)) $this->simpla->categories->delete_category([$category->id]);
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
        if (is_null($this->setting['server_delete'])) $this->setting['server_delete'] = 1;
        if (is_null($this->setting['server_add'])) $this->setting['server_add'] = 1;
        if (is_null($this->setting['server_replace'])) $this->setting['server_replace'] = 1;
    }

    public function getSettingByCategory($category)
    {
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
