<?php
if (!defined('ABSPATH') || !current_user_can('manage_options')) return;
?>
<dialog class="manager-application-delete-dialog no-print" data-application-delete-dialog
    aria-labelledby="application-delete-title" aria-describedby="application-delete-description">
    <h3 id="application-delete-title">حذف رزومه</h3>
    <p id="application-delete-description">این رزومه به زباله‌دان منتقل شود؟</p>
    <form method="dialog">
        <button type="submit" value="cancel" autofocus>انصراف</button>
        <button type="submit" value="delete">انتقال به زباله‌دان</button>
    </form>
</dialog>
