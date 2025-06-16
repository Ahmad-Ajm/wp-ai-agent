jQuery(function($){
    $(document).on('click', '#regenerate-image', async function(){
        // 🛠️ التحقق من وجود apiKey أولًا
        if (!window.apiKey) {
            alert("يرجى أولًا حفظ مفتاح API قبل توليد الصورة.");
            return;
        }

        const prompt = $('#image').val().trim();
        if (!prompt) return alert("يرجى إدخال برومبت للصورة");

        $('#post-image-preview').attr('src', 'https://placehold.co/300x180?text=جارٍ+التوليد...');
        wpAiUI.appendLog("⏳ جاري توليد الصورة...");

        try {
            const response = await fetch('https://api.openai.com/v1/images/generations', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${window.apiKey}`
                },
                body: JSON.stringify({
                    model: "dall-e-3",
                    prompt: prompt,
                    n: 1,
                    size: "1024x1024"
                })
            });

            const data = await response.json();
            if (!response.ok) throw new Error(data.error?.message || "خطأ في التوليد");

            const imageUrl = data.data?.[0]?.url;
            if (imageUrl) {
                $('#post-image-preview').attr('src', imageUrl).data('generated-url', imageUrl);
                wpAiUI.appendLog("✅ تم توليد الصورة باستخدام DALL-E");
            } else {
                throw new Error("لم يتم إنشاء صورة");
            }
        } catch (e) {
            wpAiUI.appendLog("❌ فشل في توليد الصورة: " + e.message);
            $('#post-image-preview').attr('src', 'https://placehold.co/300x180?text=خطأ+في+التوليد');
        }
    });
});
