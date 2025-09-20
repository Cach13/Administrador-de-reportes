<?php
// ========================================
// views/layouts/components/table.php
// Componente de tabla reutilizable
// ========================================

$tableClass = $tableClass ?? 'table table-hover';
$tableId = $tableId ?? 'data-table';
$showSearch = $showSearch ?? true;
$showPagination = $showPagination ?? true;
?>

<div class="table-responsive">
    <?php if ($showSearch): ?>
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" placeholder="Buscar..." 
                       id="<?php echo $tableId; ?>-search">
            </div>
        </div>
        <div class="col-md-6 text-end">
            <?php if (isset($tableActions)): ?>
                <?php echo $tableActions; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <table class="<?php echo $tableClass; ?>" id="<?php echo $tableId; ?>">
        <?php if (isset($headers)): ?>
        <thead class="table-light">
            <tr>
                <?php foreach ($headers as $header): ?>
                <th scope="col" class="<?php echo $header['class'] ?? ''; ?>">
                    <?php if (isset($header['sortable']) && $header['sortable']): ?>
                    <a href="#" class="text-decoration-none text-dark sortable" 
                       data-column="<?php echo $header['key']; ?>">
                        <?php echo htmlspecialchars($header['label']); ?>
                        <i class="bi bi-chevron-expand ms-1 sort-icon"></i>
                    </a>
                    <?php else: ?>
                        <?php echo htmlspecialchars($header['label']); ?>
                    <?php endif; ?>
                </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <?php endif; ?>

        <tbody>
            <?php if (isset($data) && !empty($data)): ?>
                <?php foreach ($data as $row): ?>
                <tr>
                    <?php foreach ($headers as $header): ?>
                    <td class="<?php echo $header['class'] ?? ''; ?>">
                        <?php 
                        $value = $row[$header['key']] ?? '';
                        if (isset($header['formatter']) && is_callable($header['formatter'])) {
                            echo $header['formatter']($value, $row);
                        } else {
                            echo htmlspecialchars($value);
                        }
                        ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
            <tr>
                <td colspan="<?php echo count($headers ?? []); ?>" class="text-center py-4 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    No se encontraron registros
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($showPagination && isset($pagination)): ?>
    <nav aria-label="Table pagination">
        <ul class="pagination justify-content-center">
            <!-- Previous -->
            <li class="page-item <?php echo $pagination['current_page'] <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $pagination['current_page'] - 1; ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>

            <!-- Page numbers -->
            <?php 
            $start = max(1, $pagination['current_page'] - 2);
            $end = min($pagination['last_page'], $pagination['current_page'] + 2);
            ?>
            
            <?php for ($i = $start; $i <= $end; $i++): ?>
            <li class="page-item <?php echo $i === $pagination['current_page'] ? 'active' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>

            <!-- Next -->
            <li class="page-item <?php echo $pagination['current_page'] >= $pagination['last_page'] ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $pagination['current_page'] + 1; ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>

        <div class="text-center text-muted small mt-2">
            Mostrando <?php echo $pagination['from']; ?>-<?php echo $pagination['to']; ?> 
            de <?php echo $pagination['total']; ?> registros
        </div>
    </nav>
    <?php endif; ?>
</div>