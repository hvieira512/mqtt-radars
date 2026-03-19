<?php
function component($path, $data = [])
{
    extract($data, EXTR_SKIP);
    include __DIR__ . "/components/$path.php";
}
