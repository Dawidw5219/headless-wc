<?php
if (!defined('ABSPATH')) {
    exit;
}

class HWC_Product
{
    protected $wc_product;

    public function __construct($wc_product)
    {
        $this->wc_product = $wc_product;
    }

    protected function get_product_type()
    {
        switch ($this->wc_product->get_type()) {
            case 'variable':
                return new HWC_Variable_Product($this->wc_product);
            default:
                return new HWC_Simple_Product($this->wc_product);
        }
    }

    public function get_base_data()
    {
        return $this->get_product_type()->get_base_data();
    }

    public function get_detailed_data()
    {
        return $this->get_product_type()->get_detailed_data();
    }

}
