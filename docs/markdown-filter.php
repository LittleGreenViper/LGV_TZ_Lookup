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
    \brief This preserves repository Markdown while adapting links to non-API files for the generated HTML.

    Doxygen supplies the source filename as an argument. PHP source links are resolved by Doxygen itself. The demo
    shell script has a verbatim section in the documentation guide, and the small benchmark JSON is copied to HTML.
    We use HTML anchors for those two links, so Doxygen does not try to resolve them as undocumented API symbols.
    The original README and changelog are never modified by this filter.
*/
declare(strict_types = 1);

if ($argc !== 2 || !is_file($argv[1])) {
    fwrite(STDERR, "Expected a Markdown input filename.\n");
    exit(1);
}
$markdown = file_get_contents($argv[1]);
// Older README fences and note markers remain intact in GitHub; normalize them only for Doxygen's Markdown reader.
$markdown = preg_replace('/^```\((bash|php)\)\s*$/m', '```$1', $markdown);
$markdown = preg_replace('/^>(NOTE|CAUTION):/m', '> $1:', $markdown);
echo preg_replace_callback('~\[([^\]]+)\]\((docs/Doxyfile|deploy\.sh|demo/run\.sh|demo/demo\.php|tests/demo\.php|tests/benchmarks/query-2026-10-07\.json|tests/benchmarks/postgres-2026-10-09\.json)\)~',
    static function(array $match): string {
        $target = [
            'docs/Doxyfile' => 'documentation_guide.html#doxygen-configuration',
            'deploy.sh' => 'documentation_guide.html#deployment-command',
            'demo/run.sh' => 'documentation_guide.html#demo-command',
            'demo/demo.php' => 'demo_2demo_8php.html',
            'tests/demo.php' => 'tests_2demo_8php.html',
            'tests/benchmarks/query-2026-10-07.json' => 'query-2026-10-07.json',
            'tests/benchmarks/postgres-2026-10-09.json' => 'postgres-2026-10-09.json',
        ][$match[2]];
        return '<a href="'.$target.'">'.htmlspecialchars($match[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</a>';
    }, $markdown);
