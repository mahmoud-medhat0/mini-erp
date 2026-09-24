from __future__ import annotations

import sys
from pathlib import Path

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

from generate_system_guide_pdf import (
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
    PAGE_HEIGHT,
    PAGE_WIDTH,
    PALE,
    RTLHeading,
    RTLText,
    SLATE,
    WHITE,
    callout,
    clean_text,
    register_fonts,
    rtl_visual,
)

ROOT = Path(__file__).resolve().parents[2]
OUTPUT_PATH = ROOT / "output" / "pdf" / "mini-erp-user-operational-guide-ar.pdf"

GUIDE_VERSION = "1.0"
GUIDE_DATE_AR = "24 سبتمبر 2026"


def draw_cover_page(canvas, doc) -> None:
    del doc
    canvas.saveState()
    # Dark Navy Header / Cover Background
    canvas.setFillColor(NAVY)
    canvas.rect(0, 0, PAGE_WIDTH, PAGE_HEIGHT, fill=1, stroke=0)

    # Decorative Circles & Badges
    canvas.setFillColor(colors.HexColor("#1E3A8A"))
    canvas.circle(PAGE_WIDTH - 15 * mm, PAGE_HEIGHT - 18 * mm, 60 * mm, fill=1, stroke=0)
    canvas.setFillColor(colors.HexColor("#0D9488"))
    canvas.circle(10 * mm, 15 * mm, 45 * mm, fill=1, stroke=0)

    # Pill Badge
    canvas.setFillColor(colors.HexColor("#0D9488"))
    canvas.roundRect(18 * mm, PAGE_HEIGHT - 28 * mm, 52 * mm, 7 * mm, 3.5 * mm, fill=1, stroke=0)
    canvas.setFillColor(WHITE)
    canvas.setFont(FONT_SEMIBOLD, 8)
    canvas.drawCentredString(44 * mm, PAGE_HEIGHT - 23.5 * mm, "MINI ERP - OPERATIONAL GUIDE")
    canvas.restoreState()


def draw_body_page(canvas, doc) -> None:
    page_number = canvas.getPageNumber()
    canvas.saveState()

    # Top accent bar
    canvas.setFillColor(colors.HexColor("#0D9488"))
    canvas.rect(0, PAGE_HEIGHT - 4, PAGE_WIDTH, 4, fill=1, stroke=0)

    # Top Header
    canvas.setFont(FONT_MEDIUM, 9)
    canvas.setFillColor(MUTED)
    canvas.drawRightString(
        PAGE_WIDTH - BODY_RIGHT,
        PAGE_HEIGHT - 11 * mm,
        rtl_visual("الدليل التشغيلي المنظومي | إدارة الفروع والمخازن والموردين والمبيعات الداخلية"),
    )
    canvas.setStrokeColor(BORDER)
    canvas.setLineWidth(0.5)
    canvas.line(BODY_LEFT, PAGE_HEIGHT - 13 * mm, PAGE_WIDTH - BODY_RIGHT, PAGE_HEIGHT - 13 * mm)

    # Footer
    canvas.line(BODY_LEFT, 13 * mm, PAGE_WIDTH - BODY_RIGHT, 13 * mm)
    canvas.drawRightString(
        PAGE_WIDTH - BODY_RIGHT,
        8.5 * mm,
        rtl_visual("نظام Mini ERP - جميع الحقوق محفوظة"),
    )
    canvas.drawString(BODY_LEFT, 8.5 * mm, rtl_visual(f"صفحة {page_number}"))
    canvas.restoreState()


def section_card(number: str, title: str, subtitle: str) -> Table:
    title_p = RTLText(f"{number}. {title}", font_name=FONT_BOLD, font_size=12, color=NAVY)
    sub_p = RTLText(subtitle, font_name=FONT_REGULAR, font_size=9, color=SLATE)
    tbl = Table([[title_p], [sub_p]], colWidths=[CONTENT_WIDTH])
    tbl.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), colors.HexColor("#F0FDF4")),
                ("LEFTPADDING", (0, 0), (-1, -1), 10),
                ("RIGHTPADDING", (0, 0), (-1, -1), 10),
                ("TOPPADDING", (0, 0), (-1, -1), 5),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
                ("LINEBEFORE", (0, 0), (0, -1), 4, colors.HexColor("#0D9488")),
                ("BOX", (0, 0), (-1, -1), 0.5, colors.HexColor("#CCFBF1")),
            ]
        )
    )
    return tbl


def compact_callout(title: str, body: str, *, background: colors.Color, border: colors.Color) -> Table:
    content = [
        RTLText(title, font_name=FONT_SEMIBOLD, font_size=10, leading=14, color=NAVY),
        Spacer(1, 2),
        RTLText(body, font_size=8.5, leading=12.5, color=SLATE),
    ]
    table = Table([[content]], colWidths=[CONTENT_WIDTH])
    table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), background),
                ("BOX", (0, 0), (-1, -1), 0.6, border),
                ("LINEAFTER", (0, 0), (0, 0), 3.5, border),
                ("LEFTPADDING", (0, 0), (-1, -1), 8),
                ("RIGHTPADDING", (0, 0), (-1, -1), 8),
                ("TOPPADDING", (0, 0), (-1, -1), 4),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
            ]
        )
    )
    return table


def make_route_table(rows: list[list[str]]) -> Table:
    col_w = [55 * mm, 95 * mm, 32 * mm]
    
    table_data = []
    h0 = RTLText("المسار بالمنظومة", font_name=FONT_BOLD, font_size=8.5, color=WHITE)
    h1 = RTLText("الوظيفة التشغيلية والهدف", font_name=FONT_BOLD, font_size=8.5, color=WHITE)
    h2 = RTLText("المرحلة", font_name=FONT_BOLD, font_size=8.5, color=WHITE)
    table_data.append([h0, h1, h2])

    for r in rows:
        c0 = RTLText(r[0], font_name=FONT_MEDIUM, font_size=8, color=BLUE_DARK)
        c1 = RTLText(r[1], font_name=FONT_REGULAR, font_size=8, color=SLATE)
        c2 = RTLText(r[2], font_name=FONT_SEMIBOLD, font_size=8, color=NAVY)
        table_data.append([c0, c1, c2])

    t = Table(table_data, colWidths=col_w)
    ts = [
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("GRID", (0, 0), (-1, -1), 0.5, BORDER),
        ("TOPPADDING", (0, 0), (-1, -1), 3),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 3),
        ("LEFTPADDING", (0, 0), (-1, -1), 5),
        ("RIGHTPADDING", (0, 0), (-1, -1), 5),
    ]
    for idx in range(1, len(table_data)):
        if idx % 2 == 0:
            ts.append(("BACKGROUND", (0, idx), (-1, idx), PALE))
    t.setStyle(TableStyle(ts))
    return t


def build_pdf() -> Path:
    register_fonts()
    OUTPUT_PATH.parent.mkdir(parents=True, exist_ok=True)

    doc = BaseDocTemplate(
        str(OUTPUT_PATH),
        pagesize=A4,
        leftMargin=BODY_LEFT,
        rightMargin=BODY_RIGHT,
        topMargin=BODY_TOP,
        bottomMargin=BODY_BOTTOM,
    )

    frame_cover = Frame(
        BODY_LEFT,
        BODY_BOTTOM,
        CONTENT_WIDTH,
        PAGE_HEIGHT - BODY_TOP - BODY_BOTTOM,
        id="cover_frame",
        topPadding=35 * mm,
    )
    frame_body = Frame(
        BODY_LEFT,
        BODY_BOTTOM,
        CONTENT_WIDTH,
        PAGE_HEIGHT - BODY_TOP - BODY_BOTTOM,
        id="body_frame",
        topPadding=0,
        bottomPadding=0,
    )

    doc.addPageTemplates(
        [
            PageTemplate(id="Cover", frames=frame_cover, onPage=draw_cover_page),
            PageTemplate(id="Body", frames=frame_body, onPage=draw_body_page),
        ]
    )

    story = []

    # COVER PAGE CONTENT
    story.append(Spacer(1, 15 * mm))
    story.append(
        RTLText(
            "الدليل التشغيلي المنظومي الشامل",
            font_name=FONT_BOLD,
            font_size=24,
            color=WHITE,
            leading=32,
        )
    )
    story.append(Spacer(1, 4 * mm))
    story.append(
        RTLText(
            "دليل خطوة بخطوة لإدارة الفروع الخمسة، المخازن المتعددة، الموردين وحساباتهم، النقل المخزني، ومبيعات الجهات الداخلية بسعر صفر",
            font_name=FONT_REGULAR,
            font_size=12,
            color=colors.HexColor("#94A3B8"),
            leading=18,
        )
    )
    story.append(Spacer(1, 25 * mm))

    # Meta Box on Cover
    meta_data = [
        [
            RTLText("إصدار المنظومة: Mini ERP 15.0", font_name=FONT_MEDIUM, font_size=9.5, color=WHITE),
            RTLText(f"تاريخ الدليل: {GUIDE_DATE_AR}", font_name=FONT_MEDIUM, font_size=9.5, color=WHITE),
        ],
        [
            RTLText("حالة الدليل: معتمد ومفعل تشغيلياً", font_name=FONT_MEDIUM, font_size=9.5, color=colors.HexColor("#6EE7B7")),
            RTLText("النطاق: الفروع، المخازن، المشتريات، المبيعات", font_name=FONT_MEDIUM, font_size=9.5, color=WHITE),
        ],
    ]
    meta_table = Table(meta_data, colWidths=[CONTENT_WIDTH / 2, CONTENT_WIDTH / 2])
    meta_table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), colors.HexColor("#1E293B")),
                ("BOX", (0, 0), (-1, -1), 1, colors.HexColor("#334155")),
                ("TOPPADDING", (0, 0), (-1, -1), 8),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
                ("LEFTPADDING", (0, 0), (-1, -1), 10),
                ("RIGHTPADDING", (0, 0), (-1, -1), 10),
            ]
        )
    )
    story.append(meta_table)

    story.append(NextPageTemplate("Body"))
    story.append(PageBreak())

    # PAGE 1: BODY CONTENT
    story.append(
        RTLHeading("نظرة عامة على الهدف التشغيلي للمنظومة", level=1, bookmark="overview")
    )
    story.append(Spacer(1, 2 * mm))
    story.append(
        RTLText(
            "يهدف هذا الدليل إلى توضيح خطوات التنفيذ المباشرة على سيستم Mini ERP للتعامل مع كافة متطلبات الدورة المستندية والمخزنية والمشتريات والمبيعات وفقاً لاحتياجات المؤسسة التنسيقية الهيكلية التالية:",
            font_size=10,
            color=SLATE,
            leading=15,
        )
    )
    story.append(Spacer(1, 2 * mm))
    story.append(
        RTLText(
            "• إدارة 5 فروع رئيسية (مسمياتهم من المركز الأول إلى المركز الخامس).",
            bullet=True,
            font_size=9.5,
            color=NAVY,
        )
    )
    story.append(
        RTLText(
            "• تخصيص عدة مخازن لكل فرع مع التبعية المباشرة وضبط المواقع الداخلية.",
            bullet=True,
            font_size=9.5,
            color=NAVY,
        )
    )
    story.append(
        RTLText(
            "• إدار كاملة لدورة الموردين المشتريات، استلام البضاعة، تسوية الفواتير والسداد كشوف الحسابات.",
            bullet=True,
            font_size=9.5,
            color=NAVY,
        )
    )
    story.append(
        RTLText(
            "• إدارة التحويلات بين المخازن داخل الفرع الواحد أو بين الفروع عبر دورة موافقات واعتد وصرف واستلام.",
            bullet=True,
            font_size=9.5,
            color=NAVY,
        )
    )
    story.append(
        RTLText(
            "• معالجة مبيعات الجهات الداخلية بدون مقابل (بسعر 0) وتأثيرها المباشر في خصم المخزون وتوثيق حركات الصرف والمبيعات.",
            bullet=True,
            font_size=9.5,
            color=NAVY,
        )
    )
    story.append(Spacer(1, 5 * mm))

    # SECTION 1
    story.append(
        section_card(
            "1",
            "المرحلة الأولى: تهيئة الهيكل المنظمي (الفروع والمخازن الأصيلة)",
            "خطوات إنشاء الفروع الخمسة وتنسيب المخازن المتعددة لكل فرع وتعريف كود الأصناف",
        )
    )
    story.append(Spacer(1, 3 * mm))
    story.append(
        RTLText(
            "1.1 إنشاء وتسمية الفروع الخمسة بالسيستم:",
            font_name=FONT_BOLD,
            font_size=10,
            color=NAVY,
        )
    )
    story.append(
        RTLText(
            "الانتقال إلى شاشة الإعدادات > الفروع (/settings/branches) وإضافة الفروع الخمسة بأسماء صريحة: المركز الأول، المركز الثاني، المركز الثالث، المركز الرابع، والمركز الخامس.",
            font_size=9,
            color=SLATE,
            leading=14,
        )
    )
    story.append(Spacer(1, 2 * mm))
    story.append(
        RTLText(
            "1.2 إنشاء المخازن وتنسيبها للفروع التابعة:",
            font_name=FONT_BOLD,
            font_size=10,
            color=NAVY,
        )
    )
    story.append(
        RTLText(
            "الانتقال إلى شاشة المخازن > قائمة المخازن (/inventory/warehouses). يتم الضغط على إضافة مخزن جديد، وتحديد اسم المخزن (مثل: مخزن قطاع أ - المركز الأول)، واختيار الفرع التابع له من القائمة المنسدلة. يُسمح بإضافة مخازن متعددة لكل فرع.",
            font_size=9,
            color=SLATE,
            leading=14,
        )
    )
    story.append(Spacer(1, 2 * mm))
    story.append(
        RTLText(
            "1.3 تكويد الأصناف ودليل المنتجات:",
            font_name=FONT_BOLD,
            font_size=10,
            color=NAVY,
        )
    )
    story.append(
        RTLText(
            "الانتقال إلى الكتالوج > المنتجات (/catalog/products). يتم تعريف وحدات القياس (قطعة، طرد..) وفئات المنتجات، ثم إدخال المنتجات بأسماؤها ووحداتها وأسعارها القياسية.",
            font_size=9,
            color=SLATE,
            leading=14,
        )
    )

    story.append(Spacer(1, 5 * mm))

    # SECTION 2
    story.append(
        section_card(
            "2",
            "المرحلة الثانية: دورة الموردين وحساباتهم وإدخال البضاعة الواردة",
            "إدارة ملفات الموردين، أوامر الشراء، آذون استلام البضاعة بالمخازن، الفواتير والسداد",
        )
    )
    story.append(Spacer(1, 3 * mm))
    story.append(
        RTLText(
            "2.1 تعريف الموردين وإدارة الملفات:",
            font_name=FONT_BOLD,
            font_size=10,
            color=NAVY,
        )
    )
    story.append(
        RTLText(
            "من شاشة الموردين (/suppliers)، يتم إضافة بيانات المورد وقيد العملة والحساب المالي الخاص به.",
            font_size=9,
            color=SLATE,
        )
    )
    story.append(Spacer(1, 2 * mm))
    story.append(
        RTLText(
            "2.2 استلام البضاعة بالمخازن (Goods Receipt):",
            font_name=FONT_BOLD,
            font_size=10,
            color=NAVY,
        )
    )
    story.append(
        RTLText(
            "يتم إنشاء أمر شراء من (/purchasing/orders) واعتياده، ثم الانتقال إلى آذون استلام البضاعة (/purchasing/goods-receipts). يتم تحديد الفرع والمخزن المستلم للبضاعة بشكل دقيق، وإدخال الكميات الفعلية وتأكيد الإذن (Confirm). تؤثر هذه العملية فوراً على زيادة رصيد المخزن المنسوب وتسجيل حركة إضافة.",
            font_size=9,
            color=SLATE,
            leading=14,
        )
    )
    story.append(Spacer(1, 2 * mm))
    story.append(
        RTLText(
            "2.3 إثبات المستحقات وسداد كشف حساب المورد:",
            font_name=FONT_BOLD,
            font_size=10,
            color=NAVY,
        )
    )
    story.append(
        RTLText(
            "• تسجيل فاتورة المورد (/purchasing/bills) وترحيلها (Post) لإنشاء الدائنية المستحقة (AP).\n"
            "• تسجيل دفعة سداد المورد (/supplier-payments) وترحيلها لخصم المبلغ من البنك أو الخزينة.\n"
            "• ربط التسوية وتسديد الفواتير من (/payable-allocations).\n"
            "• استخراج كشف حساب المورد التفصيلي من تقارير الموردين (/reports/supplier-statement).",
            font_size=9,
            color=SLATE,
            leading=14,
        )
    )

    story.append(PageBreak())

    # PAGE 2
    # SECTION 3
    story.append(
        section_card(
            "3",
            "المرحلة الثالثة: النقل والتحويل بين المخازن بالفروع",
            "دورة التحويلات المخزنية: تقديم، موافقة، صرف من المخزن المصدر، واستلام في المخزن الوارد",
        )
    )
    story.append(Spacer(1, 3 * mm))
    story.append(
        RTLText(
            "من شاشة المخازن > التحويلات المخزنية (/inventory/transfers)، يتم تنفيذ الدورة كالتالي:",
            font_size=9.5,
            color=SLATE,
        )
    )
    story.append(Spacer(1, 2 * mm))
    story.append(
        RTLText(
            "1. إنشاء طلب تحويل جديد وتحديد (المخزن المصدر) و(المخزن المستلم) والأصناف والكميات.",
            bullet=True,
            font_size=9,
            color=NAVY,
        )
    )
    story.append(
        RTLText(
            "2. تقديم الطلب (Submit) ثم اعتماد التحويل (Approve) من قِبل المسؤول المخزني المختص.",
            bullet=True,
            font_size=9,
            color=NAVY,
        )
    )
    story.append(
        RTLText(
            "3. التنفيذ الميداني للصرف (Issue Stock): يخصم رصيد الأصناف فوراً من المخزن المصدر وتسجل حركة خروج.",
            bullet=True,
            font_size=9,
            color=NAVY,
        )
    )
    story.append(
        RTLText(
            "4. الاستلام الفعلي بالمخزن الوارد (Receive Stock): يضاف الرصيد فوراً للمخزن الوارد وتسجل حركة دخول.",
            bullet=True,
            font_size=9,
            color=NAVY,
        )
    )
    story.append(Spacer(1, 2 * mm))
    story.append(
        compact_callout(
            "تنبيه تشغيلي",
            "ملاحظة هامة: يوفر السيستم إمكانية التحويل بين مخزن في المركز الأول ومخزن في المركز الخامس بسلاسة مع الحفاظ على كارت الصنف وسجل الحركات الحية للأصناف بكل مخزن.",
            background=AMBER_PALE,
            border=AMBER,
        )
    )

    story.append(Spacer(1, 3 * mm))

    # SECTION 4
    story.append(
        section_card(
            "4",
            "المرحلة الرابعة: البيع للجهات الداخلية بسعر 0 (صفر)",
            "تسجيل المبيعات المجانية أو الاستهلاك الداخلي مع التأثير الكامل على رصيد المخزون وتقارير البيع",
        )
    )
    story.append(Spacer(1, 2 * mm))
    story.append(
        RTLText(
            "لتسجيل العملية رسمياً كـ (عملية بيع مكتملة) تظهر بتقارير حركات المبيعات وتأثير المخزون دون مطالبة مالية:",
            font_size=9,
            color=SLATE,
        )
    )
    story.append(Spacer(1, 1.5 * mm))
    story.append(
        RTLText(
            "1. تعريف الجهة الداخلية كعميل: من شاشة العملاء (/customers) يتم فتح ملف عميل باسم (مثال: الجهة الداخلية - المركز الأول).",
            bullet=True,
            font_size=8.5,
            color=NAVY,
        )
    )
    story.append(
        RTLText(
            "2. إصدار إذن تسليم مبيعات (Delivery Note): من (/sales/delivery-notes) واختيار المخزن المصدر وتأكيده (Confirm)، لخصم البضاعة فوراً من المخزن وتسجيل حركة صرف مبيعات.",
            bullet=True,
            font_size=8.5,
            color=NAVY,
        )
    )
    story.append(
        RTLText(
            "3. أصدار فاتورة مبيعات بسعر 0 (Customer Invoice): من (/sales/invoices) وإدخال سعر الوحدة 0.00 EGP وترحيل الفاتورة (Post)، حيث يتيح النظام إصدار فواتير بسعر صفر لتسجيل حركة البيع بسجلات المبيعات دون مديونية.",
            bullet=True,
            font_size=8.5,
            color=NAVY,
        )
    )
    story.append(Spacer(1, 1.5 * mm))
    story.append(
        compact_callout(
            "بديل تشغيلي",
            "خيار التسوية المخزنية المباشرة: في حالة عدم الرغبة في تمرير العملية عبر شاشات المبيعات، يمكن استخدام شاشة تسويات المخزون الخصم (/inventory/adjustments) واختيار سبب الخصم 'صرف جهة داخلية' لخصم المخزون مباشرة.",
            background=EMERALD_PALE,
            border=EMERALD,
        )
    )

    story.append(Spacer(1, 5 * mm))

    # SECTION 5: SUMMARY TABLE
    story.append(RTLHeading("جدول ملخص الشاشات والمسارات بالسيستم", level=2, bookmark="summary_table"))
    story.append(Spacer(1, 2 * mm))

    table_rows = [
        ["/settings/branches", "تعريف الفروع الخمسة وتحديد مسميات المراكز", "تهيئة الهيكل"],
        ["/inventory/warehouses", "تعريف المخازن وتنسيبها للفروع التابعة لها", "تهيئة الهيكل"],
        ["/catalog/products", "تعريف كود المنتجات والأصناف ووحدات القياس", "الكتالوج"],
        ["/suppliers", "إدارة ملفات الموردين والعملات والحسابات", "الموردين"],
        ["/purchasing/goods-receipts", "استلام البضاعة بالمخازن وتعديل رصيد المخزون", "الوارد المخزني"],
        ["/purchasing/bills", "إثبات فواتير المشتريات وترحيل الالتزام المالي", "حسابات الموردين"],
        ["/supplier-payments", "سداد دفعة للمورد من البنك أو الخزينة", "حسابات الموردين"],
        ["/inventory/transfers", "التحويل المخزني بين الفروع (صرف واسلام)", "التحويل المخزني"],
        ["/sales/delivery-notes", "إذن تسليم بضاعة للجهات الداخلية وخصم المخزون", "المبيعات الداخلية"],
        ["/sales/invoices", "اصدار فاتورة مبيعات بسعر 0 للجهات الداخلية", "المبيعات الداخلية"],
    ]

    story.append(make_route_table(table_rows))

    doc.build(story)
    return OUTPUT_PATH


if __name__ == "__main__":
    out = build_pdf()
    print(f"PDF Generated successfully at: {out}")
