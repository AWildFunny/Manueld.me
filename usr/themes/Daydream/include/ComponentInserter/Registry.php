<?php
/**
 * 组件插入注册表（由各业务插件在 ComponentInserter::collect 钩子中写入）
 * 壳由 Daydream 主题提供，无需单独启用插件。
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class ComponentInserter_Registry
{
    /** @var array<string, array> */
    private static $items = array();

    /**
     * @param array $item id,label,order,panelHtml,boot?,css?,js?
     */
    public static function register(array $item)
    {
        if (empty($item['id']) || empty($item['label'])) {
            return;
        }
        $id = (string) $item['id'];
        if (!isset($item['order'])) {
            $item['order'] = 100;
        }
        if (!isset($item['panelHtml'])) {
            $item['panelHtml'] = '';
        }
        self::$items[$id] = $item;
    }

    /**
     * @return array<int, array>
     */
    public static function all()
    {
        $list = array_values(self::$items);
        usort($list, function ($a, $b) {
            $oa = isset($a['order']) ? (int) $a['order'] : 100;
            $ob = isset($b['order']) ? (int) $b['order'] : 100;
            if ($oa === $ob) {
                return strcmp($a['id'], $b['id']);
            }
            return $oa - $ob;
        });
        return $list;
    }

    public static function reset()
    {
        self::$items = array();
    }
}
