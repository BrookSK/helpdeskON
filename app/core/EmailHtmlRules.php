<?php
/**
 * Regras puras (sem banco/HTTP) do HTML dos e-mails de prospecção.
 *
 * Problemas que resolve:
 *  - o texto formatado no editor (negrito, itálico, alinhamento, recuo) usa
 *    classes do Quill ("ql-align-center"), que os clientes de e-mail ignoram:
 *    a formatação sumia. Aqui as classes viram estilo inline;
 *  - templates antigos em texto puro chegavam com as quebras de linha perdidas
 *    (o e-mail é HTML). Texto puro vira parágrafos;
 *  - o e-mail saía "cru", sem o visual do sistema. wrapLayout() envolve o corpo
 *    no mesmo layout dos e-mails do Helpdesk (fundo cinza, cartão branco, logo),
 *    em tabelas e estilo inline (o que Gmail/Outlook entendem);
 *  - HTML colado de fora pode trazer script/eventos: sanitize() remove.
 */
class EmailHtmlRules
{
    public const LAYOUT_MARKER = 'data-onsolu-layout';
    private const FONT = "'Poppins', Arial, Helvetica, sans-serif";

    /** O texto já é HTML (tem alguma tag)? */
    public static function isHtml(?string $s): bool
    {
        return (bool) preg_match('/<\s*(p|br|div|span|strong|b|em|i|u|a|ul|ol|li|h[1-6]|table|img|blockquote)\b[^>]*>/i', (string) $s);
    }

    /** Texto puro → HTML (escapa e mantém parágrafos e quebras de linha). */
    public static function textToHtml(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        if ($text === '') return '';
        $paras = preg_split("/\n{2,}/", $text);
        return implode('', array_map(
            fn($p) => '<p>' . nl2br(htmlspecialchars($p, ENT_QUOTES, 'UTF-8'), false) . '</p>',
            $paras
        ));
    }

    /**
     * Remove o que não pode ir num e-mail: script/style/iframe/objetos/form,
     * atributos de evento (onclick...) e links javascript:.
     */
    public static function sanitize(string $html): string
    {
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select|meta|link|base)\b[^>]*>.*?</\1\s*>#is', '', $html);
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select|meta|link|base)\b[^>]*/?>#is', '', $html);
        $html = preg_replace('#\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
        $html = preg_replace('#(href|src)\s*=\s*(["\'])\s*(javascript|vbscript|data:text/html)[^"\']*\2#i', '$1="#"', $html);
        return $html;
    }

    /**
     * Classes do editor (Quill) → estilo inline, para o e-mail manter a
     * formatação. Também dá espaçamento de parágrafo igual ao do editor.
     */
    public static function inlineEditorStyles(string $html): string
    {
        $map = function (string $classes): string {
            $style = [];
            foreach (preg_split('/\s+/', trim($classes)) as $c) {
                if (preg_match('/^ql-align-(center|right|justify)$/', $c, $m)) $style[] = 'text-align:' . $m[1];
                elseif (preg_match('/^ql-indent-(\d)$/', $c, $m)) $style[] = 'padding-left:' . ((int) $m[1] * 3) . 'em';
                elseif ($c === 'ql-size-small') $style[] = 'font-size:12px';
                elseif ($c === 'ql-size-large') $style[] = 'font-size:20px';
                elseif ($c === 'ql-size-huge') $style[] = 'font-size:28px';
                elseif ($c === 'ql-font-serif') $style[] = 'font-family:Georgia,serif';
                elseif ($c === 'ql-font-monospace') $style[] = 'font-family:monospace';
            }
            return implode(';', $style);
        };

        // class="ql-..." → style (somando ao style existente, se houver).
        $html = preg_replace_callback('#<([a-z0-9]+)\b([^>]*?)\sclass\s*=\s*"([^"]*)"([^>]*)>#i', function ($m) use ($map) {
            $extra = $map($m[3]);
            $attrs = $m[2] . $m[4];
            if ($extra === '') return '<' . $m[1] . $attrs . '>';
            if (preg_match('#\sstyle\s*=\s*"([^"]*)"#i', $attrs, $s)) {
                $attrs = str_replace($s[0], ' style="' . rtrim($s[1], '; ') . ';' . $extra . '"', $attrs);
                return '<' . $m[1] . $attrs . '>';
            }
            return '<' . $m[1] . $attrs . ' style="' . $extra . '">';
        }, $html);

        // Estilo padrão por tag (só onde não há style próprio).
        $defaults = [
            'p' => 'margin:0 0 12px 0',
            'h1' => 'margin:0 0 12px 0;font-size:24px;line-height:1.3;color:#111',
            'h2' => 'margin:0 0 12px 0;font-size:20px;line-height:1.3;color:#111',
            'h3' => 'margin:0 0 10px 0;font-size:17px;line-height:1.3;color:#111',
            'ul' => 'margin:0 0 12px 0;padding-left:24px',
            'ol' => 'margin:0 0 12px 0;padding-left:24px',
            'li' => 'margin:0 0 4px 0',
            'blockquote' => 'margin:0 0 12px 0;padding:8px 14px;border-left:3px solid #00BFA6;color:#555;background:#f7faf9',
            'a' => 'color:#00997D',
            'img' => 'max-width:100%;height:auto',
        ];
        foreach ($defaults as $tag => $style) {
            $html = preg_replace_callback('#<' . $tag . '\b([^>]*)>#i', function ($m) use ($tag, $style) {
                if (preg_match('#\sstyle\s*=\s*"([^"]*)"#i', $m[1], $s)) {
                    if (strpos($s[1], $style) === 0) return $m[0]; // já aplicado (idempotente)
                    return '<' . $tag . str_replace($s[0], ' style="' . $style . ';' . $s[1] . '"', $m[1]) . '>';
                }
                return '<' . $tag . $m[1] . ' style="' . $style . '">';
            }, $html);
        }
        // Linha vazia do editor (<p><br></p>) vira um respiro discreto.
        $html = preg_replace('#<p style="margin:0 0 12px 0"><br\s*/?></p>#i', '<p style="margin:0 0 12px 0;line-height:12px">&nbsp;</p>', $html);
        return $html;
    }

    /** Corpo pronto para o e-mail: texto puro vira HTML; HTML é limpo e estilizado. */
    public static function prepareBody(?string $body): string
    {
        $body = (string) $body;
        if (strpos($body, self::LAYOUT_MARKER) !== false) return $body; // já pronto
        $html = self::isHtml($body) ? self::sanitize($body) : self::textToHtml($body);
        return self::inlineEditorStyles($html);
    }

    /**
     * Envolve o corpo no layout dos e-mails do sistema (mesmo visual do
     * Mailer::template do Helpdesk), em tabelas e estilo inline.
     * Idempotente: corpo que já tem o layout volta igual.
     *
     * @param array $opts logo_url, brand ('ON SOLUTIONS'), footer, preheader
     */
    public static function wrapLayout(string $bodyHtml, array $opts = []): string
    {
        if (strpos($bodyHtml, self::LAYOUT_MARKER) !== false) return $bodyHtml;
        $esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $font = self::FONT;
        $logo = trim((string) ($opts['logo_url'] ?? ''));
        $footer = trim((string) ($opts['footer'] ?? ('ON Solutions Brasil © ' . date('Y'))));
        $pre = trim((string) ($opts['preheader'] ?? ''));

        $header = $logo !== ''
            ? '<img src="' . $esc($logo) . '" alt="ON Solutions Brasil" style="max-height:56px;max-width:200px;border:0;display:inline-block;">'
            : '<span style="font-family:' . $font . ';color:#00BFA6;font-weight:700;font-size:22px;">ON</span>'
              . '<span style="font-family:' . $font . ';font-weight:600;font-size:18px;color:#000;"> SOLUTIONS</span>';

        return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">'
            . '</head><body style="margin:0;padding:0;background:#f5f7fa;">'
            . ($pre !== '' ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . $esc($pre) . '</div>' : '')
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" ' . self::LAYOUT_MARKER . '="1" style="background:#f5f7fa;">'
            . '<tr><td align="center" style="padding:24px 12px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.05);">'
            . '<tr><td align="center" style="padding:28px 32px 8px;border-bottom:1px solid #f0f0f0;">' . $header . '</td></tr>'
            . '<tr><td style="padding:24px 32px 28px;font-family:' . $font . ';font-size:15px;line-height:1.65;color:#333333;">'
            . $bodyHtml
            . '</td></tr>'
            . '</table>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;">'
            . '<tr><td align="center" style="padding:14px 12px 0;font-family:' . $font . ';font-size:12px;color:#9aa0a6;">' . $esc($footer) . '</td></tr>'
            . '</table>'
            . '</td></tr></table></body></html>';
    }
}
