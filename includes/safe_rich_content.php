<?php
/** Shared output boundary for authored rich content, including legacy encoded HTML. */
function qp_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function qp_rich_html(string $html): string {
    $html = trim($html);
    if ($html === '') return '';
    // Decode only recognizable legacy rich-text fragments, never arbitrary entities.
    // Each candidate still passes the complete allowlist below. Never decode the output.
    for ($i = 0; $i < 4; $i++) {
        if (preg_match('~<(pre|code)\b~i', $html)) break;
        $visible = trim(strip_tags($html));
        if (!preg_match('~^&(?:amp;)*lt;(p|div|span|font|h[1-6]|ul|ol|blockquote|strong|b|em|i)\b~i', $visible) ||
            !preg_match('~&(?:amp;)*lt;/[a-z][^;]*&(?:amp;)*gt;~i', $visible)) break;
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (!class_exists('DOMDocument')) return nl2br(qp_h($html)); // Fail closed.
    $plain = !preg_match('~</?[a-z][^>]*>~i', $html);
    $previous = libxml_use_internal_errors(true);
    try {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET);
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) return '';
        $allowed = ['p','br','strong','b','em','i','u','s','h1','h2','h3','h4','h5','h6','ul','ol','li','blockquote','span','div','a','pre','code','table','thead','tbody','tr','th','td','hr','sub','sup'];
        $drop = ['script','style','iframe','object','embed','svg','math','template','form','input','button','textarea','select','link','meta','base'];
        $render = function (DOMNode $node) use (&$render, $allowed, $drop, $plain): string {
            if ($node instanceof DOMText) return $plain ? nl2br(qp_h($node->nodeValue)) : qp_h($node->nodeValue);
            if (!($node instanceof DOMElement)) return '';
            $tag = strtolower($node->tagName);
            if (in_array($tag, $drop, true)) return '';
            $content = '';
            foreach ($node->childNodes as $child) $content .= $render($child);
            $styles = $node->getAttribute('style');
            if ($tag === 'font') {
                $styles .= ';color:' . $node->getAttribute('color') . ';font-family:' . $node->getAttribute('face');
                $tag = 'span';
            }
            if (!in_array($tag, $allowed, true)) return $content;
            $attributes = '';
            if ($tag === 'a') {
                $href = trim($node->getAttribute('href'));
                if (!preg_match('/[\x00-\x20\x7f]/', $href) && preg_match('~^(https?://|mailto:|#)~i', $href)) {
                    $attributes .= ' href="' . qp_h($href) . '" rel="noopener noreferrer"';
                }
            }
            $safe = [];
            foreach (explode(';', $styles) as $declaration) {
                $parts = explode(':', $declaration, 2);
                if (count($parts) !== 2) continue;
                [$property, $value] = array_map('trim', $parts);
                $property = strtolower($property);
                $patterns = [
                    'color' => '/^(#[0-9a-f]{3,8}|[a-z]{1,24}|rgba?\([0-9.,%\s]+\))$/i',
                    'background-color' => '/^(#[0-9a-f]{3,8}|[a-z]{1,24}|rgba?\([0-9.,%\s]+\))$/i',
                    'font-family' => '/^[a-z ,\x22\x27-]{1,100}$/i',
                    'font-size' => '/^([0-9]{1,2}(\.[0-9]+)?(px|pt)|[0-3](\.[0-9]+)?(em|rem)|small|medium|large)$/i',
                    'text-align' => '/^(left|right|center|justify)$/i',
                    'font-weight' => '/^(normal|bold|[1-9]00)$/i',
                    'font-style' => '/^(normal|italic)$/i',
                    'text-decoration' => '/^(none|underline|line-through)$/i'
                ];
                if (isset($patterns[$property]) && preg_match($patterns[$property], $value)) $safe[] = $property . ':' . $value;
            }
            if ($safe) $attributes .= ' style="' . qp_h(implode(';', $safe)) . '"';
            if (in_array($tag, ['br','hr'], true)) return '<' . $tag . '>';
            return '<' . $tag . $attributes . '>' . $content . '</' . $tag . '>';
        };
        $output = '';
        foreach ($body->childNodes as $node) $output .= $render($node);
        return trim($output);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}
