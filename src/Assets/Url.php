<?php

declare(strict_types=1);

/*
 * This file is part of Cecil.
 *
 * Copyright (c) Arnaud Ligny <arnaud@ligny.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cecil\Assets;

use Cecil\Builder;
use Cecil\Collection\Page\Page;
use Cecil\Config;
use Cecil\Renderer\Page as PageRenderer;
use Cecil\Util;
use Cocur\Slugify\Slugify;

class Url
{
    /** @var Builder */
    protected $builder;

    /** @var Config */
    protected $config;

    /** @var Page|Asset|string|null */
    protected $value;

    /** @var array */
    protected $options;

    /** @var string */
    protected $baseurl;

    /** @var string */
    protected $url;

    /** @var Slugify */
    private static $slugifier;

    /**
     * Creates an URL from a string, a Page or an Asset.
     *
     * @param Builder                $builder
     * @param Page|Asset|string|null $value
     * @param array|null             $options Render options, e.g.: ['canonical' => true, 'format' => 'json']
     */
    public function __construct(Builder $builder, $value, array $options = null)
    {
        $this->builder = $builder;
        $this->config = $builder->getConfig();
        if (!self::$slugifier instanceof Slugify) {
            self::$slugifier = Slugify::create(['regexp' => Page::SLUGIFY_PATTERN]);
        }
        $this->baseurl = (string) $this->config->get('baseurl');

        // handles options
        $canonical = null;
        $format = null;
        $language = null;
        extract(is_array($options) ? $options : [], EXTR_IF_EXISTS);

        // canonical URL?
        $base = '';
        if ((bool) $this->config->get('canonicalurl') || $canonical === true) {
            $base = rtrim($this->baseurl, '/');
        }
        if ($canonical === false) {
            $base = '';
        }

        // value is empty (ie: `url()`)
        if (is_null($value) || empty($value) || $value == '/') {
            $this->url = $base.'/';

            return;
        }

        switch (true) {
            // Page
            case $value instanceof Page:
                if (!$format) {
                    $format = $value->getVariable('output');
                    if (is_array($value->getVariable('output'))) {
                        $format = $value->getVariable('output')[0]; // first format by default
                    }
                    if (!$format) {
                        $format = 'html'; // 'html' format by default
                    }
                }
                $this->url = $base.'/'.ltrim((new PageRenderer($this->config))->getUrl($value, $format), '/');
                break;
            // Asset
            case $value instanceof Asset:
                $this->url = $base.'/'.ltrim($value['path'], '/');
                break;
            // string
            case is_string($value):
                // potential Page ID
                $pageId = self::$slugifier->slugify($value);
                // force language?
                $lang = '';
                if ($language !== null && $language != $this->config->getLanguageDefault()) {
                    $pageId = "$pageId.$language";
                    $lang = "$language/";
                }
                switch (true) {
                    // External URL
                    case Util\Url::isUrl($value):
                        $this->url = $value;
                        break;
                    // Page ID as string
                    case $this->builder->getPages()->has($pageId):
                        $this->url = (string) new self(
                            $this->builder,
                            $this->builder->getPages()->get($pageId),
                            $options
                        );
                        break;
                    // default case
                    default:
                        $this->url = $base.'/'.$lang.ltrim($value, '/');
                }
        }
    }

    /**
     * Returns URL.
     */
    public function __toString(): string
    {
        return (string) $this->url;
    }

    /**
     * Returns URL.
     */
    public function getUrl(): string
    {
        return $this->__toString();
    }
}
