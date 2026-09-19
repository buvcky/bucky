<?php
session_start();

// تحديد مسار حفظ البيانات (سيتم إنشاء مجلد data تلقائياً لحفظ الروابط)
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0777, true);
}

// مسار الرابط المطلوب
$uri = $_SERVER['REQUEST_URI'];
$isApiSave = isset($_GET['api']) && $_GET['api'] == 'save';
$isOembed = isset($_GET['oembed']) && $_GET['oembed'] == '1';

// استخراج الاسم المختصر (Slug) من الرابط إذا كان مثل /p/Name أو ?p=Name
$slug = null;
if (isset($_GET['p'])) {
    $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['p']);
} elseif (preg_match('/\/p\/([a-zA-Z0-9_-]+)/', $uri, $matches)) {
    $slug = $matches[1];
}

// 1. معالجة طلب الحفظ وإنشاء الرابط القصير
if ($isApiSave && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        header('HTTP/1.1 400 Bad Request');
        die(json_encode(['error' => 'Invalid data']));
    }
    
    // إنشاء أو استخدام الاسم المخصص
    $customSlug = (isset($data['slug']) && trim($data['slug']) !== '') ? 
                  preg_replace('/[^a-zA-Z0-9_-]/', '', trim($data['slug'])) : 
                  substr(md5(uniqid()), 0, 6);
                  
    // حفظ البيانات في ملف JSON
    file_put_contents("$dataDir/$customSlug.json", json_encode($data));
    
    // إعداد الرابط النهائي الذي سيتم إرجاعه للمستخدم
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/');
    
    // الرابط النهائي المباشر
    $link = "$protocol://$host$basePath/p/$customSlug";
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'slug' => $customSlug, 'link' => $link]);
    exit;
}

// 2. إذا تم طلب رابط قصير موجود، نقوم بإظهار بيانات الإيمبيد لديسكورد
if ($slug) {
    $filePath = "$dataDir/$slug.json";
    
    if (!file_exists($filePath)) {
        die("<h1>404 Not Found</h1><p>هذا الرابط غير موجود أو تم حذفه.</p>");
    }
    
    $embedData = json_decode(file_get_contents($filePath), true);
    
    // إذا كان الطلب للحصول على بيانات oEmbed JSON
    if ($isOembed) {
        $oembed = [
            "version" => "1.0",
            "type" => "link",
            "title" => $embedData['title'] ?? "",
            "description" => $embedData['description'] ?? "",
            "author_name" => $embedData['author'] ?? "",
            "author_url" => $embedData['authorUrl'] ?? ""
        ];
        
        if (!empty($embedData['color'])) {
            $oembed['theme_color'] = $embedData['color'];
        }
        
        // بناء الأزرار V2
        if (isset($embedData['buttons']) && is_array($embedData['buttons']) && count($embedData['buttons']) > 0) {
            $buttons = [];
            foreach ($embedData['buttons'] as $btn) {
                $button = [
                    "type" => 2,
                    "style" => 5,
                    "label" => $btn['label'] ?? "Click",
                    "url" => $btn['url'] ?? "#"
                ];
                if (!empty($btn['emoji'])) {
                    $button['emoji'] = ["name" => $btn['emoji']];
                }
                $buttons[] = $button;
            }
            $oembed["components"] = [
                [
                    "type" => 1,
                    "components" => $buttons
                ]
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($oembed);
        exit;
    }
    
    // 3. بناء صفحة HTML الوهمية التي يقرأها ديسكورد للرابط القصير
    $title = htmlspecialchars($embedData['title'] ?? "bucky.gg");
    $desc = htmlspecialchars($embedData['description'] ?? "");
    $color = htmlspecialchars($embedData['color'] ?? "#616afc");
    $img = htmlspecialchars($embedData['thumbnail'] ?? $embedData['banner'] ?? "");
    
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $basePath = rtrim(dirname($_SERVER['PHP_SELF']), '/');
    $oembedUrl = "$protocol://$host$basePath/index.php?p=$slug&oembed=1";
    
    echo "<!DOCTYPE html>\n<html lang='ar'>\n<head>\n";
    echo "    <meta charset=\"UTF-8\">\n";
    echo "    <title>$title</title>\n";
    echo "    <meta property=\"og:title\" content=\"$title\">\n";
    echo "    <meta property=\"og:description\" content=\"$desc\">\n";
    echo "    <meta name=\"theme-color\" content=\"$color\">\n";
    if ($img) {
        echo "    <meta property=\"og:image\" content=\"$img\">\n";
        echo "    <meta name=\"twitter:card\" content=\"summary_large_image\">\n";
    }
    echo "    <link rel=\"alternate\" type=\"application/json+oembed\" href=\"$oembedUrl\" title=\"$title\">\n";
    // توجيه الزائر العادي إلى الصفحة الرئيسية إذا دخل الرابط بمتصفح عادي
    echo "    <script>setTimeout(function(){ window.location.href = '/'; }, 1500);</script>\n";
    echo "</head>\n<body style='font-family:sans-serif; text-align:center; padding-top:50px;'>\n";
    echo "    <h2>جاري التوجيه...</h2>\n";
    echo "</body>\n</html>";
    exit;
}
?>

<!-- ========================================== -->
<!-- واجهة المستخدم (الفرونت إند) للموقع الرئيسي -->
<!-- ========================================== -->
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>bucky.gg | منصة توليد الإيمبيد والأزرار المباشرة</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        cairo: ['Cairo', 'sans-serif'],
                        discord: ['Inter', 'sans-serif']
                    },
                    colors: {
                        brand: { DEFAULT: '#616afc', hover: '#4f56e2', light: '#f4f6fb' },
                        discord: { bg: '#313338', embedBg: '#2b2d31', border: '#1e1f22', textLight: '#dbdee1', textMuted: '#949ba4' }
                    }
                }
            }
        }
    </script>
    <style>
        body { background-color: #f4f6fb; font-family: 'Cairo', sans-serif; }
        .accordion-content { transition: max-height 0.3s ease-in-out; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f4f6fb; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #616afc; }
        .markdown-preview strong { font-weight: 700; color: #fff; }
        .markdown-preview em { font-style: italic; }
        .markdown-preview code { background: #1e1f22; padding: 2px 4px; border-radius: 3px; font-family: monospace; font-size: 0.85em; }
        .markdown-preview a { color: #00a8fc; text-decoration: underline; }
    </style>
</head>
<body class="text-gray-800 flex flex-col min-h-screen selection:bg-[#616afc] selection:text-white">

    <!-- Header -->
    <header class="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-[#616afc] flex items-center justify-center text-white font-bold text-xl shadow-md">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div>
                    <h1 class="text-xl font-black text-gray-800 tracking-wide">bucky<span class="text-[#616afc]">.gg</span></h1>
                    <p class="text-[10px] text-gray-500 font-bold bg-gray-100 px-2 py-0.5 rounded-md w-fit">V2.5 PRO</p>
                </div>
            </div>
            
            <a href="https://discord.gg" target="_blank" class="text-sm font-bold text-gray-500 hover:text-[#616afc] transition flex items-center gap-2">
                <i class="fa-brands fa-discord"></i> مجتمعنا
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-4 md:p-6 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        
        <!-- RIGHT SIDE: Tools & Menus (Sidebar) -->
        <section class="lg:col-span-4 space-y-4">
            
            <!-- Generate Link Box (Always visible at top) -->
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-4 space-y-3 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-1 h-full bg-[#616afc]"></div>
                <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-link text-[#616afc]"></i> توليد الرابط المباشر
                </h3>
                
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 mb-1">الاسم المخصص للرابط (اختياري)</label>
                    <div class="flex items-center" dir="ltr">
                        <span class="bg-gray-100 border border-r-0 border-gray-300 rounded-l-lg px-3 py-2 text-xs text-gray-500 font-mono">bucky.opik.net/p/</span>
                        <input type="text" id="customSlug" placeholder="Narutp" class="flex-1 bg-white border border-gray-300 rounded-r-lg px-3 py-2 text-sm font-mono text-gray-800 focus:outline-none focus:border-[#616afc] transition">
                    </div>
                </div>

                <button id="generateBtn" onclick="saveAndGenerate()" class="w-full bg-[#616afc] hover:bg-[#4f56e2] text-white font-bold text-sm py-2.5 rounded-lg transition shadow-md flex items-center justify-center gap-2">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> حفظ وتوليد الرابط النهائي
                </button>

                <!-- Result Box (Hidden by default) -->
                <div id="resultBox" class="hidden pt-3 border-t border-gray-100 space-y-2 mt-2">
                    <p class="text-[11px] text-emerald-600 font-bold flex items-center gap-1">
                        <i class="fa-solid fa-circle-check"></i> تم التوليد بنجاح! انسخ الرابط أسفله:
                    </p>
                    <div class="flex items-center gap-2">
                        <a id="finalWorkingUrl" href="#" target="_blank" class="flex-1 truncate bg-gray-50 border border-gray-200 text-[#616afc] font-mono text-xs p-2 rounded-lg" dir="ltr"></a>
                        <button onclick="copyFinalLink()" class="bg-gray-800 hover:bg-gray-900 text-white p-2 rounded-lg flex-shrink-0 transition" title="نسخ">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Accordions Container -->
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-2 space-y-1">
                
                <!-- Section 1: Text -->
                <div class="border border-gray-100 rounded-xl overflow-hidden bg-white">
                    <button onclick="toggleAccordion('sec-text')" class="w-full flex items-center justify-between p-3.5 bg-gray-50 hover:bg-gray-100 transition">
                        <span class="font-bold text-sm text-gray-700 flex items-center gap-2"><i class="fa-solid fa-font text-[#616afc]"></i> النصوص والعناوين</span>
                        <i id="icon-sec-text" class="fa-solid fa-chevron-down text-gray-400 text-xs transition-transform duration-200 accordion-icon"></i>
                    </button>
                    <div id="sec-text" class="p-4 space-y-3 border-t border-gray-100 text-xs accordion-content hidden">
                        <div>
                            <label class="block font-bold text-gray-600 mb-1">العنوان الرئيسي</label>
                            <input type="text" id="embedTitle" value="سيرفر مجتمعنا - أهلاً بكم!" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#616afc] transition">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-600 mb-1">الرابط عند الضغط على العنوان</label>
                            <input type="text" id="embedTitleUrl" value="https://discord.gg" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 font-mono focus:outline-none focus:border-[#616afc] transition">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-600 mb-1">اسم الكاتب</label>
                            <input type="text" id="embedAuthor" value="إدارة السيرفر" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#616afc] transition">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-600 mb-1">الوصف التفصيلي</label>
                            <textarea id="embedDescription" rows="4" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#616afc] transition">مرحباً بكم في مجتمعنا! 
نقدم لكم أفضل الفعاليات والهدايا باستمرار.
**اضغط على الأزرار بالأسفل للانضمام**.</textarea>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Media -->
                <div class="border border-gray-100 rounded-xl overflow-hidden bg-white">
                    <button onclick="toggleAccordion('sec-media')" class="w-full flex items-center justify-between p-3.5 bg-gray-50 hover:bg-gray-100 transition">
                        <span class="font-bold text-sm text-gray-700 flex items-center gap-2"><i class="fa-solid fa-image text-[#616afc]"></i> الصور والألوان</span>
                        <i id="icon-sec-media" class="fa-solid fa-chevron-down text-gray-400 text-xs transition-transform duration-200 accordion-icon"></i>
                    </button>
                    <div id="sec-media" class="p-4 space-y-3 border-t border-gray-100 text-xs accordion-content hidden">
                        <div>
                            <label class="block font-bold text-gray-600 mb-1">لون الشريط الجانبي</label>
                            <div class="flex items-center gap-2">
                                <input type="color" id="embedColor" value="#616afc" class="w-10 h-10 rounded cursor-pointer border-0 p-0">
                                <input type="text" id="embedColorHex" value="#616afc" dir="ltr" class="flex-1 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 font-mono focus:outline-none focus:border-[#616afc]">
                            </div>
                        </div>
                        <div>
                            <label class="block font-bold text-gray-600 mb-1">رابط صورة الكاتب الصغيرة</label>
                            <input type="text" id="embedAuthorIcon" value="https://em-content.zobj.net/source/microsoft-teams/337/crown_1f451.png" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 font-mono text-[10px] focus:outline-none focus:border-[#616afc]">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-600 mb-1">رابط الصورة المصغرة (Thumbnail)</label>
                            <input type="text" id="embedThumbnail" value="https://em-content.zobj.net/source/microsoft-teams/337/high-voltage_26a1.png" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 font-mono text-[10px] focus:outline-none focus:border-[#616afc]">
                        </div>
                        <div>
                            <label class="block font-bold text-gray-600 mb-1">رابط البنر الكبير (Banner)</label>
                            <input type="text" id="embedBanner" value="https://placehold.co/600x200/616afc/ffffff?text=bucky.opik.net" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 font-mono text-[10px] focus:outline-none focus:border-[#616afc]">
                        </div>
                    </div>
                </div>

                <!-- Section 3: Buttons V2 -->
                <div class="border border-gray-100 rounded-xl overflow-hidden bg-white">
                    <button onclick="toggleAccordion('sec-buttons')" class="w-full flex items-center justify-between p-3.5 bg-gray-50 hover:bg-gray-100 transition">
                        <span class="font-bold text-sm text-gray-700 flex items-center gap-2"><i class="fa-solid fa-square-plus text-[#616afc]"></i> أزرار التفاعل V2</span>
                        <i id="icon-sec-buttons" class="fa-solid fa-chevron-down text-gray-400 text-xs transition-transform duration-200 accordion-icon"></i>
                    </button>
                    <div id="sec-buttons" class="p-4 space-y-3 border-t border-gray-100 text-xs accordion-content hidden">
                        <div id="buttonsContainer" class="space-y-2"></div>
                        <button onclick="addButton()" class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2 rounded-lg transition border border-dashed border-gray-300">
                            <i class="fa-solid fa-plus"></i> إضافة زر جديد
                        </button>
                    </div>
                </div>
                
            </div>
            
            <!-- htaccess Hint Box -->
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-3 text-[10px] text-blue-800 space-y-1">
                <p class="font-bold"><i class="fa-solid fa-circle-info"></i> ملاحظة للاستضافات:</p>
                <p>ليعمل الرابط القصير بدون مشاكل، أضف هذا الكود في ملف <code>.htaccess</code> داخل مجلد موقعك الرئيسي:</p>
                <div class="bg-white border border-blue-200 p-1.5 rounded text-left font-mono text-[9px] mt-1" dir="ltr">
                    RewriteEngine On<br>
                    RewriteRule ^p/([a-zA-Z0-9_-]+)$ index.php?p=$1 [L,QSA]
                </div>
            </div>

        </section>

        <!-- LEFT SIDE: Discord Live Preview -->
        <section class="lg:col-span-8 flex flex-col h-full">
            <div class="flex items-center justify-between mb-3 px-2">
                <h2 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> المعاينة الحية في ديسكورد
                </h2>
                <span class="text-xs text-gray-400 bg-white border border-gray-200 px-2 py-1 rounded-md shadow-sm">مظهر ديسكورد الداكن</span>
            </div>

            <!-- Discord Shell -->
            <div class="bg-discord-bg rounded-2xl p-4 md:p-6 shadow-lg border border-gray-300 flex-1 flex flex-col font-discord text-discord-textLight overflow-hidden" dir="ltr">
                <div class="flex items-start gap-4">
                    <!-- Bot Avatar -->
                    <div class="w-10 h-10 rounded-full bg-[#616afc] flex items-center justify-center text-white font-black text-lg flex-shrink-0 shadow">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <!-- Message Header -->
                        <div class="flex items-center gap-1.5 mb-1">
                            <span class="font-bold text-white text-base hover:underline cursor-pointer">bucky Bot</span>
                            <span class="bg-[#5865F2] text-white text-[10px] font-bold px-1.5 py-0.5 rounded flex items-center gap-0.5">
                                <i class="fa-solid fa-check text-[9px]"></i> APP
                            </span>
                            <span class="text-xs text-discord-textMuted ml-1">Today at 12:00 PM</span>
                        </div>

                        <!-- Embed Card -->
                        <div id="previewEmbedCard" class="mt-1 bg-discord-embedBg border-l-4 rounded bg-clip-padding p-4 max-w-[500px] shadow-sm space-y-3" style="border-left-color: #616afc;">
                            
                            <!-- Author -->
                            <div id="previewAuthorBox" class="flex items-center gap-2">
                                <img id="previewAuthorImg" src="" class="w-6 h-6 rounded-full object-cover">
                                <span id="previewAuthorText" class="text-sm font-bold text-white"></span>
                            </div>

                            <!-- Title & Description & Thumbnail -->
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-1.5 flex-1 min-w-0">
                                    <a id="previewTitle" href="#" target="_blank" class="font-bold text-[#00a8fc] text-base hover:underline block break-words"></a>
                                    <div id="previewDescription" class="text-sm text-discord-textLight leading-relaxed whitespace-pre-line markdown-preview break-words"></div>
                                </div>
                                <div id="previewThumbnailBox" class="flex-shrink-0 hidden">
                                    <img id="previewThumbnailImg" src="" class="w-20 h-20 rounded object-cover">
                                </div>
                            </div>

                            <!-- Banner -->
                            <div id="previewBannerBox" class="pt-2 hidden">
                                <img id="previewBannerImg" src="" class="w-full rounded object-cover max-h-[300px]">
                            </div>

                        </div>

                        <!-- Action Row Buttons -->
                        <div id="previewButtonsList" class="mt-3 flex flex-wrap gap-2 max-w-[500px]">
                            <!-- Buttons injected here -->
                        </div>

                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- Toast Notification -->
    <div id="toast" class="fixed bottom-5 left-1/2 -translate-x-1/2 bg-gray-800 text-white text-xs font-bold px-4 py-3 rounded-lg shadow-xl transition-all duration-300 opacity-0 pointer-events-none translate-y-4 flex items-center gap-2 z-50">
        <i class="fa-solid fa-check-circle text-[#616afc]"></i> <span id="toastMessage"></span>
    </div>

    <script>
        // State Management
        let state = {
            title: "سيرفر مجتمعنا - أهلاً بكم!",
            titleUrl: "https://discord.gg",
            author: "إدارة السيرفر",
            authorIcon: "https://em-content.zobj.net/source/microsoft-teams/337/crown_1f451.png",
            description: "مرحباً بكم في مجتمعنا! \nنقدم لكم أفضل الفعاليات والهدايا باستمرار.\n**اضغط على الأزرار بالأسفل للانضمام**.",
            color: "#616afc",
            thumbnail: "https://em-content.zobj.net/source/microsoft-teams/337/high-voltage_26a1.png",
            banner: "https://placehold.co/600x200/616afc/ffffff?text=bucky.opik.net",
            buttons: [
                { id: 1, label: "الانضمام للسيرفر", url: "https://discord.gg", emoji: "🚀" }
            ]
        };

        // Initialize App
        function init() {
            // Bind inputs to state
            const bindings = ['title', 'titleUrl', 'author', 'authorIcon', 'description', 'thumbnail', 'banner'];
            bindings.forEach(key => {
                const el = document.getElementById('embed' + key.charAt(0).toUpperCase() + key.slice(1));
                if (el) {
                    el.addEventListener('input', (e) => {
                        state[key] = e.target.value;
                        updatePreview();
                    });
                }
            });

            // Color specific bindings
            document.getElementById('embedColor').addEventListener('input', (e) => {
                state.color = e.target.value;
                document.getElementById('embedColorHex').value = e.target.value;
                updatePreview();
            });
            document.getElementById('embedColorHex').addEventListener('input', (e) => {
                if (/^#[0-9A-F]{6}$/i.test(e.target.value)) {
                    state.color = e.target.value;
                    document.getElementById('embedColor').value = e.target.value;
                    updatePreview();
                }
            });

            renderButtonsEditor();
            updatePreview();
        }

        // Accordion Logic
        function toggleAccordion(id) {
            const content = document.getElementById(id);
            const icon = document.getElementById('icon-' + id);
            const isHidden = content.classList.contains('hidden');
            
            // Close all first (optional, keeps UI clean)
            document.querySelectorAll('.accordion-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.accordion-icon').forEach(el => el.style.transform = 'rotate(0deg)');
            
            if (isHidden) {
                content.classList.remove('hidden');
                icon.style.transform = 'rotate(180deg)';
            }
        }

        // Markdown Parser (Basic)
        function parseMd(text) {
            if (!text) return '';
            let html = text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
            html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
            html = html.replace(/`(.*?)`/g, '<code>$1</code>');
            html = html.replace(/\[(.*?)\]\((.*?)\)/g, '<a href="$2" target="_blank">$1</a>');
            return html;
        }

        // Update Discord Preview
        function updatePreview() {
            // Text
            document.getElementById('previewTitle').innerText = state.title || '';
            document.getElementById('previewTitle').href = state.titleUrl || '#';
            document.getElementById('previewDescription').innerHTML = parseMd(state.description);
            document.getElementById('previewEmbedCard').style.borderLeftColor = state.color || '#616afc';

            // Author
            const authBox = document.getElementById('previewAuthorBox');
            if (state.author) {
                authBox.classList.remove('hidden');
                authBox.classList.add('flex');
                document.getElementById('previewAuthorText').innerText = state.author;
                const img = document.getElementById('previewAuthorImg');
                img.style.display = state.authorIcon ? 'block' : 'none';
                img.src = state.authorIcon || '';
            } else {
                authBox.classList.add('hidden');
                authBox.classList.remove('flex');
            }

            // Thumbnail
            const thumbBox = document.getElementById('previewThumbnailBox');
            if (state.thumbnail) {
                thumbBox.classList.remove('hidden');
                document.getElementById('previewThumbnailImg').src = state.thumbnail;
            } else {
                thumbBox.classList.add('hidden');
            }

            // Banner
            const bannerBox = document.getElementById('previewBannerBox');
            if (state.banner) {
                bannerBox.classList.remove('hidden');
                document.getElementById('previewBannerImg').src = state.banner;
            } else {
                bannerBox.classList.add('hidden');
            }

            // Buttons
            const btnList = document.getElementById('previewButtonsList');
            btnList.innerHTML = '';
            state.buttons.forEach(btn => {
                const a = document.createElement('a');
                a.href = btn.url || '#';
                a.target = '_blank';
                // Discord button styles
                a.className = 'bg-[#4e5058] hover:bg-[#6d6f78] text-white px-4 py-2 rounded flex items-center gap-2 text-sm font-medium transition cursor-pointer select-none';
                a.innerHTML = `<span>${btn.emoji || ''}</span> <span>${btn.label || 'Button'}</span> <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-gray-400 ml-1"></i>`;
                btnList.appendChild(a);
            });
        }

        // Buttons Editor Logic
        function renderButtonsEditor() {
            const container = document.getElementById('buttonsContainer');
            container.innerHTML = '';
            
            state.buttons.forEach((btn, index) => {
                const div = document.createElement('div');
                div.className = 'bg-gray-50 p-3 rounded-lg border border-gray-200 space-y-2 relative';
                div.innerHTML = `
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-[10px] font-bold text-gray-400">الزر ${index + 1}</span>
                        <button onclick="removeButton(${btn.id})" class="text-red-400 hover:text-red-600 text-xs"><i class="fa-solid fa-trash"></i></button>
                    </div>
                    <div class="flex gap-2">
                        <input type="text" value="${btn.emoji || ''}" placeholder="🚀" oninput="updateBtn(${btn.id}, 'emoji', this.value)" class="w-12 bg-white border border-gray-300 rounded px-2 py-1.5 text-center text-sm focus:border-[#616afc] outline-none">
                        <input type="text" value="${btn.label || ''}" placeholder="اسم الزر" oninput="updateBtn(${btn.id}, 'label', this.value)" class="flex-1 bg-white border border-gray-300 rounded px-2 py-1.5 text-sm focus:border-[#616afc] outline-none font-bold">
                    </div>
                    <input type="text" value="${btn.url || ''}" dir="ltr" placeholder="https://..." oninput="updateBtn(${btn.id}, 'url', this.value)" class="w-full bg-white border border-gray-300 rounded px-2 py-1.5 text-[11px] font-mono focus:border-[#616afc] outline-none">
                `;
                container.appendChild(div);
            });
            updatePreview();
        }

        function addButton() {
            if (state.buttons.length >= 5) {
                showToast("الحد الأقصى هو 5 أزرار!");
                return;
            }
            state.buttons.push({ id: Date.now(), label: "زر جديد", url: "https://", emoji: "🔗" });
            renderButtonsEditor();
        }

        function removeButton(id) {
            state.buttons = state.buttons.filter(b => b.id !== id);
            renderButtonsEditor();
        }

        function updateBtn(id, field, value) {
            const btn = state.buttons.find(b => b.id === id);
            if (btn) btn[field] = value;
            updatePreview();
        }

        // Saving and Link Generation via Backend
        async function saveAndGenerate() {
            const slugInput = document.getElementById('customSlug').value.trim();
            const btn = document.getElementById('generateBtn');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري الحفظ...';
            btn.disabled = true;

            const payload = { ...state, slug: slugInput };

            try {
                // Send to same file (index.php?api=save)
                const res = await fetch('?api=save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                const data = await res.json();
                
                if (data.success) {
                    const resultBox = document.getElementById('resultBox');
                    const linkEl = document.getElementById('finalWorkingUrl');
                    
                    resultBox.classList.remove('hidden');
                    linkEl.innerText = data.link;
                    linkEl.href = data.link;
                    
                    showToast("تم بناء الرابط بنجاح!");
                } else {
                    showToast("حدث خطأ أثناء التوليد.");
                }
            } catch (err) {
                showToast("فشل الاتصال بالسيرفر. هل الموقع يعمل ببيئة PHP؟");
            }

            btn.innerHTML = originalText;
            btn.disabled = false;
        }

        // Helpers
        function copyFinalLink() {
            const text = document.getElementById('finalWorkingUrl').innerText;
            if (!text) return;
            
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => showToast("تم النسخ!"));
            } else {
                const ta = document.createElement("textarea");
                ta.value = text; document.body.appendChild(ta); ta.select();
                document.execCommand('copy'); document.body.removeChild(ta);
                showToast("تم النسخ!");
            }
        }

        function showToast(msg) {
            const toast = document.getElementById('toast');
            document.getElementById('toastMessage').innerText = msg;
            toast.classList.remove('opacity-0', 'pointer-events-none', 'translate-y-4');
            
            setTimeout(() => {
                toast.classList.add('opacity-0', 'pointer-events-none', 'translate-y-4');
            }, 3000);
        }

        // Start
        window.onload = init;
    </script>
</body>
</html>
