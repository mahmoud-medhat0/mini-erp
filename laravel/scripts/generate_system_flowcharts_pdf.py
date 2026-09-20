from __future__ import annotations

from dataclasses import dataclass
from pathlib import Path
from typing import Sequence

import arabic_reshaper
from bidi.algorithm import get_display
from reportlab.lib import colors
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.pdfgen import canvas


ROOT = Path(__file__).resolve().parents[2]
MD_PATH = ROOT / "output" / "mini-erp-system-flowcharts-ar.md"
PDF_PATH = ROOT / "output" / "pdf" / "mini-erp-system-flowcharts-ar.pdf"

PAGE_W, PAGE_H = landscape(A4)
MARGIN = 14 * mm

NAVY = colors.HexColor("#111827")
SLATE = colors.HexColor("#334155")
MUTED = colors.HexColor("#64748B")
BORDER = colors.HexColor("#D9E2F1")
SURFACE = colors.HexColor("#F8FAFC")
BLUE = colors.HexColor("#315CF6")
BLUE_DARK = colors.HexColor("#2445C7")
BLUE_PALE = colors.HexColor("#EEF3FF")
GREEN = colors.HexColor("#14804A")
GREEN_PALE = colors.HexColor("#EAF8F0")
AMBER = colors.HexColor("#B25E09")
AMBER_PALE = colors.HexColor("#FFF5D9")
RED = colors.HexColor("#C9362B")
RED_PALE = colors.HexColor("#FFF0ED")
WHITE = colors.white

FONT = "SegoeArabic"
FONT_SEMIBOLD = "SegoeArabicSemiBold"
FONT_BOLD = "SegoeArabicBold"

VERSION = "2.1"
DATE_AR = "٢٠ سبتمبر ٢٠٢٦"


@dataclass(frozen=True)
class Page:
    title: str
    nav: str
    flow: tuple[tuple[str, str], ...]
    steps: tuple[str, ...]
    checks: tuple[str, ...]
    status: str = ""


NAVIGATION: tuple[tuple[str, tuple[str, ...]], ...] = (
    ("النظام المحاسبي الرئيسي", ("دليل الحسابات", "تصنيفات الحسابات", "أنواع الحسابات", "ربط القوائم المالية", "مابنج حسابات الترحيل", "دفتر اليومية العامة", "دفتر الأستاذ العام", "ميزان المراجعة", "الفترات المالية", "الأرصدة الافتتاحية", "أسعار الصرف", "العملات والنظام النقدي", "أكواد الضرائب", "نسب الضرائب", "الفترات الضريبية")),
    ("العملاء والقبض", ("العملاء", "أرصدة افتتاحية عملاء", "سندات القبض", "تسوية المستحقات")),
    ("الموردين والصرف", ("الموردين", "أرصدة افتتاحية موردين", "سندات الصرف", "تسوية المستحقات")),
    ("المصروفات", ("المصروفات", "فئات المصروفات", "المصروفات المقدمة", "المصروفات المستحقة")),
    ("المرتبات", ("كشوف المرتبات", "الموظفون", "مكونات المرتب")),
    ("الإيجارات", ("عقود الإيجار", "فواتير الإيجار", "تسليمات الإيجار", "مرتجعات الإيجار", "عناصر الإيجار")),
    ("النقدية والبنوك", ("حسابات الخزينة", "حسابات البنوك", "تحويلات الخزينة والبنك", "الشيكات الواردة", "الشيكات الصادرة", "تسوية البنك")),
    ("الكتالوج", ("المنتجات والخدمات", "تصنيفات المنتجات", "وحدات القياس")),
    ("المبيعات", ("أوامر البيع", "أذون التسليم", "فواتير العملاء", "مرتجعات البيع", "إشعارات دائنة", "مراجعات الفواتير")),
    ("المشتريات", ("أوامر الشراء", "أذون الاستلام", "تكاليف الوصول", "فواتير الموردين", "مرتجعات الشراء", "مذكرات التسوية")),
    ("عمليات المخزون", ("المخازن", "تحويلات المخزون", "جرد المخزون", "تسويات المخزون", "أرصدة المخزون")),
    ("الأصول الثابتة", ("الأصول الثابتة", "فئات الأصول الثابتة", "مواقع الأصول الثابتة", "جولات الإهلاك", "استبعادات الأصول")),
    ("المشاريع ومراكز التكلفة", ("المشاريع", "مراكز التكلفة", "الموازنات التقديرية", "الموازنة مقابل الفعلي")),
    ("التقارير الفرعية", ("مركز التقارير", "كشف حساب عميل", "كشف حساب مورد", "أعمار ديون العملاء", "أعمار ديون الموردين", "دفتر الخزينة", "دفتر البنك", "سجل الشيكات", "تقرير تسوية البنك", "مطابقة العملاء بالأستاذ", "مطابقة الموردين بالأستاذ", "تشغيل الفروع", "ربحية الفروع", "تشغيل الإيجارات", "الميزانية العمومية", "قائمة الدخل", "قائمة التدفقات النقدية", "النسب المالية")),
    ("الإدارة والتهيئة", ("بيانات الشركة", "الفروع", "الترقيم", "قواعد اعتماد الفروع", "المستخدمون", "سجل التدقيق")),
)


PAGES: tuple[Page, ...] = (
    Page(
        "التهيئة لأول مرة",
        "الوحدات والإدارة ← الإدارة والتهيئة، ثم النظام المحاسبي الرئيسي",
        (("بيانات الشركة والفروع", "blue"), ("المستخدمون والصلاحيات", "blue"), ("الترقيم وقواعد الاعتماد", "blue"), ("العملات والفترات", "blue"), ("أنواع وتصنيفات الحسابات", "blue"), ("دليل الحسابات", "blue"), ("ربط حسابات الترحيل", "amber"), ("الضرائب والمخازن", "blue"), ("العملاء والموردون والمنتجات", "blue"), ("الأرصدة الافتتاحية", "green")),
        ("ابدأ بالشركة والفرع والعملة الأساسية قبل إدخال أي مستند.", "أنشئ المستخدمين واربط الصلاحيات بما يفعلونه فعليًا.", "أكمل دليل الحسابات وربط حسابات الترحيل قبل أول فاتورة أو سند.", "أنشئ فترة مالية مفتوحة ثم راجع توازن الأرصدة الافتتاحية قبل ترحيلها."),
        ("لا يمكن الترحيل داخل فترة مالية مغلقة.", "الصلاحية هي التي تحدد ظهور المجموعة أو الزر.", "لا تبدأ التشغيل قبل نجاح ربط العملاء والموردين والمخزون والضرائب."),
    ),
    Page(
        "الاستخدام اليومي لأي شاشة",
        "افتح المجموعة من القائمة الجانبية ثم اختر اسم الشاشة",
        (("فتح شاشة القائمة", "blue"), ("البحث أو التصفية", "blue"), ("إنشاء سجل جديد", "blue"), ("حفظ المسودة", "blue"), ("مراجعة البيانات والمرفقات", "amber"), ("تنفيذ الإجراء المتاح", "green"), ("قراءة رسالة النجاح", "green"), ("مراجعة الحالة والتقرير", "green")),
        ("استخدم أسماء القوائم الظاهرة؛ لا يحتاج المستخدم إلى كتابة مسار تقني.", "الأزرار المعروضة تتغير حسب حالة السجل وصلاحية المستخدم.", "راجع العملة والتاريخ والفرع والمخزن قبل التأكيد أو الترحيل.", "بعد الترحيل راجع المستند المصدر والتقرير المرتبط."),
        ("المسودة قابلة للتعديل عادةً.", "التأكيد قد ينشئ أثر مخزون مباشر.", "الترحيل ينشئ الأثر المالي النهائي ويتطلب فترة مفتوحة."),
    ),
    Page(
        "القيود اليومية والفترات المالية",
        "الوحدات والإدارة ← النظام المحاسبي الرئيسي ← دفتر اليومية العامة",
        (("إنشاء قيد مسودة", "blue"), ("إدخال المدين والدائن", "blue"), ("التأكد من التوازن", "amber"), ("تقديم", "blue"), ("اعتماد", "amber"), ("ترحيل", "green"), ("مراجعة دفتر الأستاذ", "green"), ("عكس القيد عند التصحيح", "red")),
        ("اختر التاريخ والوصف والفرع ثم أضف سطور القيد.", "يجب أن يساوي إجمالي المدين إجمالي الدائن.", "بعد الاعتماد استخدم ترحيل لإظهار الحركة في دفتر الأستاذ.", "لتصحيح قيد مرحل استخدم العكس بدل تعديل القيد الأصلي."),
        ("الحالات: مسودة ← مقدمة ← معتمدة ← مرحلة.", "إغلاق الفترة من شاشة الفترات المالية بعد فحص الجاهزية.", "إعادة فتح الفترة تحتاج صلاحية مستقلة وتأكيدًا حساسًا."),
    ),
    Page(
        "دورة البيع من الأمر إلى التحصيل",
        "الوحدات والإدارة ← المبيعات، ثم العملاء والقبض",
        (("أمر بيع", "blue"), ("تقديم", "blue"), ("تأكيد", "amber"), ("إذن تسليم", "blue"), ("تأكيد التسليم وخروج المخزون", "green"), ("فاتورة عميل", "blue"), ("تقديم واعتماد", "amber"), ("ترحيل للمدينين والقيود", "green"), ("سند قبض", "blue"), ("ترحيل السند", "green"), ("تسوية المستحقات", "green")),
        ("أنشئ أمر البيع وحدد العميل والعملة والبنود ثم احفظه.", "قدّم الأمر ثم أكده؛ لا يقبل إذن التسليم إلا أمرًا مؤكدًا.", "أنشئ إذن التسليم وحدد المخزن والكميات الفعلية ثم اضغط تأكيد.", "أنشئ الفاتورة من الأمر أو إذن التسليم، ثم قدّمها واعتمدها ورحّلها.", "أنشئ سند القبض ورحّله، ثم افتح تسوية المستحقات واربط المبلغ بالفاتورة."),
        ("أمر البيع: مسودة ← مقدم ← مؤكد.", "إذن التسليم: مسودة ← مؤكد، والتأكيد يسجل خروج المخزون.", "الفاتورة: مسودة ← مقدمة ← معتمدة ← مرحلة.", "سند القبض: مسودة ← مرحل؛ التسوية خطوة مستقلة عن الترحيل."),
    ),
    Page(
        "تصحيح المبيعات",
        "الوحدات والإدارة ← المبيعات ← مرتجعات البيع أو إشعارات دائنة",
        (("تحديد المستند المصدر", "blue"), ("إنشاء مرتجع بيع", "blue"), ("تقديم واعتماد", "amber"), ("ترحيل للمخزون والقيود", "green"), ("إنشاء إشعار دائن عند الحاجة", "blue"), ("تقديم واعتماد", "amber"), ("ترحيل للمدينين والقيود", "green"), ("تسوية الإشعار", "green"), ("مراجعة نسخة الفاتورة المصححة", "green")),
        ("استخدم مرتجع البيع عندما تعود كمية من إذن تسليم مؤكد.", "حدد طريقة التصرف: إعادة للمخزون أو إتلاف.", "استخدم الإشعار الدائن لتخفيض رصيد العميل.", "تظهر النسخة المصححة داخل مراجعات الفواتير بعد ترحيل التصحيح."),
        ("لا تعدّل فاتورة مرحلة مباشرة.", "المرتجع والإشعار يمران بمسودة ثم تقديم واعتماد وترحيل.", "تسوية الإشعار تختلف عن تسوية سند القبض."),
    ),
    Page(
        "دورة الشراء من الأمر إلى السداد",
        "الوحدات والإدارة ← المشتريات، ثم الموردين والصرف",
        (("أمر شراء", "blue"), ("تقديم", "blue"), ("تأكيد", "amber"), ("إذن استلام", "blue"), ("تأكيد الاستلام ودخول المخزون", "green"), ("تكلفة وصول عند الحاجة", "blue"), ("تقديم واعتماد وترحيل التكلفة", "green"), ("فاتورة مورد", "blue"), ("تقديم واعتماد وترحيل", "green"), ("سند صرف", "blue"), ("ترحيل السند", "green"), ("تسوية المستحقات", "green")),
        ("أنشئ أمر الشراء وحدد المورد والبنود ثم قدّمه وأكده.", "أنشئ إذن الاستلام من أمر مؤكد، وسجل المخزن والكميات الفعلية، ثم أكد الإذن.", "إذا وجدت شحن أو تأمين أو رسوم، أنشئ تكلفة وصول ووزعها بالكامل ثم قدّمها واعتمدها ورحّلها.", "أنشئ فاتورة المورد ثم قدّمها واعتمدها ورحّلها.", "أنشئ سند الصرف ورحّله، ثم افتح تسوية المستحقات واربط الدفعة بالفاتورة."),
        ("إذن الاستلام لا يملك زر ترحيل مستقل؛ التأكيد يسجل دخول المخزون.", "تكلفة الوصول: مسودة ← مقدمة ← معتمدة ← مرحلة.", "فاتورة المورد: مسودة ← مقدمة ← معتمدة ← مرحلة.", "سند الصرف: مسودة ← مرحل؛ التسوية خطوة مستقلة."),
    ),
    Page(
        "تصحيح المشتريات",
        "الوحدات والإدارة ← المشتريات ← مرتجعات الشراء أو مذكرات التسوية",
        (("تحديد الاستلام أو الفاتورة", "blue"), ("إنشاء مرتجع شراء", "blue"), ("تقديم واعتماد", "amber"), ("ترحيل للمخزون والمستحقات", "green"), ("إنشاء مذكرة تسوية عند الحاجة", "blue"), ("تقديم واعتماد", "amber"), ("ترحيل للدائنين والقيود", "green"), ("تسوية المذكرة", "green")),
        ("أنشئ مرتجع الشراء من إذن استلام مؤكد.", "حدد الكمية المرتجعة والمخزن والسبب.", "استخدم مذكرة التسوية لتصحيح رصيد المورد المرتبط بفاتورة.", "راجع كشف المورد ومطابقة الموردين بالأستاذ بعد الترحيل والتسوية."),
        ("لا تعدّل فاتورة مورد مرحلة مباشرة.", "المرتجع والمذكرة يمران بمسودة ثم تقديم واعتماد وترحيل.", "كل عملية تصحيح تحتفظ برابط إلى مستندها المصدر."),
    ),
    Page(
        "القبض والصرف وتسوية المستحقات",
        "الوحدات والإدارة ← العملاء والقبض أو الموردين والصرف",
        (("اختيار العميل أو المورد", "blue"), ("إنشاء سند قبض أو صرف", "blue"), ("مراجعة الحساب والعملة", "amber"), ("ترحيل السند", "green"), ("ظهور رصيد غير مسوى", "blue"), ("فتح تسوية المستحقات", "blue"), ("اختيار الفواتير", "blue"), ("إدخال مبالغ التسوية", "blue"), ("تنفيذ التسوية", "green"), ("عكس التسوية عند الخطأ", "red")),
        ("يجب أن تتطابق عملة السند مع عملة المستحقات المختارة.", "لا تتجاوز المبلغ المتاح في السند ولا الرصيد المفتوح للفاتورة.", "يمكن تنفيذ تسوية جزئية ثم استكمال الباقي لاحقًا.", "استخدم إلغاء التسوية لإعادة فتح الرصيد دون عكس السند نفسه."),
        ("ترحيل السند ينشئ الحركة المالية.", "تسوية المستحقات تربط الحركة بالمستند المفتوح.", "راجع كشف الحساب وأعمار الديون بعد التنفيذ."),
    ),
    Page(
        "تحويلات المخزون",
        "الوحدات والإدارة ← عمليات المخزون ← تحويلات المخزون",
        (("إنشاء تحويل مسودة", "blue"), ("تحديد مخزن المصدر والوجهة", "blue"), ("إضافة الأصناف والكميات", "blue"), ("تقديم", "blue"), ("اعتماد", "amber"), ("إصدار من المصدر", "green"), ("مخزون بالطريق", "amber"), ("استلام كلي أو جزئي", "green"), ("استلام المتبقي", "green")),
        ("يجب أن يختلف مخزن المصدر عن مخزن الوجهة.", "راجع الرصيد المتاح قبل الإصدار.", "يمكن للوجهة استلام جزء من الكمية ثم استلام المتبقي.", "راجع أرصدة المخزون وحركات الصنف بعد اكتمال التحويل."),
        ("الحالات: مسودة ← مقدمة ← معتمدة ← مصدرة ← مستلمة جزئيًا ← مستلمة.", "الإصدار يخفض المصدر، والاستلام يزيد الوجهة.", "الإلغاء متاح فقط قبل الأثر غير القابل للإلغاء."),
    ),
    Page(
        "جرد وتسويات المخزون",
        "الوحدات والإدارة ← عمليات المخزون ← جرد المخزون أو تسويات المخزون",
        (("إنشاء جرد", "blue"), ("اختيار المخزن", "blue"), ("إدخال الكمية المعدودة", "blue"), ("مراجعة الفروق", "amber"), ("تقديم", "blue"), ("اعتماد", "amber"), ("ترحيل فروق الجرد", "green"), ("مراجعة الأرصدة", "green"), ("تسوية مستقلة عند الحاجة", "blue"), ("تقديم واعتماد وترحيل", "green")),
        ("أدخل الكميات الفعلية فقط بعد تثبيت نطاق الجرد.", "إذا كانت الفروق غير صحيحة، صحح المسودة وأعد المراجعة.", "ترحيل الجرد ينشئ حركات وقيد فروق المخزون.", "استخدم تسويات المخزون للحالات غير الناتجة عن جلسة جرد."),
        ("الجرد والتسوية: مسودة ← مقدمة ← معتمدة ← مرحلة.", "الترحيل يحتاج صلاحية مخزون مالية وفترة مفتوحة.", "راجع رصيد الصنف وكشف حركة المخزن بعد الترحيل."),
    ),
    Page(
        "الخزينة والبنوك وتسوية البنك",
        "الوحدات والإدارة ← النقدية والبنوك",
        (("إنشاء حساب خزينة أو بنك", "blue"), ("إنشاء تحويل خزينة وبنك", "blue"), ("مراجعة المصدر والوجهة", "amber"), ("ترحيل التحويل", "green"), ("إنشاء تسوية بنك", "blue"), ("إضافة سطور كشف البنك", "blue"), ("مطابقة الحركات", "blue"), ("التأكد أن الفرق صفر", "amber"), ("إنهاء التسوية", "green")),
        ("اختر حسابين مختلفين ومتوافقين مع الفرع والعملة.", "التحويل يبقى مسودة حتى الضغط على ترحيل.", "داخل تسوية البنك أضف سطور الكشف ثم طابقها مع الحركات المرشحة.", "لا يتاح إنهاء التسوية إلا عندما تصبح متوازنة بالكامل."),
        ("تحويل الخزينة والبنك: مسودة ← مرحل.", "تسوية البنك: مسودة ← منتهية.", "راجع دفتر الخزينة أو دفتر البنك وتقرير تسوية البنك."),
    ),
    Page(
        "الشيكات الواردة والصادرة",
        "الوحدات والإدارة ← النقدية والبنوك ← الشيكات الواردة أو الشيكات الصادرة",
        (("شيك وارد", "blue"), ("استلام", "blue"), ("إيداع", "blue"), ("تحصيل أو إرجاع", "green"), ("شيك صادر", "blue"), ("إصدار", "blue"), ("تحصيل أو إرجاع", "green"), ("إلغاء قبل التحصيل عند السماح", "red"), ("مراجعة سجل الشيكات", "green")),
        ("سجل البنك والتاريخ والقيمة ورقم الشيك بدقة.", "في الشيك الوارد نفذ الاستلام ثم الإيداع قبل التحصيل.", "في الشيك الصادر راقب حالة التقديم والتحصيل.", "استخدم سجل الشيكات لمتابعة الحالات والاستحقاقات."),
        ("كل انتقال حالة يحتاج الصلاحية المقابلة.", "الإرجاع يعالج الأثر حسب اتجاه الشيك.", "لا تستخدم الإلغاء بدل الإرجاع بعد بدء التحصيل."),
    ),
    Page(
        "المصروف المباشر",
        "الوحدات والإدارة ← المصروفات ← المصروفات",
        (("اختيار فئة المصروف", "blue"), ("إدخال القيمة والضريبة", "blue"), ("اختيار طريقة التسوية", "blue"), ("إرفاق المستند", "blue"), ("حفظ مسودة", "blue"), ("تقديم", "blue"), ("اعتماد", "amber"), ("ترحيل", "green")),
        ("اختر طريقة التسوية: مورد، خزينة، أو بنك.", "إذا كانت الطريقة موردًا فحدد المورد؛ وإذا كانت نقدية فحدد الحساب.", "راجع الفرع والعملة والضريبة والمرفق قبل التقديم.", "بعد الترحيل راجع دفتر الأستاذ أو رصيد المورد حسب الطريقة."),
        ("الحالات: مسودة ← مقدمة ← معتمدة ← مرحلة.", "المصروف المباشر شاشة مستقلة عن جداول المقدم والمستحق.", "يمكن إلغاء المستند قبل الترحيل حسب حالته وصلاحيتك."),
    ),
    Page(
        "المصروفات المقدمة والمستحقة",
        "الوحدات والإدارة ← المصروفات ← المصروفات المقدمة أو المصروفات المستحقة",
        (("إنشاء جدول", "blue"), ("تحديد الفترة والمبلغ", "blue"), ("حفظ مسودة", "blue"), ("تقديم", "blue"), ("اعتماد", "amber"), ("اختيار استحقاق الفترة", "blue"), ("ترحيل القسط أو القيد", "green"), ("تكرار للفترات التالية", "blue"), ("اكتمال الجدول", "green")),
        ("المقدم يعترف بالمصروف دوريًا مقابل تخفيض الأصل المقدم.", "المستحق يثبت مصروف الفترة مقابل التزام مستحق.", "لا ترحل بند فترة قبل اعتماده أو داخل فترة مغلقة.", "راجع البنود المرحلة والمتبقية في نفس شاشة الجدول."),
        ("الجدول: مسودة ← مقدمة ← معتمدة.", "كل قسط أو قيد دوري له إجراء ترحيل مستقل.", "هذه الدورة لا تبدأ من شاشة المصروف المباشر."),
    ),
    Page(
        "كشوف المرتبات",
        "الوحدات والإدارة ← المرتبات ← الموظفون، مكونات المرتب، ثم كشوف المرتبات",
        (("إعداد الموظفين", "blue"), ("إعداد مكونات المرتب", "blue"), ("إنشاء كشف الفترة", "blue"), ("توليد السطور", "blue"), ("مراجعة الاستحقاقات والخصومات", "amber"), ("تقديم", "blue"), ("اعتماد", "amber"), ("ترحيل الكشف", "green"), ("مراجعة القيد والالتزامات", "green")),
        ("أكمل بيانات الموظف ومكونات المرتب قبل توليد الكشف.", "يمكن إعادة توليد السطور أثناء حالة المسودة فقط.", "راجع صافي المرتب والضرائب والتأمينات قبل التقديم.", "رحّل الكشف بعد الاعتماد ثم راجع قيد المرتبات."),
        ("الحالات الحالية: مسودة ← مقدمة ← معتمدة ← مرحلة.", "لا يوجد في الواجهة الحالية زر مستقل لسداد أو إغلاق كشف المرتبات.", "السداد الفعلي يسجل وفق إجراءات الخزينة أو البنك المعتمدة لدى الشركة."),
    ),
    Page(
        "عقود وتسليم ومرتجعات الإيجار",
        "الوحدات والإدارة ← الإيجارات",
        (("إنشاء عنصر إيجار", "blue"), ("إنشاء عقد", "blue"), ("تقديم", "blue"), ("اعتماد", "amber"), ("تفعيل العقد", "green"), ("إنشاء تسليم إيجار", "blue"), ("تأكيد التسليم", "green"), ("إنشاء رجوع إيجار", "blue"), ("تقديم الرجوع", "blue"), ("إكمال الفحص", "green"), ("تحديث حالة العنصر والعقد", "green")),
        ("تحقق من توافر العنصر خلال فترة العقد.", "بعد اعتماد العقد فعّله ثم أنشئ تسليم الإيجار وأكد التسليم.", "عند الرجوع سجل حالة العنصر والملحقات وتكلفة التلف التقديرية.", "قدّم الرجوع ثم استخدم إكمال الفحص لتطبيق النتيجة."),
        ("العقد: مسودة ← مقدمة ← معتمدة ← نشطة ← مكتملة.", "التسليم: مسودة ← مؤكد.", "الرجوع: مسودة ← مقدمة ← مكتملة.", "تأكيد التسليم لا ينشئ فاتورة أو قيدًا محاسبيًا."),
    ),
    Page(
        "فواتير الإيجار",
        "الوحدات والإدارة ← الإيجارات ← فواتير الإيجار",
        (("اختيار عقد الإيجار", "blue"), ("تحديد نوع الفاتورة", "blue"), ("إضافة الإيجار أو التأمين أو الرسوم", "blue"), ("حفظ مسودة", "blue"), ("تقديم", "blue"), ("اعتماد", "amber"), ("ترحيل الفاتورة", "green"), ("مراجعة العميل والضريبة والقيود", "green")),
        ("يمكن إصدار فاتورة دورية أثناء نشاط العقد؛ لا يلزم انتظار المرتجع.", "أنواع البنود تشمل الإيجار والتأمين ورسوم التلف والتأخير.", "راجع البنود غير المفوترة من تقرير تشغيل الإيجارات.", "بعد الترحيل تظهر الذمة والضريبة والإيراد والقيد المرتبط."),
        ("الحالات: مسودة ← مقدمة ← معتمدة ← مرحلة.", "التأمين والرسوم يعالجان حسب نوع البند وربط الحسابات.", "استخدم تقرير تشغيل الإيجارات قبل إقفال الشهر."),
    ),
    Page(
        "الأصول الثابتة والإهلاك والاستبعاد",
        "الوحدات والإدارة ← الأصول الثابتة",
        (("إنشاء الفئة والموقع", "blue"), ("إضافة أصل مسودة", "blue"), ("رسملة الأصل", "green"), ("توليد جدول الإهلاك", "blue"), ("معاينة جولة الإهلاك", "amber"), ("ترحيل الجولة", "green"), ("نقل الأصل عند الحاجة", "blue"), ("معاينة الاستبعاد", "amber"), ("ترحيل الاستبعاد", "green"), ("العكس عند التصحيح", "red")),
        ("أدخل تاريخ الاقتناء وبدء الخدمة والعمر والقيمة التخريدية.", "اختر طريقة الرسملة المناسبة: أصل افتتاحي أو رسملة دفترية.", "عاين جولة الإهلاك للفترة قبل ترحيلها.", "في الاستبعاد راجع صافي القيمة الدفترية والربح أو الخسارة قبل التنفيذ."),
        ("الأصل ينشأ مسودة ويصبح نشطًا بعد الرسملة.", "جولة الإهلاك تُرحّل مباشرة بعد المعاينة والتأكيد.", "الرسملة والإهلاك والاستبعاد لها عمليات عكس حساسة."),
    ),
    Page(
        "الضرائب والقيمة المضافة",
        "الوحدات والإدارة ← النظام المحاسبي الرئيسي ← أكواد الضرائب أو نسب الضرائب أو الفترات الضريبية",
        (("إنشاء كود ضريبي", "blue"), ("إضافة النسبة وتاريخ السريان", "blue"), ("ترحيل مستندات خاضعة", "green"), ("إنشاء فترة ضريبية", "blue"), ("توليد مسودة الإقرار", "blue"), ("مراجعة سجل وملخص الضريبة", "amber"), ("مطابقة الضريبة مع الأستاذ", "amber"), ("تقديم الإقرار", "green")),
        ("حدد نوع الضريبة وطريقة الاحتساب وقابلية الاسترداد.", "استخدم تاريخ السريان الصحيح للنسبة.", "راجع سجل الضريبة وملخص القيمة المضافة والمطابقة قبل تقديم الإقرار.", "بعد تقديم الفترة يمنع النظام الترحيل داخل نطاقها الضريبي."),
        ("الفترة الضريبية الحالية: مفتوحة ← مسودة إقرار ← مقدمة.", "لا يوجد في الواجهة الحالية إجراء مستقل لسداد أو إغلاق الفترة الضريبية.", "أي فروق يجب تصحيحها من المستند المصدر قبل تقديم الإقرار."),
    ),
    Page(
        "المشاريع ومراكز التكلفة والموازنات",
        "الوحدات والإدارة ← المشاريع ومراكز التكلفة",
        (("إنشاء مشروع", "blue"), ("إنشاء مركز تكلفة", "blue"), ("ربط العمليات بالأبعاد", "blue"), ("إنشاء موازنة مسودة", "blue"), ("إدخال البنود", "blue"), ("تقديم", "blue"), ("اعتماد", "amber"), ("تفعيل", "green"), ("فتح الموازنة مقابل الفعلي", "green"), ("تحليل الانحراف", "amber")),
        ("اربط المشروع أو مركز التكلفة بسطر العملية عند الإدخال.", "أدخل الموازنة حسب الحساب والفترة والأبعاد المطلوبة.", "قدّم الموازنة واعتمدها ثم فعّل النسخة المعتمدة.", "استخدم تقرير الموازنة مقابل الفعلي للتحليل والمتابعة."),
        ("الموازنة: مسودة ← مقدمة ← معتمدة ← نشطة.", "تفعيل نسخة قد يؤرشف النسخة السابقة وفق الإعداد.", "الأبعاد لا تنشئ قيودًا مستقلة؛ تحمل على القيود المصدرية."),
    ),
    Page(
        "التقارير والإغلاق الشهري",
        "الوحدات والإدارة ← التقارير الفرعية، ثم النظام المحاسبي الرئيسي ← الفترات المالية",
        (("مراجعة المستندات غير المرحلة", "blue"), ("استكمال تسوية المستحقات", "blue"), ("إنهاء تسوية البنك", "blue"), ("ترحيل الجداول والمرتبات والإهلاك", "green"), ("مراجعة الضرائب", "amber"), ("مطابقة العملاء والموردين بالأستاذ", "amber"), ("مراجعة ميزان المراجعة", "amber"), ("مراجعة القوائم المالية", "green"), ("فحص جاهزية الإغلاق", "amber"), ("إغلاق الفترة", "green")),
        ("ابدأ بكشوف العملاء والموردين وأعمار الديون.", "راجع دفاتر الخزينة والبنك وسجل الشيكات وحركات المخزون.", "نفذ مطابقات العملاء والموردين والضريبة مع الأستاذ.", "افتح الفترات المالية واضغط إغلاق الفترة؛ يعرض النظام موانع الإغلاق أولًا."),
        ("لا تغلق الفترة قبل معالجة كل موانع الجاهزية.", "بعد الإغلاق تمنع القيود الجديدة داخل الفترة.", "إعادة الفتح تحتاج صلاحية مستقلة وسببًا واضحًا."),
    ),
    Page(
        "معالجة الأخطاء والصلاحيات",
        "من الشاشة الحالية راجع رسالة الخطأ، ثم انتقل إلى شاشة السبب من القائمة الجانبية",
        (("قراءة رسالة الخطأ", "amber"), ("تحديد نوع السبب", "blue"), ("صلاحية مفقودة", "red"), ("حالة مستند غير مناسبة", "red"), ("فترة مغلقة", "red"), ("ربط حساب ناقص", "red"), ("عملة أو رصيد أو كمية غير صحيحة", "red"), ("تصحيح المصدر", "blue"), ("إعادة الإجراء", "green"), ("مراجعة سجل التدقيق", "green")),
        ("إذا اختفى زر أو مجموعة، راجع صلاحية المستخدم أولًا.", "إذا رفض النظام الإجراء، راجع حالة المستند والمتطلبات السابقة.", "إذا فشل الترحيل، راجع الفترة المالية وربط الحسابات والضريبة.", "إذا فشلت التسوية، راجع العملة والرصيد غير المسوى والمبلغ المفتوح."),
        ("لا تتجاوز الضوابط بتعديل البيانات مباشرة.", "استخدم العكس أو المرتجع أو مذكرة التسوية للمستندات المرحلة.", "سجل التدقيق هو المرجع لمعرفة من نفذ الإجراء ومتى."),
    ),
)


def register_fonts() -> None:
    candidates = (
        # Tahoma has clearer Arabic glyphs at the compact sizes used in the
        # navigation map and workflow cards.
        ("C:/Windows/Fonts/tahoma.ttf", "C:/Windows/Fonts/tahomabd.ttf", "C:/Windows/Fonts/tahomabd.ttf"),
        ("C:/Windows/Fonts/arial.ttf", "C:/Windows/Fonts/arialbd.ttf", "C:/Windows/Fonts/arialbd.ttf"),
        ("C:/Windows/Fonts/segoeui.ttf", "C:/Windows/Fonts/seguisb.ttf", "C:/Windows/Fonts/segoeuib.ttf"),
    )
    for regular, semibold, bold in candidates:
        if all(Path(item).exists() for item in (regular, semibold, bold)):
            pdfmetrics.registerFont(TTFont(FONT, regular))
            pdfmetrics.registerFont(TTFont(FONT_SEMIBOLD, semibold))
            pdfmetrics.registerFont(TTFont(FONT_BOLD, bold))
            return
    raise FileNotFoundError("No Arabic-capable font was found.")


def visual(text: str) -> str:
    clean = text.replace("\u2013", "-").replace("\u2014", "-").replace("\u2011", "-")
    return get_display(arabic_reshaper.reshape(clean), base_dir="R")


def wrapped(text: str, width: float, font: str, size: float) -> list[str]:
    words = text.split()
    if not words:
        return [""]
    lines: list[str] = []
    current = words[0]
    for word in words[1:]:
        candidate = f"{current} {word}"
        if pdfmetrics.stringWidth(visual(candidate), font, size) <= width:
            current = candidate
        else:
            lines.append(current)
            current = word
    lines.append(current)
    return lines


def draw_rtl(c: canvas.Canvas, text: str, right: float, top: float, width: float, *, font: str = FONT, size: float = 10, color=SLATE, leading: float | None = None, max_lines: int | None = None) -> float:
    lead = leading or size * 1.55
    lines = wrapped(text, width, font, size)
    if max_lines is not None:
        lines = lines[:max_lines]
    c.setFillColor(color)
    c.setFont(font, size)
    y = top - size
    for line in lines:
        c.drawRightString(right, y, visual(line))
        y -= lead
    return y


def draw_centered_rtl(c: canvas.Canvas, text: str, x: float, y: float, width: float, height: float, *, font: str = FONT_SEMIBOLD, size: float = 9.5, color=NAVY, leading: float | None = None, max_lines: int = 3) -> None:
    lead = leading or size * 1.45
    lines = wrapped(text, width - 8 * mm, font, size)[:max_lines]
    block_height = size + max(0, len(lines) - 1) * lead
    baseline = y + height / 2 + block_height / 2 - size * 0.78
    c.setFillColor(color)
    c.setFont(font, size)
    for line in lines:
        c.drawCentredString(x + width / 2, baseline, visual(line))
        baseline -= lead


def draw_footer(c: canvas.Canvas, page_no: int) -> None:
    c.setStrokeColor(BORDER)
    c.line(MARGIN, 12 * mm, PAGE_W - MARGIN, 12 * mm)
    c.setFont(FONT, 8.1)
    c.setFillColor(MUTED)
    c.drawRightString(PAGE_W - MARGIN, 7.2 * mm, visual("Mini ERP - دليل التشغيل المعتمد"))
    c.drawString(MARGIN, 7.2 * mm, f"{page_no:02d}  |  v{VERSION}")


def draw_header(c: canvas.Canvas, number: int, page: Page) -> None:
    c.setFillColor(NAVY)
    c.roundRect(MARGIN, PAGE_H - 40 * mm, PAGE_W - 2 * MARGIN, 26 * mm, 5 * mm, fill=1, stroke=0)
    c.setFillColor(BLUE)
    c.roundRect(PAGE_W - MARGIN - 19 * mm, PAGE_H - 34 * mm, 13 * mm, 13 * mm, 3 * mm, fill=1, stroke=0)
    c.setFillColor(WHITE)
    c.setFont(FONT_BOLD, 11)
    c.drawCentredString(PAGE_W - MARGIN - 12.5 * mm, PAGE_H - 29.5 * mm, str(number))
    draw_rtl(c, page.title, PAGE_W - MARGIN - 25 * mm, PAGE_H - 20.5 * mm, 200 * mm, font=FONT_BOLD, size=20, color=WHITE, leading=25)
    draw_rtl(c, page.nav, PAGE_W - MARGIN - 25 * mm, PAGE_H - 31.5 * mm, 235 * mm, font=FONT, size=9.1, color=colors.HexColor("#DCE5F3"), leading=12)


def tone_colors(tone: str):
    return {
        "green": (GREEN_PALE, GREEN),
        "amber": (AMBER_PALE, AMBER),
        "red": (RED_PALE, RED),
    }.get(tone, (BLUE_PALE, BLUE))


def draw_arrow(c: canvas.Canvas, x1: float, y1: float, x2: float, y2: float) -> None:
    c.setStrokeColor(colors.HexColor("#94A3B8"))
    c.setFillColor(colors.HexColor("#94A3B8"))
    c.setLineWidth(1.6)
    c.line(x1, y1, x2, y2)
    c.line(x2, y2, x2 + 5, y2 + 3)
    c.line(x2, y2, x2 + 5, y2 - 3)


def draw_flow(c: canvas.Canvas, flow: Sequence[tuple[str, str]], top: float) -> float:
    per_row = 6
    gap = 4 * mm
    box_h = 28 * mm
    box_w = (PAGE_W - 2 * MARGIN - (per_row - 1) * gap) / per_row
    rows = (len(flow) + per_row - 1) // per_row
    for row in range(rows):
        items = flow[row * per_row : (row + 1) * per_row]
        y = top - row * (box_h + 8 * mm) - box_h
        for index, (label, tone) in enumerate(items):
            x = PAGE_W - MARGIN - box_w - index * (box_w + gap)
            fill, stroke = tone_colors(tone)
            c.setFillColor(colors.HexColor("#E2E8F0"))
            c.setStrokeColor(colors.HexColor("#E2E8F0"))
            c.roundRect(x, y - 1.2 * mm, box_w, box_h, 4 * mm, fill=1, stroke=0)
            c.setFillColor(fill)
            c.setStrokeColor(stroke)
            c.setLineWidth(1.25)
            c.roundRect(x, y, box_w, box_h, 4 * mm, fill=1, stroke=1)
            draw_centered_rtl(c, label, x, y, box_w, box_h, font=FONT_BOLD, size=9.4, color=NAVY, leading=12.4, max_lines=3)
            if index < len(items) - 1:
                draw_arrow(c, x - 1 * mm, y + box_h / 2, x - gap + 1 * mm, y + box_h / 2)
        if row < rows - 1:
            last_x = PAGE_W - MARGIN - box_w - (len(items) - 1) * (box_w + gap)
            next_y = top - (row + 1) * (box_h + 8 * mm) - box_h
            c.setStrokeColor(colors.HexColor("#94A3B8"))
            c.setLineWidth(1.6)
            c.line(last_x + box_w / 2, y, last_x + box_w / 2, next_y + box_h + 5 * mm)
            c.line(last_x + box_w / 2, next_y + box_h + 5 * mm, PAGE_W - MARGIN - box_w / 2, next_y + box_h + 5 * mm)
            c.line(PAGE_W - MARGIN - box_w / 2, next_y + box_h + 5 * mm, PAGE_W - MARGIN - box_w / 2, next_y + box_h)
    return top - rows * (box_h + 8 * mm) + 3 * mm


def draw_panel(c: canvas.Canvas, x: float, y: float, width: float, height: float, title: str, items: Sequence[str], accent=BLUE) -> None:
    c.setFillColor(WHITE)
    c.setStrokeColor(BORDER)
    c.roundRect(x, y, width, height, 4 * mm, fill=1, stroke=1)
    c.setFillColor(accent)
    c.roundRect(x + width - 5 * mm, y + height - 14 * mm, 3 * mm, 8 * mm, 1.5 * mm, fill=1, stroke=0)
    draw_rtl(c, title, x + width - 10 * mm, y + height - 6 * mm, width - 18 * mm, font=FONT_BOLD, size=11.8, color=NAVY, leading=15)
    cursor = y + height - 21 * mm
    for idx, item in enumerate(items, 1):
        c.setFillColor(BLUE_PALE if accent == BLUE else GREEN_PALE)
        c.circle(x + width - 8 * mm, cursor - 1.5 * mm, 3.2 * mm, fill=1, stroke=0)
        c.setFillColor(accent)
        c.setFont(FONT_BOLD, 7)
        c.drawCentredString(x + width - 8 * mm, cursor - 3.4 * mm, str(idx))
        after = draw_rtl(c, item, x + width - 14 * mm, cursor + 2 * mm, width - 22 * mm, font=FONT, size=9, color=SLATE, leading=12.2, max_lines=3)
        cursor = after - 3.2 * mm


def draw_cover(c: canvas.Canvas) -> None:
    c.setFillColor(colors.HexColor("#F4F7FF"))
    c.rect(0, 0, PAGE_W, PAGE_H, fill=1, stroke=0)
    c.setFillColor(NAVY)
    c.rect(0, PAGE_H - 18 * mm, PAGE_W, 18 * mm, fill=1, stroke=0)
    c.setFillColor(BLUE)
    c.circle(PAGE_W - 52 * mm, PAGE_H - 70 * mm, 21 * mm, fill=1, stroke=0)
    c.setStrokeColor(WHITE)
    c.setLineWidth(3)
    c.line(PAGE_W - 62 * mm, PAGE_H - 70 * mm, PAGE_W - 55 * mm, PAGE_H - 77 * mm)
    c.line(PAGE_W - 55 * mm, PAGE_H - 77 * mm, PAGE_W - 41 * mm, PAGE_H - 60 * mm)
    draw_rtl(c, "دليل تشغيل نظام Mini ERP", PAGE_W - MARGIN, PAGE_H - 92 * mm, 250 * mm, font=FONT_BOLD, size=29, color=NAVY, leading=36)
    draw_rtl(c, "مخططات التدفق، مسارات القائمة، حالات المستندات، وضوابط التشغيل", PAGE_W - MARGIN, PAGE_H - 111 * mm, 250 * mm, font=FONT_SEMIBOLD, size=14, color=BLUE_DARK, leading=20)
    draw_rtl(c, "نسخة مطابقة للواجهة والإجراءات الحالية - جاهزة للتدريب والتسليم للعميل", PAGE_W - MARGIN, PAGE_H - 126 * mm, 250 * mm, font=FONT, size=10.5, color=SLATE, leading=15)
    c.setFillColor(WHITE)
    c.setStrokeColor(BORDER)
    c.roundRect(MARGIN, 34 * mm, PAGE_W - 2 * MARGIN, 29 * mm, 5 * mm, fill=1, stroke=1)
    draw_rtl(c, "قاعدة الاستخدام", PAGE_W - MARGIN - 10 * mm, 55 * mm, 100 * mm, font=FONT_BOLD, size=11, color=NAVY)
    draw_rtl(c, "افتح المجموعة من القائمة الجانبية، اختر الشاشة بالاسم، ثم نفذ الأزرار حسب ترتيب الحالة الظاهر في هذا الدليل. لا يحتاج المستخدم إلى إدخال روابط أو مسارات تقنية.", PAGE_W - MARGIN - 10 * mm, 45 * mm, PAGE_W - 2 * MARGIN - 20 * mm, font=FONT, size=9.5, color=SLATE, leading=14)
    c.setFont(FONT_BOLD, 9)
    c.setFillColor(MUTED)
    c.drawString(MARGIN, 24 * mm, f"v{VERSION}")
    draw_rtl(c, DATE_AR, MARGIN + 50 * mm, 27.2 * mm, 36 * mm, font=FONT, size=9, color=MUTED, leading=11)
    c.showPage()


def draw_navigation_page(c: canvas.Canvas, page_no: int, start: int, end: int) -> None:
    title = "خريطة القائمة الجانبية" if start == 0 else "خريطة القائمة الجانبية - تكملة"
    page = Page(title, "الوحدات والإدارة ← افتح المجموعة المطلوبة", (), (), ())
    draw_header(c, page_no - 1, page)
    groups = NAVIGATION[start:end]
    cols = 2
    gap = 6 * mm
    width = (PAGE_W - 2 * MARGIN - (cols - 1) * gap) / cols
    top = PAGE_H - 49 * mm
    for index, (group, items) in enumerate(groups):
        col = index % cols
        row = index // cols
        x = PAGE_W - MARGIN - width - col * (width + gap)
        y_top = top - row * 69 * mm
        height = 63 * mm
        c.setFillColor(WHITE)
        c.setStrokeColor(BORDER)
        c.roundRect(x, y_top - height, width, height, 4 * mm, fill=1, stroke=1)
        c.setFillColor(BLUE_PALE)
        c.roundRect(x + 2 * mm, y_top - 14 * mm, width - 4 * mm, 11 * mm, 3 * mm, fill=1, stroke=0)
        draw_rtl(c, group, x + width - 6 * mm, y_top - 4.5 * mm, width - 12 * mm, font=FONT_BOLD, size=10.2, color=BLUE_DARK, leading=13)
        item_cols = 2 if len(items) > 9 else 1
        per_col = (len(items) + item_cols - 1) // item_cols
        item_width = (width - 10 * mm) / item_cols
        for item_index, item in enumerate(items):
            item_col = item_index // per_col
            item_row = item_index % per_col
            item_right = x + width - 6 * mm - item_col * item_width
            cursor = y_top - 19 * mm - item_row * 4.8 * mm
            c.setFillColor(BLUE)
            c.circle(item_right, cursor - 1.1 * mm, 1.1 * mm, fill=1, stroke=0)
            draw_rtl(c, item, item_right - 4 * mm, cursor + 1 * mm, item_width - 6 * mm, font=FONT, size=7.7, color=SLATE, leading=9.2, max_lines=1)
    draw_footer(c, page_no)
    c.showPage()


def draw_status_page(c: canvas.Canvas, page_no: int) -> None:
    page = Page("حالات المستندات الفعلية", "الحالة والأزرار تختلف حسب نوع المستند", (), (), ())
    draw_header(c, page_no - 1, page)
    rows = (
        ("أوامر البيع والشراء", "مسودة ← مقدمة ← مؤكدة", "تقديم، تأكيد، إلغاء"),
        ("أذون التسليم والاستلام", "مسودة ← مؤكدة", "تأكيد، إلغاء قبل التأكيد"),
        ("الفواتير والتكاليف والمرتجعات", "مسودة ← مقدمة ← معتمدة ← مرحلة", "تقديم، اعتماد، ترحيل، إلغاء قبل الترحيل"),
        ("سندات القبض والصرف", "مسودة ← مرحلة", "ترحيل"),
        ("تحويل المخزون", "مسودة ← مقدمة ← معتمدة ← مصدرة ← مستلمة", "تقديم، اعتماد، إصدار، استلام"),
        ("عقد الإيجار", "مسودة ← مقدمة ← معتمدة ← نشطة ← مكتملة", "تقديم، اعتماد، تفعيل"),
        ("رجوع الإيجار", "مسودة ← مقدمة ← مكتملة", "تقديم الرجوع، إكمال الفحص"),
        ("تسوية البنك", "مسودة ← منتهية", "مطابقة، إنهاء التسوية"),
        ("الفترة الضريبية", "مفتوحة ← مسودة إقرار ← مقدمة", "توليد المسودة، تقديم الإقرار"),
    )
    x = MARGIN
    y_top = PAGE_H - 50 * mm
    table_w = PAGE_W - 2 * MARGIN
    col_widths = (58 * mm, 105 * mm, table_w - 163 * mm)
    row_h = 13.2 * mm
    headers = ("نوع المستند", "تسلسل الحالة", "الإجراءات المتاحة")
    c.setFillColor(NAVY)
    c.roundRect(x, y_top - row_h, table_w, row_h, 3 * mm, fill=1, stroke=0)
    cursor_x = PAGE_W - MARGIN
    for header, width in zip(headers, col_widths):
        draw_rtl(c, header, cursor_x - 4 * mm, y_top - 2.8 * mm, width - 8 * mm, font=FONT_BOLD, size=9.2, color=WHITE, leading=11)
        cursor_x -= width
    y = y_top - row_h
    for idx, row in enumerate(rows):
        c.setFillColor(WHITE if idx % 2 == 0 else SURFACE)
        c.setStrokeColor(BORDER)
        c.rect(x, y - row_h, table_w, row_h, fill=1, stroke=1)
        cursor_x = PAGE_W - MARGIN
        for cell, width in zip(row, col_widths):
            draw_rtl(c, cell, cursor_x - 4 * mm, y - 2.8 * mm, width - 8 * mm, font=FONT, size=8.5, color=SLATE, leading=10.8, max_lines=2)
            cursor_x -= width
        y -= row_h
    draw_footer(c, page_no)
    c.showPage()


def draw_content_page(c: canvas.Canvas, page_no: int, number: int, page: Page) -> None:
    draw_header(c, number, page)
    flow_bottom = draw_flow(c, page.flow, PAGE_H - 48 * mm)
    panel_y = 20 * mm
    panel_h = max(58 * mm, flow_bottom - panel_y - 5 * mm)
    gap = 7 * mm
    panel_w = (PAGE_W - 2 * MARGIN - gap) / 2
    draw_panel(c, PAGE_W - MARGIN - panel_w, panel_y, panel_w, panel_h, "خطوات التنفيذ", page.steps, BLUE)
    check_title = "ضوابط ونتيجة متوقعة"
    check_items = page.checks + ((page.status,) if page.status else ())
    draw_panel(c, MARGIN, panel_y, panel_w, panel_h, check_title, check_items, GREEN)
    draw_footer(c, page_no)
    c.showPage()


def write_markdown() -> None:
    lines = [
        "# دليل تشغيل نظام Mini ERP ومخططات التدفق",
        "",
        f"الإصدار: {VERSION} - {DATE_AR}",
        "",
        "> هذا الدليل مطابق لأسماء القائمة الجانبية والإجراءات الحالية. اتبع أسماء المجموعات والشاشات كما تظهر للمستخدم، ولا تستخدم مسارات تقنية في الشرح.",
        "",
        "## خريطة القائمة الجانبية",
        "",
    ]
    for group, items in NAVIGATION:
        lines.append(f"### الوحدات والإدارة ← {group}")
        lines.append("")
        for item in items:
            lines.append(f"- {item}")
        lines.append("")

    lines.extend([
        "## حالات المستندات الفعلية",
        "",
        "| نوع المستند | تسلسل الحالة |",
        "|---|---|",
        "| أوامر البيع والشراء | مسودة ← مقدمة ← مؤكدة |",
        "| أذون التسليم والاستلام | مسودة ← مؤكدة |",
        "| الفواتير والتكاليف والمرتجعات | مسودة ← مقدمة ← معتمدة ← مرحلة |",
        "| سندات القبض والصرف | مسودة ← مرحلة |",
        "| تحويل المخزون | مسودة ← مقدمة ← معتمدة ← مصدرة ← مستلمة جزئيًا أو مستلمة |",
        "| عقد الإيجار | مسودة ← مقدمة ← معتمدة ← نشطة ← مكتملة |",
        "| رجوع الإيجار | مسودة ← مقدمة ← مكتملة |",
        "| تسوية البنك | مسودة ← منتهية |",
        "| الفترة الضريبية | مفتوحة ← مسودة إقرار ← مقدمة |",
        "",
    ])

    for index, page in enumerate(PAGES, 1):
        lines.extend([
            f"## {index}. {page.title}",
            "",
            f"**مكانها في النظام:** {page.nav}",
            "",
            "```mermaid",
            "flowchart RL",
        ])
        for node_index, (label, _tone) in enumerate(page.flow, 1):
            lines.append(f'    n{node_index}["{label}"]')
        for node_index in range(1, len(page.flow)):
            lines.append(f"    n{node_index} --> n{node_index + 1}")
        lines.extend(["```", "", "### خطوات التنفيذ", ""])
        for step_index, step in enumerate(page.steps, 1):
            lines.append(f"{step_index}. {step}")
        lines.extend(["", "### الضوابط والنتيجة المتوقعة", ""])
        for item in page.checks:
            lines.append(f"- {item}")
        if page.status:
            lines.append(f"- {page.status}")
        lines.append("")

    lines.extend([
        "## قواعد تشغيل ملزمة",
        "",
        "1. لا ترحيل داخل فترة مالية مغلقة.",
        "2. لا تعديل مباشر لمستند مرحل؛ استخدم العكس أو المرتجع أو مذكرة التسوية.",
        "3. تأكيد إذن التسليم أو الاستلام هو الذي يسجل حركة المخزون.",
        "4. ترحيل سند القبض أو الصرف يختلف عن تسوية المستحقات.",
        "5. لا تنه تسوية البنك قبل وصول الفرق إلى صفر.",
        "6. لا ترحل تكلفة وصول قبل توزيعها بالكامل وربطها بإذن استلام مؤكد.",
        "7. ظهور الشاشة والزر يعتمد على صلاحيات المستخدم وحالة المستند.",
        "",
    ])
    MD_PATH.parent.mkdir(parents=True, exist_ok=True)
    MD_PATH.write_text("\n".join(lines), encoding="utf-8")


def build_pdf() -> None:
    register_fonts()
    PDF_PATH.parent.mkdir(parents=True, exist_ok=True)
    c = canvas.Canvas(str(PDF_PATH), pagesize=landscape(A4), pageCompression=1)
    c.setTitle("دليل تشغيل نظام Mini ERP ومخططات التدفق")
    c.setAuthor("Mini ERP")
    c.setSubject("مسارات الاستخدام وحالات المستندات ومخططات التشغيل")
    draw_cover(c)
    page_no = 2
    for start in range(0, len(NAVIGATION), 4):
        draw_navigation_page(c, page_no, start, min(start + 4, len(NAVIGATION)))
        page_no += 1
    draw_status_page(c, page_no)
    page_no += 1
    for number, page in enumerate(PAGES, 1):
        draw_content_page(c, page_no, number, page)
        page_no += 1
    c.save()


def main() -> None:
    write_markdown()
    build_pdf()
    print(MD_PATH)
    print(PDF_PATH)


if __name__ == "__main__":
    main()
