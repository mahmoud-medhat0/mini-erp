(function () {
    if (window.MiniErpAiWidgetLoaded) return;
    window.MiniErpAiWidgetLoaded = true;

    // Detect script tag configuration attributes
    const currentScript = document.currentScript || (function() {
        const scripts = document.getElementsByTagName('script');
        return scripts[scripts.length - 1];
    })();

    const scriptSrc = currentScript ? currentScript.src : '';
    let scriptOrigin = window.location.origin;
    if (scriptSrc) {
        try {
            scriptOrigin = new URL(scriptSrc).origin;
        } catch (e) {
            scriptOrigin = window.location.origin;
        }
    }

    // Global Config object override support (window.MiniErpAiConfig)
    const cfg = window.MiniErpAiConfig || {};
    const INITIAL_HISTORY = Array.isArray(cfg.initialHistory)
        ? cfg.initialHistory
            .filter((message) => message
                && (message.role === 'user' || message.role === 'assistant')
                && typeof message.content === 'string')
            .map((message) => ({
                role: message.role,
                content: message.content.slice(0, 4000)
            }))
            .slice(-20)
        : [];

    const API_URL = (cfg.apiUrl || currentScript.getAttribute('data-api-url') || scriptOrigin).replace(/\/$/, '');
    const CONTEXT_ID = cfg.contextId || cfg.tenantId || currentScript.getAttribute('data-context-id') || currentScript.getAttribute('data-tenant-id') || null;
    const PERSIST_HISTORY = cfg.persistHistory === true || currentScript.getAttribute('data-persist-history') === 'true';
    const ENABLE_VOICE = cfg.enableVoice !== false && currentScript.getAttribute('data-enable-voice') !== 'false';
    const ENABLE_VISION = cfg.enableVision !== false && currentScript.getAttribute('data-enable-vision') !== 'false';
    const STORAGE_SCOPE = String(CONTEXT_ID || 'guest').replace(/[^a-zA-Z0-9_-]/g, '_').slice(0, 64);
    const HISTORY_STORAGE_KEY = `mini_erp_ai_history_v1_${STORAGE_SCOPE}`;
    const TOUR_STORAGE_KEY = `mini_erp_ai_tour_completed_v1_${STORAGE_SCOPE}`;
    // Language & Localization (ar / en)
    const LANG = (cfg.lang || currentScript.getAttribute('data-lang') || currentScript.getAttribute('lang') || 'ar').toLowerCase();
    const isEnglish = LANG === 'en';
    const DIR = isEnglish ? 'ltr' : 'rtl';

    const i18n = {
        ar: {
            title: 'مساعد Mini ERP',
            online: 'متصل · مساعد إرشادي',
            footerNote: 'إرشادات <bdi dir="ltr">Mini ERP</bdi> بالذكاء الاصطناعي',
            inputLabel: 'اكتب سؤالك للمساعد',
            welcome: 'أهلًا بك، أنا مساعد <bdi dir="ltr">Mini ERP</bdi>.<br>أشرح لك خطوات المحاسبة والمبيعات والمشتريات والمخزون والتقارير حسب صلاحياتك، ويمكنك استخدام الصوت أو إرفاق صورة.',
            listenMsg: 'استماع للرسالة',
            listenBtn: 'استماع',
            pauseBtn: 'إيقاف مؤقت',
            resumeBtn: 'استكمال',
            stopListenBtn: 'إيقاف',
            pauseMsg: 'إيقاف القراءة مؤقتاً',
            resumeMsg: 'استكمال قراءة الرسالة',
            faqTitle: 'أسئلة شائعة:',
            faq1Title: 'التهيئة لأول مرة',
            faq1Q: 'ما ترتيب التهيئة لأول مرة قبل التشغيل الفعلي؟',
            faq2Title: 'دورة البيع والتحصيل',
            faq2Q: 'كيف أنفذ دورة البيع من أمر البيع حتى القبض والتخصيص؟',
            faq3Title: 'دورة الشراء والسداد',
            faq3Q: 'كيف أنفذ دورة الشراء من أمر الشراء حتى الدفع والتخصيص؟',
            imgAttachedInfo: '🖼️ تم إرفاق صورة (جاهزة للتحليل والقراءة)',
            placeholder: 'اكتب سؤالك، تحدث، أو ارفق صورة...',
            attachImgTitle: 'إرفاق مستند أو لقطة شاشة',
            voiceTitle: 'تحدث بالصوت',
            sendTitle: 'إرسال الرسالة',
            liveVoiceTitle: 'المحادثة الصوتية المباشرة (Live Voice Mode)',
            clearTitle: 'مسح المحادثة',
            closeTitle: 'إغلاق',
            closeVoiceTitle: 'إغلاق وضع الصوت',
            muteMicTitle: 'كتم الميكروفون',
            endVoiceTitle: 'إنهاء المحادثة الصوتية',
            muteSpeakerTitle: 'إيقاف الصوت',
            voicePromptReady: '🎙️ اضغط للتحدث أو ابدأ الكلام مباشرة...',
            voiceListening: 'المساعد جاهز للاستماع إلى سؤالك الصوتي بالنظام...',
            listeningNow: '🎙️ جاري الاستماع... يمكنك التحدث الآن',
            listeningSub: 'الميكروفون مفتوح ويستمع لسؤالك...',
            welcomeBack: 'أهلًا بك مجددًا. كيف يمكنني مساعدتك اليوم؟',
            errServer: 'عذراً، حدث خطأ أثناء الاتصال بالخادم. يرجى المحاولة مرة أخرى.',
            browserNotSupported: 'خاصية التعرف الصوتي غير مدعومة مباشرة في متصفحك. يرجى استخدام متصفح Chrome أو Edge.',
            tourHelpTitle: 'الجولة التعليمية (كيفية الاستخدام)',
            tourSkip: 'تخطي',
            tourNext: 'التالي',
            tourBack: 'السابق',
            tourFinish: 'بدء الاستخدام 🚀',
            tourStepOf: 'من',
            tourSteps: [
                {
                    targetId: 'aiWidgetTitle',
                    title: 'مساعد Mini ERP',
                    desc: 'هذه نافذة مساعد Mini ERP التي تظل متاحة أثناء استخدام النظام.'
                },
                {
                    targetId: 'aiWidgetVoiceModeBtn',
                    title: 'المحادثة الصوتية التفاعلية 🎙️',
                    desc: 'اضغط هنا للبدء بمحادثة صوتية مباشرة تفاعلية مع المساعد واستماع الإجابة فورياً!'
                },
                {
                    targetId: 'aiWidgetImgBtn',
                    title: 'قراءة المستندات ولقطات الشاشة 📷',
                    desc: 'يمكنك إرفاق فاتورة أو مستند أو لقطة شاشة ليقرأ المساعد الظاهر فيها ويطابقه مع دليل Mini ERP.'
                },
                {
                    targetId: 'aiWidgetSuggestions',
                    title: 'الأسئلة الشائعة ونطق الإجابات 🔊',
                    desc: 'اختر أي سؤال شائع للتجربة السريعة، ويمكنك الاستماع لأي رد نصي بنقرة واحدة على زر الاستماع.'
                }
            ]
        },
        en: {
            title: 'Mini ERP Assistant',
            online: 'Online · System guide',
            footerNote: 'Mini ERP · AI-guided help',
            inputLabel: 'Ask the assistant a question',
            welcome: 'Welcome, I am your Mini ERP assistant.<br>I can explain Accounting, Sales, Purchasing, Inventory, and Reports based on your permissions. You can use voice or attach an image.',
            listenMsg: 'Listen to message',
            listenBtn: 'Listen',
            pauseBtn: 'Pause',
            resumeBtn: 'Resume',
            stopListenBtn: 'Stop',
            pauseMsg: 'Pause reading',
            resumeMsg: 'Resume reading message',
            faqTitle: 'Frequently Asked Questions:',
            faq1Title: 'First-time setup',
            faq1Q: 'What is the correct first-time setup order before go-live?',
            faq2Title: 'Sales and collection cycle',
            faq2Q: 'How do I complete the sales cycle through receipt allocation?',
            faq3Title: 'Purchasing and payment cycle',
            faq3Q: 'How do I complete the purchasing cycle through payment allocation?',
            imgAttachedInfo: '🖼️ Image attached (ready for vision analysis)',
            placeholder: 'Type your question, speak, or attach image...',
            attachImgTitle: 'Attach document or screenshot',
            voiceTitle: 'Voice Input',
            sendTitle: 'Send message',
            liveVoiceTitle: 'Live Voice Chat Mode',
            clearTitle: 'Clear conversation',
            closeTitle: 'Close',
            closeVoiceTitle: 'Close voice mode',
            muteMicTitle: 'Mute microphone',
            endVoiceTitle: 'End voice call',
            muteSpeakerTitle: 'Mute speaker',
            voicePromptReady: '🎙️ Tap to speak or start talking directly...',
            voiceListening: 'Assistant is ready and listening to your voice request...',
            listeningNow: '🎙️ Listening now... speak your question',
            listeningSub: 'Microphone is active and listening...',
            welcomeBack: 'Welcome back. How can I assist you today?',
            errServer: 'Sorry, a server error occurred. Please try again.',
            browserNotSupported: 'Speech recognition is not supported in your current browser. Please use Chrome or Edge.',
            tourHelpTitle: 'Usage Tour Guide',
            tourSkip: 'Skip',
            tourNext: 'Next',
            tourBack: 'Back',
            tourFinish: 'Get Started 🚀',
            tourStepOf: 'of',
            tourSteps: [
                {
                    targetId: 'aiWidgetTitle',
                    title: 'Mini ERP Assistant',
                    desc: 'This Mini ERP assistant stays available while you use the system.'
                },
                {
                    targetId: 'aiWidgetVoiceModeBtn',
                    title: 'Live Voice Chat Mode 🎙️',
                    desc: 'Click here for real-time interactive voice conversation with spoken AI answers!'
                },
                {
                    targetId: 'aiWidgetImgBtn',
                    title: 'Image & Vision Analysis 📷',
                    desc: 'Attach invoices, documents, or screenshots for guidance based on the Mini ERP knowledge base.'
                },
                {
                    targetId: 'aiWidgetSuggestions',
                    title: 'Quick FAQs & Audio Playback 🔊',
                    desc: 'Select suggested questions for instant answers and click listen to hear spoken text.'
                }
            ]
        }
    };

    const t = i18n[isEnglish ? 'en' : 'ar'];
    if (!ENABLE_VOICE && ENABLE_VISION) {
        t.welcome = isEnglish
            ? 'Welcome, I am your Mini ERP guide. I explain accounting, sales, purchasing, inventory, and reporting workflows based on your access, and you can attach a document or screenshot.'
            : 'أهلًا بك، أنا مساعد <bdi dir="ltr">Mini ERP</bdi>. أشرح لك خطوات المحاسبة والمبيعات والمشتريات والمخزون والتقارير حسب صلاحياتك، ويمكنك إرفاق مستند أو لقطة شاشة.';
        t.placeholder = isEnglish
            ? 'Type your question or attach an image...'
            : 'اكتب سؤالك أو أرفق صورة...';
    } else if (ENABLE_VOICE && !ENABLE_VISION) {
        t.welcome = isEnglish
            ? 'Welcome, I am your Mini ERP guide. I explain accounting, sales, purchasing, inventory, and reporting workflows based on your access, and you can use voice input.'
            : 'أهلًا بك، أنا مساعد <bdi dir="ltr">Mini ERP</bdi>. أشرح لك خطوات المحاسبة والمبيعات والمشتريات والمخزون والتقارير حسب صلاحياتك، ويمكنك استخدام الإدخال الصوتي.';
        t.placeholder = isEnglish
            ? 'Type or speak your question...'
            : 'اكتب سؤالك أو تحدث...';
    } else if (!ENABLE_VOICE && !ENABLE_VISION) {
        t.welcome = isEnglish
            ? 'Welcome, I am your Mini ERP guide for accounting, sales, purchasing, inventory, and reporting workflows based on your access.'
            : 'أهلًا بك، أنا مساعد <bdi dir="ltr">Mini ERP</bdi> لشرح خطوات المحاسبة والمبيعات والمشتريات والمخزون والتقارير حسب صلاحياتك.';
        t.placeholder = isEnglish ? 'Type your question...' : 'اكتب سؤالك...';
    }
    const WIDGET_ICONS = Object.freeze({
        assistant: '<rect x="4" y="6.5" width="16" height="13" rx="4"/><path d="M9 3.5h6M12 3.5v3M4 12H2.5v3H4M20 12h1.5v3H20"/><circle cx="9" cy="12.5" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="12.5" r="1" fill="currentColor" stroke="none"/><path d="M9 16h6"/>',
        help: '<circle cx="12" cy="12" r="9"/><path d="M9.8 9a2.4 2.4 0 1 1 3.6 2.1c-.9.5-1.4 1-1.4 2.1"/><path d="M12 17h.01"/>',
        mic: '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5.5 11.5a6.5 6.5 0 0 0 13 0M12 18v3M9 21h6"/>',
        micOff: '<path d="m4 4 16 16"/><path d="M9 9v2.5a3 3 0 0 0 5.2 2M15 10V6a3 3 0 0 0-5.6-1.5M6 11.5a6 6 0 0 0 9.5 4.9M18 11.5a6 6 0 0 1-.4 2.1M12 18v3M9 21h6"/>',
        trash: '<path d="M4 7h16M9 7V4h6v3M6.5 7l.8 13h9.4l.8-13M10 11v5M14 11v5"/>',
        close: '<path d="m6 6 12 12M18 6 6 18"/>',
        volume: '<path d="M5 10v4h3l4 3V7l-4 3H5zM16 9a4 4 0 0 1 0 6M18.5 6.5a8 8 0 0 1 0 11"/>',
        volumeOff: '<path d="M5 10v4h3l4 3V7l-4 3H5zM16 10l5 5M21 10l-5 5"/>',
        bulb: '<path d="M9 18h6M10 21h4"/><path d="M8.4 15.2A7 7 0 1 1 15.6 15.2c-.8.6-1.1 1.2-1.1 1.8h-5c0-.6-.3-1.2-1.1-1.8z"/>',
        image: '<rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="1.5"/><path d="m4 17 4.5-4.5 3 3 2-2 6.5 6.5"/>',
        send: '<path d="m3 11 18-8-8 18-2.5-7.5L3 11zM10.5 13.5 21 3"/>',
        wave: '<path d="M4 10v4M8 7v10M12 4v16M16 7v10M20 10v4"/>',
        phoneOff: '<path d="m3 3 18 18M8.5 8.5 6.8 6.8c-.8-.8-2.1-.8-2.9 0L2.7 8c-.5.5-.7 1.2-.4 1.9 2.3 5.8 6.9 10.4 12.7 12.7.7.3 1.4.1 1.9-.4l1.2-1.2c.8-.8.8-2.1 0-2.9l-2.6-2.6c-.8-.8-2.1-.8-2.9 0l-.8.8"/>',
        zoomIn: '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5M10.5 7.5v6M7.5 10.5h6"/>',
        zoomOut: '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5M7.5 10.5h6"/>',
        reset: '<path d="M4 8V3m0 0h5M4 3l3.2 3.2A8 8 0 1 1 4 12"/>',
        play: '<path d="m9 6 9 6-9 6V6z"/>',
        pause: '<path d="M9 6v12M15 6v12"/>',
        user: '<circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/>',
        brain: '<path d="M9.5 4.5A3.5 3.5 0 0 0 6 8v.3A3.5 3.5 0 0 0 4 14.7 3.5 3.5 0 0 0 9.5 19M14.5 4.5A3.5 3.5 0 0 1 18 8v.3a3.5 3.5 0 0 1 2 6.4 3.5 3.5 0 0 1-5.5 4.3M9.5 4.5V19M14.5 4.5V19M9.5 9H7M14.5 9H17M9.5 14H7M14.5 14H17"/>',
        gear: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1z"/>',
        receipt: '<path d="M6 3h12v18l-2-1.5L14 21l-2-1.5L10 21l-2-1.5L6 21V3zM9 8h6M9 12h6M9 16h4"/>',
        cart: '<circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/><path d="M3 4h2l2.4 10.2a2 2 0 0 0 2 1.5h7.9a2 2 0 0 0 2-1.6L21 7H6"/>',
        chevron: '<path d="m9 6 6 6-6 6"/>'
    });

    function widgetIcon(name, extraClass = '') {
        const safeClass = String(extraClass || '').replace(/[^a-zA-Z0-9 _-]/g, '').trim();
        const classes = 'ai-icon' + (safeClass ? ' ' + safeClass : '');
        const body = WIDGET_ICONS[name] || WIDGET_ICONS.assistant;
        return '<svg class="' + classes + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + body + '</svg>';
    }

    const WIDGET_TITLE = cfg.title || currentScript.getAttribute('data-title') || t.title;
    const POSITION = (cfg.position || currentScript.getAttribute('data-position') || 'right').toLowerCase(); // 'right' or 'left'
    const THEME = (cfg.theme || currentScript.getAttribute('data-theme') || 'light').toLowerCase(); // 'dark' or 'light'

    const isLight = THEME === 'light';

    // Appearance & Style Tokens
    const PRIMARY_COLOR = cfg.primaryColor || currentScript.getAttribute('data-primary-color') || currentScript.getAttribute('data-color') || '#2563EB';
    const SECONDARY_COLOR = cfg.secondaryColor || currentScript.getAttribute('data-secondary-color') || '#1D4ED8';
    const ACCENT_COLOR = cfg.accentColor || currentScript.getAttribute('data-accent-color') || '#4F46E5';
    const BG_COLOR = cfg.bgColor || currentScript.getAttribute('data-bg-color') || (isLight ? '#FFFFFF' : 'rgba(15, 23, 42, 0.94)');
    const HEADER_BG = cfg.headerBg || currentScript.getAttribute('data-header-bg') || (isLight ? '#F1F5F9' : 'rgba(11, 17, 32, 0.85)');
    const CARD_BG = cfg.cardBg || currentScript.getAttribute('data-card-bg') || (isLight ? '#F8FAFC' : 'rgba(30, 41, 59, 0.6)');
    const TEXT_COLOR = cfg.textColor || currentScript.getAttribute('data-text-color') || (isLight ? '#0F172A' : '#F8FAFC');
    const TEXT_MUTED = cfg.textMuted || currentScript.getAttribute('data-text-muted') || (isLight ? '#64748B' : '#94A3B8');
    const BORDER_COLOR = cfg.borderColor || currentScript.getAttribute('data-border-color') || (isLight ? 'rgba(226, 232, 240, 0.9)' : 'rgba(99, 102, 241, 0.3)');
    const RADIUS = cfg.radius || currentScript.getAttribute('data-radius') || '20px';
    const WIDTH = cfg.width || currentScript.getAttribute('data-width') || '390px';
    const HEIGHT = cfg.height || currentScript.getAttribute('data-height') || '580px';
    const BOTTOM_OFFSET = cfg.bottom || currentScript.getAttribute('data-bottom') || '20px';
    const SIDE_OFFSET = cfg.side || currentScript.getAttribute('data-side') || currentScript.getAttribute('data-side-offset') || '20px';
    const Z_INDEX = cfg.zIndex || currentScript.getAttribute('data-z-index') || '999990';
    const FONT_FAMILY = cfg.fontFamily || currentScript.getAttribute('data-font-family') || currentScript.getAttribute('data-font') || "'Alexandria', 'Instrument Sans', system-ui, -apple-system, sans-serif";

    // Inject Google Fonts if missing
    let injectedFontLink = null;
    if (!document.getElementById('ai-widget-font')) {
        const fontLink = document.createElement('link');
        fontLink.id = 'ai-widget-font';
        fontLink.rel = 'stylesheet';
        fontLink.href = 'https://fonts.googleapis.com/css2?family=Alexandria:wght@400;500;600;700&family=Instrument+Sans:wght@400;500;600;700&display=swap';
        document.head.appendChild(fontLink);
        injectedFontLink = fontLink;
    }

    // Inject Widget Styles
    const style = document.createElement('style');
    style.id = 'ai-widget-styles';
    style.innerHTML = `
        .ai-widget-wrapper {
            --ai-primary: ${PRIMARY_COLOR};
            --ai-secondary: ${SECONDARY_COLOR};
            --ai-accent: ${ACCENT_COLOR};
            --ai-bg: ${BG_COLOR};
            --ai-header-bg: ${HEADER_BG};
            --ai-card-bg: ${CARD_BG};
            --ai-text: ${TEXT_COLOR};
            --ai-text-muted: ${TEXT_MUTED};
            --ai-border: ${BORDER_COLOR};
            --ai-radius: ${RADIUS};
            --ai-width: ${WIDTH};
            --ai-height: ${HEIGHT};
            --ai-bottom: ${BOTTOM_OFFSET};
            --ai-side: ${SIDE_OFFSET};
            --ai-z-index: ${Z_INDEX};
            --ai-font: ${FONT_FAMILY};
        }

        .ai-widget-wrapper * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: var(--ai-font, 'Alexandria', 'Instrument Sans', system-ui, sans-serif);
            direction: ${DIR};
        }

        /* Floating Trigger Button */
        .ai-widget-trigger {
            position: fixed;
            bottom: var(--ai-bottom, 24px);
            ${POSITION}: var(--ai-side, 24px);
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--ai-primary) 0%, var(--ai-secondary) 50%, var(--ai-accent) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
            z-index: var(--ai-z-index, 999990);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            border: none;
            outline: none;
        }

        .ai-widget-trigger:hover {
            transform: scale(1.08) rotate(-4deg);
            box-shadow: 0 15px 30px rgba(99, 102, 241, 0.6);
        }

        .ai-widget-trigger .ai-badge-dot {
            position: absolute;
            top: 2px;
            right: 2px;
            width: 12px;
            height: 12px;
            background: #10B981;
            border: 2px solid #090D16;
            border-radius: 50%;
        }

        /* Floating Chat Popup Window */
        .ai-widget-window {
            position: fixed;
            bottom: calc(var(--ai-bottom, 24px) + 71px);
            ${POSITION}: var(--ai-side, 24px);
            width: var(--ai-width, 395px);
            height: var(--ai-height, 600px);
            max-height: calc(100vh - 120px);
            max-width: calc(100vw - 32px);
            background: var(--ai-bg);
            color: var(--ai-text);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--ai-border);
            border-radius: var(--ai-radius, 24px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6), 0 0 40px rgba(99, 102, 241, 0.2);
            z-index: calc(var(--ai-z-index, 999990) + 5);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            opacity: 0;
            transform: translateY(20px) scale(0.95);
            pointer-events: none;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .ai-widget-window.ai-widget-open {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }

        /* Header */
        .ai-widget-header {
            padding: 0.9rem 1.1rem;
            background: var(--ai-header-bg);
            border-bottom: 1px solid var(--ai-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            flex-shrink: 0;
            min-width: 0;
        }

        .ai-widget-header .ai-brand {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            min-width: 0;
            flex: 1;
        }

        .ai-widget-header .ai-brand-icon {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--ai-primary) 0%, var(--ai-secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .ai-widget-header .ai-brand-title {
            min-width: 0;
            flex: 1;
        }

        .ai-widget-header .ai-brand-title h4 {
            color: var(--ai-text);
            font-size: 0.92rem;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ai-widget-header .ai-brand-title p {
            color: var(--ai-text-muted);
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ai-widget-header .ai-status-dot {
            width: 6px;
            height: 6px;
            background: #10B981;
            border-radius: 50%;
            display: inline-block;
            flex-shrink: 0;
        }

        .ai-widget-header .ai-actions {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            flex-shrink: 0;
        }

        .ai-widget-header .ai-action-btn {
            background: transparent;
            border: none;
            color: #94A3B8;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .ai-widget-header .ai-action-btn:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }

        .ai-widget-header .ai-action-btn.ai-voice-active {
            color: #38BDF8;
            background: rgba(56, 189, 248, 0.2);
            border: 1px solid rgba(56, 189, 248, 0.4);
            animation: aiPulseGlow 2s infinite;
        }

        @keyframes aiPulseGlow {
            0% { box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(56, 189, 248, 0); }
            100% { box-shadow: 0 0 0 0 rgba(56, 189, 248, 0); }
        }

        /* Body */
        .ai-widget-body {
            flex: 1;
            min-height: 0;
            padding: 0.85rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            scroll-behavior: smooth;
            overscroll-behavior-y: contain;
            -webkit-overflow-scrolling: touch;
        }

        .ai-widget-body::-webkit-scrollbar {
            width: 4px;
        }

        .ai-widget-body::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
        }

        /* Message Rows */
        .ai-widget-msg {
            display: flex;
            gap: 0.6rem;
            max-width: 88%;
            animation: aiMsgIn 0.3s ease forwards;
        }

        @keyframes aiMsgIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .ai-widget-msg.user {
            align-self: flex-start;
        }

        .ai-widget-msg.assistant {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .ai-widget-msg .ai-avatar {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .ai-widget-msg.user .ai-avatar {
            background: linear-gradient(135deg, #4F46E5 0%, #2563EB 100%);
            color: white;
        }

        .ai-widget-msg.assistant .ai-avatar {
            background: rgba(14, 165, 233, 0.15);
            color: #38BDF8;
            border: 1px solid rgba(56, 189, 248, 0.3);
        }

        .ai-widget-msg .ai-bubble-container {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            max-width: 100%;
        }

        .ai-widget-msg .ai-bubble {
            padding: 0.8rem 1.1rem;
            border-radius: 16px;
            font-size: 0.88rem;
            line-height: 1.6;
            color: #F1F5F9;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            position: relative;
            word-break: break-word;
        }

        .ai-widget-msg.user .ai-bubble {
            background: linear-gradient(135deg, #4F46E5 0%, #2563EB 100%);
            border-bottom-right-radius: 4px;
        }

        .ai-widget-msg.assistant .ai-bubble {
            background: rgba(23, 32, 51, 0.85);
            border: 1px solid rgba(99, 102, 241, 0.25);
            border-bottom-left-radius: 4px;
        }

        /* Image Thumbnail Preview in Chat Bubble */
        .ai-chat-image-preview {
            max-width: 220px;
            max-height: 180px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            object-fit: cover;
            margin-bottom: 0.4rem;
            display: block;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .ai-chat-response-image {
            max-width: min(100%, 260px);
            max-height: 260px;
            border-radius: 12px;
            border: 1px solid rgba(56, 189, 248, 0.28);
            object-fit: contain;
            margin: 0.35rem 0;
            display: block;
            background: rgba(2, 6, 23, 0.55);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.28);
        }

        .ai-msg-speak-btn {
            align-self: flex-start;
            background: transparent;
            border: none;
            color: #64748B;
            font-size: 0.78rem;
            cursor: pointer;
            padding: 2px 6px;
            border-radius: 6px;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }

        .ai-msg-speak-btn:hover {
            color: #38BDF8;
            background: rgba(56, 189, 248, 0.1);
        }

        .ai-msg-speak-btn.speaking {
            color: #38BDF8;
            background: rgba(56, 189, 248, 0.12);
        }

        .ai-msg-speak-btn.paused {
            color: #F59E0B;
            background: rgba(245, 158, 11, 0.15);
        }

        /* Suggestions */
        .ai-widget-suggestions {
            margin-top: auto;
            padding-top: 0.5rem;
        }

        .ai-widget-suggestions-title {
            font-size: 0.75rem;
            color: #94A3B8;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .ai-widget-suggestion-card {
            background: rgba(20, 30, 48, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 0.6rem 0.8rem;
            font-size: 0.8rem;
            color: #CBD5E1;
            margin-bottom: 0.4rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .ai-widget-suggestion-card:hover {
            background: rgba(99, 102, 241, 0.2);
            border-color: rgba(99, 102, 241, 0.4);
            color: white;
        }

        /* Footer Input */
        .ai-widget-footer {
            padding: 0.75rem 0.9rem;
            background: rgba(11, 17, 32, 0.9);
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        /* Attached Image Preview Bar Above Input */
        .ai-widget-img-preview-bar {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.4rem 0.6rem;
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 12px;
            margin-bottom: 0.5rem;
        }

        .ai-widget-img-preview-thumb {
            position: relative;
            width: 44px;
            height: 44px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .ai-widget-img-preview-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ai-widget-img-remove-btn {
            position: absolute;
            top: 2px;
            right: 2px;
            background: rgba(239, 68, 68, 0.9);
            border: none;
            color: white;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            font-size: 0.6rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ai-widget-img-preview-info {
            font-size: 0.75rem;
            color: #CBD5E1;
            flex: 1;
        }

        .ai-widget-input-wrapper {
            display: flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: 14px;
            padding: 0.2rem 0.3rem 0.2rem 0.6rem;
        }

        .ai-widget-input-wrapper:focus-within {
            border-color: #6366F1;
            box-shadow: 0 0 12px rgba(99, 102, 241, 0.3);
        }

        .ai-widget-input {
            flex: 1;
            background: transparent;
            border: none;
            outline: none;
            color: white;
            font-size: 0.88rem;
            padding: 0.5rem 0.2rem;
        }

        .ai-widget-img-btn,
        .ai-widget-mic-btn {
            background: transparent;
            border: none;
            color: #94A3B8;
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.95rem;
            transition: all 0.2s;
        }

        .ai-widget-img-btn:hover,
        .ai-widget-mic-btn:hover {
            color: #38BDF8;
            background: rgba(56, 189, 248, 0.1);
        }

        .ai-widget-img-btn.active {
            color: #10B981;
            background: rgba(16, 185, 129, 0.15);
        }

        .ai-widget-mic-btn.recording {
            color: #EF4444;
            background: rgba(239, 68, 68, 0.15);
            animation: aiMicPulse 1.2s infinite;
        }

        @keyframes aiMicPulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.5); }
            50% { transform: scale(1.1); box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }

        .ai-widget-send-btn {
            background: linear-gradient(135deg, #6366F1 0%, #3B82F6 100%);
            border: none;
            border-radius: 10px;
            width: 36px;
            height: 36px;
            color: white;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
        }

        .ai-widget-send-btn:hover {
            transform: scale(1.05);
        }

        .ai-widget-send-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        /* Typing dots */
        .ai-widget-dots {
            display: flex;
            gap: 4px;
            align-items: center;
            padding: 0.3rem;
        }
        .ai-widget-dots span {
            width: 6px;
            height: 6px;
            background: #38BDF8;
            border-radius: 50%;
            animation: aiBounce 1.4s infinite ease-in-out both;
        }
        .ai-widget-dots span:nth-child(1) { animation-delay: -0.32s; }
        .ai-widget-dots span:nth-child(2) { animation-delay: -0.16s; }

        @keyframes aiBounce {
            0%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-5px); background: #818CF8; }
        }

        /* Live Voice Mode Full Overlay */
        .ai-voice-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at center, #1E1B4B 0%, #0F172A 70%, #090D16 100%);
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem 1rem;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transform: scale(0.95);
            transition: opacity 0.35s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.35s cubic-bezier(0.16, 1, 0.3, 1), transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .ai-voice-overlay.ai-voice-open {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
            transform: scale(1);
        }

        .ai-voice-header {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 0.5rem;
        }

        .ai-voice-badge {
            background: rgba(99, 102, 241, 0.2);
            border: 1px solid rgba(99, 102, 241, 0.4);
            color: #818CF8;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .ai-voice-close-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #94A3B8;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .ai-voice-close-btn:hover {
            color: white;
            background: rgba(255, 255, 255, 0.2);
        }

        /* Central Orb & Wave Visualizer */
        .ai-voice-center {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
            width: 100%;
        }

        .ai-voice-orb-wrapper {
            position: relative;
            width: 130px;
            height: 130px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ai-voice-ring {
            position: absolute;
            border-radius: 50%;
            border: 2px solid rgba(99, 102, 241, 0.3);
            animation: aiRingExpand 3s infinite ease-out;
        }

        .ai-voice-ring:nth-child(1) { width: 100%; height: 100%; animation-delay: 0s; }
        .ai-voice-ring:nth-child(2) { width: 125%; height: 125%; animation-delay: 1s; }
        .ai-voice-ring:nth-child(3) { width: 150%; height: 150%; animation-delay: 2s; }

        @keyframes aiRingExpand {
            0% { transform: scale(0.8); opacity: 0.8; border-color: rgba(99, 102, 241, 0.6); }
            100% { transform: scale(1.4); opacity: 0; border-color: rgba(56, 189, 248, 0); }
        }

        .ai-voice-orb {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366F1 0%, #3B82F6 50%, #EC4899 100%);
            box-shadow: 0 0 35px rgba(99, 102, 241, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.2rem;
            z-index: 2;
            transition: all 0.4s ease;
        }

        .ai-voice-overlay.state-listening .ai-voice-orb {
            background: linear-gradient(135deg, #10B981 0%, #06B6D4 100%);
            box-shadow: 0 0 45px rgba(16, 185, 129, 0.8);
            transform: scale(1.08);
        }

        .ai-voice-overlay.state-thinking .ai-voice-orb {
            background: linear-gradient(135deg, #8B5CF6 0%, #6366F1 100%);
            box-shadow: 0 0 45px rgba(139, 92, 246, 0.8);
            animation: aiOrbRotate 2s linear infinite;
        }

        .ai-voice-overlay.state-speaking .ai-voice-orb {
            background: linear-gradient(135deg, #3B82F6 0%, #06B6D4 100%);
            box-shadow: 0 0 50px rgba(59, 130, 246, 0.9);
            transform: scale(1.12);
        }

        @keyframes aiOrbRotate {
            0% { transform: rotate(0deg) scale(1); }
            50% { transform: rotate(180deg) scale(1.05); }
            100% { transform: rotate(360deg) scale(1); }
        }

        .ai-voice-waves {
            display: flex;
            align-items: center;
            gap: 6px;
            height: 32px;
        }

        .ai-voice-wave-bar {
            width: 4px;
            height: 8px;
            background: #6366F1;
            border-radius: 4px;
            transition: height 0.15s ease;
        }

        .ai-voice-overlay.state-listening .ai-voice-wave-bar {
            background: #10B981;
            animation: aiWaveAnim 0.8s infinite ease-in-out alternate;
        }

        .ai-voice-overlay.state-speaking .ai-voice-wave-bar {
            background: #38BDF8;
            animation: aiWaveAnim 0.5s infinite ease-in-out alternate;
        }

        .ai-voice-wave-bar:nth-child(1) { animation-delay: 0.1s; }
        .ai-voice-wave-bar:nth-child(2) { animation-delay: 0.25s; }
        .ai-voice-wave-bar:nth-child(3) { animation-delay: 0.4s; }
        .ai-voice-wave-bar:nth-child(4) { animation-delay: 0.2s; }
        .ai-voice-wave-bar:nth-child(5) { animation-delay: 0.35s; }

        @keyframes aiWaveAnim {
            0% { height: 6px; }
            100% { height: 28px; }
        }

        .ai-voice-status-title {
            color: white;
            font-size: 1.05rem;
            font-weight: 700;
            text-align: center;
        }

        .ai-voice-subtitle-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 0.9rem 1.1rem;
            width: 100%;
            max-height: 110px;
            overflow-y: auto;
            color: #CBD5E1;
            font-size: 0.85rem;
            line-height: 1.6;
            text-align: center;
        }

        .ai-voice-controls {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            margin-top: auto;
        }

        .ai-voice-ctrl-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            color: white;
            font-size: 1.1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .ai-voice-ctrl-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }

        .ai-voice-ctrl-btn.off {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.4);
            color: #EF4444;
        }

        .ai-voice-ctrl-btn.end-call {
            width: 58px;
            height: 58px;
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            border: none;
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
            font-size: 1.3rem;
        }

        .ai-voice-ctrl-btn.end-call:hover {
            transform: scale(1.08);
            box-shadow: 0 12px 25px rgba(239, 68, 68, 0.6);
        }

        @media (max-width: 640px) {
            .ai-widget-trigger {
                bottom: 16px;
                ${POSITION}: 16px;
                width: 52px;
                height: 52px;
                font-size: 1.3rem;
            }

            .ai-widget-window {
                width: 100% !important;
                height: 100% !important;
                height: 100dvh !important;
                max-height: 100vh !important;
                max-height: 100dvh !important;
                max-width: 100vw !important;
                bottom: 0 !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                border-radius: 0 !important;
                border: none !important;
                box-shadow: none !important;
                position: fixed !important;
            }

            .ai-widget-header {
                padding: 0.75rem 0.85rem;
            }

            .ai-widget-header .ai-brand-title h4 {
                font-size: 0.88rem;
            }

            .ai-widget-header .ai-brand-title p {
                font-size: 0.68rem;
            }

            .ai-widget-body {
                padding: 0.75rem 0.65rem;
                gap: 0.75rem;
            }

            .ai-widget-msg {
                max-width: 95%;
            }

            .ai-widget-footer {
                padding: 0.6rem 0.75rem;
                padding-bottom: max(0.6rem, env(safe-area-inset-bottom));
            }

            .ai-widget-input-wrapper {
                padding: 0.2rem 0.3rem 0.2rem 0.5rem;
                border-radius: 12px;
            }

            .ai-widget-input {
                font-size: 0.84rem;
                padding: 0.4rem 0.2rem;
            }

            .ai-widget-send-btn {
                width: 34px;
                height: 34px;
                font-size: 0.85rem;
                border-radius: 8px;
            }

            .ai-widget-img-btn,
            .ai-widget-mic-btn {
                width: 30px;
                height: 30px;
                font-size: 0.88rem;
            }
        }

        /* Tour Spotlight & Overlay */
        .ai-tour-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(11, 17, 32, 0.7);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 999998;
            display: none;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .ai-tour-backdrop.active {
            display: block;
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .ai-tour-highlight {
            position: fixed;
            z-index: 999999;
            border-radius: 16px;
            box-shadow: 0 0 0 9999px rgba(11, 17, 32, 0.7), 0 0 25px var(--ai-primary, #6366F1);
            outline: 2.5px solid var(--ai-primary, #6366F1);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            pointer-events: none;
        }

        .ai-tour-card {
            position: fixed;
            z-index: 1000000;
            width: 330px;
            max-width: calc(100vw - 32px);
            background: var(--ai-bg, #0F172A);
            color: var(--ai-text, #F8FAFC);
            border: 1px solid var(--ai-border, rgba(99, 102, 241, 0.4));
            border-radius: 20px;
            padding: 1.1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6), 0 0 30px rgba(99, 102, 241, 0.25);
            display: none;
            opacity: 0;
            visibility: hidden;
            transform: scale(0.92) translateY(10px);
            pointer-events: none;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            direction: ${DIR};
        }

        .ai-tour-card.active {
            display: block;
            opacity: 1;
            visibility: visible;
            transform: scale(1) translateY(0);
            pointer-events: auto;
        }

        .ai-tour-progress-bar {
            width: 100%;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            margin-bottom: 0.75rem;
            overflow: hidden;
        }

        .ai-tour-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--ai-primary) 0%, var(--ai-secondary) 100%);
            width: 25%;
            transition: width 0.3s ease;
        }

        .ai-tour-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.6rem;
        }

        .ai-tour-step-badge {
            font-size: 0.75rem;
            color: var(--ai-text-muted, #94A3B8);
            background: rgba(255, 255, 255, 0.08);
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-weight: 600;
        }

        .ai-tour-skip-btn {
            background: transparent;
            border: none;
            color: var(--ai-text-muted, #94A3B8);
            font-size: 0.8rem;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .ai-tour-skip-btn:hover {
            color: #EF4444;
            background: rgba(239, 68, 68, 0.12);
        }

        .ai-tour-title {
            font-size: 1.02rem;
            font-weight: 700;
            color: var(--ai-text, #F8FAFC);
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .ai-tour-desc {
            font-size: 0.85rem;
            color: var(--ai-text-muted, #94A3B8);
            line-height: 1.55;
            margin-bottom: 1rem;
        }

        .ai-tour-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .ai-tour-nav-btn {
            padding: 0.45rem 0.95rem;
            border-radius: 10px;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .ai-tour-btn-back {
            background: rgba(255, 255, 255, 0.08);
            color: var(--ai-text, #F8FAFC);
        }

        .ai-tour-btn-back:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .ai-tour-btn-next {
            background: linear-gradient(135deg, var(--ai-primary) 0%, var(--ai-secondary) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.35);
        }

        /* Image Lightbox Modal */
        .ai-img-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 1000010;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .ai-img-modal.active {
            display: flex;
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .ai-img-modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(7, 10, 18, 0.88);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }

        .ai-img-modal-toolbar {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000012;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 0.4rem 0.8rem;
            border-radius: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            direction: ltr !important;
        }

        .ai-img-modal-btn {
            background: transparent;
            border: none;
            color: #94A3B8;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.95rem;
            transition: all 0.2s;
            float: none !important;
            position: relative !important;
            top: auto !important;
            left: auto !important;
            right: auto !important;
            bottom: auto !important;
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
            text-shadow: none !important;
            opacity: 1 !important;
            line-height: 1 !important;
        }

        .ai-img-modal-btn:hover {
            color: white;
            background: rgba(255, 255, 255, 0.15);
        }

        .ai-img-modal-btn.ai-img-modal-close-btn:hover {
            color: #EF4444;
            background: rgba(239, 68, 68, 0.2);
        }

        .ai-img-modal-scale {
            font-size: 0.78rem;
            color: #CBD5E1;
            font-weight: 600;
            min-width: 45px;
            text-align: center;
            user-select: none;
        }

        .ai-img-modal-content {
            position: relative;
            z-index: 1000011;
            max-width: 90vw;
            max-height: 85vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            cursor: grab;
            user-select: none;
        }

        .ai-img-modal-content:active {
            cursor: grabbing;
        }

        .ai-img-modal-content img {
            max-width: 90vw;
            max-height: 85vh;
            object-fit: contain;
            border-radius: 12px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.7);
            transition: transform 0.1s cubic-bezier(0.16, 1, 0.3, 1);
            transform-origin: center center;
        }

        .ai-widget-wrapper .ai-chat-image-preview,
        .ai-widget-wrapper .ai-chat-response-image,
        .ai-widget-wrapper .ai-bubble img {
            cursor: zoom-in !important;
            transition: transform 0.2s, filter 0.2s;
        }

        .ai-widget-wrapper .ai-chat-image-preview:hover,
        .ai-widget-wrapper .ai-chat-response-image:hover,
        .ai-widget-wrapper .ai-bubble img:hover {
            transform: scale(1.02);
            filter: brightness(1.05);
        }

        /* Mini ERP visual system */
        .ai-widget-wrapper {
            text-align: start;
            isolation: isolate;
            color-scheme: light dark;
        }

        .ai-widget-wrapper [hidden] {
            display: none !important;
        }

        .ai-widget-wrapper button,
        .ai-widget-wrapper input {
            font: inherit;
        }

        .ai-widget-wrapper .ai-icon {
            width: 1em;
            height: 1em;
            display: block;
            flex: 0 0 auto;
            direction: ltr;
            overflow: visible;
        }

        .ai-widget-trigger {
            width: 56px;
            height: 56px;
            font-size: 1.65rem;
            border: 3px solid rgba(255, 255, 255, 0.92);
            box-shadow: 0 12px 28px rgba(37, 99, 235, 0.28);
            transition: transform 0.22s ease, opacity 0.18s ease, box-shadow 0.22s ease;
        }

        .ai-widget-trigger:hover {
            transform: translateY(-2px) scale(1.04);
            box-shadow: 0 16px 34px rgba(37, 99, 235, 0.34);
        }

        .ai-widget-trigger[aria-expanded="true"] {
            opacity: 0;
            transform: translateY(8px) scale(0.82);
            pointer-events: none;
        }

        .ai-widget-trigger .ai-badge-dot {
            width: 11px;
            height: 11px;
            top: 1px;
            right: 1px;
            border-color: var(--ai-bg);
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.15);
        }

        .ai-widget-window {
            bottom: var(--ai-bottom, 20px);
            max-height: calc(100dvh - 40px);
            border-radius: var(--ai-radius, 20px);
            box-shadow: 0 24px 64px rgba(15, 23, 42, 0.2), 0 8px 24px rgba(37, 99, 235, 0.08);
            overscroll-behavior: contain;
        }

        .ai-widget-header {
            padding: 0.85rem 1rem;
            background: var(--ai-bg);
            gap: 0.65rem;
        }

        .ai-widget-header .ai-brand {
            gap: 0.65rem;
        }

        .ai-widget-header .ai-brand-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            font-size: 1.35rem;
            box-shadow: 0 7px 18px rgba(37, 99, 235, 0.22);
        }

        .ai-widget-header .ai-brand-title h4 {
            display: flex;
            align-items: baseline;
            gap: 0.28rem;
            font-size: 0.9rem;
            line-height: 1.35;
            font-weight: 700;
            text-align: start;
        }

        .ai-widget-header .ai-brand-title h4 bdi {
            direction: ltr;
            unicode-bidi: isolate;
        }

        .ai-widget-header .ai-brand-title p {
            margin-top: 0.12rem;
            font-size: 0.68rem;
            line-height: 1.4;
        }

        .ai-widget-header .ai-actions {
            gap: 0.15rem;
        }

        .ai-widget-header .ai-action-btn {
            width: 36px;
            height: 36px;
            color: var(--ai-text-muted);
            font-size: 1rem;
            border-radius: 10px;
        }

        .ai-widget-header .ai-action-btn:hover {
            color: var(--ai-primary);
            background: color-mix(in srgb, var(--ai-primary) 10%, transparent);
        }

        .ai-widget-wrapper button:focus-visible {
            outline: 3px solid color-mix(in srgb, var(--ai-primary) 30%, transparent);
            outline-offset: 2px;
        }

        .ai-widget-wrapper .ai-widget-input:focus-visible {
            outline: none;
        }

        .ai-widget-body {
            padding: 0.9rem;
            gap: 0.75rem;
            background: var(--ai-bg);
        }

        .ai-widget-body::-webkit-scrollbar-thumb {
            background: color-mix(in srgb, var(--ai-text-muted) 38%, transparent);
        }

        .ai-widget-msg {
            max-width: 92%;
            gap: 0.5rem;
        }

        .ai-widget-msg.assistant {
            align-self: flex-start;
            flex-direction: row;
        }

        .ai-widget-msg.user {
            align-self: flex-end;
            flex-direction: row-reverse;
        }

        .ai-widget-msg .ai-avatar {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            font-size: 0.95rem;
        }

        .ai-widget-msg .ai-bubble {
            padding: 0.72rem 0.85rem;
            border-radius: 14px;
            color: var(--ai-text);
            font-size: 0.84rem;
            font-weight: 400;
            line-height: 1.75;
            text-align: start;
            unicode-bidi: plaintext;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.08);
        }

        .ai-widget-msg.assistant .ai-bubble {
            color: var(--ai-text);
            background: var(--ai-card-bg);
            border: 1px solid var(--ai-border);
            border-radius: 14px;
            border-end-start-radius: 4px;
        }

        .ai-widget-msg.user .ai-bubble {
            color: #FFFFFF;
            border-radius: 14px;
            border-end-end-radius: 4px;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.18);
        }

        .ai-msg-speak-btn {
            align-self: flex-start;
            color: var(--ai-text-muted);
            font-size: 0.72rem;
            min-height: 28px;
            padding: 0.2rem 0.45rem;
        }

        .ai-widget-suggestions {
            margin-top: 0.25rem;
            padding-top: 0.2rem;
        }

        .ai-widget-suggestions-title {
            color: var(--ai-text-muted);
            font-size: 0.72rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .ai-widget-suggestion-card {
            width: 100%;
            min-height: 44px;
            margin-bottom: 0.45rem;
            padding: 0.62rem 0.7rem;
            border-radius: 12px;
            border: 1px solid var(--ai-border);
            background: var(--ai-card-bg);
            color: var(--ai-text);
            font-size: 0.78rem;
            font-weight: 500;
            line-height: 1.45;
            text-align: start;
            appearance: none;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        }

        .ai-widget-suggestion-card:hover {
            color: var(--ai-primary);
            background: color-mix(in srgb, var(--ai-primary) 8%, var(--ai-bg));
            border-color: color-mix(in srgb, var(--ai-primary) 32%, var(--ai-border));
            transform: translateY(-1px);
            box-shadow: 0 5px 14px rgba(37, 99, 235, 0.1);
        }

        .ai-widget-suggestion-card .ai-suggestion-icon {
            width: 28px;
            height: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            color: var(--ai-primary);
            background: color-mix(in srgb, var(--ai-primary) 10%, transparent);
            font-size: 0.9rem;
            flex: 0 0 auto;
        }

        .ai-widget-suggestion-card .ai-suggestion-label {
            flex: 1;
            min-width: 0;
        }

        .ai-widget-suggestion-card .ai-suggestion-chevron {
            color: var(--ai-text-muted);
            font-size: 0.8rem;
            transition: transform 0.18s ease, color 0.18s ease;
        }

        .ai-widget-wrapper[dir="rtl"] .ai-suggestion-chevron {
            transform: rotate(180deg);
        }

        .ai-widget-footer {
            padding: 0.72rem 0.85rem 0.62rem;
            background: var(--ai-header-bg);
            border-top: 1px solid var(--ai-border);
        }

        .ai-widget-input-wrapper {
            min-height: 48px;
            gap: 0.25rem;
            padding: 0.25rem 0.3rem;
            border-radius: 14px;
            background: var(--ai-bg);
            border: 1px solid var(--ai-border);
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.04);
        }

        .ai-widget-input-wrapper:focus-within {
            border-color: var(--ai-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--ai-primary) 13%, transparent);
        }

        .ai-widget-input {
            min-width: 0;
            color: var(--ai-text);
            font-size: 0.84rem;
            font-weight: 400;
            padding: 0.55rem 0.45rem;
            text-align: start;
            unicode-bidi: plaintext;
        }

        .ai-widget-input::placeholder {
            color: var(--ai-text-muted);
            opacity: 0.86;
        }

        .ai-widget-img-btn,
        .ai-widget-mic-btn {
            width: 36px;
            height: 36px;
            color: var(--ai-text-muted);
            font-size: 1rem;
            border-radius: 10px;
            flex: 0 0 auto;
        }

        .ai-widget-send-btn {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            font-size: 1rem;
            flex: 0 0 auto;
            box-shadow: 0 5px 12px rgba(37, 99, 235, 0.24);
        }

        .ai-widget-footer-note {
            margin-top: 0.42rem;
            color: var(--ai-text-muted);
            font-size: 0.64rem;
            font-weight: 400;
            text-align: center;
            direction: auto;
        }

        .ai-widget-img-preview-bar {
            background: var(--ai-card-bg);
            border-color: var(--ai-border);
        }

        .ai-widget-img-preview-info {
            color: var(--ai-text);
        }

        @media (max-width: 640px) {
            .ai-widget-trigger {
                width: 54px;
                height: 54px;
                font-size: 1.55rem;
            }

            .ai-widget-window {
                top: max(8px, env(safe-area-inset-top)) !important;
                right: 8px !important;
                bottom: max(8px, env(safe-area-inset-bottom)) !important;
                left: 8px !important;
                width: auto !important;
                height: auto !important;
                max-width: none !important;
                max-height: none !important;
                border: 1px solid var(--ai-border) !important;
                border-radius: 20px !important;
                box-shadow: 0 18px 55px rgba(15, 23, 42, 0.24) !important;
            }

            .ai-widget-header {
                padding: 0.72rem 0.78rem;
            }

            .ai-widget-header .ai-action-btn {
                width: 40px;
                height: 40px;
            }

            .ai-widget-body {
                padding: 0.78rem;
            }

            .ai-widget-msg {
                max-width: 96%;
            }

            .ai-widget-footer {
                padding: 0.65rem 0.7rem max(0.65rem, env(safe-area-inset-bottom));
            }

            .ai-widget-input {
                font-size: 16px;
            }

            .ai-widget-img-btn,
            .ai-widget-mic-btn,
            .ai-widget-send-btn {
                width: 42px;
                height: 42px;
            }
        }

        @media (max-width: 360px) {
            .ai-widget-header {
                gap: 0.3rem;
            }

            .ai-widget-header .ai-brand {
                gap: 0.45rem;
            }

            .ai-widget-header .ai-brand-icon {
                width: 36px;
                height: 36px;
            }

            .ai-widget-header .ai-brand-title p {
                display: none;
            }

            .ai-widget-header .ai-action-btn {
                width: 36px;
                height: 36px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .ai-widget-wrapper *,
            .ai-widget-wrapper *::before,
            .ai-widget-wrapper *::after {
                scroll-behavior: auto !important;
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    `;

    document.head.appendChild(style);

    // Create Main Widget DOM Structure
    const widgetWrapper = document.createElement('div');
    widgetWrapper.className = 'ai-widget-wrapper';
    widgetWrapper.dir = DIR;
    widgetWrapper.innerHTML = `
        <!-- Hidden File Input for Image Upload -->
        <input type="file" id="aiWidgetFileInput" accept="image/png,image/jpeg,image/webp" style="display:none;">

        <!-- Floating Trigger Button -->
        <button type="button" class="ai-widget-trigger" id="aiWidgetTrigger"
                title="${escapeAttr(WIDGET_TITLE)}" aria-label="${escapeAttr(WIDGET_TITLE)}"
                aria-controls="aiWidgetWindow" aria-expanded="false">
            ${widgetIcon('assistant')}
            <span class="ai-badge-dot"></span>
        </button>

        <!-- Floating Chat Popup Window -->
        <div class="ai-widget-window" id="aiWidgetWindow" role="dialog" aria-modal="false"
             aria-labelledby="aiWidgetTitle" aria-hidden="true" inert>
            <header class="ai-widget-header">
                <div class="ai-brand">
                    <div class="ai-brand-icon">${widgetIcon('assistant')}</div>
                    <div class="ai-brand-title">
                        <h4 id="aiWidgetTitle">${isEnglish
                            ? '<bdi dir="ltr">Mini ERP</bdi><span>Assistant</span>'
                            : '<span>مساعد</span><bdi dir="ltr">Mini ERP</bdi>'}</h4>
                        <p><span class="ai-status-dot"></span> ${t.online}</p>
                    </div>
                </div>
                <div class="ai-actions">
                    <button type="button" class="ai-action-btn" id="aiWidgetTourBtn"
                            title="${t.tourHelpTitle}" aria-label="${t.tourHelpTitle}">
                        ${widgetIcon('help')}
                    </button>
                    <button type="button" class="ai-action-btn" id="aiWidgetVoiceModeBtn"
                            title="${t.liveVoiceTitle}" aria-label="${t.liveVoiceTitle}">
                        ${widgetIcon('mic')}
                    </button>
                    <button type="button" class="ai-action-btn" id="aiWidgetClearBtn"
                            title="${t.clearTitle}" aria-label="${t.clearTitle}">
                        ${widgetIcon('trash')}
                    </button>
                    <button type="button" class="ai-action-btn" id="aiWidgetCloseBtn"
                            title="${t.closeTitle}" aria-label="${t.closeTitle}">
                        ${widgetIcon('close')}
                    </button>
                </div>
            </header>

            <div class="ai-widget-body" id="aiWidgetBody" role="log" aria-live="polite" aria-relevant="additions">
                <div class="ai-widget-msg assistant">
                    <div class="ai-avatar">${widgetIcon('assistant')}</div>
                    <div class="ai-bubble-container">
                        <div class="ai-bubble" dir="auto">
                            ${t.welcome}
                        </div>
                        <button type="button" class="ai-msg-speak-btn" title="${t.listenMsg}">
                            ${widgetIcon('volume')} ${t.listenBtn}
                        </button>
                    </div>
                </div>

                <div class="ai-widget-suggestions" id="aiWidgetSuggestions">
                    <div class="ai-widget-suggestions-title">${widgetIcon('bulb')} ${t.faqTitle}</div>
                    <button type="button" class="ai-widget-suggestion-card" data-q="${t.faq1Q}">
                        <span class="ai-suggestion-icon">${widgetIcon('gear')}</span>
                        <span class="ai-suggestion-label">${t.faq1Title}</span>
                        ${widgetIcon('chevron', 'ai-suggestion-chevron')}
                    </button>
                    <button type="button" class="ai-widget-suggestion-card" data-q="${t.faq2Q}">
                        <span class="ai-suggestion-icon">${widgetIcon('receipt')}</span>
                        <span class="ai-suggestion-label">${t.faq2Title}</span>
                        ${widgetIcon('chevron', 'ai-suggestion-chevron')}
                    </button>
                    <button type="button" class="ai-widget-suggestion-card" data-q="${t.faq3Q}">
                        <span class="ai-suggestion-icon">${widgetIcon('cart')}</span>
                        <span class="ai-suggestion-label">${t.faq3Title}</span>
                        ${widgetIcon('chevron', 'ai-suggestion-chevron')}
                    </button>
                </div>
            </div>

            <footer class="ai-widget-footer">
                <!-- Attached Image Preview Bar -->
                <div class="ai-widget-img-preview-bar" id="aiWidgetImgPreviewBar" style="display:none;">
                    <div class="ai-widget-img-preview-thumb">
                        <img id="aiWidgetImgPreviewImg" src="" alt="Image attached">
                        <button type="button" class="ai-widget-img-remove-btn" id="aiWidgetImgRemoveBtn"
                                title="${t.closeTitle}" aria-label="${t.closeTitle}">
                            ${widgetIcon('close')}
                        </button>
                    </div>
                    <div class="ai-widget-img-preview-info">
                        ${t.imgAttachedInfo}
                    </div>
                </div>

                <form id="aiWidgetForm">
                    <div class="ai-widget-input-wrapper">
                        <input type="text" id="aiWidgetInput" class="ai-widget-input" placeholder="${t.placeholder}"
                               aria-label="${t.inputLabel}" dir="auto" autocomplete="off" maxlength="4000">
                        <button type="button" id="aiWidgetImgBtn" class="ai-widget-img-btn"
                                title="${t.attachImgTitle}" aria-label="${t.attachImgTitle}">
                            ${widgetIcon('image')}
                        </button>
                        <button type="button" id="aiWidgetMicBtn" class="ai-widget-mic-btn"
                                title="${t.voiceTitle}" aria-label="${t.voiceTitle}">
                            ${widgetIcon('mic')}
                        </button>
                        <button type="submit" id="aiWidgetSendBtn" class="ai-widget-send-btn"
                                title="${t.sendTitle}" aria-label="${t.sendTitle}">
                            ${widgetIcon('send')}
                        </button>
                    </div>
                </form>
                <div class="ai-widget-footer-note">${t.footerNote}</div>
            </footer>

            <!-- Live Voice Overlay -->
            <div class="ai-voice-overlay" id="aiVoiceOverlay">
                <div class="ai-voice-header">
                    <div class="ai-voice-badge">
                        ${widgetIcon('wave')} Live Voice Chat
                    </div>
                    <button type="button" class="ai-voice-close-btn" id="aiVoiceCloseBtn"
                            title="${t.closeVoiceTitle}" aria-label="${t.closeVoiceTitle}">
                        ${widgetIcon('close')}
                    </button>
                </div>

                <div class="ai-voice-center">
                    <div class="ai-voice-orb-wrapper">
                        <div class="ai-voice-ring"></div>
                        <div class="ai-voice-ring"></div>
                        <div class="ai-voice-ring"></div>
                        <div class="ai-voice-orb" id="aiVoiceOrb">
                            ${widgetIcon('mic')}
                        </div>
                    </div>

                    <div class="ai-voice-waves">
                        <div class="ai-voice-wave-bar"></div>
                        <div class="ai-voice-wave-bar"></div>
                        <div class="ai-voice-wave-bar"></div>
                        <div class="ai-voice-wave-bar"></div>
                        <div class="ai-voice-wave-bar"></div>
                    </div>

                    <div class="ai-voice-status-title" id="aiVoiceStatusText">
                        ${t.voicePromptReady}
                    </div>

                    <div class="ai-voice-subtitle-box" id="aiVoiceSubtitle">
                        ${t.voiceListening}
                    </div>
                </div>

                <div class="ai-voice-controls">
                    <button type="button" class="ai-voice-ctrl-btn" id="aiVoiceMuteMicBtn"
                            title="${t.muteMicTitle}" aria-label="${t.muteMicTitle}">
                        ${widgetIcon('mic')}
                    </button>
                    <button type="button" class="ai-voice-ctrl-btn end-call" id="aiVoiceEndCallBtn"
                            title="${t.endVoiceTitle}" aria-label="${t.endVoiceTitle}">
                        ${widgetIcon('phoneOff')}
                    </button>
                    <button type="button" class="ai-voice-ctrl-btn" id="aiVoiceMuteSpeakerBtn"
                            title="${t.muteSpeakerTitle}" aria-label="${t.muteSpeakerTitle}">
                        ${widgetIcon('volume')}
                    </button>
                </div>
            </div>
        </div>

        <!-- Tour Guide Overlay & Spotlight Elements -->
        <div class="ai-tour-backdrop" id="aiTourBackdrop"></div>
        <div class="ai-tour-highlight" id="aiTourHighlight" style="display:none;"></div>
        <div class="ai-tour-card" id="aiTourCard">
            <div class="ai-tour-progress-bar">
                <div class="ai-tour-progress-fill" id="aiTourProgressFill"></div>
            </div>
            <div class="ai-tour-header">
                <span class="ai-tour-step-badge" id="aiTourStepBadge">1 / 4</span>
                <button type="button" class="ai-tour-skip-btn" id="aiTourSkipBtn">${t.tourSkip} ✕</button>
            </div>
            <div class="ai-tour-title" id="aiTourTitle"></div>
            <div class="ai-tour-desc" id="aiTourDesc"></div>
            <div class="ai-tour-footer">
                <button type="button" class="ai-tour-nav-btn ai-tour-btn-back" id="aiTourBackBtn">${t.tourBack}</button>
                <button type="button" class="ai-tour-nav-btn ai-tour-btn-next" id="aiTourNextBtn">${t.tourNext}</button>
            </div>
        </div>

        <!-- Image Lightbox Modal with Zoom Controls -->
        <div class="ai-img-modal" id="aiImgModal">
            <div class="ai-img-modal-backdrop" id="aiImgModalBackdrop"></div>
            <div class="ai-img-modal-toolbar">
                <button type="button" class="ai-img-modal-btn" id="aiImgZoomIn" title="تكبير (+)" aria-label="تكبير">${widgetIcon('zoomIn')}</button>
                <button type="button" class="ai-img-modal-btn" id="aiImgZoomOut" title="تصغير (-)" aria-label="تصغير">${widgetIcon('zoomOut')}</button>
                <button type="button" class="ai-img-modal-btn" id="aiImgZoomReset" title="إعادة الضبط" aria-label="إعادة الضبط">${widgetIcon('reset')}</button>
                <span class="ai-img-modal-scale" id="aiImgZoomScale">100%</span>
                <button type="button" class="ai-img-modal-btn ai-img-modal-close-btn" id="aiImgModalClose" title="إغلاق (Esc)" aria-label="إغلاق">${widgetIcon('close')}</button>
            </div>
            <div class="ai-img-modal-content" id="aiImgModalContent">
                <img id="aiImgModalImg" src="" alt="صورة مكبرة">
            </div>
        </div>
    `;

    document.body.appendChild(widgetWrapper);

    // Widget State & Elements
    const triggerBtn = document.getElementById('aiWidgetTrigger');
    const windowEl = document.getElementById('aiWidgetWindow');
    const closeBtn = document.getElementById('aiWidgetCloseBtn');
    const clearBtn = document.getElementById('aiWidgetClearBtn');
    const voiceModeBtn = document.getElementById('aiWidgetVoiceModeBtn');
    const chatBody = document.getElementById('aiWidgetBody');
    const chatForm = document.getElementById('aiWidgetForm');
    const chatInput = document.getElementById('aiWidgetInput');
    const micBtn = document.getElementById('aiWidgetMicBtn');
    const sendBtn = document.getElementById('aiWidgetSendBtn');
    const suggestionsEl = document.getElementById('aiWidgetSuggestions');

    // Image Upload Elements
    const fileInput = document.getElementById('aiWidgetFileInput');
    const imgBtn = document.getElementById('aiWidgetImgBtn');
    const imgPreviewBar = document.getElementById('aiWidgetImgPreviewBar');
    const imgPreviewImg = document.getElementById('aiWidgetImgPreviewImg');
    const imgRemoveBtn = document.getElementById('aiWidgetImgRemoveBtn');

    // Live Voice Overlay Elements
    const voiceOverlay = document.getElementById('aiVoiceOverlay');
    const voiceCloseBtn = document.getElementById('aiVoiceCloseBtn');
    const voiceOrb = document.getElementById('aiVoiceOrb');
    const voiceStatusText = document.getElementById('aiVoiceStatusText');
    const voiceSubtitle = document.getElementById('aiVoiceSubtitle');
    const voiceMuteMicBtn = document.getElementById('aiVoiceMuteMicBtn');
    const voiceEndCallBtn = document.getElementById('aiVoiceEndCallBtn');
    const voiceMuteSpeakerBtn = document.getElementById('aiVoiceMuteSpeakerBtn');

    if (!ENABLE_VOICE) {
        voiceModeBtn.hidden = true;
        micBtn.hidden = true;
    }
    if (!ENABLE_VISION) {
        imgBtn.hidden = true;
    }

    let history = [];
    let isVoiceModeActive = false;
    let isQuickRecording = false;
    let isMicMuted = false;
    let isSpeakerMuted = false;
    let selectedImageBase64 = null;
    let isDestroyed = false;
    let liveVoiceRestartTimeout = null;
    let previousBodyOverflow = null;
    const activeRequestControllers = new Set();
    const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    // Web Speech API references
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    let quickRecognition = null;
    let liveVoiceRecognition = null;

    // Image Upload Handler
    imgBtn.addEventListener('click', () => {
        fileInput.click();
    });

    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;

        if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) {
            alert('يرجى اختيار ملف صورة صالحة (PNG, JPG, WEBP).');
            return;
        }
        if (file.size > MAX_IMAGE_BYTES) {
            alert('حجم الصورة أكبر من 5 ميجابايت. اختر صورة أصغر.');
            clearSelectedImage();
            return;
        }

        const reader = new FileReader();
        reader.onload = (evt) => {
            selectedImageBase64 = evt.target.result;
            imgPreviewImg.src = selectedImageBase64;
            imgPreviewBar.style.display = 'flex';
            imgBtn.classList.add('active');
            chatInput.focus();
        };
        reader.readAsDataURL(file);
    });

    imgRemoveBtn.addEventListener('click', clearSelectedImage);

    function clearSelectedImage() {
        selectedImageBase64 = null;
        fileInput.value = '';
        imgPreviewImg.src = '';
        imgPreviewBar.style.display = 'none';
        imgBtn.classList.remove('active');
    }

    // Load saved memory from localStorage
    try {
        const savedHistory = PERSIST_HISTORY ? localStorage.getItem(HISTORY_STORAGE_KEY) : null;
        history = savedHistory ? JSON.parse(savedHistory) : INITIAL_HISTORY;
        if (Array.isArray(history) && history.length > 0) {
            if (suggestionsEl) suggestionsEl.style.display = 'none';
            history.forEach(msg => appendMsg(msg.role, msg.content, null, false));
        }
    } catch (e) {
        history = [];
    }

    window.MiniErpAiWidgetGetHistory = function () {
        return history
            .filter((message) => message
                && (message.role === 'user' || message.role === 'assistant')
                && typeof message.content === 'string')
            .map((message) => ({
                role: message.role,
                content: message.content.slice(0, 4000)
            }))
            .slice(-20);
    };

    function lockBodyScroll() {
        if (previousBodyOverflow === null) {
            previousBodyOverflow = document.body.style.overflow;
        }
        document.body.style.overflow = 'hidden';
    }

    function restoreBodyScroll() {
        if (previousBodyOverflow === null) return;
        document.body.style.overflow = previousBodyOverflow;
        previousBodyOverflow = null;
    }

    function setWindowOpen(isOpen, restoreFocus = true) {
        windowEl.classList.toggle('ai-widget-open', isOpen);
        triggerBtn.setAttribute('aria-expanded', String(isOpen));
        windowEl.setAttribute('aria-hidden', String(!isOpen));
        windowEl.inert = !isOpen;

        if (isOpen) {
            windowEl.removeAttribute('inert');
            window.requestAnimationFrame(() => chatInput.focus());
            if (window.innerWidth <= 640) {
                lockBodyScroll();
            }
        } else {
            windowEl.setAttribute('inert', '');
            if (isVoiceModeActive) closeVoiceMode();
            restoreBodyScroll();
            if (restoreFocus && document.body.contains(triggerBtn)) {
                triggerBtn.focus();
            }
        }
    }

    function toggleWindow() {
        setWindowOpen(!windowEl.classList.contains('ai-widget-open'));
    }

    triggerBtn.addEventListener('click', toggleWindow);
    closeBtn.addEventListener('click', () => setWindowOpen(false));

    clearBtn.addEventListener('click', () => {
        stopSpeechSynthesis();
        clearSelectedImage();
        chatBody.innerHTML = `
            <div class="ai-widget-msg assistant">
                <div class="ai-avatar">${widgetIcon('assistant')}</div>
                <div class="ai-bubble-container">
                    <div class="ai-bubble" dir="auto">${t.welcomeBack}</div>
                    <button type="button" class="ai-msg-speak-btn" title="${t.listenMsg}">
                        ${widgetIcon('volume')} ${t.listenBtn}
                    </button>
                </div>
            </div>
        `;
        history = [];
        try { localStorage.removeItem(HISTORY_STORAGE_KEY); } catch(e){}
        if (suggestionsEl) {
            suggestionsEl.style.display = 'block';
            chatBody.appendChild(suggestionsEl);
        }
    });

    // Formatting & TTS Helpers
    function escapeAttr(value) {
        return String(value || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function stripSafetyLabels(text) {
        return String(text || '').replace(/(?:User|Response)\s*Safety\s*:\s*(?:safe|unsafe)/gi, '').trim();
    }

    function formatWidgetText(text) {
        const cleanText = stripSafetyLabels(text);
        if (!cleanText) return '';
        return cleanText
            .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
            .replace(/!\[([^\]]*)\]\((https?:\/\/[^\s)"']+|\/(?!\/)[^\s)"']+|data:image\/(?:png|jpe?g|gif|webp);base64,[A-Za-z0-9+/=]+)\)/gi, (_, alt, url) => {
                const imgAlt = alt || 'صورة توضيحية';
                return `<img src="${escapeAttr(url)}" alt="${escapeAttr(imgAlt)}" class="ai-chat-response-image" loading="lazy">`;
            })
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/`(.*?)`/g, '<code>$1</code>')
            .replace(/\n/g, '<br>');
    }

    function cleanTextForSpeech(text) {
        const cleanText = stripSafetyLabels(text);
        if (!cleanText) return '';
        return cleanText
            .replace(/\[NOT_FOUND\]/g, '')
            .replace(/!\[(.*?)\]\((.*?)\)/g, '$1')
            .replace(/\[(.*?)\]\((.*?)\)/g, '$1')
            .replace(/\*\*(.*?)\*\*/g, '$1')
            .replace(/`(.*?)`/g, '$1')
            .replace(/<[^>]*>?/gm, '')
            .replace(/[\#\*\-\_]/g, ' ')
            .trim();
    }

    let activeSpeakBtn = null;

    function resetSpeakButton(btn) {
        if (!btn) return;
        btn.classList.remove('speaking', 'paused');
        btn.innerHTML = widgetIcon('volume') + ' ' + t.listenBtn;
        btn.title = t.listenMsg;
    }

    function stopSpeechSynthesis() {
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }
        document.querySelectorAll('.ai-msg-speak-btn').forEach(resetSpeakButton);
        activeSpeakBtn = null;
    }

    function handleSpeakBtnClick(speakBtn, text) {
        if (!('speechSynthesis' in window)) return;

        // 1. If currently speaking on THIS button -> PAUSE
        if (speakBtn === activeSpeakBtn && window.speechSynthesis.speaking && !window.speechSynthesis.paused) {
            window.speechSynthesis.pause();
            speakBtn.classList.remove('speaking');
            speakBtn.classList.add('paused');
            speakBtn.innerHTML = widgetIcon('play') + ' ' + t.resumeBtn;
            speakBtn.title = t.resumeMsg;
            return;
        }

        // 2. If currently PAUSED on THIS button -> RESUME
        if (speakBtn === activeSpeakBtn && window.speechSynthesis.paused) {
            window.speechSynthesis.resume();
            speakBtn.classList.remove('paused');
            speakBtn.classList.add('speaking');
            speakBtn.innerHTML = widgetIcon('pause') + ' ' + t.pauseBtn;
            speakBtn.title = t.pauseMsg;
            return;
        }

        // 3. Otherwise -> START NEW SPEECH
        stopSpeechSynthesis();
        activeSpeakBtn = speakBtn;
        speakBtn.classList.add('speaking');
        speakBtn.classList.remove('paused');
        speakBtn.innerHTML = widgetIcon('pause') + ' ' + t.pauseBtn;
        speakBtn.title = t.pauseMsg;

        speakText(text, () => {
            resetSpeakButton(speakBtn);
            if (activeSpeakBtn === speakBtn) activeSpeakBtn = null;
        });
    }

    function speakText(text, onEndCallback) {
        if (isSpeakerMuted || !('speechSynthesis' in window)) {
            if (onEndCallback) onEndCallback();
            return;
        }

        const cleanStr = cleanTextForSpeech(text);
        if (!cleanStr) {
            if (onEndCallback) onEndCallback();
            return;
        }

        const utterance = new SpeechSynthesisUtterance(cleanStr);
        utterance.lang = isEnglish ? 'en-US' : 'ar-SA';
        utterance.rate = 0.98;
        utterance.pitch = 1.0;

        const voices = window.speechSynthesis.getVoices();
        const matchingVoice = voices.find(v => isEnglish ? (v.lang.startsWith('en') || v.name.toLowerCase().includes('english')) : (v.lang.startsWith('ar') || v.name.toLowerCase().includes('arabic')));
        if (matchingVoice) utterance.voice = matchingVoice;

        utterance.onend = () => {
            if (onEndCallback) onEndCallback();
        };

        utterance.onerror = () => {
            if (onEndCallback) onEndCallback();
        };

        window.speechSynthesis.speak(utterance);
    }

    // Append Message to UI
    function appendMsg(sender, text, imageSrc = null, save = true) {
        if (suggestionsEl && sender === 'user') {
            suggestionsEl.style.display = 'none';
        }

        const msgRow = document.createElement('div');
        msgRow.className = `ai-widget-msg ${sender}`;

        const avatar = document.createElement('div');
        avatar.className = 'ai-avatar';
        avatar.innerHTML = widgetIcon(sender === 'user' ? 'user' : 'assistant');

        const bubbleContainer = document.createElement('div');
        bubbleContainer.className = 'ai-bubble-container';

        const bubble = document.createElement('div');
        bubble.className = 'ai-bubble';
        bubble.dir = 'auto';

        if (imageSrc) {
            const img = document.createElement('img');
            img.src = imageSrc;
            img.className = 'ai-chat-image-preview';
            img.alt = 'صورة مرفقة';
            bubble.appendChild(img);
        }

        if (text) {
            const textDiv = document.createElement('div');
            textDiv.innerHTML = formatWidgetText(text);
            bubble.appendChild(textDiv);
        }

        bubbleContainer.appendChild(bubble);

        if (sender === 'assistant') {
            const speakBtn = document.createElement('button');
            speakBtn.type = 'button';
            speakBtn.className = 'ai-msg-speak-btn';
            speakBtn.title = t.listenMsg;
            speakBtn.innerHTML = widgetIcon('volume') + ' ' + t.listenBtn;

            speakBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                handleSpeakBtnClick(speakBtn, text);
            });
            bubbleContainer.appendChild(speakBtn);
        }

        msgRow.appendChild(avatar);
        msgRow.appendChild(bubbleContainer);
        chatBody.appendChild(msgRow);
        chatBody.scrollTop = chatBody.scrollHeight;

        if (save) {
            history.push({ role: sender, content: text || '[صورة مرفقة]' });
            try {
                if (PERSIST_HISTORY) localStorage.setItem(HISTORY_STORAGE_KEY, JSON.stringify(history.slice(-20)));
            } catch (e) {}
        }
    }

    function showTyping() {
        const row = document.createElement('div');
        row.className = 'ai-widget-msg assistant';
        row.id = 'aiWidgetTyping';
        row.innerHTML = `
            <div class="ai-avatar">${widgetIcon('assistant')}</div>
            <div class="ai-bubble-container">
                <div class="ai-bubble"><div class="ai-widget-dots"><span></span><span></span><span></span></div></div>
            </div>
        `;
        chatBody.appendChild(row);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    function removeTyping() {
        const typing = document.getElementById('aiWidgetTyping');
        if (typing) typing.remove();
    }

    // Core Question Sending Logic
    async function sendQuestion(q, isVoice = false) {
        if (isDestroyed) return;

        const imageToSend = selectedImageBase64;
        if (!q && !imageToSend) return;

        appendMsg('user', q, imageToSend);
        chatInput.value = '';
        clearSelectedImage();
        chatInput.disabled = true;
        sendBtn.disabled = true;
        showTyping();

        if (isVoiceModeActive) {
            setVoiceState('thinking', '🧠 جاري تحليل السؤال والبحث في دليل النظام...', q);
        }

        const controller = new AbortController();
        activeRequestControllers.add(controller);
        const timeoutId = window.setTimeout(() => controller.abort(), 90000);
        try {
            // The current user message is sent as `question`; keep only prior turns in history.
            const requestHistory = history.slice(0, -1).slice(-8);
            const headers = { 'Content-Type': 'application/json' };
            try {
                const targetOrigin = new URL(API_URL, window.location.href).origin;
                if (targetOrigin === window.location.origin) {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    if (csrf) headers['X-CSRF-TOKEN'] = csrf;
                    headers['X-Requested-With'] = 'XMLHttpRequest';
                }
            } catch (e) {}

            const bodyPayload = {
                question: q,
                tenant_id: CONTEXT_ID,
                history: requestHistory,
                is_voice: isVoiceModeActive
            };
            if (imageToSend) {
                bodyPayload.image_base64 = imageToSend;
            }

            const response = await fetch(`${API_URL}/chat`, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify(bodyPayload),
                credentials: 'same-origin',
                signal: controller.signal
            });

            if (isDestroyed) return;
            const data = await response.json();
            if (isDestroyed) return;
            removeTyping();

            if (response.ok) {
                const answer = data.answer;
                appendMsg('assistant', answer);

                if (isVoiceModeActive) {
                    setVoiceState('speaking', '🔊 جاري التحدث بنتيجة الإجابة...', answer);
                    speakText(answer, () => {
                        if (isVoiceModeActive && !isMicMuted) {
                            startLiveVoiceListening();
                        } else if (isVoiceModeActive) {
                            setVoiceState('idle', '🎙️ الميكروفون مكتوم حالياً', answer);
                        }
                    });
                }
            } else {
                const errMsg = `⚠️ خطأ (${response.status}): ${data.detail || 'تعذر الحصول على إجابة'}`;
                appendMsg('assistant', errMsg);
                if (isVoiceModeActive) {
                    setVoiceState('idle', '❌ تعذر الحصول على إجابة من السيرفر', errMsg);
                }
            }
        } catch (err) {
            if (isDestroyed) return;
            removeTyping();
            const errMsg = err && err.name === 'AbortError'
                ? '⏱️ انتهت مهلة الاتصال. حاول مرة أخرى.'
                : '❌ حدث خطأ أثناء الاتصال بالسيرفر.';
            appendMsg('assistant', errMsg);
            if (isVoiceModeActive) {
                setVoiceState('idle', '❌ تعذر الاتصال بالسيرفر', errMsg);
            }
        } finally {
            activeRequestControllers.delete(controller);
            window.clearTimeout(timeoutId);
            if (!isDestroyed) {
                chatInput.disabled = false;
                sendBtn.disabled = false;
                if (!isVoiceModeActive) chatInput.focus();
            }
        }
    }

    chatForm.addEventListener('submit', (e) => {
        e.preventDefault();
        sendQuestion(chatInput.value.trim());
    });

    // Delegation for suggestion cards, speak buttons, and image modal opening
    chatBody.addEventListener('click', (e) => {
        const img = e.target.closest('img');
        if (img && img.src && !img.closest('#aiWidgetImgPreviewBar')) {
            e.stopPropagation();
            openImageModal(img.src);
            return;
        }

        const speakBtn = e.target.closest('.ai-msg-speak-btn');
        if (speakBtn) {
            const container = speakBtn.closest('.ai-bubble-container');
            const bubble = container ? container.querySelector('.ai-bubble') : null;
            const textToSpeak = bubble ? bubble.textContent : '';
            if (textToSpeak) {
                handleSpeakBtnClick(speakBtn, textToSpeak);
            }
            return;
        }

        const card = e.target.closest('.ai-widget-suggestion-card');
        if (card) {
            const q = card.getAttribute('data-q');
            if (q) sendQuestion(q);
        }
    });

    // Quick Mic Input (Speech-to-Text in Footer)
    if (SpeechRecognition) {
        quickRecognition = new SpeechRecognition();
        quickRecognition.lang = isEnglish ? 'en-US' : 'ar-SA';
        quickRecognition.interimResults = true;
        quickRecognition.continuous = false;

        quickRecognition.onstart = () => {
            isQuickRecording = true;
            micBtn.classList.add('recording');
            micBtn.title = isEnglish ? 'Listening... click to stop' : 'جاري الاستماع... اضغط للإيقاف';
            chatInput.placeholder = isEnglish ? 'Listening to your voice...' : 'جاري الاستماع لصوتك الآن...';
        };

        quickRecognition.onresult = (event) => {
            let transcript = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                transcript += event.results[i][0].transcript;
            }
            chatInput.value = transcript;
        };

        quickRecognition.onerror = () => {
            stopQuickMic();
        };

        quickRecognition.onend = () => {
            stopQuickMic();
        };

        function stopQuickMic() {
            isQuickRecording = false;
            micBtn.classList.remove('recording');
            micBtn.title = t.voiceTitle;
            chatInput.placeholder = t.placeholder;
        }

        micBtn.addEventListener('click', () => {
            if (isQuickRecording) {
                quickRecognition.stop();
            } else {
                try {
                    quickRecognition.start();
                } catch (e) {
                    stopQuickMic();
                }
            }
        });
    } else {
        micBtn.style.display = 'none';
    }

    // Live Voice Mode Overlay Logic
    function setVoiceState(state, statusText, subtitleText) {
        voiceOverlay.className = `ai-voice-overlay ai-voice-open state-${state}`;
        if (statusText) voiceStatusText.innerHTML = statusText;
        if (subtitleText) voiceSubtitle.innerHTML = formatWidgetText(subtitleText);

        if (state === 'listening') {
            voiceOrb.innerHTML = widgetIcon('mic');
        } else if (state === 'thinking') {
            voiceOrb.innerHTML = widgetIcon('brain');
        } else if (state === 'speaking') {
            voiceOrb.innerHTML = widgetIcon('volume');
        } else {
            voiceOrb.innerHTML = widgetIcon('micOff');
        }
    }

    function openVoiceMode() {
        if (!SpeechRecognition) {
            alert(t.browserNotSupported);
            return;
        }
        isVoiceModeActive = true;
        voiceModeBtn.classList.add('ai-voice-active');
        voiceOverlay.classList.add('ai-voice-open');
        setVoiceState('listening', t.listeningNow, t.listeningSub);
        startLiveVoiceListening();
    }

    function closeVoiceMode() {
        isVoiceModeActive = false;
        if (liveVoiceRestartTimeout !== null) {
            window.clearTimeout(liveVoiceRestartTimeout);
            liveVoiceRestartTimeout = null;
        }
        voiceModeBtn.classList.remove('ai-voice-active');
        voiceOverlay.classList.remove('ai-voice-open');
        stopSpeechSynthesis();
        if (liveVoiceRecognition) {
            try { liveVoiceRecognition.stop(); } catch(e){}
        }
    }

    function startLiveVoiceListening() {
        if (!isVoiceModeActive || isMicMuted || !SpeechRecognition) return;

        if (liveVoiceRecognition) {
            try { liveVoiceRecognition.stop(); } catch(e){}
        }

        liveVoiceRecognition = new SpeechRecognition();
        liveVoiceRecognition.lang = isEnglish ? 'en-US' : 'ar-SA';
        liveVoiceRecognition.interimResults = true;
        liveVoiceRecognition.continuous = false;

        let finalTranscript = '';

        liveVoiceRecognition.onstart = () => {
            setVoiceState('listening', t.listeningNow, t.listeningSub);
        };

        liveVoiceRecognition.onresult = (event) => {
            let interim = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                if (event.results[i].isFinal) {
                    finalTranscript += event.results[i][0].transcript;
                } else {
                    interim += event.results[i][0].transcript;
                }
            }
            const currentText = finalTranscript || interim;
            if (currentText) {
                voiceSubtitle.innerText = `🗣️ أنت تقول: "${currentText}"`;
            }
        };

        liveVoiceRecognition.onerror = (event) => {
            if (event.error !== 'no-speech' && isVoiceModeActive) {
                setVoiceState('idle', '⚠️ لم يتم التعرف على الصوت. اضغط على الميكروفون لإعادة المحاولة.', '');
            }
        };

        liveVoiceRecognition.onend = () => {
            if (!isVoiceModeActive) return;
            const textToSend = finalTranscript.trim();
            if (textToSend) {
                sendQuestion(textToSend, true);
            } else if (!isMicMuted) {
                liveVoiceRestartTimeout = window.setTimeout(() => {
                    liveVoiceRestartTimeout = null;
                    if (isVoiceModeActive && !isMicMuted) startLiveVoiceListening();
                }, 1000);
            }
        };

        try {
            liveVoiceRecognition.start();
        } catch (e) {
            setVoiceState('idle', '⚠️ تعذر فتح الميكروفون', 'تأكد من إعطاء الصلاحية للمتصفح.');
        }
    }

    voiceModeBtn.addEventListener('click', () => {
        if (isVoiceModeActive) {
            closeVoiceMode();
        } else {
            openVoiceMode();
        }
    });

    voiceCloseBtn.addEventListener('click', closeVoiceMode);
    voiceEndCallBtn.addEventListener('click', closeVoiceMode);

    voiceMuteMicBtn.addEventListener('click', () => {
        isMicMuted = !isMicMuted;
        if (isMicMuted) {
            voiceMuteMicBtn.classList.add('off');
            voiceMuteMicBtn.innerHTML = widgetIcon('micOff');
            if (liveVoiceRecognition) try { liveVoiceRecognition.stop(); } catch(e){}
            setVoiceState('idle', '🔇 الميكروفون مكتوم حالياً', 'اضغط على زر الميكروفون أدناه لإعادة الفتح...');
        } else {
            voiceMuteMicBtn.classList.remove('off');
            voiceMuteMicBtn.innerHTML = widgetIcon('mic');
            startLiveVoiceListening();
        }
    });

    voiceMuteSpeakerBtn.addEventListener('click', () => {
        isSpeakerMuted = !isSpeakerMuted;
        if (isSpeakerMuted) {
            voiceMuteSpeakerBtn.classList.add('off');
            voiceMuteSpeakerBtn.innerHTML = widgetIcon('volumeOff');
            stopSpeechSynthesis();
        } else {
            voiceMuteSpeakerBtn.classList.remove('off');
            voiceMuteSpeakerBtn.innerHTML = widgetIcon('volume');
        }
    });

    // Tour Guide Controller
    let currentTourStep = 0;
    const tourSteps = t.tourSteps.filter((step) => {
        if (!ENABLE_VOICE && step.targetId === 'aiWidgetVoiceModeBtn') return false;
        if (!ENABLE_VISION && step.targetId === 'aiWidgetImgBtn') return false;
        return true;
    });

    const tourBackdrop = document.getElementById('aiTourBackdrop');
    const tourHighlight = document.getElementById('aiTourHighlight');
    const tourCard = document.getElementById('aiTourCard');
    const tourProgressFill = document.getElementById('aiTourProgressFill');
    const tourStepBadge = document.getElementById('aiTourStepBadge');
    const tourSkipBtn = document.getElementById('aiTourSkipBtn');
    const tourTitleEl = document.getElementById('aiTourTitle');
    const tourDescEl = document.getElementById('aiTourDesc');
    const tourBackBtn = document.getElementById('aiTourBackBtn');
    const tourNextBtn = document.getElementById('aiTourNextBtn');
    const tourBtn = document.getElementById('aiWidgetTourBtn');

    function positionTourStep(stepIndex) {
        const step = tourSteps[stepIndex];
        if (!step) return;

        const targetEl = document.getElementById(step.targetId);
        if (!targetEl) return;

        // Open chat window if target is inside window
        if (step.targetId !== 'aiWidgetTrigger' && !windowEl.classList.contains('ai-widget-open')) {
            windowEl.classList.add('ai-widget-open');
        }

        const rect = targetEl.getBoundingClientRect();
        const padding = 6;

        // Position Highlight Box
        tourHighlight.style.display = 'block';
        tourHighlight.style.top = `${Math.max(0, rect.top - padding)}px`;
        tourHighlight.style.left = `${Math.max(0, rect.left - padding)}px`;
        tourHighlight.style.width = `${rect.width + padding * 2}px`;
        tourHighlight.style.height = `${rect.height + padding * 2}px`;

        // Update Card Text & Step Badges
        tourTitleEl.innerHTML = step.title;
        tourDescEl.innerHTML = step.desc;
        tourStepBadge.textContent = `${stepIndex + 1} ${t.tourStepOf} ${tourSteps.length}`;
        tourProgressFill.style.width = `${((stepIndex + 1) / tourSteps.length) * 100}%`;

        // Update Nav Buttons
        tourBackBtn.style.display = stepIndex === 0 ? 'none' : 'inline-flex';

        if (stepIndex === tourSteps.length - 1) {
            tourNextBtn.innerHTML = `${t.tourFinish}`;
        } else {
            tourNextBtn.innerHTML = `${t.tourNext} ${isEnglish ? '→' : '←'}`;
        }

        // Position Tour Tooltip Card
        const cardWidth = 330;
        const cardHeight = 170;
        const margin = 16;

        let cardTop, cardLeft;

        if (step.targetId === 'aiWidgetTrigger') {
            cardTop = rect.top - cardHeight - margin;
            if (POSITION === 'left') {
                cardLeft = rect.left;
            } else {
                cardLeft = Math.max(margin, rect.right - cardWidth);
            }
        } else {
            cardTop = rect.top + rect.height + margin;
            if (cardTop + cardHeight > window.innerHeight) {
                cardTop = rect.top - cardHeight - margin;
            }

            cardLeft = rect.left + (rect.width / 2) - (cardWidth / 2);
            cardLeft = Math.max(margin, Math.min(window.innerWidth - cardWidth - margin, cardLeft));
        }

        tourCard.style.top = `${Math.max(margin, cardTop)}px`;
        tourCard.style.left = `${Math.max(margin, cardLeft)}px`;
    }

    function startTour(force = false) {
        if (!force && localStorage.getItem(TOUR_STORAGE_KEY) === 'true') {
            return;
        }

        currentTourStep = 0;
        tourBackdrop.style.display = 'block';
        tourCard.style.display = 'block';
        positionTourStep(0);
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                tourBackdrop.classList.add('active');
                tourCard.classList.add('active');
            });
        });
    }

    function stopTour() {
        tourBackdrop.classList.remove('active');
        tourCard.classList.remove('active');
        tourHighlight.style.display = 'none';
        window.setTimeout(() => {
            if (!tourBackdrop.classList.contains('active')) {
                tourBackdrop.style.display = 'none';
            }
            if (!tourCard.classList.contains('active')) {
                tourCard.style.display = 'none';
            }
        }, 300);
        try {
            localStorage.setItem(TOUR_STORAGE_KEY, 'true');
        } catch(e){}
    }

    tourSkipBtn.addEventListener('click', stopTour);
    tourBackdrop.addEventListener('click', stopTour);

    tourBackBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (currentTourStep > 0) {
            currentTourStep--;
            positionTourStep(currentTourStep);
        }
    });

    tourNextBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (currentTourStep < tourSteps.length - 1) {
            currentTourStep++;
            positionTourStep(currentTourStep);
        } else {
            stopTour();
            if (!windowEl.classList.contains('ai-widget-open')) {
                toggleWindow();
            }
        }
    });

    if (tourBtn) {
        tourBtn.addEventListener('click', () => {
            startTour(true);
        });
    }

    function handleTourResize() {
        if (tourCard.classList.contains('active')) {
            positionTourStep(currentTourStep);
        }
    }
    window.addEventListener('resize', handleTourResize);

    // Auto-launch tour if configured or first time
    const autoTour = cfg.autoTour !== undefined ? cfg.autoTour : (currentScript.getAttribute('data-auto-tour') === 'true');
    const autoTourTimeout = autoTour
        ? window.setTimeout(() => startTour(false), 1000)
        : null;

    // Image Modal & Interactive Zoom Controller
    const imgModal = document.getElementById('aiImgModal');
    const imgModalBackdrop = document.getElementById('aiImgModalBackdrop');
    const imgModalImg = document.getElementById('aiImgModalImg');
    const imgModalClose = document.getElementById('aiImgModalClose');
    const imgZoomIn = document.getElementById('aiImgZoomIn');
    const imgZoomOut = document.getElementById('aiImgZoomOut');
    const imgZoomReset = document.getElementById('aiImgZoomReset');
    const imgZoomScale = document.getElementById('aiImgZoomScale');
    const imgModalContent = document.getElementById('aiImgModalContent');

    let currentScale = 1.0;
    let translateX = 0;
    let translateY = 0;
    let isDragging = false;
    let startX = 0;
    let startY = 0;

    function updateImageTransform() {
        if (!imgModalImg) return;
        imgModalImg.style.transform = `translate(${translateX}px, ${translateY}px) scale(${currentScale})`;
        if (imgZoomScale) imgZoomScale.textContent = `${Math.round(currentScale * 100)}%`;
    }

    function resetZoom() {
        currentScale = 1.0;
        translateX = 0;
        translateY = 0;
        updateImageTransform();
    }

    function openImageModal(src) {
        if (!src || !imgModal) return;
        imgModalImg.src = src;
        resetZoom();
        imgModal.style.display = 'flex';
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                imgModal.classList.add('active');
            });
        });
        lockBodyScroll();
    }

    function closeImageModal() {
        if (!imgModal) return;
        imgModal.classList.remove('active');
        imgModalImg.src = '';
        window.setTimeout(() => {
            if (!imgModal.classList.contains('active')) {
                imgModal.style.display = 'none';
            }
        }, 300);
        if (!windowEl.classList.contains('ai-widget-open') || window.innerWidth > 640) {
            restoreBodyScroll();
        }
    }

    if (imgZoomIn) {
        imgZoomIn.addEventListener('click', (e) => {
            e.stopPropagation();
            currentScale = Math.min(4.0, currentScale + 0.25);
            updateImageTransform();
        });
    }

    if (imgZoomOut) {
        imgZoomOut.addEventListener('click', (e) => {
            e.stopPropagation();
            currentScale = Math.max(0.5, currentScale - 0.25);
            updateImageTransform();
        });
    }

    if (imgZoomReset) {
        imgZoomReset.addEventListener('click', (e) => {
            e.stopPropagation();
            resetZoom();
        });
    }

    if (imgModalClose) imgModalClose.addEventListener('click', closeImageModal);
    if (imgModalBackdrop) imgModalBackdrop.addEventListener('click', closeImageModal);

    // Mouse wheel zoom
    if (imgModalContent) {
        imgModalContent.addEventListener('wheel', (e) => {
            e.preventDefault();
            if (e.deltaY < 0) {
                currentScale = Math.min(4.0, currentScale + 0.15);
            } else {
                currentScale = Math.max(0.5, currentScale - 0.15);
            }
            updateImageTransform();
        }, { passive: false });

        // Double click to toggle 2.2x zoom
        imgModalContent.addEventListener('dblclick', () => {
            if (currentScale > 1.2) {
                resetZoom();
            } else {
                currentScale = 2.2;
                updateImageTransform();
            }
        });

        // Mouse Drag / Pan when zoomed in
        imgModalContent.addEventListener('mousedown', (e) => {
            if (currentScale <= 1.0) return;
            isDragging = true;
            startX = e.clientX - translateX;
            startY = e.clientY - translateY;
        });

        // Touch drag for mobile devices
        imgModalContent.addEventListener('touchstart', (e) => {
            if (e.touches.length === 1 && currentScale > 1.0) {
                isDragging = true;
                startX = e.touches[0].clientX - translateX;
                startY = e.touches[0].clientY - translateY;
            }
        });
    }

    function handleImageMouseMove(e) {
        if (!isDragging) return;
        translateX = e.clientX - startX;
        translateY = e.clientY - startY;
        updateImageTransform();
    }
    window.addEventListener('mousemove', handleImageMouseMove);

    function handleImageMouseUp() {
        isDragging = false;
    }
    window.addEventListener('mouseup', handleImageMouseUp);

    function handleImageTouchMove(e) {
        if (isDragging && e.touches.length === 1) {
            translateX = e.touches[0].clientX - startX;
            translateY = e.touches[0].clientY - startY;
            updateImageTransform();
        }
    }
    window.addEventListener('touchmove', handleImageTouchMove);

    function handleImageTouchEnd() {
        isDragging = false;
    }
    window.addEventListener('touchend', handleImageTouchEnd);

    // Keyboard ESC key to close modal
    function handleImageKeydown(e) {
        if (e.key === 'Escape' && imgModal && imgModal.classList.contains('active')) {
            closeImageModal();
        } else if (e.key === 'Escape' && isVoiceModeActive) {
            closeVoiceMode();
        } else if (e.key === 'Escape' && windowEl.classList.contains('ai-widget-open')) {
            setWindowOpen(false);
        }
    }
    window.addEventListener('keydown', handleImageKeydown);

    window.MiniErpAiWidgetDestroy = function () {
        isDestroyed = true;
        isVoiceModeActive = false;
        isQuickRecording = false;
        if (autoTourTimeout !== null) window.clearTimeout(autoTourTimeout);
        if (liveVoiceRestartTimeout !== null) window.clearTimeout(liveVoiceRestartTimeout);
        activeRequestControllers.forEach((controller) => controller.abort());
        activeRequestControllers.clear();
        window.removeEventListener('resize', handleTourResize);
        window.removeEventListener('mousemove', handleImageMouseMove);
        window.removeEventListener('mouseup', handleImageMouseUp);
        window.removeEventListener('touchmove', handleImageTouchMove);
        window.removeEventListener('touchend', handleImageTouchEnd);
        window.removeEventListener('keydown', handleImageKeydown);

        if (quickRecognition) {
            quickRecognition.onstart = null;
            quickRecognition.onresult = null;
            quickRecognition.onerror = null;
            quickRecognition.onend = null;
            try { quickRecognition.abort(); } catch (e) {}
        }
        if (liveVoiceRecognition) {
            liveVoiceRecognition.onstart = null;
            liveVoiceRecognition.onresult = null;
            liveVoiceRecognition.onerror = null;
            liveVoiceRecognition.onend = null;
            try { liveVoiceRecognition.abort(); } catch (e) {}
        }
        stopSpeechSynthesis();
        restoreBodyScroll();
        widgetWrapper.remove();
        style.remove();
        injectedFontLink?.remove();
        window.MiniErpAiWidgetLoaded = false;
        delete window.MiniErpAiWidgetDestroy;
        delete window.MiniErpAiWidgetGetHistory;
        delete window.MiniErpAiConfig;
    };

})();
