<?php
// ========================================
// views/layouts/components/stat_card.php
// Componente de tarjeta de estadística
// ========================================
?>

<div class="col-md-<?php echo $colSize ?? '3'; ?>">
    <div class="card border-0 shadow-sm h-100">
        <div class="card-body p-4">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0">
                    <div class="bg-<?php echo $color ?? 'primary'; ?> rounded-circle p-3 text-white">
                        <i class="<?php echo $icon ?? 'bi bi-bar-chart'; ?> fs-4"></i>
                    </div>
                </div>
                <div class="flex-grow-1 ms-3">
                    <h6 class="text-muted text-uppercase fw-semibold mb-1">
                        <?php echo htmlspecialchars($label ?? 'Estadística'); ?>
                    </h6>
                    <h3 class="mb-0 fw-bold">
                        <?php echo htmlspecialchars($value ?? '0'); ?>
                    </h3>
                    <?php if (isset($change)): ?>
                    <small class="text-<?php echo $change > 0 ? 'success' : 'danger'; ?>">
                        <i class="bi bi-<?php echo $change > 0 ? 'arrow-up' : 'arrow-down'; ?> me-1"></i>
                        <?php echo abs($change); ?>% vs mes anterior
                    </small>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (isset($description)): ?>
            <p class="text-muted mt-3 mb-0 small">
                <?php echo htmlspecialchars($description); ?>
            </p>
            <?php endif; ?>
        </div>
        <?php if (isset($link)): ?>
        <div class="card-footer bg-transparent border-0 pt-0">
            <a href="<?php echo $link['url']; ?>" class="btn btn-link text-<?php echo $color ?? 'primary'; ?> p-0 text-decoration-none">
                <?php echo htmlspecialchars($link['text']); ?>
                <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>