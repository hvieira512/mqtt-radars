<?php
function component($path, $data = [])
{
    extract($data, EXTR_SKIP);
    include __DIR__ . "/components/$path.php";
}

function modal($path, $data = []) {
    extract($data, EXTR_SKIP);
    include __DIR__ . "/modals/$path.php";
}