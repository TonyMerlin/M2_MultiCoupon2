<?php
/** @var \Merlin\MultiCoupon\Block\Cart\Coupons $block */
$appliedCodes = $block->getAppliedCodes();
$allowedCodes = $block->getAllowedCodes();
?>
<div class="block discount merlin-multi-coupon" style="margin-top:20px; padding:16px; border:1px solid #dcdcdc;">
    <div class="title"><strong><?= $block->escapeHtml(__('Apply multiple deal codes')) ?></strong></div>
    <div class="content">
        <p><?= $block->escapeHtml(__('Use this form for approved combined deal codes only: %1', implode(', ', $allowedCodes))) ?></p>

        <form class="form form-discount" action="<?= $block->escapeUrl($block->getAddUrl()) ?>" method="post">
            <?= /* @noEscape */ $block->getBlockHtml('formkey') ?>
            <div class="field">
                <label for="merlin_multi_coupon_code"><span><?= $block->escapeHtml(__('Coupon Code')) ?></span></label>
                <div class="control">
                    <input type="text" name="coupon_code" id="merlin_multi_coupon_code" class="input-text" />
                </div>
            </div>
            <div class="actions-toolbar" style="margin-top:10px;">
                <div class="primary">
                    <button class="action apply primary" type="submit"><span><?= $block->escapeHtml(__('Add Deal Code')) ?></span></button>
                </div>
            </div>
        </form>

        <?php if ($appliedCodes): ?>
            <div class="merlin-multi-coupon__applied" style="margin-top:18px;">
                <strong><?= $block->escapeHtml(__('Applied Codes')) ?></strong>
                <ul style="margin-top:8px;">
                    <?php foreach ($appliedCodes as $code): ?>
                        <li style="margin-bottom:8px;">
                            <span><?= $block->escapeHtml($code) ?></span>
                            <form action="<?= $block->escapeUrl($block->getRemoveUrl()) ?>" method="post" style="display:inline-block; margin-left:10px;">
                                <?= /* @noEscape */ $block->getBlockHtml('formkey') ?>
                                <input type="hidden" name="coupon_code" value="<?= $block->escapeHtmlAttr($code) ?>" />
                                <button class="action action-delete" type="submit"><span><?= $block->escapeHtml(__('Remove')) ?></span></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <form action="<?= $block->escapeUrl($block->getClearUrl()) ?>" method="post">
                    <?= /* @noEscape */ $block->getBlockHtml('formkey') ?>
                    <button class="action secondary" type="submit"><span><?= $block->escapeHtml(__('Clear All Deal Codes')) ?></span></button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
