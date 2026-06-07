<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

require_once __DIR__ . '/Fixtures/AbstractProvider.php';
require_once __DIR__ . '/Fixtures/Crawlers.php';
require_once __DIR__ . '/Fixtures/Exclusions.php';
require_once __DIR__ . '/Fixtures/Headers.php';
require_once __DIR__ . '/CrawlerDetect.php';

class MiraiCore_Crawler_Detect
{
    protected $crawlerDetect;

    public function __construct()
    {
        $this->crawlerDetect = new \Jaybizzle\CrawlerDetect\CrawlerDetect();
    }

    public function isCrawler(): bool
    {
        return $this->crawlerDetect->isCrawler();
    }

    public function getMatches(): ?string
    {
        return $this->crawlerDetect->getMatches();
    }
}