from __future__ import annotations

import sys
from pathlib import Path
from typing import Sequence

sys.path.insert(0, str(Path(__file__).resolve().parent))

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_RIGHT
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
    AMBER,
    AMBER_PALE,
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
    RED,
    RED_PALE,
    RTLHeading,
    RTLTableOfContents,
    RTLText,
    SLATE,
    StoryBuilder,
    WHITE,
    callout,
    cards,
    clean_text,
    paragraph,
    register_fonts,
    rtl_visual,
)

ROOT = Path(__file__).resolve().parents[2]
OUTPUT_PATH = ROOT / "output" / "pdf" / "mini-erp-gap-analysis-ar.pdf"

REPORT_VERSION = "1.0"
REPORT_DATE_ISO = "2026-09-21"
REPORT_DATE_AR = "21 سبتمبر 2026"

STATUS_FULL = "مطابق بالكامل"
STATUS_PARTIAL = "مطابق جزئيًا"
STATUS_MISSING = "غير موجود"

STATUS_COLORS = {
    STATUS_FULL: (EMERALD, EMERALD_PALE),
    STATUS_PARTIAL: (AMBER, AMBER_PALE),
    STATUS_MISSING: (RED, RED_PALE),
}


def draw_cover_page(canvas, doc) -> None:
    del doc
    canvas.saveState()
    canvas.setFillColor(NAVY)
    canvas.rect(0, 0, PAGE_WIDTH, PAGE_HEIGHT, fill=1, stroke=0)

    canvas.setFillColor(colors.HexColor("#1E3A8A"))
    canvas.circle(PAGE_WIDTH - 15 * mm, PAGE_HEIGHT - 18 * mm, 58 * mm, fill=1, stroke=0)
    canvas.setFillColor(colors.HexColor("#312E81"))
    canvas.circle(12 * mm, 16 * mm, 48 * mm, fill=1, stroke=0)
    canvas.setFillColor(colors.HexColor("#B45309"))
    canvas.roundRect(18 * mm, PAGE_HEIGHT - 26 * mm, 34 * mm, 6 * mm, 3 * mm, fill=1, stroke=0)
    canvas.setFillColor(WHITE)
    canvas.setFont(FONT_SEMIBOLD, 7.2)
    canvas.drawCentredString(35 * mm, PAGE_HEIGHT - 24.25 * mm, "GAP ANALYSIS")
    canvas.restoreState()


def draw_body_page(canvas, doc) -> None:
    page_number = canvas.getPageNumber()
    canvas.saveState()

    canvas.setFillColor(AMBER)
    canvas.rect(0, PAGE_HEIGHT - 4, PAGE_WIDTH, 4, fill=1, stroke=0)

    canvas.setFont(FONT_MEDIUM, 9)
    canvas.setFillColor(MUTED)
    canvas.drawRightString(
        PAGE_WIDTH - BODY_RIGHT,
        PAGE_HEIGHT - 11 * mm,
        rtl_visual("تقرير الفجوة - رؤية Mini ERP الشاملة مقابل النظام الفعلي"),
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


class GapReportDocTemplate(BaseDocTemplate):
    def __init__(self, filename: str) -> None:
        super().__init__(
            filename,
            pagesize=A4,
            leftMargin=BODY_LEFT,
            rightMargin=BODY_RIGHT,
            topMargin=BODY_TOP,
            bottomMargin=BODY_BOTTOM,
            title="Mini ERP Gap Analysis - Arabic",
            author="Mini ERP",
            subject="Vision vs actual system coverage report",
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


def status_badge(text: str) -> Table:
    border, bg = STATUS_COLORS[text]
    cell = Table(
        [[RTLText(text, font_name=FONT_SEMIBOLD, font_size=9.4, leading=13, color=border)]],
        colWidths=[26 * mm],
    )
    cell.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), bg),
                ("BOX", (0, 0), (-1, -1), 0.7, border),
                ("LEFTPADDING", (0, 0), (-1, -1), 5),
                ("RIGHTPADDING", (0, 0), (-1, -1), 5),
                ("TOPPADDING", (0, 0), (-1, -1), 4),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
                ("ALIGN", (0, 0), (-1, -1), "CENTER"),
            ]
        )
    )
    return cell


def coverage_table(rows: Sequence[tuple[int, str, str, str]]) -> LongTable:
    header = [
        RTLText("#", font_name=FONT_SEMIBOLD, font_size=10, color=WHITE),
        RTLText("الموديول في الرؤية", font_name=FONT_SEMIBOLD, font_size=10, color=WHITE),
        RTLText("الحالة", font_name=FONT_SEMIBOLD, font_size=10, color=WHITE),
        RTLText("الشاهد من النظام الفعلي", font_name=FONT_SEMIBOLD, font_size=10, color=WHITE),
    ]
    data: list[list[object]] = [header]
    for number, title, status, note in rows:
        data.append(
            [
                Paragraph(
                    str(number),
                    ParagraphStyle(
                        "GapRowNumber",
                        fontName=FONT_BOLD,
                        fontSize=10,
                        leading=15,
                        textColor=NAVY,
                        alignment=TA_CENTER,
                    ),
                ),
                RTLText(title, font_name=FONT_SEMIBOLD, font_size=10, leading=15, color=NAVY),
                status_badge(status),
                RTLText(note, font_size=9.3, leading=14, color=SLATE),
            ]
        )

    table = LongTable(
        data,
        colWidths=[9 * mm, 38 * mm, 28 * mm, CONTENT_WIDTH - 75 * mm],
        repeatRows=1,
        hAlign="CENTER",
        splitByRow=1,
        splitInRow=0,
    )
    style = [
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("BOX", (0, 0), (-1, -1), 0.6, BORDER),
        ("INNERGRID", (0, 0), (-1, -1), 0.35, BORDER),
        ("LEFTPADDING", (0, 0), (-1, -1), 7),
        ("RIGHTPADDING", (0, 0), (-1, -1), 7),
        ("TOPPADDING", (0, 0), (-1, -1), 6.5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 6.5),
    ]
    for row in range(1, len(data)):
        if row % 2 == 0:
            style.append(("BACKGROUND", (0, row), (0, row), PALE))
            style.append(("BACKGROUND", (1, row), (1, row), PALE))
            style.append(("BACKGROUND", (3, row), (3, row), PALE))
    table.setStyle(TableStyle(style))
    return table


def summary_stat_table(rows: Sequence[tuple[str, str, colors.Color, colors.Color]]) -> Table:
    cells = []
    for count, label, border, bg in rows:
        content = [
            Paragraph(
                count,
                ParagraphStyle(
                    "SummaryStat",
                    fontName=FONT_BOLD,
                    fontSize=24,
                    leading=28,
                    textColor=border,
                    alignment=TA_CENTER,
                ),
            ),
            Spacer(1, 3),
            RTLText(label, font_name=FONT_MEDIUM, font_size=10, leading=15, color=SLATE),
        ]
        cell_table = Table([[content]], colWidths=[(CONTENT_WIDTH - 14) / 3])
        cell_table.setStyle(
            TableStyle(
                [
                    ("BACKGROUND", (0, 0), (-1, -1), bg),
                    ("BOX", (0, 0), (-1, -1), 0.8, border),
                    ("ALIGN", (0, 0), (-1, -1), "CENTER"),
                    ("TOPPADDING", (0, 0), (-1, -1), 12),
                    ("BOTTOMPADDING", (0, 0), (-1, -1), 12),
                ]
            )
        )
        cells.append(cell_table)

    wrapper = Table([cells], colWidths=[(CONTENT_WIDTH - 14) / 3] * 3, hAlign="CENTER")
    wrapper.setStyle(
        TableStyle(
            [
                ("LEFTPADDING", (0, 0), (-1, -1), 0),
                ("RIGHTPADDING", (0, 0), (-1, -1), 3.5),
                ("TOPPADDING", (0, 0), (-1, -1), 0),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
            ]
        )
    )
    return wrapper


def gap_item(title: str, priority_label: str, priority_color: colors.Color, body_lines: Sequence[str]) -> list:
    header_row = Table(
        [
            [
                RTLText(title, font_name=FONT_SEMIBOLD, font_size=12.3, leading=18, color=NAVY),
                RTLText(priority_label, font_name=FONT_SEMIBOLD, font_size=9.2, leading=13, color=WHITE),
            ]
        ],
        colWidths=[CONTENT_WIDTH - 32 * mm, 32 * mm],
    )
    header_row.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (1, 0), (1, 0), priority_color),
                ("ALIGN", (1, 0), (1, 0), "CENTER"),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                ("LEFTPADDING", (1, 0), (1, 0), 4),
                ("RIGHTPADDING", (1, 0), (1, 0), 4),
                ("TOPPADDING", (0, 0), (-1, -1), 5),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
            ]
        )
    )
    out: list = [header_row, Spacer(1, 5)]
    for line in body_lines:
        out.extend([paragraph(line, size=10.3, color=SLATE), Spacer(1, 3)])
    out.append(Spacer(1, 9))
    return out


def roadmap_table(rows: Sequence[tuple[str, str, str]]) -> LongTable:
    header = [
        RTLText("المرحلة", font_name=FONT_SEMIBOLD, font_size=10, color=WHITE),
        RTLText("المحتوى", font_name=FONT_SEMIBOLD, font_size=10, color=WHITE),
        RTLText("المبرر", font_name=FONT_SEMIBOLD, font_size=10, color=WHITE),
    ]
    data: list[list[object]] = [header]
    for phase, content, reason in rows:
        data.append(
            [
                RTLText(phase, font_name=FONT_SEMIBOLD, font_size=10, leading=15, color=BLUE_DARK),
                RTLText(content, font_size=9.6, leading=14.8, color=NAVY),
                RTLText(reason, font_size=9.3, leading=14.3, color=SLATE),
            ]
        )

    table = LongTable(
        data,
        colWidths=[22 * mm, 70 * mm, CONTENT_WIDTH - 92 * mm],
        repeatRows=1,
        hAlign="CENTER",
        splitByRow=1,
        splitInRow=0,
    )
    style = [
        ("BACKGROUND", (0, 0), (-1, 0), INDIGO),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("BOX", (0, 0), (-1, -1), 0.6, BORDER),
        ("INNERGRID", (0, 0), (-1, -1), 0.35, BORDER),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("RIGHTPADDING", (0, 0), (-1, -1), 8),
        ("TOPPADDING", (0, 0), (-1, -1), 8),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
    ]
    for row in range(1, len(data)):
        if row % 2 == 0:
            style.append(("BACKGROUND", (0, row), (-1, row), PALE))
    table.setStyle(TableStyle(style))
    return table


COVERAGE_ROWS: list[tuple[int, str, str, str]] = [
    (1, "Accounting & GL", STATUS_FULL, "دليل حسابات، قيود متوازنة، أستاذ، ميزان مراجعة، فترات وإغلاق، ترحيل تلقائي من كل المستندات."),
    (2, "Financial Statements", STATUS_PARTIAL, "قائمة الدخل والميزانية والتدفقات والنسب موجودة. الناقص: قائمة التغير في حقوق الملكية."),
    (3, "Sales", STATUS_PARTIAL, "دورة كاملة من أمر البيع للفاتورة والمرتجع. الناقص: عروض الأسعار قبل أمر البيع."),
    (4, "Purchasing", STATUS_PARTIAL, "دورة كاملة من أمر الشراء للفاتورة. الناقص: طلبات الشراء ودفعات مقدمة مستقلة للمورد."),
    (5, "Inventory", STATUS_PARTIAL, "مخازن وتحويلات وجرد وتقييم Weighted Average فعلي. الناقص: Barcode وحد إعادة الطلب."),
    (6, "Tools & Equipment", STATUS_MISSING, "لا يوجد موديول عهدة داخلية؛ الإيجار للعميل الخارجي فقط، والأصول الثابتة للرسملة المحاسبية فقط."),
    (7, "Rental Management", STATUS_FULL, "عناصر، عقود، تسليم، عودة وفحص، فواتير بتأمين وتأخير وتلف، منع تعارض الحجز."),
    (8, "Customers & AR", STATUS_PARTIAL, "فواتير وقبض وتخصيص وأعمار ديون كاملة. الناقص: دفعة مقدمة من عميل كبند مستقل."),
    (9, "Suppliers & AP", STATUS_PARTIAL, "نفس تغطية العملاء على الموردين. الناقص: دفعة مقدمة للمورد كبند مستقل."),
    (10, "Cash Management", STATUS_FULL, "خزائن متعددة، قبض وصرف وتحويلات، دفتر خزينة. العهدة النثرية تُدار كحساب خزينة عادي."),
    (11, "Banks", STATUS_FULL, "حسابات بنكية متعددة، تحويلات، تسوية بنكية بفرق صفر إلزامي، دفتر بنك."),
    (12, "Cheques", STATUS_FULL, "شيكات واردة وصادرة بكل الحالات (استلام، إيداع، تحصيل، ارتداد، إرجاع)، سجل شيكات."),
    (13, "Expenses", STATUS_FULL, "مصروف نقدي أو بنكي أو على مورد، فئات، ربط بالفرع والمشروع ومركز التكلفة."),
    (14, "Prepaid & Accrued Expenses", STATUS_FULL, "جدولة وترحيل دوري لكل قسط بحماية من التكرار حتى الاكتمال."),
    (15, "Fixed Assets", STATUS_FULL, "فئات ومواقع، رسملة، جدول إهلاك، جولات دورية، نقل، استبعاد بربح أو خسارة."),
    (16, "Payroll", STATUS_PARTIAL, "موظفون ومكونات وكشوف مرحلة تلقائيًا. الناقص: سلف/قروض موظفين وتقرير مرتبات مستقل."),
    (17, "Taxes", STATUS_PARTIAL, "VAT مكتملة بالكامل (أكواد، فترات، إقرار، مطابقة). الناقص: WHT مستبعدة بقرار أونر موثق."),
    (18, "Partners & Equity", STATUS_MISSING, "حقوق الملكية نوع حساب عادي فقط في الدليل والميزانية؛ لا يوجد تتبع مستقل لكل شريك."),
    (19, "Projects & Cost Centers", STATUS_FULL, "أبعاد تحليلية في المستندات التشغيلية، تقارير ربحية المشاريع وفعليات مراكز التكلفة."),
    (20, "Budgeting & Forecasting", STATUS_PARTIAL, "موازنة مقابل فعلي بالانحراف موجودة. الناقص: أي تنبؤ استشرافي (مبيعات/مصروف/تدفق/ربح)."),
    (21, "Recurring Transactions", STATUS_MISSING, "لا يوجد محرك عام؛ فقط جداول ضيقة للمصروف المقدم والمستحق وجولات الإهلاك."),
    (22, "Reports", STATUS_PARTIAL, "38 شاشة تقارير فعلية. الناقص: تقرير مرتبات مستقل، كشوف شركاء، قائمة تغير حقوق ملكية."),
    (23, "Dashboard", STATUS_PARTIAL, "لوحة صحة نظام فقط (عدادات وحالات فنية)؛ لا تعرض أي مؤشر مالي رغم توفر الأرقام في التقارير."),
    (24, "Users & Permissions", STATUS_FULL, "أدوار جاهزة وأدوار مخصصة، صلاحيات دقيقة على مستوى الشاشة والإجراء والحالة."),
    (25, "Audit Trail", STATUS_FULL, "سجل تدقيق مستقل يتتبع العمليات الحساسة، مدعوم بدورة حالة تحفظ الأثر التاريخي."),
    (26, "Document Numbering", STATUS_FULL, "شاشة ترقيم مستقلة تضبط البادئة والسنة وعدد الخانات لكل نوع مستند فعليًا."),
]


def build_story() -> list:
    builder = StoryBuilder()
    story = builder.story

    full_count = sum(1 for row in COVERAGE_ROWS if row[2] == STATUS_FULL)
    partial_count = sum(1 for row in COVERAGE_ROWS if row[2] == STATUS_PARTIAL)
    missing_count = sum(1 for row in COVERAGE_ROWS if row[2] == STATUS_MISSING)

    # Cover page
    story.extend(
        [
            Spacer(1, 24 * mm),
            Paragraph(
                "MINI ERP",
                ParagraphStyle(
                    "CoverBrand",
                    fontName=FONT_SEMIBOLD,
                    fontSize=13,
                    leading=18,
                    textColor=colors.HexColor("#FCD34D"),
                    alignment=TA_RIGHT,
                ),
            ),
            Spacer(1, 3 * mm),
            RTLText(
                "تقرير مطابقة الرؤية الشاملة بالنظام الفعلي",
                font_name=FONT_BOLD,
                font_size=30,
                leading=40,
                color=WHITE,
            ),
            Spacer(1, 5 * mm),
            RTLText(
                "26 موديولًا من الرؤية المرسلة مقابل 135 شاشة فعلية، وخطة إكمال الفجوات",
                font_name=FONT_SEMIBOLD,
                font_size=15,
                leading=23,
                color=colors.HexColor("#FDE68A"),
            ),
            Spacer(1, 16 * mm),
        ]
    )

    stats = summary_stat_table(
        [
            (str(full_count), "موديول مطابق بالكامل", EMERALD, colors.HexColor("#052E22")),
            (str(partial_count), "موديول مطابق جزئيًا", AMBER, colors.HexColor("#3A2506")),
            (str(missing_count), "موديول غير موجود", RED, colors.HexColor("#3B0D0A")),
        ]
    )
    story.extend(
        [
            stats,
            Spacer(1, 20 * mm),
            RTLText(
                f"الإصدار {REPORT_VERSION} | {REPORT_DATE_AR}",
                font_name=FONT_SEMIBOLD,
                font_size=12.5,
                leading=19,
                color=colors.HexColor("#FDE68A"),
            ),
            Spacer(1, 3 * mm),
            RTLText(
                "مبني على دليل النظام الفعلي (135 شاشة) وفحص مباشر لكود تطبيق Laravel",
                font_size=11.2,
                leading=17,
                color=colors.HexColor("#94A3B8"),
            ),
            NextPageTemplate("Body"),
            PageBreak(),
        ]
    )

    builder.heading("محتويات التقرير")
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
                "طريقة القراءة",
                "الملخص التنفيذي أولًا لمعرفة الصورة الكاملة، ثم جدول المطابقة الكامل للـ26 موديولًا، ثم تفصيل كل فجوة بالأولوية وخطة الإكمال المقترحة في نهاية التقرير.",
            ),
            Spacer(1, 10),
            toc,
        ]
    )

    # 1. Methodology
    builder.heading("منهجية المقارنة", page_break=True)
    builder.p(
        "تم فحص كل موديول من الـ26 موديولًا الواردة في الرؤية المرسلة، ومطابقته بشاشات ومسارات النظام الفعلي كما في دليل الاستخدام الحالي. عند الشك، تم فحص الكود مباشرة (المسارات والموديلات والميجريشنز وتقارير قرارات الأونر) بدل الاعتماد على الوثائق فقط، لضمان أن كل حكم مدعوم بدليل فعلي."
    )
    story.append(
        cards(
            [
                ("مطابق بالكامل", "كل ما ورد في وصف الموديول له شاشة أو مسار فعلي يعمل في النظام."),
                ("مطابق جزئيًا", "جوهر الموديول موجود ويعمل، لكن عنصرًا أو أكثر مما وصفته الرؤية غير موجود حاليًا."),
                ("غير موجود", "لا يوجد موديول أو شاشة مستقلة تغطي هذا الجزء، رغم احتمال تقاطع جزئي مع موديول آخر."),
            ],
            columns=1,
        )
    )

    # 2. Executive summary
    builder.heading("الملخص التنفيذي", page_break=True)
    builder.p(
        "نسبة التغطية التفصيلية تتجاوز 85% من الرؤية المكتوبة. النظام أقوى مما توحي به الرسالة في نواحٍ معينة (38 شاشة تقارير جاهزة، تسوية بنكية كاملة، دورتا إيجارات وأصول ثابتة مكتملتان). لكن توجد فجوات محددة يجب معرفتها قبل اعتبار النظام مطابقًا بالكامل لما ورد في الرؤية:"
    )
    story.append(
        callout(
            "أهم فجوة غير متوقعة: لوحة التحكم لا تعرض أي مؤشر مالي",
            "لوحة التحكم الحالية (/dashboard) تعرض فقط عدادات صحة النظام (عدد الحسابات، القيود المرحلة، حركات الأستاذ، العملات، العملاء، الموردين) وحالات فنية (توازن الأستاذ، الفترات المفتوحة، القيود المعلقة، آخر ترحيل، الفروع النشطة). لا توجد إيرادات أو مصروفات أو ربح أو رصيد خزينة أو بنك أو مديونيات أو قيمة مخزون أو إيجارات نشطة أو فواتير متأخرة — رغم أن كل هذه الأرقام محسوبة بالفعل داخل تقارير أخرى في النظام.",
            background=RED_PALE,
            border=RED,
        )
    )
    story.append(Spacer(1, 8))
    builder.bullets(
        [
            "العُدّة والمعدات كموديول عهدة داخلية مستقل غير موجودة. الأقرب لها عناصر الإيجار (للعميل الخارجي) والأصول الثابتة (للرسملة المحاسبية)، وكلاهما غير مصمم لعهدة يومية بسيطة.",
            "لا يوجد موديول شركاء وحقوق ملكية. حقوق الملكية موجودة فقط كنوع حساب عادي في الدليل وتظهر كبند في الميزانية، دون تتبع مستقل لكل شريك.",
            "ضريبة الخصم والإضافة (WHT) مستبعدة عمدًا من نطاق النظام حتى الآن، بقرار أونر موثق في مرحلة الضرائب، وليست نسيانًا.",
        ]
    )

    # 3. Coverage table
    builder.heading("جدول المطابقة الكامل (26 موديول)", page_break=True)
    builder.p("مرتب بنفس ترقيم الرؤية المرسلة من 1 إلى 26.")
    story.append(coverage_table(COVERAGE_ROWS))

    # 4. High priority gaps
    builder.heading("تفصيل الفجوات - أولوية عالية", page_break=True)
    builder.p("أثر مباشر على الاستخدام اليومي أو التزام قانوني، وجهد بناء منضبط نسبيًا.")
    for block in [
        gap_item(
            "1. لوحة تحكم مالية حقيقية",
            "أولوية عالية",
            RED,
            [
                "المشكلة: لوحة التحكم الحالية لوحة صحة نظام، بينما الرؤية تطلب لوحة مؤشرات أداء مالي وتشغيلي.",
                "لماذا أولوية عالية: كل الأرقام المطلوبة (الإيراد، المصروف، الربح، السيولة، المديونيات، قيمة المخزون، الإيجارات النشطة، الفواتير المتأخرة) محسوبة بالفعل داخل تقارير قائمة الدخل والميزانية ودفتر الخزينة والبنك وأعمار الديون وأرصدة المخزون وتشغيل الإيجارات. المطلوب طبقة تجميع وعرض فوق بيانات موجودة، وليس منطقًا محاسبيًا جديدًا.",
                "المقترح: إعادة بناء شاشة لوحة التحكم لتستدعي نفس استعلامات التقارير الحالية وتعرضها كبطاقات مؤشرات، مقيدة بنفس صلاحية view_financials المستخدمة في التقارير.",
            ],
        ),
        gap_item(
            "2. موديول العُدّة والمعدات",
            "أولوية عالية",
            RED,
            [
                "المشكلة: لا يوجد تتبع عهدة داخلية للأدوات والمعدات مستقل عن الإيجار والأصول الثابتة.",
                "لماذا أولوية عالية: ورد بتفصيل صريح في الرؤية (رقم تسلسلي، حالة، تسليم واستلام، عهدة موظف وموقع، نقل بين مواقع، تالف أو مفقود أو تحت الصيانة) — استخدام يومي مختلف جوهريًا عن الإيجار (بمقابل مالي لعميل) والأصل الثابت (رسملة وإهلاك محاسبي).",
                "المقترح: موديول جديد مستقل بنموذج: صنف العُدّة، الرقم التسلسلي، الكمية، الحالة، سجل حركة (تسليم/استرجاع/نقل)، بدون أثر محاسبي إلزامي إلا عند الربط اللاحق بأصل ثابت أو عملية إيجار.",
            ],
        ),
        gap_item(
            "3. ضريبة الخصم والإضافة (WHT)",
            "أولوية عالية",
            RED,
            [
                "المشكلة: مستبعدة عمدًا من النطاق حتى الآن بقرار أونر موثق أثناء بناء مرحلة الضرائب.",
                "لماذا أولوية عالية: للاستقطاع الضريبي أثر قانوني مباشر على مدفوعات الموردين في السياق المصري؛ تأجيله يعني إدارة جزء من الالتزام الفعلي يدويًا خارج النظام.",
                "المقترح: مرحلة تُبنى فوق بنية أكواد ونسب الضرائب الحالية (نفس نمط VAT)، بإضافة نوع ضريبة WHT وحسابات استقطاع مستقلة وتقرير التزامات، مع مراجعة النسب وفق القوانين المصرية الحالية قبل البناء.",
            ],
        ),
    ]:
        story.extend(block)

    # 5. Medium priority
    builder.heading("تفصيل الفجوات - أولوية متوسطة", page_break=True)
    builder.p("تكمل الدورة المحاسبية والتشغيلية القائمة دون تغيير جوهري فيما هو موجود بالفعل.")
    for block in [
        gap_item(
            "4. موديول الشركاء وحقوق الملكية",
            "أولوية متوسطة",
            AMBER,
            [
                "لا يوجد حاليًا تتبع لكل شريك على حدة (مساهمة، سحب، حساب جارٍ، قرض شريك، توزيع أرباح). الموجود فقط حقوق الملكية كبند إجمالي في الميزانية.",
                "إذا كانت الشركة مملوكة لأكثر من شريك ويحتاج كل شريك متابعة مستقلة لحصته، فهذه فجوة مهمة تستحق موديولًا كاملًا بحسابات فرعية لكل شريك مرتبطة بدليل الحسابات.",
            ],
        ),
        gap_item(
            "5. عروض الأسعار قبل أمر البيع",
            "أولوية متوسطة",
            AMBER,
            [
                "دورة المبيعات تبدأ حاليًا من أمر البيع مباشرة. إضافة شاشة عرض سعر (مسودة ← إرسال ← قبول أو رفض ← تحويل لأمر بيع) تكمل الدورة التجارية قبل الالتزام التعاقدي، بنفس نمط باقي مستندات المبيعات.",
            ],
        ),
        gap_item(
            "6. طلبات الشراء قبل أمر الشراء",
            "أولوية متوسطة",
            AMBER,
            [
                "نفس الفكرة على جانب المشتريات: طلب داخلي يمر باعتماد قبل تحويله لأمر شراء فعلي، مفيد للضبط الداخلي على من يطلب الشراء قبل الالتزام بمورد.",
            ],
        ),
        gap_item(
            "7. سلف وقروض الموظفين وتقرير مرتبات مستقل",
            "أولوية متوسطة",
            AMBER,
            [
                "لا يوجد نموذج سلفة أو قرض موظف له رصيد متبقٍ يُتابع عبر أكثر من كشف؛ الخصم حاليًا مكوّن راتب عام فقط.",
                "لا توجد شاشة تقرير مرتبات تحليلية مستقلة تحت مركز التقارير؛ الموجود فقط شاشة تشغيل الكشوف نفسها.",
            ],
        ),
    ]:
        story.extend(block)

    # 6. Low priority
    builder.heading("تفصيل الفجوات - أولوية منخفضة", page_break=True)
    builder.p("تحسينات وتكامل إضافي تبني فوق الأساس القائم.")
    for block in [
        gap_item(
            "8. محرك معاملات متكررة عام",
            "أولوية منخفضة",
            BLUE,
            [
                "الموجود حاليًا جداول خاصة بسياق ضيق فقط (مصروف مقدم، مصروف مستحق، إهلاك دوري). محرك عام لأي مستند متكرر بجدول زمني يقلل تكرار الإدخال اليدوي لبنود مثل الإيجار والاشتراكات والتأمين.",
            ],
        ),
        gap_item(
            "9. التنبؤ المالي",
            "أولوية منخفضة",
            BLUE,
            [
                "الموجود فقط موازنة مقابل فعلي بمقارنة أرقام مُدخلة يدويًا. لا توجد آلية تنبؤ حسابي بالإيراد أو المصروف أو التدفق النقدي أو الربح بناءً على بيانات تاريخية.",
            ],
        ),
        gap_item(
            "10. قائمة التغير في حقوق الملكية",
            "أولوية منخفضة",
            BLUE,
            [
                "تقرير مستقل غير موجود؛ الميزانية تعرض رصيد حقوق الملكية في تاريخ معين دون حركة تفصيلية عبر الفترة.",
            ],
        ),
        gap_item(
            "11. الدفعات المقدمة كبند مستقل",
            "أولوية منخفضة",
            BLUE,
            [
                "يمكن تسجيل مبلغ مقدم حاليًا كسند قبض أو صرف غير مخصص لفاتورة، لكن لا توجد شاشة أو تقرير يُظهره تحديدًا كدفعة مقدمة منفصلة عن باقي الأرصدة غير المخصصة.",
            ],
        ),
        gap_item(
            "12. الباركود وحد إعادة الطلب",
            "أولوية منخفضة",
            BLUE,
            [
                "لا يوجد حقل Barcode في بيانات الصنف، ولا حد أدنى أو نقطة إعادة طلب لإصدار تنبيه تلقائي عند اقتراب نفاد المخزون.",
            ],
        ),
    ]:
        story.extend(block)

    # 7. Not actually gaps
    builder.heading("توضيحات مهمة - أشياء قد تبدو فجوة وليست كذلك", page_break=True)
    story.append(
        cards(
            [
                ("تقييم المخزون", "النظام يطبق فعليًا المتوسط المرجح المتحرك (Moving Weighted Average) — أحد الخيارين المطلوبين بالضبط، وقرارها موثق رسميًا من الأونر."),
                ("حقوق الملكية", "موجودة كنوع حساب طبيعي وتظهر في الميزانية والأرباح المحتجزة ضمنها؛ الفجوة الحقيقية غياب موديول متابعة كل شريك على حدة فقط."),
                ("العهدة النثرية", "تُدار وظيفيًا كحساب خزينة عادي داخل موديول النقدية، وهذا كافٍ عمليًا دون حاجة لموديول منفصل."),
                ("الأرصدة الافتتاحية النقدية والبنكية", "تتم من شاشة الأرصدة الافتتاحية الموحدة في المحاسبة، وهذا نمط تصميم موحّد متعمد وليس نقصًا."),
            ]
        )
    )

    # 8. Roadmap
    builder.heading("خطة تنفيذ مقترحة", page_break=True)
    builder.p("ترتيب مقترح حسب الأثر والاعتماديات بين الفجوات، وليس التزامًا زمنيًا. يحتاج كل بند تقدير جهد تفصيليًا منفصلًا قبل الالتزام بجدول زمني فعلي.")
    story.append(
        roadmap_table(
            [
                ("المرحلة أ", "لوحة تحكم مالية + موديول العُدّة والمعدات", "أعلى أثر مباشر؛ لوحة التحكم تعتمد على بيانات موجودة بالفعل، والعُدّة والمعدات موديول محدد النطاق وواضح المتطلبات."),
                ("المرحلة ب", "ضريبة الخصم والإضافة (WHT) + موديول الشركاء وحقوق الملكية", "التزام قانوني وضريبي من جهة، واكتمال الصورة المالية الكاملة من جهة أخرى؛ يحتاجان تصميمًا محاسبيًا دقيقًا قبل البناء."),
                ("المرحلة ج", "عروض الأسعار + طلبات الشراء + سلف وقروض الموظفين وتقرير مرتبات مستقل", "تكمل دورتي المبيعات والمشتريات والمرتبات دون تغيير جوهري فيما هو موجود."),
                ("المرحلة د", "محرك المعاملات المتكررة + التنبؤ المالي + قائمة التغير في حقوق الملكية + تحسينات المخزون + الدفعات المقدمة", "تحسينات تراكمية تبني فوق الأساس المكتمل، وتستفيد من موديول الشركاء والموازنات."),
            ]
        )
    )

    # 9. Conclusion
    builder.heading("الخلاصة", page_break=True)
    builder.p(
        "النظام الفعلي يغطي الجزء الأكبر من الرؤية الشاملة بدقة عالية: كل الدورة المحاسبية الأساسية (قيد ← أستاذ ← ميزان ← قوائم مالية) تعمل تلقائيًا خلف كل عملية تشغيلية تقريبًا، تمامًا كما هو مطلوب. المتبقي 3 موديولات غائبة بالكامل (العُدّة والمعدات، الشركاء وحقوق الملكية، المعاملات المتكررة العامة) و11 موديولًا يحتاج إضافات محددة وواضحة وليس إعادة بناء."
    )
    story.append(
        callout(
            "أول خطوة موصى بها",
            "لوحة التحكم المالية، لأن أثرها كبير على الاستخدام اليومي وجهد إغلاقها محدود نسبيًا مقارنة ببقية الفجوات — البيانات كلها موجودة بالفعل داخل التقارير الحالية.",
            background=EMERALD_PALE,
            border=EMERALD,
        )
    )

    return story


def main() -> None:
    register_fonts()
    OUTPUT_PATH.parent.mkdir(parents=True, exist_ok=True)
    document = GapReportDocTemplate(str(OUTPUT_PATH))
    story = build_story()
    document.multiBuild(story)
    print(OUTPUT_PATH)


if __name__ == "__main__":
    main()
