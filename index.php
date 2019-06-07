<?php

function array_group_by(array $array, string $key, string ...$args)
{
    if (!is_string($key) && !is_int($key) && !is_float($key) && !is_callable($key)) {
        trigger_error('array_group_by(): The key should be a string, an integer, or a callback', E_USER_ERROR);
        return null;
    }
    $func = (!is_string($key) && is_callable($key) ? $key : null);
    $_key = $key;
    // Load the new array, splitting by the target key
    $grouped = [];
    foreach ($array as $value) {
        $key = null;
        if (is_callable($func)) {
            $key = call_user_func($func, $value);
        } elseif (is_object($value) && property_exists($value, $_key)) {
            $key = $value->{$_key};
        } elseif (isset($value[$_key])) {
            $key = $value[$_key];
        }
        if ($key === null) {
            continue;
        }
        $grouped[$key][] = $value;
    }
    // Recursively build a nested grouping if more parameters are supplied
    // Each grouped array value is grouped according to the next sequential key
    if ($args) {
        foreach ($grouped as $key => $value) {
            $params = array_merge([$value], $args);
            $grouped[$key] = call_user_func_array('array_group_by', $params);
        }
    }
    return $grouped;
}

/**
 * @var ContentBlock[] $pages
 * @var ContentBlock[] $chapters
 * @var ContentBlock[] $headers
 * @var ContentBlock[] $subHeaders
 * @var ContentBlock[] $sentences
 */
extract((require __DIR__ . '/parseHtmlSentences.php')(__DIR__ . '/html'));

?>
<!doctype html>
<html lang="en">
<head>
    <link rel="icon" href="data:;base64,iVBORwOKGO="/>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>TPON</title>
    <link rel="stylesheet" href="index.css">
</head>
<body>
<?php
$page = null;
$chapter = null;
$header = null;
$subHeader = null;
?>
<?php foreach (array_group_by($sentences, 'paragraph') as $paragraph): $sentence = reset($paragraph) ?>
    <?php if ($page !== $sentence->page): $page = $sentence->page ?>
        <h5><?= $pages[$page] ?></h5>
    <?php endif; ?>
    <?php if ($chapter !== $sentence->chapter): $chapter = $sentence->chapter ?>
        <h1><?= $chapters[$chapter] ?></h1>
    <?php endif; ?>
    <?php if ($header !== $sentence->header): $header = $sentence->header ?>
        <h3><?= $headers[$header] ?></h3>
    <?php endif; ?>
    <?php if ($subHeader !== $sentence->subHeader): $subHeader = $sentence->subHeader ?>
        <h3><i><?= $subHeaders[$subHeader] ?></i></h3>
    <?php endif; ?>
    <p>
        <?php foreach ($paragraph as $i => $sentence): ?>
            <span id="_<?= implode('_',
                [$pages[$sentence->page], $sentence->paragraph + 1, $i + 1]) ?>"><?= $sentence ?></span>
        <?php endforeach; ?>
    </p>
<?php endforeach; ?>
<script type="application/javascript" src="index.js"></script>
</body>
</html>
