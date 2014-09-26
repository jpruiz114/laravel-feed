<?php namespace Roumen\Feed;
/**
 * Feed generator class for laravel-feed package.
 *
 * @author Roumen Damianoff <roumen@dawebs.com>
 * @version 2.6.11
 * @link http://roumen.it/projects/laravel-feed
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */

use Config;
use Response;
use View;
use Cache;

class Feed
{

    public $items = array();
    public $title = 'My feed title';
    public $description = 'My feed description';
    public $link;
    public $logo;
    public $icon;
    public $pubdate;
    public $lang;
    public $charset = 'utf-8';
    public $ctype = 'application/atom+xml';
    public $caching = 0;
    public $cacheKey = 'laravel-feed';
    private $shortening = false;
    private $shorteningLimit = 150;
    private $dateFormat = 'datetime';
    private $namespaces = array();


    /**
     * Returns new instance of Feed class
     *
     * @return Feed
     */
    public function make()
    {
        return new Feed();
    }


    /**
     * Add new item to $items array
     *
     * @param string $title
     * @param string $author
     * @param string $link
     * @param string $pubdate
     * @param string $description
     * @param string $content
     *
     * @return void
     */
    public function add($title, $author, $link, $pubdate, $description, $content='')
    {

        if ($this->shortening)
        {
            $description = mb_substr($description, 0, $this->shorteningLimit, 'UTF-8');
        }

        $pubdate = $this->formatDate($pubdate);

        $this->items[] = array(
            'title' => $title,
            'author' => $author,
            'link' => $link,
            'pubdate' => $pubdate,
            'description' => $description,
            'content' => $content
        );
    }


    /**
     * Add new item to $items array
     *
     * @param array $a
     *
     * @return void
     */
    public function addArray(array $a)
    {

        if ($this->shortening)
        {
            $a['description'] = mb_substr($a['description'], 0, $this->shorteningLimit, 'UTF-8');
        }

        $a['pubdate'] = $this->formatDate($a['pubdate']);

        $this->items[] = $a;
    }


    /**
     * Returns aggregated feed with all items from $items array
     *
     * @param string $format (options: 'atom', 'rss')
     * @param carbon|datetime|integer $cache (0 - turns off the cache)
     *
     * @return view
     */
    public function render($format = 'atom', $cache = 0, $key = 'laravel-feed')
    {
        if (empty($this->lang)) $this->lang = Config::get('application.language');
        if (empty($this->link)) $this->link = Config::get('application.url');
        if (empty($this->pubdate)) $this->pubdate = date('D, d M Y H:i:s O');

        $pubdate = $this->formatDate($this->pubdate, $format);

        $this->cacheKey = $key;
        $this->caching = $cache;

        $channel = array(
            'title'=>$this->title,
            'description'=>$this->description,
            'logo' => $this->logo,
            'icon' => $this->icon,
            'link'=>$this->link,
            'pubdate'=>$this->pubdate,
            'lang'=>$this->lang
        );

        if ($format == 'rss')
        {
            $this->ctype = 'application/rss+xml';

            $channel['title'] = html_entity_decode(strip_tags($channel['title']));
            $channel['description'] = html_entity_decode(strip_tags($channel['description']));

            foreach($this->items as $k => $v)
            {
                $this->items[$k]['description'] = html_entity_decode(strip_tags($this->items[$k]['description']));
                $this->items[$k]['title'] = html_entity_decode(strip_tags($this->items[$k]['title']));
                $this->items[$k]['pubdate'] = $this->formatDate($pubdate, "rss");
            }
        }

        // cache check
        if ($cache > 0)
        {
            if (Cache::has($key))
            {
                return Response::make(Cache::get($key), 200, array('Content-type' => $this->ctype.'; charset='.$this->charset));
            } else
                {
                    Cache::put($key, View::make('feed::'.$format, array('items' => $this->items, 'channel' => $channel, 'namespaces' => $this->getNamespaces()))->render(), $cache);

                    return Response::make(Cache::get($key), 200, array('Content-type' => $this->ctype.'; charset='.$this->charset));
                }

        } else if ($cache < 0)
            {
                return View::make('feed::'.$format, array('items' => $this->items, 'channel' => $channel, 'namespaces' => $this->getNamespaces()))->render();
            } else
                {
                    return Response::make(View::make('feed::'.$format, array('items' => $this->items, 'channel' => $channel, 'namespaces' => $this->getNamespaces())), 200, array('Content-type' => $this->ctype.'; charset='.$this->charset));
                }

     }


     /**
      * Create link
      *
      * @param string $url
      * @param string $format
      *
      * @return string
      */
     public function link($url, $format='atom')
     {
        $t = 'application/atom+xml';

        if ($format != 'atom')
        {
            $t = 'application/rss+xml';
        }

        return '<link rel="alternate" type="'.$t.'" href="'.$url.'" />';
     }


    /**
     * Check if feed is cached
     *
     * @return bool
     */
    public function isCached()
    {

        if (Cache::has($this->cacheKey))
        {
            return true;
        }

        return false;
    }


    /**
     * Set maximum characters lenght for text shortening
     *
     * @param integer $l
     *
     * @return void
     */
    public function setTextLimit($l=150)
    {
        $this->shorteningLimit = $l;
    }


    /**
     * Turn on/off text shortening for item content
     *
     * @param boolean $b
     *
     * @return void
     */
    public function setShortening($b=false)
    {
        $this->shortening = $b;
    }


    /**
     * Format datetime string, timestamp integer or carbon object in valid feed format
     *
     * @param string/integer $date
     *
     * @return string
     */
    private function formatDate($date, $format="atom")
    {
        if ($format == "atom")
        {
            switch ($this->dateFormat)
            {
                case "carbon":
                    $date = date('c', strtotime($date->toDateTimeString()));
                    break;
                case "timestamp":
                    $date = date('c', $date);
                    break;
                case "datetime":
                    $date = date('c', strtotime($date));
                    break;
            }
        }
        else
        {
            switch ($this->dateFormat)
            {
                case "carbon":
                    $date = date('D, d M Y H:i:s O', strtotime($date->toDateTimeString()));
                    break;
                case "timestamp":
                    $date = date('D, d M Y H:i:s O', $date);
                    break;
                case "datetime":
                    $date = date('D, d M Y H:i:s O', strtotime($date));
                    break;
            }
        }


        return $date;
    }


    /**
     * Add namespace
     *
     * @param string $n
     *
     * @return void
     */
    public function addNamespace($n)
    {
        $this->namespaces[] = $n;
    }


    /**
     * Get all namespaces
     *
     * @param string $n
     *
     * @return void
     */
    public function getNamespaces()
    {
        return $this->namespaces;
    }


    public function setDateFormat($format="datetime")
    {
        $this->dateFormat = $format;
    }


}