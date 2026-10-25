<?php
if (!defined('ABSPATH')) {
    return;
}
$toggle_brand = ($args['brand'] ?? '') === 'unofficial' ? 'unofficial' : 'official';
$toggle_enabled = !empty($args['enabled']);
?>
<label class="invoice-branch-toggle is-full"><span class="invoice-branch-switch"><input type="checkbox" role="switch" name="setting_<?php echo esc_attr($toggle_brand); ?>_bottom_borders" value="1" <?php checked($toggle_enabled); ?>></span><span><strong>کادر توضیحات و جمع‌ها</strong><small>نمایش خطوط تفکیک توضیحات، اطلاعات پرداخت و جمع مبالغ در چاپ و PDF.</small></span></label>
