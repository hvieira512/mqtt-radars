<?php

$color = $color ?? 'info';
$label = $label ?? 'Sugestão';
$contentId = $contentId ?? 'suggestion-content';
$icon = $icon ?? 'fa-brain';
$subtitle = $subtitle ?? 'Análise automática';

?>

<div class="card shadow-sm h-100 border-<?= $color ?>">
    <div class="card-body d-flex flex-column gap-3">
        
        <div class="d-flex align-items-center gap-3 border-bottom border-<?= $color ?> pb-3">
            <div class="bg-<?= $color ?> bg-opacity-10 rounded d-flex align-items-center justify-content-center" style="width: 2.5rem; height: 2.5rem;">
                <i class="fa-solid <?= $icon ?> text-<?= $color ?>"></i>
            </div>
            <div class="lh-sm">
                <div class="fw-semibold"><?= $label ?></div>
                <small class="text-muted"><?= $subtitle ?></small>
            </div>
        </div>

        <div class="d-flex flex-column gap-2 small text-muted" id="<?= $contentId ?>"></div>
    </div>
</div>