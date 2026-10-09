<?php
/***************************************************************************************************************************/
/**
    © Copyright 2023-2026, [Little Green Viper Software Development LLC](https://littlegreenviper.com)

    MIT License

    Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation
    files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy,
    modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the
    Software is furnished to do so, subject to the following conditions:

    The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

    THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
    OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
    IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
    CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
/***************************************************************************************************************************/
/**
    \brief This fixes generated alphabetical-index links and verifies the local HTML before publication.

    Doxygen 1.18 emits raw dollar/underscore index links for PHP names, while the corresponding anchors are escaped.
    Some short, ungrouped function lists also receive an alphabetical bar without section anchors. We repair only
    these generated index links, then check all generated local files, images, scripts, styles, and HTML anchors.
    A failed check prevents generate-docs.sh from replacing the existing documentation.
*/
declare(strict_types = 1);

if ($argc !== 2 || !is_dir($argv[1])) {
    fwrite(STDERR, "Expected the generated HTML directory.\n");
    exit(1);
}
$directory = realpath($argv[1]);
$pages = [];
foreach (glob($directory.'/*.html') as $file) {
    $html = file_get_contents($file);
    foreach (['#index_$' => '#index__24', '#index__' => '#index__5F'] as $old => $new) {
        if (str_contains($html, 'id="'.substr($new, 1).'"')) {
            $html = str_replace('href="'.$old.'"', 'href="'.$new.'"', $html);
        }
    }
    if (str_contains($html, 'href="#index_') && !str_contains($html, 'id="index_')) {
        $html = preg_replace('/^<div class="qindex">[^\r\n]*<\/div>\r?\n/m', '', $html);
    }
    file_put_contents($file, $html);
    preg_match_all('/\b(?:id|name)="([^"]*)"/', $html, $ids);
    $pages[$file] = ['html' => $html, 'ids' => array_fill_keys($ids[1], true)];
}
$errors = [];
foreach ($pages as $file => $page) {
    preg_match_all('/\b(?:href|src|data)="([^"]*)"/', $page['html'], $links);
    foreach ($links[1] as $link) {
        $link = html_entity_decode($link, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($link === '' || preg_match('/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i', $link)) { continue; }
        $url = parse_url($link);
        $path = rawurldecode($url['path'] ?? '');
        $target = $path === '' ? $file : realpath(dirname($file).'/'.$path);
        if ($target === false || !file_exists($target)) {
            $errors[] = basename($file).': missing local resource '.$link;
        } elseif (isset($url['fragment'], $pages[$target]) &&
            !isset($pages[$target]['ids'][rawurldecode($url['fragment'])])) {
            $errors[] = basename($file).': missing anchor '.$link;
        }
    }
}
if (!empty($errors)) {
    fwrite(STDERR, implode(PHP_EOL, array_unique($errors)).PHP_EOL);
    exit(1);
}
echo 'Verified '.count($pages)." HTML pages and their local links/assets.\n";
