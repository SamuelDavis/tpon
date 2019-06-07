<?php

class ContentBlock
{
    const TYPE_PAGE = 'page';
    const TYPE_CHAPTER = 'chapter';
    const TYPE_SUBHEADER = 'subheader';
    const TYPE_HEADER = 'header';
    const TYPE_PARAGRAPH = 'paragraph';
    const TYPE_TEXT = 'text';
    const TYPES_INTER_PARAGRAPH = [self::TYPE_PARAGRAPH, self::TYPE_TEXT, self::TYPE_PAGE];

    public $type;
    public $text;
    public $page;
    public $chapter;
    public $header;
    public $subHeader;
    public $paragraph;

    public function __construct(string $type, string $text)
    {
        $this->type = $type;
        $this->text = $text;
    }

    public function matches(ContentBlock $block)
    {
        return ($this->type !== static::TYPE_PARAGRAPH && $this->type === $block->type)
            || ($this->type === static::TYPE_PARAGRAPH && $block->type === static::TYPE_TEXT);
    }

    public function __toString()
    {
        return $this->text;
    }
}

function compileOrderedFiles(string $dir)
{
    $files = array_reduce(scandir($dir), function (array $files, string $filename) use ($dir) {
        if (preg_match('/page[0-9]+\.html$/', $filename)) {
            array_push($files, $dir . DIRECTORY_SEPARATOR . $filename);
        }
        return $files;
    }, []);
    usort($files, function (string $a, string $b) {
        $regex = '/[^0-9]/';
        return intval(preg_replace($regex, '', $a)) - intval(preg_replace($regex, '', $b));
    });
    return $files;
}

function parseStyleTag(DOMNode $node)
{
    $styles = explode("\n", $node->textContent);
    return array_reduce($styles, function (array $styles, string $style) {
        $style = str_replace(['{', '}'], '', $style);
        $style = explode(' ', $style);
        $key = array_shift($style);
        $value = implode(' ', $style);

        return $key ? $styles + [$key => $value] : $styles;
    }, []);
}

function getContentType(DOMNode $node, array $headerStyles = [], ContentBlock $previous = null)
{
    $headerStyles = implode(' ', [
        $node->attributes->getNamedItem('style')->textContent,
        $node->firstChild->attributes->getNamedItem('style')->textContent,
        $headerStyles["#{$node->firstChild->attributes->getNamedItem('id')->textContent}"] ?? '',
    ]);

    preg_match('/left:([0-9]+)px;/', $headerStyles, $leftMargin);
    $leftMargin = intval(end($leftMargin));
    preg_match('/font-style:([^;]+);/', $headerStyles, $fontStyle);
    $fontStyle = end($fontStyle);
    preg_match('/font-size:([0-9]+)px;/', $headerStyles, $fontSize);
    $fontSize = intval(end($fontSize));
    preg_match('/color:rgba\(([^;]+)\);/', $headerStyles, $color);
    $color = end($color);

    if (!preg_match('/[^0-9]/', $node->textContent)) {
        return ContentBlock::TYPE_PAGE;
    }
    if ($fontSize === 16) {
        return ContentBlock::TYPE_CHAPTER;
    }
    if ($fontStyle === 'italic') {
        return ContentBlock::TYPE_SUBHEADER;
    }
    if ($color === '0,0,255,1') {
        return ContentBlock::TYPE_HEADER;
    }
    if ($leftMargin === 84) {
        return ContentBlock::TYPE_PARAGRAPH;
    }
    if ($previous && !in_array($previous->type, ContentBlock::TYPES_INTER_PARAGRAPH)) {
        return ContentBlock::TYPE_PARAGRAPH;
    }
    return ContentBlock::TYPE_TEXT;
}

function parseSentences(string $text)
{
    $text = str_replace('" "', '";"', $text);
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

return function (string $dir) {
    $files = compileOrderedFiles($dir);
    $dom = new DOMDocument;
    $previous = null;
    /** @var ContentBlock[] $blocks */
    $blocks = array_reduce($files, function (array $blocks, string $file) use (&$previous, $dom) {
        $libXmlErrorSetting = libxml_use_internal_errors(true);
        $dom->loadHTMLFile($file);
        libxml_use_internal_errors($libXmlErrorSetting);
        $headerStyles = parseStyleTag($dom->getElementsByTagName('style')[0]);
        $divs = iterator_to_array($dom->getElementsByTagName('div'));

        return array_reduce($divs, function (array $blocks, DOMNode $current) use (&$previous, $headerStyles) {
            $text = $current->textContent;
            $type = getContentType($current, $headerStyles, $previous);
            $current = new ContentBlock($type, $text);
            array_push($blocks, $previous = $current);
            return $blocks;
        }, $blocks);
    }, []);

    $pages = [];
    $chapters = [];
    $headers = [];
    $subHeaders = [];
    $paragraphs = [];

    $page = 1;
    $chapter = 0;
    $header = 0;
    $subHeader = 0;
    $sentence = 0;
    /** @var ContentBlock $paragraphBlock */
    $paragraphBlock = null;
    /** @var ContentBlock $previous */
    $previous = null;
    foreach ($blocks as $block) {
        if ($previous && $previous->matches($block)) {
            $previous->text .= " {$block->text}";
            $block = $previous;
        } elseif ($block->type === ContentBlock::TYPE_PAGE) {
            array_push($pages, $block);
            $page++;
        } elseif ($block->type === ContentBlock::TYPE_CHAPTER) {
            array_push($chapters, $block);
            $chapter++;
        } elseif ($block->type === ContentBlock::TYPE_HEADER) {
            array_push($headers, $block);
            $header++;
        } elseif ($block->type === ContentBlock::TYPE_SUBHEADER) {
            array_push($subHeaders, $block);
            $subHeader++;
        } elseif ($block->type === ContentBlock::TYPE_PARAGRAPH) {
            array_push($paragraphs, $paragraphBlock = $block);
            $sentence++;
            $paragraphBlock->page = $page - 1;
            $paragraphBlock->chapter = $chapter - 1;
            $paragraphBlock->header = $header - 1;
            $paragraphBlock->subHeader = $subHeader - 1;
            $paragraphBlock->paragraph = $sentence - 1;
        } elseif ($paragraphBlock) {
            $paragraphBlock->text .= " {$block->text}";
        }
        $previous = $block;
    }

    /** @var ContentBlock[] $sentences */
    $sentences = array_reduce($paragraphs, function (array $sentences, ContentBlock $block) {
        $sentencesInParagraph = parseSentences($block->text);
        return array_reduce($sentencesInParagraph, function (array $sentences, string $sentence) use ($block) {
            $sentence = new ContentBlock(ContentBlock::TYPE_TEXT, $sentence);
            $sentence->page = $block->page;
            $sentence->chapter = $block->chapter;
            $sentence->header = $block->header;
            $sentence->subHeader = $block->subHeader;
            $sentence->paragraph = $block->paragraph;
            array_push($sentences, $sentence);
            return $sentences;
        }, $sentences);
    }, []);

    return compact('pages', 'chapters', 'headers', 'subHeaders', 'paragraphs', 'sentences');
};
