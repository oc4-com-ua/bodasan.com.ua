<?php
namespace Opencart\Catalog\Controller\Startup;

class SeoUrl extends \Opencart\System\Engine\Controller {
    private array $data = [];

    public function index() {
        if ($this->config->get('config_seo_url')) {
            $this->url->addRewrite($this);

            $this->load->model('design/seo_url');

            if (isset($this->request->get['_route_'])) {
                $parts = explode('/', $this->request->get['_route_']);

                if (oc_strlen(end($parts)) == 0) {
                    array_pop($parts);
                }

                foreach ($parts as $key => $value) {
                    $seo_url_info = $this->model_design_seo_url->getSeoUrlByKeyword($value);

                    if ($seo_url_info) {
                        // Skip known route aliases like 'product', 'information', 'catalog', etc.
                        if ($seo_url_info['key'] === 'route' && in_array($seo_url_info['value'], [
                                'product/product',
                                'product/category',
                                'information/information',
                                'common/home'
                            ])) {
                            // Do not set this as route. We'll deduce route by other keys like product_id, path, etc.
                            unset($parts[$key]);
                            continue;
                        }

                        $this->request->get[$seo_url_info['key']] = html_entity_decode($seo_url_info['value'], ENT_QUOTES, 'UTF-8');
                        unset($parts[$key]);
                    }
                }

                // Deduce route manually if not set
                if (!isset($this->request->get['route'])) {
                    if (isset($this->request->get['product_id'])) {
                        $this->request->get['route'] = 'product/product';
                    } elseif (isset($this->request->get['path'])) {
                        $this->request->get['route'] = 'product/category';
                    } elseif (isset($this->request->get['information_id'])) {
                        $this->request->get['route'] = 'information/information';
                    } else {
                        $this->request->get['route'] = $this->config->get('action_default');
                    }
                }

                if ($parts) {
                    $this->request->get['route'] = $this->config->get('action_error');
                }
            }
        }
        return null;
    }

    public function rewrite(string $link): string {
        $url_info = parse_url(str_replace('&amp;', '&', $link));
        $url = '';

        if ($url_info['scheme']) {
            $url .= $url_info['scheme'];
        }
        $url .= '://';

        if ($url_info['host']) {
            $url .= $url_info['host'];
        }

        if (isset($url_info['port'])) {
            $url .= ':' . $url_info['port'];
        }

        parse_str($url_info['query'], $query);
        $paths = [];
        $parts = explode('&', $url_info['query']);

        foreach ($parts as $part) {
            $pair = explode('=', $part);
            $key = $pair[0] ?? '';
            $value = $pair[1] ?? '';
            $index = $key . '=' . $value;

            if (!isset($this->data[$index])) {
                $this->data[$index] = $this->model_design_seo_url->getSeoUrlByKeyValue($key, $value);
            }

            if ($this->data[$index]) {
                if ($key === 'route' && in_array($value, [
                        'product/product',
                        'product/category',
                        'information/information',
                        'common/home'
                    ])) {
                    unset($query[$key]); // Do not include route keywords in URL
                    continue;
                }

                $paths[] = $this->data[$index];
                unset($query[$key]);
            }
        }

        array_multisort(array_column($paths, 'sort_order'), SORT_ASC, $paths);
        $url .= str_replace('/index.php', '', $url_info['path']);

        foreach ($paths as $result) {
            $url .= '/' . $result['keyword'];
        }

        if ($query) {
            $url .= '?' . str_replace(['%2F'], ['/'], http_build_query($query));
        }

        return $url;
    }
}
