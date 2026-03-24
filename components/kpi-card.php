<?php
$label = $label ?? 'KPI';
$unit = $unit ?? '';
$value = $value ?? '0';
$icon = $icon ?? 'fa-user';
$color = $color ?? 'primary';

$id = $id ?? null;
$statId = $statId ?? null;
$meta = $meta ?? null;
$metaId = $metaId ?? null;
$tooltip = $tooltip ?? null;
?>

<div class="card shadow-sm" <?= $id ? "id='$id'" : '' ?>>
    <div class="card-body pb-2">

        <div class="d-flex align-items-center gap-2 mb-2">
            <div class="bg-<?= $color ?> bg-opacity-10 rounded d-flex align-items-center justify-content-center" style="width: 2rem; height: 2rem;">
                <i class="fa-solid <?= $icon ?> text-<?= $color ?>"></i>
            </div>
            <span class="fw-semibold text-muted"><?= $label ?></span>
            <?php if ($tooltip): ?>
                <span role="button" class="d-inline-flex justify-content-center align-items-center bg-primary rounded-circle" style="min-width: 1.25rem; min-height: 1.25rem;" data-bs-toggle="tooltip" data-bs-placement="top" title="<?= htmlspecialchars($tooltip) ?>">
                    <i class="fa-solid fa-question text-white mx-auto" style="font-size: 10px;"></i>
                </span>
            <?php endif; ?>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div class="fs-4 fw-semibold" <?= $statId ? "id='$statId'" : '' ?>>
                <?= $value ?> <?= $unit ?>
            </div>

            <?php if ($meta): ?>
                <div
                    class="text-end small lh-sm text-muted"
                    <?= $metaId ? "id='$metaId'" : '' ?>>
                    <?= $meta ?>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>
