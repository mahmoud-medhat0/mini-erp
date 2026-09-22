from __future__ import annotations

import sys
from pathlib import Path
from typing import Sequence

sys.path.insert(0, str(Path(__file__).resolve().parent))

from reportlab.lib import colors
from reportlab.lib.enums import TA_RIGHT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import mm
from reportlab.platypus import (
    BaseDocTemplate,
    Frame,
    LongTable,
    NextPageTemplate,
    PageBreak,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
)

from generate_system_guide_pdf import (  # noqa: E402  (re-uses the guide's Arabic RTL rendering engine)
    BLUE,
    BLUE_DARK,
    BLUE_PALE,
    BODY_BOTTOM,
    BODY_LEFT,
    BODY_RIGHT,
    BODY_TOP,
    BORDER,
    CONTENT_WIDTH,
    EMERALD,
    EMERALD_PALE,
    FONT_BOLD,
    FONT_MEDIUM,
    FONT_REGULAR,
    FONT_SEMIBOLD,
    INDIGO,
    MUTED,
    NAVY,
    OutlineMarker,
    PAGE_HEIGHT,
    PAGE_WIDTH,
    PALE,
    RTLHeading,
    RTLTableOfContents,
    RTLText,
    SLATE,
    StoryBuilder,
    WHITE,
    callout,
    clean_text,
    register_fonts,
    rtl_visual,
    workflow_diagram,
)

ROOT = Path(__file__).resolve().parents[2]
OUTPUT_PATH = ROOT / "output" / "pdf" / "mini-erp-integrated-vision-guide-ar.pdf"

REPORT_VERSION = "1.0"
REPORT_DATE_ISO = "2026-09-21"
REPORT_DATE_AR = "21 سبتمبر 2026"


def draw_cover_page(canvas, doc) -> None:
    del doc
    canvas.saveState()
    canvas.setFillColor(NAVY)
    canvas.rect(0, 0, PAGE_WIDTH, PAGE_HEIGHT, fill=1, stroke=0)

    canvas.setFillColor(colors.HexColor("#3730A3"))
    canvas.circle(PAGE_WIDTH - 15 * mm, PAGE_HEIGHT - 18 * mm, 58 * mm, fill=1, stroke=0)
    canvas.setFillColor(colors.HexColor("#1E3A8A"))
    canvas.circle(12 * mm, 16 * mm, 48 * mm, fill=1, stroke=0)
    canvas.setFillColor(INDIGO)
    canvas.roundRect(18 * mm, PAGE_HEIGHT - 26 * mm, 46 * mm, 6 * mm, 3 * mm, fill=1, stroke=0)
    canvas.setFillColor(WHITE)
    canvas.setFont(FONT_SEMIBOLD, 7.2)
    canvas.drawCentredString(41 * mm, PAGE_HEIGHT - 24.25 * mm, "INTEGRATED VISION GUIDE")
    canvas.restoreState()


def draw_body_page(canvas, doc) -> None:
    page_number = canvas.getPageNumber()
    canvas.saveState()

    canvas.setFillColor(INDIGO)
    canvas.rect(0, PAGE_HEIGHT - 4, PAGE_WIDTH, 4, fill=1, stroke=0)

    canvas.setFont(FONT_MEDIUM, 9)
    canvas.setFillColor(MUTED)
    canvas.drawRightString(
        PAGE_WIDTH - BODY_RIGHT,
        PAGE_HEIGHT - 11 * mm,
        rtl_visual("الدليل الإرشادي المتكامل - رؤية نظام Mini ERP الشامل"),
    )
    canvas.drawString(
        BODY_LEFT,
        PAGE_HEIGHT - 11 * mm,
        f"v{REPORT_VERSION} | {REPORT_DATE_ISO}",
    )

    canvas.setStrokeColor(BORDER)
    canvas.line(BODY_LEFT, 12 * mm, PAGE_WIDTH - BODY_RIGHT, 12 * mm)
    canvas.setFont(FONT_MEDIUM, 9.2)
    canvas.setFillColor(MUTED)
    canvas.drawCentredString(
        PAGE_WIDTH / 2,
        7.5 * mm,
        f"{page_number} {rtl_visual('صفحة')}",
    )
    canvas.restoreState()


class IntegratedGuideDocTemplate(BaseDocTemplate):
    def __init__(self, filename: str) -> None:
        super().__init__(
            filename,
            pagesize=A4,
            leftMargin=BODY_LEFT,
            rightMargin=BODY_RIGHT,
            topMargin=BODY_TOP,
            bottomMargin=BODY_BOTTOM,
            title="Mini ERP Integrated Vision Guide - Arabic",
            author="Mini ERP",
            subject="Standalone guide to the full integrated ERP vision",
        )

        cover_frame = Frame(
            BODY_LEFT,
            18 * mm,
            CONTENT_WIDTH,
            PAGE_HEIGHT - 36 * mm,
            id="cover-frame",
            showBoundary=0,
            leftPadding=0,
            rightPadding=0,
            topPadding=0,
            bottomPadding=0,
        )
        body_frame = Frame(
            BODY_LEFT,
            BODY_BOTTOM,
            CONTENT_WIDTH,
            PAGE_HEIGHT - BODY_TOP - BODY_BOTTOM,
            id="body-frame",
            showBoundary=0,
            leftPadding=0,
            rightPadding=0,
            topPadding=0,
            bottomPadding=0,
        )

        self.addPageTemplates(
            [
                PageTemplate(id="Cover", frames=[cover_frame], onPage=draw_cover_page),
                PageTemplate(id="Body", frames=[body_frame], onPage=draw_body_page),
            ]
        )

    def afterFlowable(self, flowable) -> None:
        if not isinstance(flowable, (RTLHeading, OutlineMarker)):
            return

        self.canv.bookmarkPage(flowable.bookmark)
        try:
            self.canv.addOutlineEntry(
                clean_text(flowable.toc_title),
                flowable.bookmark,
                flowable.level,
                closed=flowable.level > 0,
            )
        except Exception:
            pass

        self.notify(
            "TOCEntry",
            (
                flowable.level,
                rtl_visual(flowable.toc_title),
                self.page,
                flowable.bookmark,
            ),
        )


def part_map_table(rows: Sequence[tuple[str, str, str]]) -> LongTable:
    header = [
        RTLText("#", font_name=FONT_SEMIBOLD, font_size=10, color=WHITE),
        RTLText("القسم", font_name=FONT_SEMIBOLD, font_size=10, color=WHITE),
        RTLText("الموديولات", font_name=FONT_SEMIBOLD, font_size=10, color=WHITE),
    ]
    data: list[list[object]] = [header]
    for number, part, modules in rows:
        data.append(
            [
                Paragraph(
                    number,
                    ParagraphStyle(
                        "PartNumber",
                        fontName=FONT_BOLD,
                        fontSize=11,
                        leading=16,
                        textColor=WHITE,
                        alignment=TA_RIGHT,
                    ),
                ),
                RTLText(part, font_name=FONT_SEMIBOLD, font_size=10.2, leading=15.5, color=NAVY),
                RTLText(modules, font_size=9.3, leading=14.3, color=SLATE),
            ]
        )

    table = LongTable(
        data,
        colWidths=[10 * mm, 42 * mm, CONTENT_WIDTH - 52 * mm],
        repeatRows=1,
        hAlign="CENTER",
        splitByRow=1,
        splitInRow=0,
    )
    style = [
        ("BACKGROUND", (0, 0), (-1, 0), INDIGO),
        ("BACKGROUND", (0, 1), (0, -1), BLUE_DARK),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("BOX", (0, 0), (-1, -1), 0.6, BORDER),
        ("INNERGRID", (0, 0), (-1, -1), 0.35, BORDER),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("RIGHTPADDING", (0, 0), (-1, -1), 8),
        ("TOPPADDING", (0, 0), (-1, -1), 7.5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 7.5),
        ("ALIGN", (0, 1), (0, -1), "CENTER"),
    ]
    for row in range(1, len(data)):
        if row % 2 == 0:
            style.append(("BACKGROUND", (1, row), (2, row), PALE))
    table.setStyle(TableStyle(style))
    return table


PARTS_MAP: list[tuple[str, str, str]] = [
    ("1", "الأساس المحاسبي", "المحاسبة والأستاذ العام، القوائم المالية"),
    ("2", "دورة المبيعات والمشتريات", "المبيعات، المشتريات"),
    ("3", "المخزون والعُدّة والإيجارات", "المخزون، العُدّة والمعدات، إدارة الإيجارات"),
    ("4", "العملاء والموردون والسيولة", "العملاء والمديونيات، الموردون والالتزامات، النقدية، البنوك، الشيكات"),
    ("5", "المصروفات والأصول والرواتب والضرائب", "المصروفات، المصروفات المقدمة والمستحقة، الأصول الثابتة، الرواتب، الضرائب"),
    ("6", "حوكمة الأداء المالي", "الشركاء وحقوق الملكية، المشاريع ومراكز التكلفة، الموازنات والتنبؤ، المعاملات المتكررة"),
    ("7", "التقارير والمتابعة", "التقارير، لوحة التحكم"),
    ("8", "الإدارة والأمان والتوثيق", "المستخدمون والصلاحيات، سجل التدقيق، ترقيم المستندات"),
]


def module_section(
    builder: StoryBuilder,
    number: str,
    title_ar: str,
    title_en: str,
    intro: str,
    items: Sequence[str],
) -> None:
    builder.heading(f"{number}. {title_ar} ({title_en})", level=1)
    if intro:
        builder.p(intro)
    builder.bullets(items)


def build_story() -> list:
    builder = StoryBuilder()
    story = builder.story

    # Cover page
    story.extend(
        [
            Spacer(1, 22 * mm),
            Paragraph(
                "MINI ERP",
                ParagraphStyle(
                    "CoverBrand",
                    fontName=FONT_SEMIBOLD,
                    fontSize=13,
                    leading=18,
                    textColor=colors.HexColor("#C7D2FE"),
                    alignment=TA_RIGHT,
                ),
            ),
            Spacer(1, 3 * mm),
            RTLText(
                "الدليل الإرشادي المتكامل",
                font_name=FONT_BOLD,
                font_size=30,
                leading=40,
                color=WHITE,
            ),
            Spacer(1, 2 * mm),
            RTLText(
                "رؤية نظام Mini ERP الشامل - من العملية الواحدة إلى القوائم المالية",
                font_name=FONT_SEMIBOLD,
                font_size=15,
                leading=23,
                color=colors.HexColor("#C7D2FE"),
            ),
            Spacer(1, 10 * mm),
            RTLText(
                "26 موديولًا متكاملًا موزعة على 8 أقسام، توثّق الرؤية الكاملة لتحويل إدارة الشركة إلى نظام محاسبي وإداري واحد - بحيث تُدخل كل عملية مرة واحدة، وتتولد القيود والحسابات والتقارير تلقائيًا من خلفها.",
                font_size=11.5,
                leading=18,
                color=colors.HexColor("#E0E7FF"),
            ),
            Spacer(1, 14 * mm),
            RTLText(
                f"الإصدار {REPORT_VERSION} | {REPORT_DATE_AR}",
                font_name=FONT_SEMIBOLD,
                font_size=12.5,
                leading=19,
                color=colors.HexColor("#C7D2FE"),
            ),
            Spacer(1, 3 * mm),
            RTLText(
                "دليل مستقل عن دليل الاستخدام الحالي وعن تقرير الفجوة - مرجع رؤية شامل قائم بذاته",
                font_size=11.2,
                leading=17,
                color=colors.HexColor("#94A3B8"),
            ),
            NextPageTemplate("Body"),
            PageBreak(),
        ]
    )

    builder.heading("محتويات الدليل")
    toc = RTLTableOfContents()
    toc.levelStyles = [
        ParagraphStyle(
            "TOCLevel0",
            fontName=FONT_SEMIBOLD,
            fontSize=12.5,
            leading=20.5,
            textColor=NAVY,
            leftIndent=12,
            rightIndent=10,
            firstLineIndent=0,
            alignment=TA_RIGHT,
            spaceBefore=5,
        ),
        ParagraphStyle(
            "TOCLevel1",
            fontName=FONT_REGULAR,
            fontSize=10.5,
            leading=17,
            textColor=SLATE,
            leftIndent=22,
            rightIndent=20,
            alignment=TA_RIGHT,
        ),
    ]
    story.extend(
        [
            callout(
                "عن هذا الدليل",
                "مستقل تمامًا عن دليل الاستخدام الحالي وعن تقرير الفجوة. غرضه توثيق الرؤية الكاملة كمرجع واحد شامل، بصرف النظر عمّا هو مبني بالفعل الآن وما لم يُبنَ بعد. ابدأ بقسم الفكرة الجوهرية لأنه مفتاح فهم باقي الدليل.",
            ),
            Spacer(1, 10),
            toc,
        ]
    )

    # Core idea
    builder.heading("الفكرة الجوهرية: قيد واحد يشغّل كل شيء", page_break=True)
    story.append(
        callout(
            "أهم قاعدة في النظام كله",
            "المستخدم لا يكتب القيود المحاسبية يدويًا في العمليات العادية. المستخدم يسجّل العملية التجارية كما يفهمها فعليًا - فاتورة، أمر شراء، سند قبض، عقد إيجار، مصروف - والنظام هو من يحوّلها تلقائيًا إلى قيد متوازن، يرحّله للأستاذ، يجمّعه في ميزان المراجعة، ويعكسه في القوائم المالية. مرة واحدة عند الإدخال، وكل ما بعدها تلقائي.",
            background=EMERALD_PALE,
            border=EMERALD,
        )
    )
    story.append(Spacer(1, 10))
    builder.p("مثال المبيعات - من الفاتورة إلى القوائم المالية دون أي تدخل يدوي إضافي:")
    story.extend(
        workflow_diagram(
            [
                "فاتورة بيع",
                "مديونية العميل",
                "الإيراد",
                "الضريبة",
                "تكلفة البضاعة",
                "خصم المخزون",
                "قيد يومية متوازن",
                "دفتر الأستاذ العام",
                "ميزان المراجعة",
                "القوائم المالية",
            ]
        )
    )
    story.append(Spacer(1, 6))
    builder.p("ونفس الفكرة بالضبط تتكرر خلف كل عملية أخرى في النظام:")
    builder.bullets(
        [
            "فاتورة شراء ← التزام على المورد + المخزون أو المصروف + الضريبة ← قيد ← أستاذ ← قوائم مالية.",
            "سند قبض من عميل ← تخفيض مديونية العميل + زيادة رصيد الخزينة أو البنك ← قيد ← أستاذ.",
            "سند دفع لمورد ← تخفيض التزام المورد + تخفيض رصيد الخزينة أو البنك ← قيد ← أستاذ.",
            "فاتورة إيجار ← مديونية العميل + إيراد إيجار + تأمين محتمل ← قيد ← أستاذ.",
            "مصروف ← تخفيض الخزينة أو البنك أو زيادة التزام مورد + تحميل حساب المصروف المناسب ← قيد ← أستاذ.",
            "رسملة أصل ثابت وإهلاكه الدوري ← قيد إهلاك تلقائي كل فترة ← أستاذ.",
        ]
    )
    story.append(
        callout(
            "النتيجة المقصودة",
            "أرقام الشركة كلها مترابطة من أول عملية حتى آخر قائمة مالية، بحيث يمكن في أي لحظة معرفة: الشركة باعت كام، اشترت كام، عليها كام، ليها كام، المخزون قيمته كام، العُدّة موجودة فين، الإيجارات قيمتها كام، المصروفات كام، السيولة كام، وفي النهاية الشركة كسبت أو خسرت كام - دون أي تجميع يدوي خارج النظام. هذا المبدأ هو المعيار الذي يُقاس عليه كل موديول في هذا الدليل.",
        )
    )

    # Parts map
    builder.heading("خريطة الأقسام الثمانية", page_break=True)
    story.append(part_map_table(PARTS_MAP))

    # Part 1
    builder.heading("القسم الأول: الأساس المحاسبي", page_break=True)
    builder.p(
        "هذا القسم هو المحرك الذي يشغّل كل الموديولات الأخرى؛ كل عملية في أي موديول لاحق تنتهي هنا، وكل قائمة مالية تُقرأ من هنا."
    )
    module_section(
        builder,
        "1",
        "المحاسبة والأستاذ العام",
        "Accounting & General Ledger",
        "القلب النابض للنظام كله - كل عملية تجارية، بصرف النظر عن مصدرها، تمر من هنا قبل أن تصبح رقمًا في أي تقرير.",
        [
            "شجرة حسابات (Chart of Accounts) متعددة المستويات تستوعب كل أنواع الحسابات: أصول، التزامات، حقوق ملكية، إيرادات، مصروفات.",
            "قيود يومية بمبدأ القيد المزدوج (Double Entry) - كل قيد متوازن إلزاميًا.",
            "دفتر اليومية: سجل زمني لكل القيود كما حدثت.",
            "دفتر الأستاذ العام: تجميع كل القيود على مستوى كل حساب على حدة.",
            "ميزان المراجعة: تلخيص أرصدة كل الحسابات في لحظة معينة للتأكد من توازن النظام.",
            "أرصدة افتتاحية وختامية لكل حساب ولكل فترة مالية.",
            "إقفالات شهرية وسنوية منضبطة.",
            "ترحيل القيود تلقائيًا من كل المستندات التشغيلية.",
            "منع أي تعديل على الفترات المقفلة لحماية الأرقام التاريخية المعتمدة.",
        ],
    )
    module_section(
        builder,
        "2",
        "القوائم المالية",
        "Financial Statements",
        "نتيجة مباشرة ومُشتقة بالكامل من الأستاذ العام - لا تُبنى يدويًا، بل تُقرأ تلقائيًا من نفس البيانات المرحّلة.",
        [
            "قائمة الدخل (Income Statement).",
            "الميزانية العمومية (Balance Sheet).",
            "قائمة التدفقات النقدية (Cash Flow Statement).",
            "قائمة التغير في حقوق الملكية.",
            "مؤشرات الربحية: إجمالي الربح، ربح التشغيل، صافي الربح.",
            "تقارير شهرية وسنوية، ومقارنات بين الفترات المختلفة.",
        ],
    )

    # Part 2
    builder.heading("القسم الثاني: دورة المبيعات والمشتريات", page_break=True)
    builder.p(
        "دورتا العمل التجاريتان الأساسيتان؛ كل منهما تبدأ بمستند تجاري وتنتهي تلقائيًا بأثر محاسبي كامل على الإيراد أو المصروف، المخزون، والمديونية أو الالتزام."
    )
    module_section(
        builder,
        "3",
        "المبيعات",
        "Sales",
        "الدورة الكاملة من أول اتصال تجاري بالعميل حتى تحصيل قيمة البضاعة أو الخدمة.",
        [
            "عروض أسعار تُرسل للعميل قبل الالتزام.",
            "أوامر بيع (Sales Orders).",
            "إشعارات تسليم (Delivery Notes).",
            "فواتير مبيعات (Sales Invoices).",
            "إشعارات دائنة (Credit Notes).",
            "مرتجعات مبيعات (Sales Returns).",
            "بيع نقدي وآجل، بخصومات وضرائب على مستوى الفاتورة أو البند.",
            "دفعات جزئية من العميل مع متابعة المتبقي تلقائيًا.",
            "ترقيم تلقائي ومتسلسل لكل الفواتير.",
            "ترحيل كل عملية بيع لحسابات الإيراد والمديونية والضريبة والمخزون تلقائيًا.",
        ],
    )
    module_section(
        builder,
        "4",
        "المشتريات",
        "Purchasing",
        "نفس منطق المبيعات، من جهة الشركة كمشترٍ، من الطلب الداخلي حتى سداد المورد.",
        [
            "طلبات شراء داخلية (Purchase Requests) قبل الالتزام بمورد.",
            "أوامر شراء (Purchase Orders).",
            "استلام بضاعة (Goods Received).",
            "فواتير مشتريات (Purchase Invoices).",
            "مرتجعات مشتريات (Purchase Returns).",
            "شراء نقدي وآجل، بخصومات وضرائب.",
            "دفعات للموردين بما في ذلك دفعات جزئية، ودفعات مقدمة (Supplier Advances).",
            "ترحيل المشتريات والمخزون والالتزامات تلقائيًا.",
        ],
    )

    # Part 3
    builder.heading("القسم الثالث: المخزون والعُدّة والإيجارات", page_break=True)
    builder.p(
        "كل ما يخص الأصول المتحركة للشركة - سواء بضاعة تُباع، أو عُدّة تُستخدم داخليًا، أو معدات تُؤجَّر لعملاء خارجيين - بثلاث طرق إدارة مختلفة الغرض رغم تشابه الفكرة."
    )
    module_section(
        builder,
        "5",
        "المخزون",
        "Inventory",
        "إدارة كاملة لحركة وقيمة كل صنف تملكه الشركة للبيع أو الاستهلاك.",
        [
            "الأصناف، رمز الصنف (SKU)، والباركود.",
            "تصنيفات ووحدات قياس.",
            "أكثر من مخزن في نفس الوقت.",
            "أرصدة افتتاحية لكل صنف ولكل مخزن.",
            "حركة الأصناف عبر المشتريات والمبيعات والمرتجعات.",
            "تحويلات بين المخازن (Transfers) وتسويات جرد (Adjustments).",
            "تسجيل التالف والمفقود والمُستهلك داخليًا (Damage / Loss / Consumption).",
            "حد أدنى للمخزون ونقطة إعادة الطلب (Minimum Stock / Reorder Level).",
            "سجل حركة المخزون (Stock Movement) بالتفصيل.",
            "تقييم المخزون (Inventory Valuation) بطريقة FIFO أو المتوسط المرجح (Weighted Average).",
            "معرفة القيمة الفعلية للمخزون الحالي في أي لحظة.",
        ],
    )
    module_section(
        builder,
        "6",
        "العُدّة والمعدات",
        "Tools & Equipment",
        "عهدة داخلية بحتة - عُدّة وأدوات تُستخدم في تشغيل الشركة نفسها، وليست للبيع ولا للتأجير الخارجي؛ الفارق عن الإيجار أنه لا مقابل مالي من طرف ثالث، والفارق عن الأصول الثابتة أنه ليس بالضرورة رسملة محاسبية بإهلاك.",
        [
            "تسجيل كل أصناف العُدّة والمعدات.",
            "الكميات والحالة والقيمة التقديرية لكل صنف.",
            "الرقم التسلسلي (Serial Number) إن وُجد.",
            "تسليم واستلام العُدّة من وإلى المخزن.",
            "عهدة كل موظف أو موقع تشغيلي، ونقل العُدّة بين المواقع.",
            "تسجيل الحالات الاستثنائية: تالف، مفقود، أو تحت الصيانة.",
            "معرفة فورية بما هو متاح في المخزن، وما هو مُسلَّم كعهدة، وما تم إرجاعه.",
        ],
    )
    module_section(
        builder,
        "7",
        "إدارة الإيجارات",
        "Rental Management",
        "تأجير معدات وأدوات لعملاء خارجيين مقابل عائد مالي - دورة تجارية كاملة، وليست مجرد عهدة.",
        [
            "عقود إيجار مرتبطة بعميل محدد، بالعُدّة أو المعدة المؤجَّرة والكمية.",
            "سعر اليوم أو الشهر، وتاريخ بداية ونهاية الإيجار.",
            "تأمين (Deposit) يُحصَّل من العميل.",
            "حساب مدة الإيجار وقيمته تلقائيًا من التواريخ والأسعار.",
            "رسوم إضافية، غرامات تأخير، وخصومات عند الحاجة.",
            "متابعة المدفوع والمتبقي لكل عقد.",
            "متابعة العقود المنتهية والقريبة من الانتهاء لتجنّب أي تعارض حجز.",
            "إنشاء فاتورة الإيجار وترحيلها محاسبيًا تلقائيًا.",
        ],
    )

    # Part 4
    builder.heading("القسم الرابع: العملاء والموردون والسيولة", page_break=True)
    builder.p(
        "الأطراف الخارجية التي تتحرك معها النقدية داخلة وخارجة، وكل قنوات هذه الحركة: نقدًا، بنكًا، أو شيكًا."
    )
    module_section(
        builder,
        "8",
        "العملاء والمديونيات",
        "Customers & Accounts Receivable",
        "",
        [
            "قاعدة بيانات كاملة للعملاء، برصيد افتتاحي لكل عميل.",
            "فواتير العملاء، الدفعات المُحصَّلة، والمرتجعات.",
            "الإشعارات الدائنة (Credit Notes) ودفعات مقدمة من العملاء (Customer Advances).",
            "كشف حساب مستقل لكل عميل (Customer Statement).",
            "حساب الرصيد المستحق تلقائيًا.",
            "تحليل أعمار الديون (AR Aging): حالي / 1-30 يوم / 31-60 يوم / 61-90 يوم / أكثر من 90 يومًا.",
        ],
    )
    module_section(
        builder,
        "9",
        "الموردون والالتزامات",
        "Suppliers & Accounts Payable",
        "نفس منطق العملاء تمامًا، معكوسًا على جانب الموردين.",
        [
            "قاعدة بيانات كاملة للموردين، برصيد افتتاحي لكل مورد.",
            "فواتير المشتريات، الدفعات المسددة، والمرتجعات.",
            "دفعات مقدمة للموردين (Supplier Advances).",
            "كشف حساب مستقل لكل مورد (Supplier Statement).",
            "حساب الرصيد المستحق تلقائيًا.",
            "تحليل أعمار الالتزامات (AP Aging).",
        ],
    )
    module_section(
        builder,
        "10",
        "إدارة النقدية",
        "Cash Management",
        "",
        [
            "أكثر من خزينة نقدية في نفس الوقت.",
            "سندات قبض نقدية (Cash Receipts) وسندات صرف نقدية (Cash Payments).",
            "تحويلات بين الخزائن (Cash Transfers) وعهدة نثرية (Petty Cash).",
            "رصيد افتتاحي لكل خزينة.",
            "دفتر الخزينة (Cash Book) الكامل.",
            "رصيد الخزينة الفعلي والمتوقع في أي لحظة.",
        ],
    )
    module_section(
        builder,
        "11",
        "البنوك",
        "Banks",
        "",
        [
            "أكثر من حساب بنكي في نفس الوقت.",
            "إيداعات وسحوبات (Deposits / Withdrawals) وتحويلات بين الحسابات.",
            "مصاريف بنكية (Bank Charges).",
            "ربط مباشر مع موديول الشيكات.",
            "تسوية بنكية (Bank Reconciliation) دورية، بمقارنة رصيد النظام برصيد كشف الحساب البنكي.",
        ],
    )
    module_section(
        builder,
        "12",
        "الشيكات",
        "Cheques",
        "",
        [
            "شيكات مستلمة من عملاء وشيكات صادرة لموردين.",
            "بيانات كل شيك: الرقم، البنك، القيمة، تاريخ الاستحقاق.",
            "دورة حالة كاملة: صادر (Issued) ← معلّق (Pending) ← مودَع (Deposited) ← محصَّل (Cleared) ← مرتد (Returned).",
        ],
    )

    # Part 5
    builder.heading("القسم الخامس: المصروفات والأصول والرواتب والضرائب", page_break=True)
    builder.p(
        "كل ما يستهلك موارد الشركة أو يبني أصولها طويلة الأجل، بالإضافة إلى الالتزامات القانونية تجاه الموظفين ومصلحة الضرائب."
    )
    module_section(
        builder,
        "13",
        "المصروفات",
        "Expenses",
        "",
        [
            "تسجيل كل أنواع المصروفات: إيجار، رواتب، مرافق، إنترنت، وقود، مواصلات، صيانة، تسويق، برمجيات واشتراكات، مصروفات مكتبية، أتعاب قانونية ومحاسبية، تأمين، مصاريف بنكية، وغيرها.",
            "ربط كل مصروف بالحساب المحاسبي المناسب، وبمركز التكلفة، وبالمشروع المرتبط به إن وُجد.",
        ],
    )
    module_section(
        builder,
        "14",
        "المصروفات المقدمة والمستحقة",
        "Prepaid & Accrued Expenses",
        "",
        [
            "المصروفات المدفوعة مقدمًا - تُوزَّع على الأشهر المستفيدة تلقائيًا.",
            "المصروفات المستحقة غير المدفوعة بعد - تُسجَّل كالتزام حتى السداد الفعلي.",
            "إنشاء القيود المحاسبية الخاصة بكل نوع تلقائيًا حسب الجدول الزمني.",
        ],
    )
    module_section(
        builder,
        "15",
        "الأصول الثابتة",
        "Fixed Assets",
        "",
        [
            "سجل كامل لكل أصل: تاريخ الشراء، التكلفة، العمر الإنتاجي، القيمة التخريدية، طريقة الإهلاك.",
            "مجمّع الإهلاك (Accumulated Depreciation) والقيمة الدفترية الصافية (Net Book Value).",
            "الموقع والشخص المسؤول عن كل أصل.",
            "حساب الإهلاك الدوري تلقائيًا حسب الطريقة المحددة.",
            "سجل بيع أو استبعاد الأصل عند نهاية استخدامه.",
        ],
    )
    module_section(
        builder,
        "16",
        "الرواتب",
        "Payroll",
        "",
        [
            "بيانات الموظفين، الرواتب الأساسية، والبدلات.",
            "ساعات أو أجر العمل الإضافي، والخصومات والمكافآت.",
            "سلف وقروض الموظفين.",
            "حساب صافي الراتب تلقائيًا.",
            "قيد رواتب (Payroll Journal Entry) يُنشأ تلقائيًا من كل كشف مرتبات.",
        ],
    )
    module_section(
        builder,
        "17",
        "الضرائب",
        "Taxes",
        "",
        [
            "ضريبة القيمة المضافة على المدخلات (Input VAT) والمخرجات (Output VAT).",
            "ضرائب الخصم والإضافة (Withholding Taxes).",
            "الالتزامات الضريبية المستحقة (Tax Payables).",
            "تقارير ضريبية جاهزة.",
            "تجهيز البيانات اللازمة للإقرارات الضريبية، مع ضرورة مراجعتها وفق القوانين المصرية الحالية قبل الاعتماد عليها في أي إقرار رسمي.",
        ],
    )

    # Part 6
    builder.heading("القسم السادس: حوكمة الأداء المالي", page_break=True)
    builder.p(
        "الطبقة التي تربط الأرقام التشغيلية بقرارات الملاك والإدارة: من يملك ماذا، كيف تُقاس ربحية كل جزء من الشركة، وإلى أين تتجه الأرقام مستقبلاً."
    )
    module_section(
        builder,
        "18",
        "الشركاء وحقوق الملكية",
        "Partners & Equity",
        "",
        [
            "رأس المال، ومساهمات الشركاء (Contributions).",
            "مسحوبات الشركاء (Drawings).",
            "حساب جارٍ (Current Account) مستقل لكل شريك.",
            "قروض الشركاء للشركة (Partner Loans).",
            "توزيع الأرباح على الشركاء.",
            "الأرباح المحتجزة (Retained Earnings).",
        ],
    )
    module_section(
        builder,
        "19",
        "المشاريع ومراكز التكلفة",
        "Projects & Cost Centers",
        "",
        [
            "إنشاء مشاريع ومراكز تكلفة مستقلة.",
            "ربط كل إيراد ومصروف بمشروع أو قسم أو فرع محدد.",
            "تكاليف مباشرة (Direct Costs) وغير مباشرة (Indirect Costs).",
            "إيراد وربحية كل مشروع على حدة (Project Profitability).",
            "معرفة ربحية كل مشروع أو قسم بشكل مستقل عن الشركة ككل.",
        ],
    )
    module_section(
        builder,
        "20",
        "الموازنات والتنبؤ",
        "Budgeting & Forecasting",
        "",
        [
            "موازنة شهرية وسنوية.",
            "مقارنة الموازنة بالفعلي (Budget vs Actual).",
            "تنبؤ بالمبيعات، بالمصروفات، بالتدفق النقدي، وبالربح.",
            "تحليل الانحراف (Variance Analysis) بين المخطط والفعلي.",
        ],
    )
    module_section(
        builder,
        "21",
        "المعاملات المتكررة",
        "Recurring Transactions",
        "",
        [
            "إيجارات شهرية، اشتراكات، إنترنت، تأمين، أو أي مصروف أو إيراد يتكرر بجدول زمني ثابت.",
            "إنشاء العمليات تلقائيًا في موعدها المحدد دون الحاجة لإعادة إدخالها يدويًا كل مرة.",
        ],
    )

    # Part 7
    builder.heading("القسم السابع: التقارير والمتابعة", page_break=True)
    builder.p("الواجهة التي يرى منها صاحب القرار كل ما سبق مجمَّعًا وجاهزًا للقراءة الفورية.")
    module_section(
        builder,
        "22",
        "التقارير",
        "Reports",
        "مجموعة شاملة من التقارير المشتقة مباشرة من الأستاذ العام والمستندات التشغيلية، منها:",
        [
            "دفتر اليومية العام ودفتر الأستاذ العام وميزان المراجعة.",
            "قائمة الدخل والميزانية العمومية والتدفقات النقدية.",
            "كشف حساب عميل وكشف حساب مورد.",
            "أعمار ديون العملاء وأعمار التزامات الموردين.",
            "دفتر الخزينة ودفتر البنك والتسوية البنكية.",
            "تقييم المخزون وحركة المخزون.",
            "تقرير المبيعات وتقرير المشتريات وتقرير المصروفات.",
            "سجل الأصول الثابتة وتقرير الإهلاك.",
            "التقرير الضريبي وتقرير الرواتب.",
            "ربحية المشاريع وتقرير مراكز التكلفة والموازنة مقابل الفعلي.",
            "كشوف حسابات الشركاء.",
        ],
    )
    module_section(
        builder,
        "23",
        "لوحة التحكم",
        "Dashboard",
        "شاشة واحدة تجمع أهم مؤشرات الأداء للشركة دفعة واحدة، دون الحاجة للدخول لأي تقرير تفصيلي:",
        [
            "إجمالي الإيرادات وإجمالي المصروفات.",
            "إجمالي الربح وصافي الربح.",
            "رصيد الخزينة ورصيد البنك.",
            "المديونيات (Receivables) والالتزامات (Payables).",
            "قيمة المخزون وقيمة الأصول الثابتة.",
            "الفواتير المستحقة والإيجارات النشطة والمدفوعات المتأخرة.",
            "اتجاه الإيرادات (Revenue Trend) واتجاه المصروفات (Expense Trend).",
            "التدفق النقدي والربحية العامة.",
        ],
    )

    # Part 8
    builder.heading("القسم الثامن: الإدارة والأمان والتوثيق", page_break=True)
    builder.p("الطبقة التي تحمي البيانات وتضمن أن كل رقم في النظام له مصدر وصاحب ومستند معروف.")
    module_section(
        builder,
        "24",
        "المستخدمون والصلاحيات",
        "Users & Permissions",
        "",
        [
            "أدوار جاهزة: مدير النظام، محاسب، مبيعات، مشتريات، مخازن، إدارة عليا.",
            "صلاحيات مختلفة ودقيقة لكل مستخدم حسب دوره.",
            "منع أي مستخدم من الوصول للأجزاء غير المسموح له بها.",
        ],
    )
    module_section(
        builder,
        "25",
        "سجل التدقيق",
        "Audit Trail",
        "كل عملية في النظام تحمل معها تلقائيًا:",
        [
            "مَن أنشأها (Created By) ومتى (Created Date).",
            "مَن عدّلها (Modified By) ومتى (Modified Date).",
            "مَن اعتمدها (Approved By) ومتى (Approval Date).",
            "سجل كامل لكل التعديلات التي طرأت عليها عبر الوقت.",
        ],
    )
    module_section(
        builder,
        "26",
        "ترقيم المستندات",
        "Document Numbering",
        "ترقيم تلقائي ومنظم لكل أنواع المستندات، بنمط ثابت وواضح، مثل:",
        [
            "INV-2026-00001 للفواتير.",
            "PUR-2026-00001 للمشتريات.",
            "REC-2026-00001 لسندات القبض.",
            "PAY-2026-00001 لسندات الصرف.",
            "JV-2026-00001 لقيود اليومية.",
            "RENT-2026-00001 لعقود الإيجار.",
        ],
    )

    # Conclusion
    builder.heading("الخلاصة", page_break=True)
    builder.p(
        "الهدف النهائي من كل ما سبق ليس تجميع 26 موديولًا منفصلًا، بل بناء نظام واحد مترابط: كل عملية تُدخل مرة واحدة في مكانها الطبيعي (فاتورة، سند، عقد، مصروف)، والنظام هو من يتولى تحويلها لقيد متوازن، ترحيله للأستاذ، تجميعه في ميزان المراجعة، وعكسه في القوائم المالية ولوحة التحكم فورًا."
    )
    story.append(
        callout(
            "بهذا الترابط",
            "يصبح بإمكان صاحب القرار أن يسأل في أي لحظة: الشركة باعت كام؟ اشترت كام؟ عليها كام؟ ليها كام؟ المخزون قيمته كام؟ العُدّة موجودة فين؟ الإيجارات قيمتها كام؟ المصروفات كام؟ السيولة كام؟ وفي النهاية - الشركة كسبت أو خسرت كام؟ - ويجد الإجابة جاهزة ودقيقة ومحدّثة، دون أي تجميع يدوي خارج النظام.",
            background=EMERALD_PALE,
            border=EMERALD,
        )
    )

    return story


def main() -> None:
    register_fonts()
    OUTPUT_PATH.parent.mkdir(parents=True, exist_ok=True)
    document = IntegratedGuideDocTemplate(str(OUTPUT_PATH))
    story = build_story()
    document.multiBuild(story)
    print(OUTPUT_PATH)


if __name__ == "__main__":
    main()
