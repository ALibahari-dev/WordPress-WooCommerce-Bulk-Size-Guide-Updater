<?php
/**
 * Plugin Name: SD Image to Gallery Tool
 * Plugin URI:  https://noraste.com/wp-admin/admin.php?page=shortdesc_gallery_tool
 * Description: ابزار انتقال خودکار تصاویر توضیحات کوتاه به گالری ووکامرس (نسخه اصلاح شده GUID).
 * Version:     1.2.0
 * Author:      Nora Dev
 * Text Domain: sd-gallery-tool
 */

/**
 * افزودن منو به پیشخوان وردپرس
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
 * تابع اصلی پردازش محصولات (نسخه اصلاح شده با جستجو در GUID)
 * این نسخه برای حل مشکل فایل‌هایی که نامش در عنوان (Title) تغییر کرده است، جستجو در URL انجام می‌دهد.
 */
function sd_process_products($product_ids) {
    global $wpdb; // دسترسی به دیتابیس برای جستجوی دقیق

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
            continue;
        }

        // Regex جدید: پشتیبانی از src="..." و src='...'
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $short_desc, $matches);
        
        if (empty($matches[1])) {
            continue;
        }

        $gallery = $product->get_gallery_image_ids();
        $added = [];
        $not_found = [];

        foreach ($matches[1] as $img_url) {
            $filename = basename(parse_url($img_url, PHP_URL_PATH));
            $path_info = pathinfo($filename);
            $name_only = isset($path_info['filename']) ? sanitize_title($path_info['filename']) : sanitize_title($filename);
            
            $att_id = null;

            // --- روش 1: جستجو با Slug دقیق (سریع) ---
            $attachment = get_posts([
                'post_type'      => 'attachment',
                'name'           => sanitize_title($filename),
                'posts_per_page' => 1,
                'post_status'    => 'inherit',
                'suppress_filters' => false,
            ]);

            if ($attachment) {
                $att_id = $attachment[0]->ID;
            }

            // --- روش 2: جستجو با Slug بدون پسوند (Fallback) ---
            if (!$att_id) {
                $attachment = get_posts([
                    'post_type'      => 'attachment',
                    'name'           => $name_only,
                    'posts_per_page' => 1,
                    'post_status'    => 'inherit',
                    'suppress_filters' => false,
                ]);
                if ($attachment) {
                    $att_id = $attachment[0]->ID;
                }
            }

            // --- روش 3: جستجوی قدرتمند در آدرس فایل (GUID) ---
            // این روش مشکلی مثل اختلاف نام فایل و عنوان را حل می‌کند
            // مثلا اگر فایل 6-months-1.webp است اما عنوانش 6-months است
            if (!$att_id) {
                // جستجوی مستقیم در دیتابیس برای نام فایل در ستون guid
                $att_id = $wpdb->get_var( $wpdb->prepare( 
                    "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' AND guid LIKE %s LIMIT 1", 
                    '%' . $wpdb->esc_like($filename) . '%' 
                ) );
            }

            // اگر همه روش‌ها شکست خوردند
            if (!$att_id) {
                $not_found[] = $filename;
                continue;
            }

            // اگر عکس قبلاً در گالری نبود، اضافه کن
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
        
        // نمایش لیست عکس‌هایی که پیدا نشدند (برای بررسی)
        if (!empty($not_found)) {
            echo "<div style='margin-top:5px; padding:5px; background:#fff3cd; border-radius:3px;'>";
            echo "<span class='warning'>⚠️ محصول ID $product_id:</span> " . count($not_found) . " عکس پیدا نشد:<br>";
            echo "<ul style='font-size:11px; color:#555; margin-right:20px;'>";
            foreach (array_slice($not_found, 0, 5) as $miss_file) {
                echo "<li>$miss_file</li>";
            }
            if (count($not_found) > 5) {
                echo "<li>... و " . (count($not_found) - 5) . " مورد دیگر.</li>";
            }
            echo "</ul></div>";
        }
    }

    echo '<hr>';
    echo "<strong>پایان عملیات. تعداد محصولات بروزرسانی شده: $counter</strong>";
    echo '</div>';
}
?>