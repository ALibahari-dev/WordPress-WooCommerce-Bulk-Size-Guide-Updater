<?php

/*📄 داکیومنت فنی (Documentation)
عنوان پروژه: ابزار انتقال خودکار تصاویر توضیحات کوتاه به گالری ووکامرس
مسیر دسترسی (Route): https://noraste.com/wp-admin/admin.php?page=shortdesc_gallery_tool

۱. توضیحات کلی
این اسکریپت یک ابزار مدیریتی در پیشخوان وردپرس ایجاد می‌کند که وظیفه آن بررسی «توضیحات کوتاه» (Short Description) محصولات ووکامرس است. اگر تصویری در متن توضیحات یافت شود که قبلاً در «گالری تصاویر» محصول وجود نداشته باشد، اسکریپت به صورت خودکار آن تصویر را به گالری محصول اضافه می‌کند.

۲. ویژگی‌ها
رابط کاربری اختصاصی (UI): یک صفحه مدرن و واکنش‌گرا (Responsive) با استایل‌دهی اختصاصی.
حالت تست (Single Test): امکان تست روی یک محصول خاص (پیش‌فرض ID: 7023) جهت بررسی صحت عملکرد.
پردازش انبوه (Bulk Process): قابلیت اجرا روی تمامی محصولات منتشر شده با لغو محدودیت زمانی سرور (set_time_limit(0)) و بهینه‌سازی کش.
گزارش‌دهی (Logging): نمایش گزارش لحظه‌ای از عملیات، شامل تعداد تصاویر اضافه شده و محصولات بروزرسانی شده.
هوشمندی در یافتن: جستجوی تصاویر بر اساس نام فایل (Filename) به جای URL کامل که باعث پایداری بالاتر می‌شود.
۳. نحوه نصب و اجرا
کدهای زیر را در فایل functions.php قالب فرزند (Child Theme) یا در یک پلاگین اختصاصی قرار دهید.
وارد پیشخوان وردپرس شوید.
آدرس زیر را در مرورگر وارد کنید:
https://noraste.com/wp-admin/admin.php?page=shortdesc_gallery_tool
یکی از دکمه‌ها را برای شروع انتخاب کنید.
۴. نکات فنی
امنیت: استفاده از Nonce برای جلوگیری از درخواست‌های جعلی (CSRF) و بررسی سطح دسترسی manage_options.
پرفورمنس: در حالت "همه محصولات"، از تابع wp_suspend_cache_invalidation(true) استفاده شده تا سرعت پردازش بالا برود.
ریفرش صفحه: پس از زدن دکمه، صفحه رفرش شده و نتیجه در کادر "گزارش عملیات" نمایش داده می‌شود (مشکل صفحه سفید قبلی رفع شده است).

*/

add_action('admin_post_test_shortdesc_images_6945', function () {

/**
 * 
 Rute : https://noraste.com/wp-admin/admin.php?page=shortdesc_gallery_tool
افزودن منو به پیشخوان وردپرس
 */
add_action('admin_menu', 'sd_add_admin_menu');
function sd_add_admin_menu() {
    add_submenu_page(
        null, // مخفی کردن از منوی اصلی برای دسترسی مستقیم
        'انتقال تصاویر توضیحات به گالری',
        'انتقال تصاویر توضیحات به گالری',
        'manage_options',
        'shortdesc_gallery_tool',
        'sd_render_admin_page'
    );
}

/**
 * رندر کردن صفحه ابزار و هندل کردن فرم
 */
function sd_render_admin_page() {
    
    // متغیر برای نگه داشتن خروجی لاگ
    $log_output = '';
    $has_error = false;
    $show_log = false;

    // -------------------------------------------------------------
    // بررسی اینکه آیا فرم ارسال شده است یا خیر
    // -------------------------------------------------------------
    if (isset($_POST['sd_action']) && isset($_POST['sd_nonce'])) {
        
        // بررسی امنیتی
        if (!wp_verify_nonce($_POST['sd_nonce'], 'sd_process_action')) {
            wp_die('خطای امنیتی: Nonce معتبر نیست.');
        }

        if (!current_user_can('manage_options')) {
            wp_die('❌ دسترسی غیرمجاز');
        }

        $action = sanitize_text_field($_POST['sd_action']);
        $product_ids = [];

        // ---------------- انتخاب نوع عملیات ----------------

        // حالت 1: تست روی یک محصول خاص (ID 7023)
        if ($action === 'single') {
            $product_ids = [7023]; 
        }
        // حالت 2: اجرا روی همه محصولات
        elseif ($action === 'all') {
            // افزایش محدودیت زمان اجرا
            set_time_limit(0);
            ignore_user_abort(true); 
            // غیرفعال کردن کش برای سرعت بیشتر
            wp_suspend_cache_invalidation(true);

            $args = [
                'post_type'      => 'product',
                'posts_per_page' => -1, 
                'fields'         => 'ids', 
                'post_status'    => 'publish',
            ];
            $product_ids = get_posts($args);
        }

        // ---------------- شروع پردازش و گرفتن لاگ ----------------
        if (!empty($product_ids)) {
            ob_start(); // شروع ذخیره خروجی در حافظه
            sd_process_products($product_ids);
            $log_output = ob_get_clean(); // گرفتن خروجی و ریست کردن
            $show_log = true;
        }
    }

    ?>
    <!-- استایل‌ها -->
    <style>
        .sd-wrapper {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            font-family: Tahoma, sans-serif;
            direction: rtl;
            text-align: right;
        }
        .sd-header h1 {
            margin-top: 0;
            color: #2271b1;
            border-bottom: 2px solid #eee;
            padding-bottom: 15px;
        }
        .sd-card {
            background: #f9f9f9;
            border: 1px solid #eee;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .sd-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .sd-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .sd-btn-primary {
            background-color: #2271b1;
            color: #fff;
        }
        .sd-btn-primary:hover {
            background-color: #135e96;
        }
        .sd-btn-danger {
            background-color: #d63638;
            color: #fff;
        }
        .sd-btn-danger:hover {
            background-color: #b32d2e;
        }
        .sd-log {
            margin-top: 30px;
            padding: 20px;
            background: #f6f7f7;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            max-height: 500px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 13px;
            white-space: pre-wrap;
            display: none; /* به صورت پیش‌فرض مخفی */
        }
        .success { color: #00a32a; font-weight: bold; }
        .error { color: #d63638; font-weight: bold; }
        .warning { color: #dba617; }
    </style>

    <div class="sd-wrapper">
        <div class="sd-header">
            <h1>🛠️ ابزار انتقال تصاویر به گالری محصول</h1>
            <p>این ابزار تصاویر موجود در «توضیحات کوتاه» محصول را اسکرول کرده و اگر در گالری نباشند، اضافه می‌کند.</p>
        </div>

        <div class="sd-card">
            <h3>📋 وضعیت و تنظیمات:</h3>
            <p>لطفاً یکی از گزینه‌های زیر را برای شروع انتخاب کنید.</p>
            
            <form id="sdForm" method="post">
                <?php wp_nonce_field('sd_process_action', 'sd_nonce'); ?>
                
                <div class="sd-actions">
                    <!-- دکمه اجرا برای همه محصولات -->
                    <button type="submit" name="sd_action" value="all" class="sd-btn sd-btn-primary" onclick="return confirm('آیا مطمئن هستید؟ این عملیات ممکن است زمان‌بر باشد.')">
                        🚀 اجرا روی همه محصولات
                    </button>

                    <!-- دکمه تست روی یک آیدی خاص -->
                    <button type="submit" name="sd_action" value="single" class="sd-btn sd-btn-danger">
                        🧪 تست روی محصول ID: 7023
                    </button>
                </div>
            </form>
            
            <p style="margin-top:15px; font-size:12px; color:#666;">
                نکته: پس از کلیک، صفحه به صورت خودکار رفرش می‌شود و نتیجه عملیات در کادر پایین نمایش داده می‌شود.
            </p>
        </div>

        <!-- بخش لاگ خروجی -->
        <!-- اگر متغیر show_log true باشد، باکس را نمایش بده -->
        <div id="logOutput" class="sd-log" <?php echo $show_log ? 'style="display:block;"' : ''; ?>>
            <?php echo $log_output; ?>
        </div>
    </div>
    <?php
}

/**
 * تابع اصلی پردازش محصولات (منطق اسکریپت شما)
 */
function sd_process_products($product_ids) {
    echo '<div style="direction: rtl; font-family: Tahoma, sans-serif;">';
    echo '<h3>گزارش عملیات:</h3><hr>';

    $counter = 0;

    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);

        if (!$product) {
            echo "<span class='error'>❌ محصول ID $product_id پیدا نشد.</span><br>";
            continue;
        }

        $short_desc = $product->get_short_description();
        if (empty($short_desc)) {
            // نادیده گرفتن محصولات بدون توضیحات کوتاه برای تمیزی خروجی
            continue;
        }

        // پیدا کردن تصاویر در متن
        preg_match_all('/<img[^>]+src="([^"]+)"/i', $short_desc, $matches);
        if (empty($matches[1])) {
            continue;
        }

        $gallery = $product->get_gallery_image_ids();
        $added = [];
        $not_found = [];

        foreach ($matches[1] as $img_url) {
            $filename = basename(parse_url($img_url, PHP_URL_PATH));

            // جستجو بدون محدودیت allowed_images
            $attachment = get_posts([
                'post_type'      => 'attachment',
                'name'           => sanitize_title(pathinfo($filename, PATHINFO_FILENAME)),
                'posts_per_page' => 1,
                'post_status'    => 'inherit',
                'suppress_filters' => false,
            ]);

            if (!$attachment) {
                $not_found[] = $filename;
                continue;
            }

            $att_id = $attachment[0]->ID;

            if (!in_array($att_id, $gallery)) {
                $gallery[] = $att_id;
                $added[] = $filename;
            }
        }

        // ذخیره تغییرات
        if (!empty($added)) {
            $product->set_gallery_image_ids($gallery);
            $product->save();
            echo "<span class='success'>✅ محصول ID $product_id:</span> " . count($added) . " تصویر اضافه شد.<br>";
            $counter++;
        }
        
        // لاگ خطاها (اختیاری)
        /*
        if (!empty($not_found)) {
            echo "<span class='error'>⚠️ محصول ID $product_id:</span> " . count($not_found) . " تصویر پیدا نشد.<br>";
        }
        */
    }

    echo '<hr>';
    echo "<strong>پایان عملیات. تعداد محصولات بروزرسانی شده: $counter</strong>";
    echo '</div>';
}
?>