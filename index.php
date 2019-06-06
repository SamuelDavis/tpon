<?php

define('HTML_DIR', __DIR__ . '/html/');

function parseHeaderStyles(DOMNode $styleNode)
{
    $styles = explode("\n", $styleNode->textContent);
    return array_reduce($styles, function (array $styles, string $style) {
        $style = str_replace(['{', '}'], '', $style);
        $style = explode(' ', $style);
        $key = array_shift($style);
        $value = implode(' ', $style);

        return $key ? $styles + [$key => $value] : $styles;
    }, []);
}

class Paragraph implements IteratorAggregate
{
    const TYPE_CHAPTER = 'chapter';
    const TYPE_HEADER = 'header';
    const TYPE_SUBHEADER = 'subHeader';
    const TYPE_TEXT = 'text';

    /** @var string */
    public $type;
    public $page;
    public $paragraph;
    public $text;

    public function __construct(string $type, int $page, int $paragraph, string $text)
    {
        $this->type = $type;
        $this->page = $page;
        $this->paragraph = $paragraph;
        $this->text = $text;
    }

    public function matches(Paragraph $other)
    {
        return $this->type === $other->type && $this->page === $other->page && $this->paragraph === $other->paragraph;
    }

    public function append(Paragraph $paragraph)
    {
        $this->text .= " {$paragraph->text}";
        return $this;
    }

    public function getIterator()
    {
        $text = $this->type === static::TYPE_TEXT ? $this->parseSentences() : [$this->text];
        foreach ($text as $line) {
            yield $line;
        }
    }

    function parseSentences()
    {
        $text = str_replace('" "', '";"', $this->text);

        $sentence = '';
        $inQuote = false;
        return array_reduce(str_split($text), function (array $sentences, string $chr) use (
            &$sentence,
            &$inQuote
        ) {
            if ($chr !== ';') {
                $sentence .= $chr;
            }

            if ($chr === '"') {
                $inQuote = !$inQuote;
            }

            if (!$inQuote && $chr && stristr('.?!;', $chr)) {
                $sentences[] = trim($sentence);
                $sentence = '';
            }

            return $sentences;
        }, []);
    }

}

$files = array_filter(scandir(HTML_DIR), function (string $filename) {
    return preg_match('/[0-9].*html$/', $filename);
});

usort($files, function (string $a, string $b) {
    $regex = '/[^0-9]/';
    return intval(preg_replace($regex, '', $a)) - intval(preg_replace($regex, '', $b));
});

$dom = new DOMDocument;
/** @var Paragraph[] $paragraphs */
$paragraphs = array_reduce($files, function (array $paragraphs, string $filename) use ($dom) {
    $page = intval(preg_replace('/[^0-9]/', '', $filename));

    $tmp = libxml_use_internal_errors(true);
    $dom->loadHTMLFile(HTML_DIR . $filename);
    libxml_use_internal_errors($tmp);

    /** @var DOMNode $styleNode */
    $styleNode = $dom->getElementsByTagName('style')[0];
    $headerStyles = parseHeaderStyles($styleNode);

    $divs = iterator_to_array($dom->getElementsByTagName('div'));
    return array_reduce($divs, function (array $paragraphs, DOMNode $div) use ($page, $headerStyles) {
        static $paragraph = 0;
        if (is_numeric($div->textContent)) {
            return $paragraphs;
        }

        $styles = implode(' ', [
            $div->attributes->getNamedItem('style')->textContent,
            $div->firstChild->attributes->getNamedItem('style')->textContent,
            $headerStyles["#{$div->firstChild->attributes->getNamedItem('id')->textContent}"] ?? '',
        ]);

        preg_match('/left:([0-9]+)px;/', $styles, $leftMargin);
        $leftMargin = intval(end($leftMargin));
        preg_match('/font-style:([^;]+);/', $styles, $fontStyle);
        $fontStyle = end($fontStyle);
        preg_match('/font-size:([0-9]+)px;/', $styles, $fontSize);
        $fontSize = intval(end($fontSize));
        preg_match('/color:rgba\(([^;]+)\);/', $styles, $color);
        $color = end($color);

        if ($leftMargin === 84) {
            $paragraph++;
        }

        if ($fontSize === 16) {
            $type = Paragraph::TYPE_CHAPTER;
        } elseif ($fontStyle === 'italic') {
            $type = Paragraph::TYPE_SUBHEADER;
        } elseif ($color === '0,0,255,1') {
            $type = Paragraph::TYPE_HEADER;
        } else {
            $type = Paragraph::TYPE_TEXT;
        }

        $current = new Paragraph($type, $page, $paragraph, $div->textContent);
        if ($last = array_pop($paragraphs)) {
            if ($last->matches($current)) {
                $current = $last->append($current);
            } else {
                array_push($paragraphs, $last);
            }
        }
        $paragraphs[] = $current;
        return $paragraphs;
    }, $paragraphs);
}, []);

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="data:;base64,iVBORwOKGO="/>
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>TPON</title>
</head>
<body>
<?php $chapter = 0 ?>
<?php foreach ($paragraphs as $paragraph): ?>
    <?php if ($paragraph->type === Paragraph::TYPE_CHAPTER): ?>
        <?php $chapter = intval(preg_replace('/[^0-9]/', '', $paragraph->text)) ?>
        <h1><?= $paragraph->text ?></h1>
    <?php elseif ($paragraph->type === Paragraph::TYPE_HEADER): ?>
        <h3><?= $paragraph->text ?></h3>
    <?php elseif ($paragraph->type === Paragraph::TYPE_SUBHEADER): ?>
        <h3><i><?= $paragraph->text ?></i></h3>
    <?php else: ?>
        <p>
            <?php foreach ($paragraph as $i => $text): ?>
                <span id="<?= implode('.', [$chapter, $paragraph->paragraph + 1, $i + 1]) ?>"><?= $text ?></span>
            <?php endforeach; ?>
        </p>
    <?php endif; ?>
<?php endforeach; ?>
</body>
</html>
