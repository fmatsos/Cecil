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

namespace Cecil\Renderer;

use Cecil\Collection\Page\Page as CollectionPage;
use Cecil\Collection\Page\Type as PageType;
use Cecil\Config;
use Cecil\Exception\RuntimeException;
use Cecil\Util;

/**
 * Class Layout.
 */
class Layout
{
    public const EXT = 'twig';

    /**
     * Layout files finder.
     *
     * @throws RuntimeException
     */
    public static function finder(CollectionPage $page, string $format, Config $config): array
    {
        $layout = 'unknown';

        // what layouts, in what format, could be use for the page?
        $layouts = self::fallback($page, $format, $config);

        // take the first available layout
        foreach ($layouts as $layout) {
            $layout = Util::joinFile($layout);
            // is it in `layouts/` dir?
            if (Util\File::getFS()->exists(Util::joinFile($config->getLayoutsPath(), $layout))) {
                return [
                    'scope' => 'site',
                    'file'  => $layout,
                ];
            }
            // is it in `<theme>/layouts/` dir?
            if ($config->hasTheme()) {
                $themes = $config->getTheme();
                foreach ($themes as $theme) {
                    if (Util\File::getFS()->exists(Util::joinFile($config->getThemeDirPath($theme, 'layouts'), $layout))) {
                        return [
                            'scope' => $theme,
                            'file'  => $layout,
                        ];
                    }
                }
            }
            // is it in `resources/layouts/` dir?
            if (Util\File::getFS()->exists(Util::joinPath($config->getInternalLayoutsPath(), $layout))) {
                return [
                    'scope' => 'cecil',
                    'file'  => $layout,
                ];
            }
        }

        throw new RuntimeException(sprintf('Layout "%s" not found (page: %s).', $layout, $page->getId()));
    }

    /**
     * Layout fall-back.
     *
     * @see finder()
     */
    protected static function fallback(CollectionPage $page, string $format, Config $config): array
    {
        $ext = self::EXT;

        // remove potential redundant extension
        $layout = str_replace(".$ext", '', (string) $page->getVariable('layout'));

        switch ($page->getType()) {
            case PageType::HOMEPAGE:
                $layouts = [
                    // "$layout.$format.$ext",
                    "index.$format.$ext",
                    "home.$format.$ext",
                    "list.$format.$ext",
                ];
                if ($page->hasVariable('layout')) {
                    $layouts = array_merge(["$layout.$format.$ext"], $layouts, ["_default/$layout.$format.$ext"]);
                }
                $layouts = array_merge($layouts, [
                    // "_default/$layout.$format.$ext",
                    "_default/index.$format.$ext",
                    "_default/home.$format.$ext",
                    "_default/list.$format.$ext",
                    "_default/page.$format.$ext",
                ]);
                break;
            case PageType::SECTION:
                $layouts = [
                    // "$layout.$format.$ext",
                    // "$section/index.$format.$ext",
                    // "$section/list.$format.$ext",
                    // "section/$section.$format.$ext",
                    "_default/section.$format.$ext",
                    "_default/list.$format.$ext",
                ];
                if ($page->getPath()) {
                    $layouts = array_merge(["section/{$page->getSection()}.$format.$ext"], $layouts);
                    $layouts = array_merge(["{$page->getSection()}/list.$format.$ext"], $layouts);
                    $layouts = array_merge(["{$page->getSection()}/index.$format.$ext"], $layouts);
                }
                if ($page->hasVariable('layout')) {
                    $layouts = array_merge(["$layout.$format.$ext"], $layouts);
                }
                break;
            case PageType::VOCABULARY:
                $layouts = [
                    // "taxonomy/$plural.$format.$ext", // e.g.: taxonomy/tags.html.twig
                    "_default/vocabulary.$format.$ext", // e.g.: _default/vocabulary.html.twig
                ];
                if ($page->hasVariable('plural')) {
                    $layouts = array_merge(["taxonomy/{$page->getVariable('plural')}.$format.$ext"], $layouts);
                }
                break;
            case PageType::TERM:
                $layouts = [
                    // "taxonomy/$term.$format.$ext",     // e.g.: taxonomy/velo.html.twig
                    // "taxonomy/$singular.$format.$ext", // e.g.: taxonomy/tag.html.twig
                    "_default/term.$format.$ext",         // e.g.: _default/term.html.twig
                    "_default/list.$format.$ext",         // e.g.: _default/list.html.twig
                ];
                if ($page->hasVariable('term')) {
                    $layouts = array_merge(["taxonomy/{$page->getVariable('term')}.$format.$ext"], $layouts);
                }
                if ($page->hasVariable('singular')) {
                    $layouts = array_merge(["taxonomy/{$page->getVariable('singular')}.$format.$ext"], $layouts);
                }
                break;
            default:
                $layouts = [
                    // "$section/$layout.$format.$ext",
                    // "$layout.$format.$ext",
                    // "$section/page.$format.$ext",
                    // "page.$format.$ext",
                    // "_default/$layout.$format.$ext",
                    "_default/page.$format.$ext",
                ];
                if ($page->hasVariable('layout')) {
                    $layouts = array_merge(["_default/$layout.$format.$ext"], $layouts);
                }
                $layouts = array_merge(["page.$format.$ext"], $layouts);
                if ($page->getSection()) {
                    $layouts = array_merge(["{$page->getSection()}/page.$format.$ext"], $layouts);
                }
                if ($page->hasVariable('layout')) {
                    $layouts = array_merge(["$layout.$format.$ext"], $layouts);
                    if ($page->getSection()) {
                        $layouts = array_merge(["{$page->getSection()}/$layout.$format.$ext"], $layouts);
                    }
                }
        }

        // add localized layouts
        if ($page->getVariable('language') !== $config->getLanguageDefault()) {
            foreach ($layouts as $key => $value) {
                $layouts = array_merge(\array_slice($layouts, 0, $key), [str_replace(".$ext", ".{$page->getVariable('language')}.$ext", $value)], \array_slice($layouts, $key));
            }
        }

        return $layouts;
    }
}
