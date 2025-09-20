<?php
// ========================================
// views/layouts/components/form.php
// Componente de formulario reutilizable
// ========================================

$formClass = $formClass ?? '';
$formMethod = $formMethod ?? 'POST';
$formAction = $formAction ?? '';
?>

<form class="<?php echo $formClass; ?>" method="<?php echo $formMethod; ?>" 
      action="<?php echo $formAction; ?>" <?php echo $formAttributes ?? ''; ?>>
    
    <!-- CSRF Token -->
    <input type="hidden" name="csrf_token" value="<?php echo App\Utils\ResponseHelper::generateCSRFToken(); ?>">
    
    <?php if (isset($formFields)): ?>
        <?php foreach ($formFields as $field): ?>
            <div class="<?php echo $field['containerClass'] ?? 'mb-3'; ?>">
                <?php if (isset($field['label'])): ?>
                <label for="<?php echo $field['name']; ?>" class="form-label">
                    <?php echo htmlspecialchars($field['label']); ?>
                    <?php if ($field['required'] ?? false): ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>
                <?php endif; ?>

                <?php 
                $fieldType = $field['type'] ?? 'text';
                $fieldClass = $field['class'] ?? 'form-control';
                $fieldValue = $field['value'] ?? '';
                $fieldName = $field['name'];
                $fieldId = $field['id'] ?? $fieldName;
                ?>

                <?php if ($fieldType === 'select'): ?>
                    <select name="<?php echo $fieldName; ?>" id="<?php echo $fieldId; ?>" 
                            class="<?php echo $fieldClass; ?>" <?php echo $field['attributes'] ?? ''; ?>>
                        <?php if (isset($field['placeholder'])): ?>
                            <option value=""><?php echo htmlspecialchars($field['placeholder']); ?></option>
                        <?php endif; ?>
                        <?php if (isset($field['options'])): ?>
                            <?php foreach ($field['options'] as $option): ?>
                                <option value="<?php echo htmlspecialchars($option['value']); ?>"
                                    <?php echo $option['value'] == $fieldValue ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($option['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>

                <?php elseif ($fieldType === 'textarea'): ?>
                    <textarea name="<?php echo $fieldName; ?>" id="<?php echo $fieldId; ?>" 
                              class="<?php echo $fieldClass; ?>" <?php echo $field['attributes'] ?? ''; ?>
                              placeholder="<?php echo htmlspecialchars($field['placeholder'] ?? ''); ?>"><?php echo htmlspecialchars($fieldValue); ?></textarea>

                <?php elseif ($fieldType === 'file'): ?>
                    <input type="file" name="<?php echo $fieldName; ?>" id="<?php echo $fieldId; ?>" 
                           class="<?php echo $fieldClass; ?>" <?php echo $field['attributes'] ?? ''; ?>>

                <?php else: ?>
                    <input type="<?php echo $fieldType; ?>" name="<?php echo $fieldName; ?>" id="<?php echo $fieldId; ?>" 
                           class="<?php echo $fieldClass; ?>" value="<?php echo htmlspecialchars($fieldValue); ?>"
                           placeholder="<?php echo htmlspecialchars($field['placeholder'] ?? ''); ?>"
                           <?php echo $field['attributes'] ?? ''; ?>>
                <?php endif; ?>

                <?php if (isset($field['help'])): ?>
                <div class="form-text">
                    <?php echo htmlspecialchars($field['help']); ?>
                </div>
                <?php endif; ?>

                <?php if (isset($field['error'])): ?>
                <div class="invalid-feedback d-block">
                    <?php echo htmlspecialchars($field['error']); ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (isset($formButtons)): ?>
    <div class="<?php echo $buttonsContainerClass ?? 'd-flex justify-content-end gap-2'; ?>">
        <?php foreach ($formButtons as $button): ?>
            <button type="<?php echo $button['type'] ?? 'button'; ?>" 
                    class="<?php echo $button['class'] ?? 'btn btn-primary'; ?>"
                    <?php echo $button['attributes'] ?? ''; ?>>
                <?php if (isset($button['icon'])): ?>
                    <i class="<?php echo $button['icon']; ?> me-1"></i>
                <?php endif; ?>
                <?php echo htmlspecialchars($button['text']); ?>
            </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</form>