<?php

namespace Saitdarom\Mutmarket\Parser\Api;

require_once(__DIR__ . '/../../../../../api/Simpla.php');

class ServerClient extends \Simpla
{

    public function get_categories()
    {
        $this->db->query("SELECT c.id FROM __categories c
	                    WHERE server_id>0");
        $returnArr = [];
        foreach ($this->db->results() as $category) {
            $returnArr[] = $this->categories->get_category((int)$category->id);
        }
        return $returnArr;
    }

    public function get_products_all()
    {
        $this->db->query("SELECT p.* FROM __products p
	                    WHERE server_id>0");
        $returnArr = [];
        foreach ($this->db->results() as $product) {
            $returnArr[] = $this->products->get_product((int)$product->id);
        }
        return $returnArr;
    }

    public function get_product($server_id)
    {
        $this->db->query("SELECT p.* FROM __products p
	                    WHERE server_id=" . (int)$server_id);
        if ($product = $this->db->result()) {
            return $this->products->get_product((int)$product->id);
        }
        return 0;
    }


    public function add_category($name, $parent_id, $catServer_id)
    {
        $this->db->query("SELECT c.* FROM __categories c
	                    WHERE parent_id=" . (int)$parent_id . " AND (name=\"$name\" OR  server_id=" . (int)$catServer_id . ")");
        if (!$category = $this->db->result()) {
            $category_id = $this->categories->add_category([
                'parent_id' => $parent_id,
                'name'      => $name,
                'url'       => str_slug($name),
            ]);
        } else$category_id = $category->id;
        return $category_id;
    }


    public function add_product($productServer, $flagReplace = NULL)
    {
        $product_id = 0;
        if ($product = $this->get_product($productServer->id))
            $product_id = $product->id;

        if (!$product_id && $flagReplace) {
            $this->db->query("SELECT p.* FROM __products p
	                    WHERE p.name=\"{$productServer->name}\" and !p.server_id");
            if ($product = $this->db->result()) {
                $product_id = $this->products->update_product((int)$product->id, [
                    'body'               => $productServer->body,
                    'server_id'          => $productServer->id,
                    'server_status_edit' => 0,
                ]);
                $this->add_product_images($product_id, $productServer);
            }
        }


        if (!$product_id) {
            $product_id = $this->products->add_product(
                [
                    'name'               => $productServer->name,
                    'url'                => str_slug($productServer->name),
                    'body'               => $productServer->body,
                    'server_id'          => $productServer->id,
                    'server_status_edit' => 0,
                    'visible'            => 0,
                ]
            );
            $this->add_product_images($product_id, $productServer);
        }

        return $product_id;
    }

    public function add_product_images($product_id, $productServer)
    {
        $dir = $this->config->root_dir . $this->config->original_images_dir;
        if (isset($productServer->images) && $productServer->images)
            foreach ($productServer->images as $key => $image) {
                $imageTemp = explode('?', $image);
                $imageTemp = $imageTemp[0];
                $path_parts = pathinfo($imageTemp);
                $extension = $path_parts['extension'];
                if (copy($image, $dir . $product_id . '_' . $key . '.' . $extension))
                    $this->products->add_image((int)$product_id, $product_id . '_' . $key . '.' . $extension);
            }
    }

    public function add_product_category($product_id, $category_id)
    {
//        $this->db->query("SELECT * FROM __products_categories
//	                    WHERE product_id=$product_id");
//        if (count($this->db->results()) > 1) {
//            $this->db->query("DELETE  FROM __products_categories
//	                    WHERE product_id=$product_id");
//            $this->db->result();
//        }
        $this->db->query("SELECT id FROM __products_categories
	                    WHERE product_id=" . (int)$product_id . " AND category_id=" . (int)$category_id);
        if (!$this->db->result()) {
            $this->categories->add_product_category((int)$product_id, (int)$category_id);
        }
    }

    public function get_setting_by_product()
    {

    }


}
