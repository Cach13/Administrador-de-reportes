<?php
// ========================================
// views/layouts/components/card.php
// Componente de tarjeta reutilizable
// ========================================

$cardClass = $cardClass ?? 'card';
$headerClass = $headerClass ?? 'card-header bg-primary text-white';
$bodyClass = $bodyClass ?? 'card-body';
$footerClass = $footerClass ?? 'card-footer bg-light';
?>

<div class="<?php echo $cardClass; ?>">
    <?php if (isset($title) || isset($actions)): ?>
    <div class="<?php echo $headerClass; ?>">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <?php if (isset($icon)): ?>
                    <i class="<?php echo $icon; ?> me-2"></i>
                <?php endif; ?>
                <?php echo htmlspecialchars($title ?? ''); ?>
            </h5>
            <?php if (isset($actions)): ?>
            <div class="card-actions">
                <?php echo $actions; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if (isset($subtitle)): ?>
        <small class="text-white-50 mt-1 d-block">
            <?php echo htmlspecialchars($subtitle); ?>
        </small>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="<?php echo $bodyClass; ?>">
        <?php 
        if (isset($content)) {
            echo $content;
        } elseif (isset($contentFile)) {
            include $contentFile;
        }
        ?>
    </div>

    <?php if (isset($footer)): ?>
    <div class="<?php echo $footerClass; ?>">
        <?php echo $footer; ?>
    </div>
    <?php endif; ?>
</div>
