 <?php
 /**
 * Route:
 * https://exampel.com/wp-admin/admin-post.php?action=update_products
 */

add_action('admin_post_update_products', 'run_update_products_with_report');

function run_update_products_with_report() {

    if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }

    $start_time = microtime(true);

    // 🔹 محصولات
    // $product_ids = [7037, 7101];
// دریافت لیست تمام آی‌دی‌های محصولات منتشر شده
   if (!current_user_can('manage_options')) {
        wp_die('Access denied');
    }

    set_time_limit(0); // افزایش زمان اجرا برای تعداد بالای محصولات

    $product_ids = get_posts([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'posts_per_page' => -1,
    ]);

    $start_time = microtime(true);
    // 🔑 مپ تصویر → متن
    $size_text_map = [

        'name-img.webp' => '
		&nbsp;
<p>
Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. 
Ut enim ad minim veniam,quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. 
Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.
Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
</p>
        '
    ];

    $stats = [
        'total'   => count($product_ids),
        'updated' => 0,
        'skipped' => 0,
        'errors'  => 0,
    ];

    $timeline = [];

    foreach ($product_ids as $product_id) {

        $timeline[] = "🔍 بررسی محصول {$product_id}";

        $excerpt = get_post_field('post_excerpt', $product_id);

        if (!$excerpt) {
            $timeline[] = "❌ توضیحات کوتاه خالی است";
            $stats['errors']++;
            continue;
        }

        // حذف بلاک‌های قبلی
        $excerpt = preg_replace(
            '/<div class="size-guide">.*?<\/div>/si',
            '',
            $excerpt
        );

        // گرفتن همه تصاویر
        preg_match_all('/<img[^>]+src="([^">]+)"/i', $excerpt, $matches);

        if (empty($matches[1])) {
            $timeline[] = "❌ تصویری پیدا نشد";
            $stats['errors']++;
            continue;
        }

        $size_blocks = '';
        $found_any = false;

        foreach ($matches[1] as $img_src) {

            $img_name = basename($img_src);
            $timeline[] = "🖼 تصویر: {$img_name}";

            if (!isset($size_text_map[$img_name])) {
                $timeline[] = "⚠️ متنی برای این تصویر تعریف نشده";
                continue;
            }

            $size_blocks .= '<div class="size-guide">' . $size_text_map[$img_name] . '</div>';
            $found_any = true;
        }

        if (!$found_any) {
            $stats['skipped']++;
            continue;
        }

        $new_excerpt = trim($excerpt) . $size_blocks;

        wp_update_post([
            'ID'           => $product_id,
            'post_excerpt' => $new_excerpt,
        ]);

        $timeline[] = "✅ آپدیت شد (چند تصویر)";
        $stats['updated']++;
    }

    $duration = round(microtime(true) - $start_time, 2);
    ?>
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<title>گزارش بروزرسانی</title>

<style>
@import url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/fonts/webfonts/Vazirmatn-font-face.css');

* {
    font-family: Vazirmatn, Tahoma, sans-serif;
}

body {
    background:#f4f6f8;
    direction:rtl;
    padding:40px;
}

.box {
    max-width:900px;
    margin:auto;
    background:#fff;
    border-radius:14px;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
    padding:30px;
}

.stats {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:15px;
    margin:25px 0;
}

.stat {
    padding:15px;
    border-radius:10px;
    text-align:center;
    background:#f9fafb;
}

.timeline {
    background:#0f172a;
    color:#e5e7eb;
    padding:20px;
    border-radius:12px;
    font-family: Consolas, monospace;
    font-size:13px;
    white-space:pre-line;
    line-height:1.9;
}

/* ✅ فاصله بین خطوط متن سایزبندی */
.size-guide {
    margin-top:15px;
    padding:12px;
    background:#f8fafc;
    border-radius:10px;
    line-height:1.9;
}

.size-guide br {
    display:block;
    margin-bottom:6px;
}

.size-guide strong {
    display:block;
    margin-bottom:8px;
}

.size-guide small {
    display:block;
    margin-top:8px;
    color:#64748b;
}
</style>
</head>

<body>
<div class="box">

<h2>📦 گزارش اجرای بروزرسانی محصولات</h2>

⏱ مدت اجرا: <strong><?php echo esc_html($duration); ?> ثانیه</strong><br>
📅 تاریخ اجرا: <?php echo esc_html(date('Y-m-d H:i:s')); ?>

<div class="stats">
    <div class="stat">کل: <?php echo $stats['total']; ?></div>
    <div class="stat">✅ موفق: <?php echo $stats['updated']; ?></div>
    <div class="stat">⚠️ اسکیپ: <?php echo $stats['skipped']; ?></div>
    <div class="stat">❌ خطا: <?php echo $stats['errors']; ?></div>
</div>

<div class="timeline"><?php echo esc_html(implode("\n", $timeline)); ?></div>

</div>
</body>
</html>
<?php
exit;
}
