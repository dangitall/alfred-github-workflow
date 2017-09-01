<?php

/*
 * This file is part of the alfred-github-workflow package.
 *
 * (c) Gregor Harlan
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Item
{
    private $randomUid = false;
    private $prefix = '';
    private $prefixOnlyTitle = true;
    private $title;
    private $comparator;
    private $subtitle;
    private $icon;
    private $arg;
    private $valid = true;
    private $add = '…';
    private $autocomplete = true;
    private $prio = 0;
    private $missingChars = 0;
    private $sameChars = 0;

    public static function create()
    {
        return new self();
    }

    public function randomUid()
    {
        $this->randomUid = true;
        return $this;
    }

    public function prefix($prefix, $onlyTitle = true)
    {
        $this->prefix = $prefix;
        $this->prefixOnlyTitle = $onlyTitle;
        return $this;
    }

    public function title($title)
    {
        $this->title = $title;
        return $this;
    }

    public function comparator($comparator)
    {
        $this->comparator = $comparator;
        return $this;
    }

    public function subtitle($subtitle)
    {
        $this->subtitle = $subtitle;
        return $this;
    }

    public function icon($icon)
    {
        $this->icon = $icon;
        return $this;
    }

    public function arg($arg)
    {
        $this->arg = $arg;
        return $this;
    }

    public function valid($valid, $add = '…')
    {
        $this->valid = (bool) $valid;
        $this->add = $add;
        return $this;
    }

    public function autocomplete($autocomplete = true)
    {
        $this->autocomplete = $autocomplete;
        return $this;
    }

    public function prio($prio)
    {
        $this->prio = $prio;
        return $this;
    }

    public function match($query)
    {
        $comparator = strtolower($this->comparator ?: $this->title);
        $query = strtolower($query);
        if (!$this->prefixOnlyTitle && stripos($query, $this->prefix) === 0) {
            $query = substr($query, strlen($this->prefix));
        }
        $query_re = "/" . join(".*?", str_split($query)) . "/";
        $maybe_matches = array_map(function ($i) use($comparator, $query_re) {
            preg_match($query_re, $comparator, $m, 0, $i);
            return $m[0];
          }, range(0, strlen($comparator)));
        $matches = array_filter($maybe_matches);
        if (!sizeof($matches)) {
            return false;
        }
        $this->sameChars = strlen($query) - min(array_map(strlen, $matches));
        $this->missingChars = strlen($comparator) - strlen($query);
        return true;
    }

    public function compare(self $another)
    {
        if ($this->sameChars != $another->sameChars) {
            return $this->sameChars < $another->sameChars ? 1 : -1;
        }
        if ($this->prio != $another->prio) {
            return $this->prio < $another->prio ? 1 : -1;
        }
        return $this->missingChars > $another->missingChars ? 1 : -1;
    }

    /**
     * @param self[] $items
     *
     * @return string
     */
    public static function toXml(array $items, $enterprise, $hotkey, $baseUrl)
    {
        $xml = new SimpleXMLElement('<items></items>');
        $prefix = $hotkey ? '' : ' ';
        foreach ($items as $item) {
            $c = $xml->addChild('item');
            $title = $item->prefix.$item->title;
            $c->addAttribute('uid', $item->randomUid ? md5(time().$title) : md5($title));
            if ($item->icon && file_exists(__DIR__.'/icons/'.$item->icon.'.png')) {
                $c->addChild('icon', 'icons/'.$item->icon.'.png');
            } else {
                $c->addChild('icon', 'icon.png');
            }
            if ($item->arg) {
                $arg = $item->arg;
                if ('/' === $arg[0]) {
                    $arg = $baseUrl.$arg;
                } elseif (false === strpos($arg, '://')) {
                    $arg = ($enterprise ? 'e ' : '').$arg;
                }
                $c->addAttribute('arg', $arg);
            }
            if ($item->autocomplete) {
                if (is_string($item->autocomplete)) {
                    $autocomplete = $item->autocomplete;
                } elseif (null !== $item->comparator) {
                    $autocomplete = $item->comparator;
                } else {
                    $autocomplete = $item->title;
                }
                $c->addAttribute('autocomplete', $prefix.($item->prefixOnlyTitle ? $autocomplete : $item->prefix.$autocomplete));
            }
            if (!$item->valid) {
                $c->addAttribute('valid', 'no');
                $title .= $item->add;
            }
            $c->addChild('title', htmlspecialchars($title));
            if ($item->subtitle) {
                $c->addChild('subtitle', htmlspecialchars($item->subtitle));
            }
        }
        return $xml->asXML();
    }
}
