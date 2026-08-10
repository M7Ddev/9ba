/**
 * translations.js
 * ---------------------------------------------------------------------------
 * Every user-facing string in the app. Arabic is the default language.
 *
 * `dir` is read by App.jsx and written to <html dir> so the whole layout flips
 * between RTL and LTR.
 *
 * Note on the dropdowns: `value` is always the canonical English term, because
 * that is what we send to Gemini. Only `label` is translated.
 */

export const LANGUAGES = {
  ar: { dir: 'rtl', label: 'English' }, // label = the language you switch TO
  en: { dir: 'ltr', label: 'العربية' },
};

export const BREW_METHODS = ['V60', 'French Press', 'Espresso', 'Moka Pot', 'AeroPress'];
export const ROAST_LEVELS = ['Light', 'Medium', 'Dark'];
export const TASTE_PREFERENCES = ['Strong', 'Balanced', 'Light', 'Less sour', 'Less bitter'];

// Must match the keys of config/coffee.php on the backend.
export const SERVE_STYLES = ['Hot', 'Iced'];
export const ORIGINS = ['Colombia', 'Ethiopia', 'Yemen', 'Brazil', 'Kenya', 'Other'];
export const PROCESSES = ['Washed', 'Natural', 'Honey', 'Anaerobic'];
export const GRINDERS = [
  'Other',
  'Comandante C40',
  '1Zpresso JX',
  'Timemore C2',
  'Baratza Encore',
  'Hario Skerton Pro',
  'DF54',
];

export const translations = {
  ar: {
    // Brand name — deliberately not translated, in either language.
    appName: 'صَبّة',
    tagline: 'مساعدك لتحضير قهوة مختصة بوصفات دقيقة',

    // Footer. The author name is a separate key so it can be wrapped in a link.
    footerText: 'تم تطوير صَبّة من',
    footerAuthor: 'M7dev',

    // Theme toggle. The label describes what the button switches TO.
    switchToDark: 'الوضع الداكن',
    switchToLight: 'الوضع الفاتح',

    // Access gate
    gateTitle: 'رمز الدخول',
    gateHint: 'هذا الموقع محمي برمز دخول. أدخله مرة واحدة وسيُحفظ في متصفحك.',
    gateLabel: 'الرمز',
    gateSubmit: 'دخول',
    gateChecking: 'جارٍ التحقق…',
    gateRejected: 'الرمز غير صحيح.',

    // Shown when the backend is down or has no key (a setup problem)
    envHint: 'الخادم غير جاهز. شغّل خادم Laravel وتأكد من ضبط GEMINI_API_KEY.',

    // Form
    formTitle: 'إعداداتك',
    beansTitle: 'محصولك',
    method: 'طريقة التحضير',
    roast: 'درجة التحميص',
    amount: 'كمية الماء (مل)',
    amountEspresso: 'كمية المشروب (مل)',
    taste: 'التفضيل في الطعم',
    serve: 'التقديم',
    serves: { Hot: 'ساخنة', Iced: 'مثلجة' },
    ice: 'الثلج',
    brewWater: 'ماء التحضير',
    coffeeGrams: 'كمية القهوة (جم) — اختياري',
    iceGrams: 'كمية الثلج (جم) — اختياري',
    autoPlaceholder: 'اتركه فارغًا ليختار المساعد',
    origin: 'المنشأ',
    process: 'طريقة المعالجة',
    flavorNotes: 'الإيحاءات المكتوبة على الكيس (اختياري)',
    flavorNotesPlaceholder: 'مثال: توت، ياسمين، شوكولاتة',
    grinder: 'المطحنة',
    grinderOther: 'أخرى / غير مدرجة',

    // Bag scanner
    scanBag: '📷 صوّر كيس القهوة',
    scanning: 'جارٍ القراءة…',
    scanHint: 'التقط صورة للملصق وسنملأ الحقول تلقائيًا.',
    scanFilled: 'تم ملء بيانات الحبوب من الصورة.',
    scanRejected: 'تعذّر قراءة هذه الصورة. جرّب صورة أوضح أو أصغر حجمًا.',
    scanNotCoffee: 'لم نتعرف على كيس قهوة في الصورة. جرّب صورة أوضح للملصق.',

    // Brew timer
    timerTitle: 'مؤقّت التحضير',
    start: 'ابدأ',
    pause: 'إيقاف مؤقت',
    resume: 'متابعة',
    reset: 'إعادة',
    nextStepIn: 'الخطوة التالية بعد',

    // Brew log
    logTitle: 'سجلّ تحضيراتك',
    logHint: 'يستخدم المساعد هذا السجل ليتعلّم ذوقك ويصحّح الوصفات القادمة.',
    logUnrated: 'بدون تقييم',
    generate: 'أنشئ الوصفة',
    generating: 'جارٍ التحضير…',
    // Shown when the displayed recipe was generated in the other language.
    languageMismatch: 'هذه الوصفة مكتوبة بالإنجليزية.',
    translate: 'ترجمها للعربية',
    translating: 'جارٍ الترجمة…',
    regenerate: 'وصفة جديدة',
    amountError: 'أدخل كمية بين 20 و 2000 مل.',

    // Options
    methods: {
      V60: 'V60',
      'French Press': 'فرنش برس',
      Espresso: 'إسبريسو',
      'Moka Pot': 'موكا بوت',
      AeroPress: 'إيروبريس',
    },
    roasts: { Light: 'فاتح', Medium: 'متوسط', Dark: 'غامق' },
    origins: {
      Colombia: 'كولومبيا',
      Ethiopia: 'إثيوبيا',
      Yemen: 'اليمن',
      Brazil: 'البرازيل',
      Kenya: 'كينيا',
      Other: 'أخرى / غير معروف',
    },
    processes: {
      Washed: 'مغسولة',
      Natural: 'طبيعية',
      Honey: 'عسلية',
      Anaerobic: 'لا هوائية',
    },
    tastes: {
      Strong: 'قوي',
      Balanced: 'متوازن',
      Light: 'خفيف',
      'Less sour': 'أقل حموضة',
      'Less bitter': 'أقل مرارة',
    },

    // Recipe card
    recipeTitle: 'وصفتك',
    coffee: 'القهوة',
    water: 'الماء',
    temp: 'الحرارة',
    grams: 'جم',
    ml: 'مل',
    celsius: '°م',
    ratio: 'النسبة',
    grind: 'درجة الطحن',
    clicks: 'إعداد المطحنة',
    time: 'الزمن الكلي',
    steps: 'الخطوات',
    notes: 'ملاحظات',
    beanInsight: 'لماذا هذه الوصفة لحبوبك؟',
    changed: 'ما الذي تغيّر؟',

    // Feedback
    feedbackTitle: 'كيف طلعت؟',
    tooSour: 'طلعت حامضة',
    tooBitter: 'طلعت مرّة',
    perfect: 'ممتازة',
    adjusting: 'جارٍ التعديل…',
    perfectMessage: 'ممتاز! احتفظ بهذه الوصفة وكرّرها بنفس الأرقام. ☕',

    // Errors
    errors: {
      API_UNREACHABLE: 'تعذّر الوصول إلى الخادم. شغّل Laravel بالأمر: php artisan serve',
      UNAUTHORIZED: 'انتهت صلاحية رمز الدخول أو أنه غير صحيح. أعد إدخاله.',
      MISSING_KEY: 'لم يتم ضبط GEMINI_API_KEY في ملف backend/.env. أضف المفتاح ثم أعد تشغيل الخادم.',
      INVALID_KEY: 'المفتاح غير صالح أو لا يملك صلاحية. تأكد من نسخه كاملًا في backend/.env.',
      RATE_LIMIT: 'تجاوزت الحد المسموح من الطلبات. انتظر قليلًا ثم أعد المحاولة.',
      MODEL_NOT_FOUND:
        'النموذج غير متاح لهذا المفتاح. غيّر GEMINI_MODEL في backend/.env ثم أعد تشغيل الخادم.',
      VALIDATION: 'المدخلات غير صحيحة. راجع القيم ثم أعد المحاولة.',
      NETWORK: 'تعذّر اتصال الخادم بـ Gemini. تحقّق من الإنترنت وأعد المحاولة.',
      TIMEOUT: 'انتهت مهلة الطلب. أعد المحاولة.',
      SERVER: 'خدمة Gemini غير متاحة حاليًا. أعد المحاولة بعد قليل.',
      BAD_JSON: 'وصل رد غير مفهوم من النموذج. أعد المحاولة.',
      EMPTY_RESPONSE: 'لم يصل أي رد من النموذج. أعد المحاولة.',
      UNKNOWN: 'حدث خطأ غير متوقع. راجع سجلّ Laravel لمزيد من التفاصيل.',
    },
  },

  en: {
    // Brand name — deliberately not translated, in either language.
    appName: 'صَبّة',
    tagline: 'Your specialty coffee assistant for precise recipes',

    footerText: 'صَبّة was developed by',
    footerAuthor: 'M7dev',

    switchToDark: 'Dark mode',
    switchToLight: 'Light mode',

    gateTitle: 'Access code',
    gateHint: 'This site is protected by an access code. Enter it once and it will be remembered in this browser.',
    gateLabel: 'Code',
    gateSubmit: 'Enter',
    gateChecking: 'Checking…',
    gateRejected: 'That code is not correct.',

    envHint: 'The backend is not ready. Start the Laravel server and set GEMINI_API_KEY.',

    formTitle: 'Your setup',
    beansTitle: 'Your beans',
    method: 'Brew method',
    roast: 'Roast level',
    amount: 'Water amount (ml)',
    amountEspresso: 'Target yield (ml)',
    taste: 'Taste preference',
    serve: 'Serve',
    serves: { Hot: 'Hot', Iced: 'Iced' },
    ice: 'Ice',
    brewWater: 'Brew water',
    coffeeGrams: 'Coffee dose (g) — optional',
    iceGrams: 'Ice (g) — optional',
    autoPlaceholder: 'Leave blank and the assistant decides',
    origin: 'Origin',
    process: 'Processing method',
    flavorNotes: 'Flavour notes on the bag (optional)',
    flavorNotesPlaceholder: 'e.g. berry, jasmine, chocolate',
    grinder: 'Grinder',
    grinderOther: 'Other / not listed',

    // Bag scanner
    scanBag: '📷 Photograph the bag',
    scanning: 'Reading…',
    scanHint: 'Snap the label and we will fill the fields for you.',
    scanFilled: 'Bean details filled in from the photo.',
    scanRejected: 'That image could not be read. Try a clearer or smaller photo.',
    scanNotCoffee: 'That does not look like a coffee bag. Try a clearer shot of the label.',

    // Brew timer
    timerTitle: 'Brew timer',
    start: 'Start',
    pause: 'Pause',
    resume: 'Resume',
    reset: 'Reset',
    nextStepIn: 'Next step in',

    // Brew log
    logTitle: 'Your brew log',
    logHint: 'The assistant reads this log to learn your palate and pre-correct future recipes.',
    logUnrated: 'Not rated',
    generate: 'Generate recipe',
    generating: 'Brewing…',
    // Shown when the displayed recipe was generated in the other language.
    languageMismatch: 'This recipe was written in Arabic.',
    translate: 'Translate to English',
    translating: 'Translating…',
    regenerate: 'New recipe',
    amountError: 'Enter an amount between 20 and 2000 ml.',

    methods: {
      V60: 'V60',
      'French Press': 'French Press',
      Espresso: 'Espresso',
      'Moka Pot': 'Moka Pot',
      AeroPress: 'AeroPress',
    },
    roasts: { Light: 'Light', Medium: 'Medium', Dark: 'Dark' },
    origins: {
      Colombia: 'Colombia',
      Ethiopia: 'Ethiopia',
      Yemen: 'Yemen',
      Brazil: 'Brazil',
      Kenya: 'Kenya',
      Other: 'Other / unknown',
    },
    processes: {
      Washed: 'Washed',
      Natural: 'Natural',
      Honey: 'Honey',
      Anaerobic: 'Anaerobic',
    },
    tastes: {
      Strong: 'Strong',
      Balanced: 'Balanced',
      Light: 'Light',
      'Less sour': 'Less sour',
      'Less bitter': 'Less bitter',
    },

    recipeTitle: 'Your recipe',
    coffee: 'Coffee',
    water: 'Water',
    temp: 'Temperature',
    grams: 'g',
    ml: 'ml',
    celsius: '°C',
    ratio: 'Ratio',
    grind: 'Grind size',
    clicks: 'Grinder setting',
    time: 'Total time',
    steps: 'Steps',
    notes: 'Notes',
    beanInsight: 'Why this recipe for your beans',
    changed: 'What changed',

    feedbackTitle: 'How did it taste?',
    tooSour: 'Too sour',
    tooBitter: 'Too bitter',
    perfect: 'Perfect',
    adjusting: 'Adjusting…',
    perfectMessage: 'Great! Keep this recipe and repeat it with the same numbers. ☕',

    errors: {
      API_UNREACHABLE: 'Cannot reach the backend. Start Laravel with: php artisan serve',
      UNAUTHORIZED: 'The access code is missing or no longer valid. Please enter it again.',
      MISSING_KEY: 'GEMINI_API_KEY is not set in backend/.env. Add the key and restart the server.',
      INVALID_KEY: 'The key is invalid or lacks permission. Check backend/.env has the full key.',
      RATE_LIMIT: 'Rate limit reached. Wait a moment and try again.',
      MODEL_NOT_FOUND:
        'This model is not available for your key. Change GEMINI_MODEL in backend/.env and restart.',
      VALIDATION: 'Some inputs were invalid. Check the values and try again.',
      NETWORK: 'The server could not reach Gemini. Check the connection and try again.',
      TIMEOUT: 'The request timed out. Please try again.',
      SERVER: 'Gemini is temporarily unavailable. Try again shortly.',
      BAD_JSON: 'The model returned something unreadable. Please try again.',
      EMPTY_RESPONSE: 'The model returned nothing. Please try again.',
      UNKNOWN: 'Something went wrong. Check the Laravel log for details.',
    },
  },
};
