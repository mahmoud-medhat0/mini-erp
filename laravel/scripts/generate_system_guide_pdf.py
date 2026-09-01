from __future__ import annotations

import os
import re
from dataclasses import dataclass
from html import escape
from pathlib import Path
from typing import Iterable, Sequence

import arabic_reshaper
from bidi.algorithm import get_display
from reportlab.lib import colors
from reportlab.lib.enums import TA_LEFT, TA_RIGHT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    BaseDocTemplate,
    CondPageBreak,
    Flowable,
    Frame,
    KeepTogether,
    LongTable,
    NextPageTemplate,
    PageBreak,
    PageTemplate,
    Paragraph,
    Spacer,
    Table,
    TableStyle,
)
from reportlab.platypus.tableofcontents import TableOfContents


ROOT = Path(__file__).resolve().parents[2]
OUTPUT_PATH = ROOT / "output" / "pdf" / "mini-erp-system-guide-ar.pdf"

PAGE_WIDTH, PAGE_HEIGHT = A4
BODY_LEFT = 14 * mm
BODY_RIGHT = 14 * mm
BODY_TOP = 19 * mm
BODY_BOTTOM = 17 * mm
CONTENT_WIDTH = PAGE_WIDTH - BODY_LEFT - BODY_RIGHT

NAVY = colors.HexColor("#0F172A")
SLATE = colors.HexColor("#475569")
MUTED = colors.HexColor("#64748B")
PALE = colors.HexColor("#F8FAFC")
BORDER = colors.HexColor("#E2E8F0")
BLUE = colors.HexColor("#2563EB")
BLUE_DARK = colors.HexColor("#1D4ED8")
BLUE_PALE = colors.HexColor("#EFF6FF")
INDIGO = colors.HexColor("#4F46E5")
EMERALD = colors.HexColor("#059669")
EMERALD_PALE = colors.HexColor("#ECFDF5")
AMBER = colors.HexColor("#D97706")
AMBER_PALE = colors.HexColor("#FFFBEB")
RED = colors.HexColor("#DC2626")
RED_PALE = colors.HexColor("#FEF2F2")
WHITE = colors.white

FONT_REGULAR = "ArialArabic"
FONT_BOLD = "ArialArabicBold"


def register_fonts() -> None:
    candidates = [
        (Path("C:/Windows/Fonts/arial.ttf"), Path("C:/Windows/Fonts/arialbd.ttf")),
        (Path("C:/Windows/Fonts/tahoma.ttf"), Path("C:/Windows/Fonts/tahomabd.ttf")),
    ]

    for regular, bold in candidates:
        if regular.exists() and bold.exists():
            pdfmetrics.registerFont(TTFont(FONT_REGULAR, str(regular)))
            pdfmetrics.registerFont(TTFont(FONT_BOLD, str(bold)))
            return

    raise FileNotFoundError("Arabic-capable Arial or Tahoma fonts were not found.")


def clean_text(text: str) -> str:
    return (
        text.replace("\u2010", "-")
        .replace("\u2011", "-")
        .replace("\u2012", "-")
        .replace("\u2013", "-")
        .replace("\u2014", "-")
    )


def rtl_visual(text: str) -> str:
    clean = clean_text(text)
    return get_display(arabic_reshaper.reshape(clean), base_dir="R")


def contains_arabic(text: str) -> bool:
    return any("\u0600" <= character <= "\u06ff" for character in text)


def normalize_space(text: str) -> str:
    return re.sub(r"\s+", " ", clean_text(text)).strip()


class RTLText(Flowable):
    def __init__(
        self,
        text: str,
        *,
        font_name: str = FONT_REGULAR,
        font_size: float = 10,
        leading: float | None = None,
        color: colors.Color = NAVY,
        bullet: bool = False,
        top_padding: float = 0,
        bottom_padding: float = 0,
    ) -> None:
        super().__init__()
        self.logical_text = normalize_space(text)
        self.font_name = font_name
        self.font_size = font_size
        self.leading = leading or font_size * 1.65
        self.color = color
        self.bullet = bullet
        self.top_padding = top_padding
        self.bottom_padding = bottom_padding
        self.lines: list[str] = []
        self._available_width = 0.0

    def _measure(self, text: str) -> float:
        return pdfmetrics.stringWidth(rtl_visual(text), self.font_name, self.font_size)

    def _wrap_words(self, width: float) -> list[str]:
        if not self.logical_text:
            return [""]

        lines: list[str] = []
        for source_line in self.logical_text.split("\n"):
            words = source_line.split()
            if not words:
                lines.append("")
                continue

            current = words[0]
            for word in words[1:]:
                candidate = f"{current} {word}"
                if self._measure(candidate) <= width:
                    current = candidate
                else:
                    lines.append(current)
                    current = word
            lines.append(current)

        return lines

    def wrap(self, avail_width: float, avail_height: float) -> tuple[float, float]:
        del avail_height
        bullet_indent = 14 if self.bullet else 0
        self._available_width = avail_width
        self.lines = self._wrap_words(max(20, avail_width - bullet_indent))
        self.width = avail_width
        self.height = (
            self.top_padding
            + self.bottom_padding
            + len(self.lines) * self.leading
        )
        return self.width, self.height

    def draw(self) -> None:
        self.canv.saveState()
        self.canv.setFillColor(self.color)
        self.canv.setFont(self.font_name, self.font_size)
        bullet_indent = 14 if self.bullet else 0
        baseline = self.height - self.top_padding - self.font_size

        if self.bullet:
            self.canv.setFillColor(BLUE)
            self.canv.circle(self.width - 3.5, baseline + 2.5, 2.3, fill=1, stroke=0)
            self.canv.setFillColor(self.color)

        for line in self.lines:
            self.canv.drawRightString(
                self.width - bullet_indent,
                baseline,
                rtl_visual(line),
            )
            baseline -= self.leading

        self.canv.restoreState()


class RTLHeading(RTLText):
    def __init__(self, text: str, *, level: int, bookmark: str) -> None:
        if level == 0:
            font_size, leading, color = 24, 35, NAVY
        else:
            font_size, leading, color = 16, 25, BLUE_DARK

        super().__init__(
            text,
            font_name=FONT_BOLD,
            font_size=font_size,
            leading=leading,
            color=color,
            top_padding=2,
            bottom_padding=3,
        )
        self.level = level
        self.bookmark = bookmark
        self.toc_title = text

    def wrap(self, avail_width: float, avail_height: float) -> tuple[float, float]:
        inner_width = max(20, avail_width - 17)
        _, height = super().wrap(inner_width, avail_height)
        self.width = avail_width
        return self.width, height

    def draw(self) -> None:
        self.canv.saveState()
        self.canv.setFillColor(BLUE if self.level == 0 else INDIGO)
        bar_height = max(21, self.height - 7)
        self.canv.roundRect(
            self.width - 6,
            (self.height - bar_height) / 2,
            6,
            bar_height,
            2.5,
            fill=1,
            stroke=0,
        )
        self.canv.restoreState()

        original_width = self.width
        self.width = original_width - 17
        super().draw()
        self.width = original_width


class LTRText(Flowable):
    def __init__(
        self,
        text: str,
        *,
        font_name: str = FONT_REGULAR,
        font_size: float = 8,
        color: colors.Color = SLATE,
    ) -> None:
        super().__init__()
        style = ParagraphStyle(
            "LTRText",
            fontName=font_name,
            fontSize=font_size,
            leading=font_size * 1.4,
            textColor=color,
            alignment=TA_LEFT,
            wordWrap="CJK",
        )
        self.paragraph = Paragraph(escape(clean_text(text)), style)

    def wrap(self, avail_width: float, avail_height: float) -> tuple[float, float]:
        self.width, self.height = self.paragraph.wrap(avail_width, avail_height)
        return self.width, self.height

    def draw(self) -> None:
        self.paragraph.canv = self.canv
        self.paragraph.draw()


class RTLTableOfContents(TableOfContents):
    """A two-column TOC that keeps Arabic titles and page numbers separate."""

    def wrap(self, avail_width: float, avail_height: float) -> tuple[float, float]:
        entries = self._lastEntries or [(0, rtl_visual("محتوى الدليل"), 0, None)]
        rows: list[list[object]] = []
        main_entry_rows: list[int] = []

        for level, title, page_number, key in entries:
            title_style = self.getLevelStyle(level)
            page_style = ParagraphStyle(
                f"TOCPage{level}",
                fontName=FONT_BOLD if level == 0 else FONT_REGULAR,
                fontSize=title_style.fontSize,
                leading=title_style.leading,
                textColor=BLUE_DARK if level == 0 else MUTED,
                alignment=TA_LEFT,
            )

            safe_title = escape(title)
            page_label = escape(str(page_number))
            if key:
                safe_key = escape(str(key), quote=True)
                safe_title = f'<a href="#{safe_key}">{safe_title}</a>'
                page_label = f'<a href="#{safe_key}">{page_label}</a>'

            if title_style.spaceBefore:
                rows.append([Spacer(1, title_style.spaceBefore), Spacer(1, title_style.spaceBefore)])
            row_index = len(rows)
            rows.append([Paragraph(page_label, page_style), Paragraph(safe_title, title_style)])
            if level == 0:
                main_entry_rows.append(row_index)

        page_column_width = 18 * mm
        table_style = [
            ("VALIGN", (0, 0), (-1, -1), "BOTTOM"),
            ("LEFTPADDING", (0, 0), (-1, -1), 4),
            ("RIGHTPADDING", (0, 0), (-1, -1), 4),
            ("TOPPADDING", (0, 0), (-1, -1), 0),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 0),
        ]
        for row_index in main_entry_rows:
            table_style.extend(
                [
                    ("BACKGROUND", (0, row_index), (-1, row_index), BLUE_PALE),
                    ("LINEBELOW", (0, row_index), (-1, row_index), 0.4, BORDER),
                    ("TOPPADDING", (0, row_index), (-1, row_index), 4),
                    ("BOTTOMPADDING", (0, row_index), (-1, row_index), 4),
                ]
            )
        self._table = Table(
            rows,
            colWidths=[page_column_width, avail_width - page_column_width],
            style=TableStyle(table_style),
        )
        self.width, self.height = self._table.wrapOn(self.canv, avail_width, avail_height)
        return self.width, self.height


class OutlineMarker(Flowable):
    """Zero-height bookmark and TOC entry for headings rendered inside tables."""

    def __init__(self, title: str, *, level: int, bookmark: str) -> None:
        super().__init__()
        self.level = level
        self.bookmark = bookmark
        self.toc_title = title

    def wrap(self, avail_width: float, avail_height: float) -> tuple[float, float]:
        del avail_width, avail_height
        return 0, 0

    def draw(self) -> None:
        return


class GuideDocTemplate(BaseDocTemplate):
    def __init__(self, filename: str) -> None:
        super().__init__(
            filename,
            pagesize=A4,
            leftMargin=BODY_LEFT,
            rightMargin=BODY_RIGHT,
            topMargin=BODY_TOP,
            bottomMargin=BODY_BOTTOM,
            title="Mini ERP System Guide - Arabic",
            author="Mini ERP",
            subject="Comprehensive Arabic user guide",
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

    def afterFlowable(self, flowable: Flowable) -> None:
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


def draw_cover_page(canvas, doc) -> None:
    del doc
    canvas.saveState()
    canvas.setFillColor(NAVY)
    canvas.rect(0, 0, PAGE_WIDTH, PAGE_HEIGHT, fill=1, stroke=0)

    canvas.setFillColor(colors.HexColor("#1E3A8A"))
    canvas.circle(PAGE_WIDTH - 15 * mm, PAGE_HEIGHT - 18 * mm, 58 * mm, fill=1, stroke=0)
    canvas.setFillColor(colors.HexColor("#312E81"))
    canvas.circle(12 * mm, 16 * mm, 48 * mm, fill=1, stroke=0)
    canvas.setFillColor(colors.HexColor("#3B82F6"))
    canvas.roundRect(18 * mm, PAGE_HEIGHT - 26 * mm, 16 * mm, 6 * mm, 3 * mm, fill=1, stroke=0)
    canvas.restoreState()


def draw_body_page(canvas, doc) -> None:
    page_number = canvas.getPageNumber()
    canvas.saveState()

    canvas.setFillColor(BLUE)
    canvas.rect(0, PAGE_HEIGHT - 4, PAGE_WIDTH, 4, fill=1, stroke=0)

    canvas.setFont(FONT_REGULAR, 8.5)
    canvas.setFillColor(MUTED)
    canvas.drawRightString(
        PAGE_WIDTH - BODY_RIGHT,
        PAGE_HEIGHT - 11 * mm,
        rtl_visual("دليل نظام Mini ERP"),
    )
    canvas.drawString(BODY_LEFT, PAGE_HEIGHT - 11 * mm, "v1.1 | 2026-08-31")

    canvas.setStrokeColor(BORDER)
    canvas.line(BODY_LEFT, 12 * mm, PAGE_WIDTH - BODY_RIGHT, 12 * mm)
    canvas.setFont(FONT_REGULAR, 9)
    canvas.setFillColor(MUTED)
    canvas.drawCentredString(
        PAGE_WIDTH / 2,
        7.5 * mm,
        f"{page_number} {rtl_visual('صفحة')}",
    )
    canvas.restoreState()


def paragraph(text: str, *, size: float = 11.2, color: colors.Color = SLATE) -> RTLText:
    return RTLText(text, font_size=size, leading=size * 1.65, color=color)


def bullet(text: str, *, color: colors.Color = SLATE) -> RTLText:
    return RTLText(text, font_size=10.6, leading=17.4, color=color, bullet=True)


def callout(
    title: str,
    body: str,
    *,
    background: colors.Color = BLUE_PALE,
    border: colors.Color = BLUE,
) -> Table:
    content = [
        RTLText(title, font_name=FONT_BOLD, font_size=12.2, leading=19, color=NAVY),
        Spacer(1, 4),
        RTLText(body, font_size=10.6, leading=17.4, color=SLATE),
    ]
    table = Table([[content]], colWidths=[CONTENT_WIDTH])
    table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), background),
                ("BOX", (0, 0), (-1, -1), 0.8, border),
                ("LINEAFTER", (0, 0), (0, 0), 4, border),
                ("LEFTPADDING", (0, 0), (-1, -1), 12),
                ("RIGHTPADDING", (0, 0), (-1, -1), 14),
                ("TOPPADDING", (0, 0), (-1, -1), 12),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 12),
            ]
        )
    )
    return table


def cards(items: Sequence[tuple[str, str]], columns: int = 2) -> Table:
    rows: list[list[object]] = []
    for index in range(0, len(items), columns):
        row: list[object] = []
        for title, body in items[index : index + columns]:
            row.append(
                [
                    RTLText(title, font_name=FONT_BOLD, font_size=11.5, leading=18.5, color=BLUE_DARK),
                    Spacer(1, 4),
                    RTLText(body, font_size=9.8, leading=16, color=SLATE),
                ]
            )
        while len(row) < columns:
            row.append("")
        rows.append(row)

    gap = 7
    width = (CONTENT_WIDTH - gap * (columns - 1)) / columns
    table = Table(rows, colWidths=[width] * columns, hAlign="CENTER")
    style_commands: list[tuple] = [
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("BACKGROUND", (0, 0), (-1, -1), PALE),
        ("BOX", (0, 0), (-1, -1), 0.6, BORDER),
        ("INNERGRID", (0, 0), (-1, -1), gap, WHITE),
        ("LEFTPADDING", (0, 0), (-1, -1), 9),
        ("RIGHTPADDING", (0, 0), (-1, -1), 9),
        ("TOPPADDING", (0, 0), (-1, -1), 10),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 10),
    ]
    table.setStyle(TableStyle(style_commands))
    return table


def workflow_diagram(steps: Sequence[str]) -> list[Flowable]:
    output: list[Flowable] = []
    for start in range(0, len(steps), 3):
        chunk = list(steps[start : start + 3])
        cells: list[object] = []
        widths: list[float] = []
        arrow_width = 26
        step_width = (CONTENT_WIDTH - (len(chunk) - 1) * arrow_width) / len(chunk)
        for index, step in enumerate(reversed(chunk)):
            cells.append(RTLText(step, font_name=FONT_BOLD, font_size=9.5, leading=14.8, color=NAVY))
            widths.append(step_width)
            if index < len(chunk) - 1:
                cells.append(
                    Paragraph(
                        "&#8592;",
                        ParagraphStyle(
                            "Arrow",
                            fontName=FONT_BOLD,
                            fontSize=16,
                            leading=18,
                            textColor=BLUE,
                            alignment=TA_RIGHT,
                        ),
                    )
                )
                widths.append(arrow_width)

        table = Table([cells], colWidths=widths, hAlign="CENTER")
        table.setStyle(
            TableStyle(
                [
                    ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
                    ("BACKGROUND", (0, 0), (-1, -1), BLUE_PALE),
                    ("BOX", (0, 0), (-1, -1), 0.6, BORDER),
                    ("LEFTPADDING", (0, 0), (-1, -1), 6),
                    ("RIGHTPADDING", (0, 0), (-1, -1), 6),
                    ("TOPPADDING", (0, 0), (-1, -1), 9),
                    ("BOTTOMPADDING", (0, 0), (-1, -1), 9),
                ]
            )
        )
        output.extend([table, Spacer(1, 5)])

    return output


def numbered_table(items: Sequence[str]) -> LongTable:
    rows: list[list[object]] = []
    for number, item in enumerate(items, start=1):
        rows.append(
            [
                Paragraph(
                    str(number),
                    ParagraphStyle(
                        "StepNumber",
                        fontName=FONT_BOLD,
                        fontSize=10,
                        leading=16,
                        textColor=WHITE,
                        alignment=TA_RIGHT,
                    ),
                ),
                RTLText(item, font_size=10.3, leading=17.2, color=SLATE),
            ]
        )

    table = LongTable(rows, colWidths=[12 * mm, CONTENT_WIDTH - 12 * mm])
    style = [
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("BACKGROUND", (0, 0), (0, -1), BLUE),
        ("BOX", (0, 0), (-1, -1), 0.5, BORDER),
        ("INNERGRID", (0, 0), (-1, -1), 0.35, BORDER),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("RIGHTPADDING", (0, 0), (-1, -1), 8),
        ("TOPPADDING", (0, 0), (-1, -1), 8),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
    ]
    for row in range(len(rows)):
        if row % 2:
            style.append(("BACKGROUND", (1, row), (1, row), PALE))
    table.setStyle(TableStyle(style))
    return table


@dataclass(frozen=True)
class Screen:
    route: str
    title: str
    description: str


@dataclass(frozen=True)
class Module:
    title: str
    purpose: str
    screens: Sequence[Screen]
    workflow: Sequence[str]
    controls: Sequence[str]


MODULES: list[Module] = [
    Module(
        "المحاسبة العامة",
        "المحرك المالي المركزي للنظام. يحتفظ بدليل الحسابات والقيود المتوازنة والحركات المرحلة والفترات والعملات والربط المحاسبي.",
        [
            Screen("/accounting", "نظرة المحاسبة", "ملخص الحسابات والقيود والعملات وروابط العمل اليومية."),
            Screen("/accounting/currencies", "العملات", "إضافة العملات والرمز والخانات العشرية وتحديد العملة الأساسية."),
            Screen("/accounting/fx-rates", "أسعار الصرف", "تسجيل سعر العملة مقابل العملة الأساسية بتاريخ نفاذ."),
            Screen("/accounting/account-categories", "تصنيفات الحسابات", "تنظيم حسابات الدليل في تصنيفات محاسبية."),
            Screen("/accounting/account-types", "أنواع الحسابات", "تعريف النوع والطبيعة المدينة أو الدائنة."),
            Screen("/accounting/coa", "دليل الحسابات", "إنشاء المجموعات والحسابات وضبط العملة وحسابات المراقبة والترحيل اليدوي."),
            Screen("/accounting/account-mappings", "ربط حسابات الترحيل", "ربط مفاتيح المبيعات والمشتريات والضرائب والمخزون والأصول بحسابات الأستاذ."),
            Screen("/accounting/statement-mappings", "ربط القوائم المالية", "تعريف بنود الميزانية والدخل وربط حسابات الدليل بها."),
            Screen("/accounting/periods", "السنوات والفترات", "إنشاء السنة والفترات، فحص جاهزية الإغلاق، الإغلاق وإعادة الفتح."),
            Screen("/accounting/opening-balances", "الأرصدة الافتتاحية", "إدخال المدين والدائن والتأكد من التوازن ثم الترحيل."),
            Screen("/accounting/journal", "دفتر اليومية", "البحث عن القيود وتصفية حالاتها وفتح التفاصيل."),
            Screen("/accounting/journal/create", "قيد جديد", "إدخال الفترة والتاريخ والعملة والسطور وحفظ قيد متوازن."),
            Screen("/accounting/journal/{id}", "تفاصيل القيد", "الإرسال والاعتماد والترحيل والعكس بقيد مرتبط."),
            Screen("/accounting/ledger", "دفتر الأستاذ", "عرض حركات حساب محدد ورصيده الجاري حسب الفترة."),
            Screen("/accounting/trial-balance", "ميزان المراجعة", "مراجعة أرصدة الحسابات وتساوي إجمالي المدين والدائن."),
        ],
        ["مسودة", "إرسال", "اعتماد", "ترحيل", "عكس عند الحاجة"],
        [
            "لا يرحل أي مستند مالي إلا داخل فترة مفتوحة.",
            "يجب أن يتساوى إجمالي المدين والدائن قبل حفظ أو ترحيل القيد.",
            "الحركة المرحلة لا تعدل؛ التصحيح يكون بعكس أو مستند مقابل.",
            "العملة وسعر الصرف يجب أن يكونا صريحين ولا يستخدم النظام سعر 1 تلقائيًا عند غياب السعر.",
        ],
    ),
    Module(
        "العملاء والتحصيل",
        "إدارة بيانات العملاء وافتتاحياتهم وسندات القبض وتخصيص المتحصلات وتسويات الحسابات المدينة.",
        [
            Screen("/customers", "العملاء", "بيانات العميل والاتصال والضرائب والائتمان والحالة والمرفقات."),
            Screen("/customer-opening-balances", "افتتاحيات العملاء", "إنشاء رصيد افتتاحي ثم ترحيله إلى العملاء والأستاذ."),
            Screen("/customer-receipts", "سندات القبض", "تسجيل مبلغ محصل وتحديد الخزينة أو البنك ثم الترحيل."),
            Screen("/receivable-allocations", "تخصيص المتحصلات", "ربط رصيد السند بفواتير أو قيود مدينة مفتوحة وعكس التخصيص."),
            Screen("/sales/receivable-settlements", "تسويات العملاء", "تسوية إشعار دائن أو قيد مقابل مستحق وعكس التسوية."),
        ],
        ["عميل", "فاتورة أو افتتاحي", "قبض", "تخصيص", "كشف حساب"],
        [
            "حد الائتمان له صلاحية تجاوز مستقلة.",
            "القبض المرحل يثبت الأثر المالي، أما التخصيص فيوزع الرصيد على المستحقات المفتوحة.",
            "استخدم كشف العميل وأعمار الديون ومطابقة AR مع الأستاذ للمراجعة.",
        ],
    ),
    Module(
        "الموردون والمدفوعات",
        "إدارة الموردين وافتتاحياتهم وسندات الصرف وتخصيص الدفعات وتسويات الحسابات الدائنة.",
        [
            Screen("/suppliers", "الموردون", "بيانات المورد والحالة والاتصال والضرائب والمرفقات."),
            Screen("/supplier-opening-balances", "افتتاحيات الموردين", "إنشاء رصيد افتتاحي وترحيله إلى الموردين والأستاذ."),
            Screen("/supplier-payments", "سندات الصرف", "تسجيل دفعة من خزينة أو بنك ثم ترحيلها."),
            Screen("/payable-allocations", "تخصيص الدفعات", "ربط الدفعة بالتزامات المورد المفتوحة وعكس التخصيص."),
            Screen("/purchasing/payable-settlements", "تسويات الموردين", "تسوية مذكرة المورد مقابل الالتزامات وعكسها."),
        ],
        ["مورد", "فاتورة أو افتتاحي", "دفع", "تخصيص", "كشف مورد"],
        [
            "الصرف المرحل ينشئ الأثر المالي، ثم يوزع التخصيص الرصيد على الالتزامات.",
            "استخدم كشف المورد وأعمار الدائنين ومطابقة AP مع الأستاذ للمراجعة.",
        ],
    ),
    Module(
        "المبيعات والكتالوج",
        "إدارة المنتجات والعملاء ودورة البيع من الأمر إلى التسليم والفاتورة والمرتجع والإشعار الدائن.",
        [
            Screen("/catalog/products", "المنتجات والخدمات", "الكود والوصف والوحدة والتصنيف والضريبة وسياسات المخزون."),
            Screen("/catalog/categories", "تصنيفات المنتجات", "تنظيم المنتجات والخدمات في تصنيفات."),
            Screen("/catalog/uoms", "وحدات القياس", "تعريف وحدات القياس المستخدمة في المستندات."),
            Screen("/sales/orders", "أوامر البيع", "العميل والعملة والتواريخ والبنود والكميات والأسعار والخصومات."),
            Screen("/sales/delivery-notes", "أذون التسليم", "إنشاء تسليم من أمر مؤكد وتحديد المخزن والكميات ثم التأكيد."),
            Screen("/sales/invoices", "فواتير العملاء", "إنشاء فاتورة مباشرة أو من مصدر صالح ثم الإرسال والاعتماد والترحيل."),
            Screen("/sales/returns", "مرتجعات المبيعات", "رد بنود فاتورة مرحلة وتحديد المخزن وحالة الصنف ثم الترحيل."),
            Screen("/sales/credit-notes", "الإشعارات الدائنة", "إنشاء إشعار واعتماده وترحيله ثم تسويته على مستحقات العميل."),
            Screen("/sales/invoice-revisions", "مراجعات الفواتير", "سجل نسخ الفاتورة بعد التعديل."),
            Screen("/sales/invoice-revisions/{id}", "تفاصيل المراجعة", "مقارنة بيانات المراجعة ومصدرها."),
        ],
        ["عميل", "أمر بيع", "إذن تسليم", "فاتورة", "قبض وتخصيص"],
        [
            "أمر البيع ينتقل من مسودة إلى إرسال ثم تأكيد.",
            "تأكيد إذن التسليم يسجل حركة صرف المخزون.",
            "ترحيل الفاتورة يثبت العملاء والأستاذ وضريبة المخرجات.",
            "المستند المرحل يصحح بمرتجع أو إشعار دائن أو عكس، وليس بالحذف المباشر.",
        ],
    ),
    Module(
        "المشتريات",
        "دورة المورد من أمر الشراء والاستلام إلى تكلفة الوصول وفاتورة المورد والمرتجع وإشعار التسوية.",
        [
            Screen("/purchasing/orders", "أوامر الشراء", "المورد والعملة والبنود والكميات والأسعار ثم الإرسال والتأكيد."),
            Screen("/purchasing/goods-receipts", "أذون الاستلام", "استلام بنود أمر مؤكد في مخزن وتسجيل حركة المخزون."),
            Screen("/purchasing/landed-costs", "تكاليف الوصول", "توزيع الشحن والتأمين والتكاليف بالقيمة أو الكمية أو يدويًا."),
            Screen("/purchasing/bills", "فواتير الموردين", "إنشاء الفاتورة ثم الإرسال والاعتماد والترحيل إلى AP والأستاذ."),
            Screen("/purchasing/returns", "مرتجعات المشتريات", "اختيار البنود القابلة للإرجاع ثم اعتماد وترحيل المرتجع."),
            Screen("/purchasing/adjustment-notes", "إشعارات تسوية المورد", "إنشاء مذكرة تسوية واعتمادها وترحيلها."),
        ],
        ["مورد", "أمر شراء", "استلام", "تكلفة وصول", "فاتورة ودفع"],
        [
            "أمر الشراء ينتقل من مسودة إلى إرسال ثم تأكيد.",
            "تأكيد الاستلام يسجل دخول المخزون.",
            "تكلفة الوصول ترتبط بإذن استلام مؤكد ويجب أن تتوزع بالكامل.",
            "ترحيل فاتورة المورد يثبت AP والأستاذ والضريبة حسب الإعداد.",
        ],
    ),
    Module(
        "المخزون",
        "إدارة المخازن والأرصدة والتحويلات والجرد والتسويات مع ضوابط الاعتماد والترحيل.",
        [
            Screen("/inventory/warehouses", "المخازن", "إنشاء المخزن وربطه اختياريًا بفرع وإدارة المواقع الداخلية."),
            Screen("/inventory/stock-balances", "أرصدة المخزون", "عرض الكمية والقيمة حسب المخزن والمنتج."),
            Screen("/inventory/transfers", "تحويلات المخزون", "مصدر ووجهة وبنود ثم إصدار واستلام جزئي أو كامل."),
            Screen("/inventory/stock-counts", "جلسات الجرد", "تسجيل الكمية المعدودة واعتماد وترحيل الفروق."),
            Screen("/inventory/adjustments", "تسويات المخزون", "زيادة أو خفض كمية وقيمة بسبب محدد ثم الاعتماد والترحيل."),
        ],
        ["مسودة", "إرسال", "اعتماد", "إصدار", "استلام"],
        [
            "تجاوز المخزون السالب يحتاج صلاحية مستقلة.",
            "الجرد لا يغير الرصيد حتى اعتماد وترحيل الفروق.",
            "كل حركة تحتفظ بالمخزن والمنتج والكمية والقيمة والمصدر التشغيلي.",
        ],
    ),
    Module(
        "الخزينة والبنوك والشيكات",
        "إدارة نقاط النقد والحسابات البنكية والتحويلات والشيكات والتسوية مع كشف البنك.",
        [
            Screen("/cash-accounts", "حسابات الخزينة", "ربط الخزينة بحساب أستاذ وعملة وفرع اختياري."),
            Screen("/bank-accounts", "الحسابات البنكية", "بيانات البنك وربط الحساب بحساب الأستاذ والعملة."),
            Screen("/treasury-transfers", "التحويلات", "نقل مبلغ بين خزينة أو بنك ومصدر ووجهة ثم الترحيل."),
            Screen("/incoming-cheques", "الشيكات الواردة", "استلام ثم إيداع وتحصيل، أو ارتداد وإرجاع حسب الحالة."),
            Screen("/outgoing-cheques", "الشيكات الصادرة", "إصدار ثم تحصيل، أو إرجاع وإلغاء قبل التحصيل."),
            Screen("/bank-reconciliations", "تسويات البنوك", "إنشاء تسوية لفترة كشف الحساب."),
            Screen("/bank-reconciliations/{id}", "تفاصيل التسوية", "إضافة سطور الكشف ومطابقتها مع الأستاذ ثم الإنهاء عند فرق صفر."),
        ],
        ["كشف بنك", "إضافة السطور", "مطابقة", "مراجعة الفرق", "إنهاء"],
        [
            "لا تنتهي التسوية البنكية إلا عندما يصبح الفرق صفرًا.",
            "التحويل المرحل لا يلغى بالطريقة نفسها المتاحة للمسودة.",
            "حالات الشيك تحدد الأفعال المتاحة وتحمي من التكرار أو التحصيل غير الصحيح.",
        ],
    ),
    Module(
        "المصروفات والمقدمات والمستحقات",
        "تسجيل المصروفات المباشرة وجدولة المصروف المقدم والمستحق وترحيل الاعتراف الدوري.",
        [
            Screen("/expenses/categories", "فئات المصروف", "الحسابات الافتراضية وسياسات المرفقات."),
            Screen("/expenses", "المصروفات", "مصروف نقدي أو بنكي أو على المورد مع الفرع والمشروع ومركز التكلفة والضريبة."),
            Screen("/expenses/prepaids", "المصروفات المقدمة", "إنشاء جدول وإرسال واعتماد وترحيل كل قسط حتى الاكتمال."),
            Screen("/expenses/accruals", "المصروفات المستحقة", "إنشاء جدول استحقاق وترحيل القيود الدورية حتى الاكتمال."),
        ],
        ["مسودة", "إرسال", "اعتماد", "ترحيل", "متابعة الجدول"],
        [
            "قد تفرض فئة المصروف إرفاق مستند داعم.",
            "كل قسط دوري يرحل مرة واحدة مع حماية من التكرار.",
            "يمكن استخدام المشروع ومركز التكلفة والفرع كأبعاد تحليلية عند توفرها.",
        ],
    ),
    Module(
        "المرتبات",
        "تعريف موظفي المرتبات ومكونات الاستحقاق والخصم وإنشاء كشف الفترة واعتماده وترحيله.",
        [
            Screen("/payroll/components", "مكونات المرتب", "نوع الاستحقاق أو الخصم وطريقة الحساب والحسابات المرتبطة."),
            Screen("/payroll/employees", "موظفو المرتبات", "بيانات الموظف والفرع والمكونات المتكررة."),
            Screen("/payroll/runs", "كشوف المرتبات", "إنشاء كشف للفترة وتوليد السطور ثم الإرسال والاعتماد والترحيل."),
        ],
        ["فترة مرتب", "توليد السطور", "إرسال", "اعتماد", "ترحيل"],
        [
            "عرض بيانات المرتبات يحتاج صلاحية view_payroll الحساسة.",
            "إعادة توليد السطور متاحة أثناء المسودة فقط.",
            "الترحيل يحتاج صلاحية مالية بالإضافة إلى صلاحية تشغيل المرتبات.",
        ],
    ),
    Module(
        "الإيجارات",
        "إدارة عناصر الإيجار وعقودها وتسليمها وعودتها وفحصها وفوترتها.",
        [
            Screen("/rentals/items", "عناصر الإيجار", "المصدر الاختياري منتج أو أصل ومكان العنصر وحالته."),
            Screen("/rentals/contracts", "عقود الإيجار", "العميل والتواريخ والعناصر والأسعار والتأمين ثم الاعتماد والتفعيل."),
            Screen("/rentals/handovers", "محاضر التسليم", "تأكيد التسليم ليصبح العقد نشطًا والعنصر مؤجرًا."),
            Screen("/rentals/returns", "العودة والفحص", "تسجيل العودة ثم الفحص وتحديد نتيجة العنصر وإكمالها."),
            Screen("/rentals/invoices", "فواتير الإيجار", "الإيجار والتأمين والتأخير والتلف والرسوم والضريبة ثم الترحيل."),
        ],
        ["عنصر", "عقد", "اعتماد وتفعيل", "تسليم", "عودة وفحص", "فاتورة"],
        [
            "لا يمكن حجز العنصر نفسه في عقود متعارضة.",
            "تكتمل حالة العقد بعد إغلاق جميع عناصره.",
            "نتيجة الفحص تحدد عودة العنصر للمتاح أو حالته التشغيلية الجديدة.",
        ],
    ),
    Module(
        "الأصول الثابتة",
        "إدارة سجل الأصل من الفئة والموقع والرسملة إلى الإهلاك والنقل والاستبعاد.",
        [
            Screen("/fixed-asset-categories", "فئات الأصول", "العمر وطريقة الإهلاك وحسابات التكلفة والمجمع والمصروف."),
            Screen("/fixed-asset-locations", "مواقع الأصول", "تعريف الموقع وربطه اختياريًا بفرع."),
            Screen("/fixed-assets", "سجل الأصول", "البحث والتصفية وفتح تفاصيل الأصل."),
            Screen("/fixed-assets/create", "إضافة أصل", "التكلفة والقيمة التخريدية والعمر والتواريخ والموقع."),
            Screen("/fixed-assets/{id}", "تفاصيل الأصل", "المرفقات والحركات وجدول الإهلاك والرسملة والنقل."),
            Screen("/fixed-assets/{id}/edit", "تعديل الأصل", "تعديل البيانات المسموح بها وفق حالة الأصل."),
            Screen("/fixed-assets-depreciation-runs", "جولات الإهلاك", "اختيار الفترة ومراجعة الجولات."),
            Screen("/fixed-assets-depreciation-runs/preview/{periodId}", "معاينة الجولة", "حساب أقساط الفترة قبل الترحيل."),
            Screen("/fixed-assets-depreciation-runs/{id}", "تفاصيل الجولة", "عرض الأصول والأقساط والقيد والعكس."),
            Screen("/fixed-assets-disposals", "سجل الاستبعادات", "قائمة الاستبعادات وحالاتها."),
            Screen("/fixed-assets-disposals/{id}", "تفاصيل الاستبعاد", "القيمة الدفترية والربح أو الخسارة والترحيل والعكس."),
        ],
        ["إضافة أصل", "رسملة", "توليد جدول", "إهلاك دوري", "نقل أو استبعاد"],
        [
            "لا يبدأ الإهلاك قبل رسملة الأصل وتوليد الجدول.",
            "جولة الإهلاك ترتبط بفترة مالية وتمنع تكرار القسط.",
            "الاستبعاد يحسب صافي القيمة والربح أو الخسارة قبل الترحيل.",
        ],
    ),
    Module(
        "الضرائب والقيمة المضافة",
        "إعداد أكواد ونسب الضريبة وإنشاء الفترات ومراجعة مسودة الإقرار وتقديمها ومطابقتها مع الأستاذ.",
        [
            Screen("/taxes/codes", "أكواد الضرائب", "تعريف الكود وربط حسابات المدخلات والمخرجات."),
            Screen("/taxes/codes/create", "كود جديد", "إدخال هوية الكود ونوعه وحساباته."),
            Screen("/taxes/codes/{id}/edit", "تعديل الكود", "تعديل البيانات المسموح بها للكود."),
            Screen("/taxes/rates", "نسب الضرائب", "تسجيل النسبة وفترة السريان."),
            Screen("/taxes/periods", "الفترات الضريبية", "إنشاء الفترات ومتابعة حالاتها."),
            Screen("/taxes/periods/{id}", "تفاصيل الإقرار", "توليد المسودة ومراجعة المدخلات والمخرجات والصافي ثم التقديم."),
        ],
        ["فترة ضريبية", "توليد مسودة", "مراجعة", "مطابقة", "تقديم"],
        [
            "تقديم الإقرار يحتاج صلاحية taxes.file وتأكيدًا حساسًا.",
            "استخدم سجل VAT والملخص ومطابقة VAT مع الأستاذ قبل التقديم.",
            "النسبة تطبق حسب تاريخ السريان وليس بمجرد أحدث قيمة مسجلة.",
        ],
    ),
    Module(
        "المشروعات ومراكز التكلفة والموازنات",
        "أبعاد تحليلية للعمليات مع إدارة إصدارات الموازنة واعتمادها وقياس الانحراف عن الفعلي.",
        [
            Screen("/projects", "المشروعات", "الكود والحالة والتواريخ وقابلية الفوترة."),
            Screen("/cost-centers", "مراكز التكلفة", "إنشاء مراكز التحليل وإدارتها."),
            Screen("/budgeting/budgets", "الموازنات", "السنة والنسخة والعملة وبنود الحساب والمشروع ومركز التكلفة."),
            Screen("/budgeting/variance", "الموازنة مقابل الفعلي", "تصفية الفترة والأبعاد وعرض القيمة الفعلية والانحراف والنسبة."),
        ],
        ["مسودة موازنة", "إرسال", "اعتماد", "تفعيل", "أرشفة"],
        [
            "يسمح بموازنة نشطة واحدة لكل سنة مالية؛ تفعيل الجديدة يؤرشف السابقة.",
            "تقارير الانحراف تتطلب صلاحيات الموازنة والتقارير والمالية معًا.",
            "تظهر الأبعاد في العمليات التي تدعم المشروع أو مركز التكلفة.",
        ],
    ),
]


ROLES: list[tuple[str, str]] = [
    ("SUPER_ADMIN", "كل الصلاحيات دون استثناء، ويستخدم للإدارة العليا والطوارئ المنضبطة."),
    ("ERP_ADMIN", "كل الصلاحيات تقريبًا ما عدا إعادة فتح الفترة المالية."),
    ("ACCOUNTANT", "عرض الوحدات وتشغيل المحاسبة والخزينة والبنوك والشيكات والأصول والضرائب والترحيل المالي دون صلاحيات الحذف العامة."),
    ("SALES", "إدارة دورة المبيعات والعملاء دون الترحيل والعكس ما لم تمنح صلاحيات إضافية."),
    ("PURCHASING", "إدارة دورة المشتريات والموردين وعرض المخزون دون الترحيل والعكس افتراضيًا."),
    ("INVENTORY", "عمليات المخزون والجرد والتحويلات مع صلاحية تجاوز السالب ضمن القالب."),
    ("HR", "المرتبات مع صلاحية عرض بياناتها الحساسة، دون حذف افتراضي."),
    ("AUDITOR", "عرض واسع وسجل التدقيق والتقارير والبيانات المالية دون تشغيل يومي كامل."),
    ("VIEWER", "لوحة التحكم وعرض محدود؛ الوصول للتقارير المالية يحتاج الصلاحيات المساندة المطلوبة."),
    ("دور مخصص", "يبدأ بلا صلاحيات ويختار المسؤول أقل مجموعة لازمة للمهمة."),
]


FIRST_SETUP = [
    "أنشئ العملات المطلوبة وحدد العملة الأساسية وسجل أسعار الصرف ذات تاريخ النفاذ.",
    "سجل بيانات الشركة الأساسية والاسم العربي والإنجليزي والعملة والمرفقات.",
    "أضف الفروع التشغيلية المطلوبة. الفرع مرجع عمليات وتقارير وليس نطاق أمان مستقل.",
    "اضبط تسلسلات ترقيم كل نوع مستند: البادئة والسنة وعدد الخانات وسياسة إعادة الضبط.",
    "أنشئ المستخدمين والأدوار وامنح أقل صلاحيات لازمة لكل وظيفة.",
    "أنشئ السنة المالية وفتراتها الشهرية وحدد الفترة المفتوحة للعمل.",
    "جهز تصنيفات وأنواع الحسابات ثم ابن دليل الحسابات.",
    "اربط مفاتيح الترحيل بحسابات الأستاذ، وأضف ربط الفرع عند الحاجة التشغيلية.",
    "اربط حسابات الدليل ببنود الميزانية وقائمة الدخل والتدفقات النقدية.",
    "أدخل الأرصدة الافتتاحية وراجع توازن المدين والدائن قبل الترحيل.",
    "أنشئ العملاء والموردين والخزن والبنوك ووحدات القياس والتصنيفات والمنتجات والمخازن.",
    "نفذ سيناريو تجريبي كامل وراجع الأستاذ وميزان المراجعة والتقارير قبل بدء التشغيل الفعلي.",
]


ALL_ROUTE_GROUPS: list[tuple[str, list[tuple[str, str]]]] = [
    (
        "الدخول والصفحات العامة",
        [
            ("/login", "تسجيل الدخول"),
            ("/dashboard", "لوحة التحكم"),
            ("/notifications", "الإشعارات"),
            ("/foundation", "تشخيصات النظام"),
        ],
    ),
    (
        "المحاسبة",
        [(screen.route, screen.title) for screen in MODULES[0].screens],
    ),
    (
        "العملاء والموردون والخزينة",
        [
            ("/customers", "العملاء"),
            ("/customer-opening-balances", "افتتاحيات العملاء"),
            ("/customer-receipts", "سندات القبض"),
            ("/receivable-allocations", "تخصيصات العملاء"),
            ("/suppliers", "الموردون"),
            ("/supplier-opening-balances", "افتتاحيات الموردين"),
            ("/supplier-payments", "سندات الصرف"),
            ("/payable-allocations", "تخصيصات الموردين"),
            ("/cash-accounts", "حسابات الخزينة"),
            ("/bank-accounts", "الحسابات البنكية"),
            ("/treasury-transfers", "التحويلات"),
            ("/incoming-cheques", "الشيكات الواردة"),
            ("/outgoing-cheques", "الشيكات الصادرة"),
            ("/bank-reconciliations", "تسويات البنوك"),
            ("/bank-reconciliations/{id}", "تفاصيل تسوية بنك"),
        ],
    ),
    (
        "الكتالوج والمبيعات والمشتريات",
        [
            ("/catalog/products", "المنتجات والخدمات"),
            ("/catalog/categories", "تصنيفات المنتجات"),
            ("/catalog/uoms", "وحدات القياس"),
            ("/sales/orders", "أوامر البيع"),
            ("/sales/delivery-notes", "أذون التسليم"),
            ("/sales/invoices", "فواتير العملاء"),
            ("/sales/returns", "مرتجعات المبيعات"),
            ("/sales/credit-notes", "الإشعارات الدائنة"),
            ("/sales/invoice-revisions", "مراجعات الفواتير"),
            ("/sales/invoice-revisions/{id}", "تفاصيل مراجعة فاتورة"),
            ("/sales/receivable-settlements", "تسويات العملاء"),
            ("/purchasing/orders", "أوامر الشراء"),
            ("/purchasing/goods-receipts", "أذون الاستلام"),
            ("/purchasing/landed-costs", "تكاليف الوصول"),
            ("/purchasing/bills", "فواتير الموردين"),
            ("/purchasing/returns", "مرتجعات المشتريات"),
            ("/purchasing/adjustment-notes", "إشعارات تسوية المورد"),
            ("/purchasing/payable-settlements", "تسويات الموردين"),
        ],
    ),
    (
        "المخزون والمصروفات والمرتبات والإيجارات",
        [
            ("/inventory/warehouses", "المخازن"),
            ("/inventory/stock-balances", "أرصدة المخزون"),
            ("/inventory/transfers", "تحويلات المخزون"),
            ("/inventory/stock-counts", "جلسات الجرد"),
            ("/inventory/adjustments", "تسويات المخزون"),
            ("/expenses", "المصروفات"),
            ("/expenses/categories", "فئات المصروف"),
            ("/expenses/prepaids", "المصروفات المقدمة"),
            ("/expenses/accruals", "المصروفات المستحقة"),
            ("/payroll/employees", "موظفو المرتبات"),
            ("/payroll/components", "مكونات المرتب"),
            ("/payroll/runs", "كشوف المرتبات"),
            ("/rentals/items", "عناصر الإيجار"),
            ("/rentals/contracts", "عقود الإيجار"),
            ("/rentals/invoices", "فواتير الإيجار"),
            ("/rentals/handovers", "تسليمات الإيجار"),
            ("/rentals/returns", "عودات الإيجار"),
        ],
    ),
    (
        "الأصول الثابتة",
        [
            ("/fixed-asset-categories", "فئات الأصول"),
            ("/fixed-asset-locations", "مواقع الأصول"),
            ("/fixed-assets", "سجل الأصول"),
            ("/fixed-assets/create", "إضافة أصل"),
            ("/fixed-assets/{id}", "تفاصيل أصل"),
            ("/fixed-assets/{id}/edit", "تعديل أصل"),
            ("/fixed-assets-depreciation-runs", "جولات الإهلاك"),
            ("/fixed-assets-depreciation-runs/preview/{financialPeriodId}", "معاينة جولة الإهلاك"),
            ("/fixed-assets-depreciation-runs/{id}", "تفاصيل جولة الإهلاك"),
            ("/fixed-assets-disposals", "استبعادات الأصول"),
            ("/fixed-assets-disposals/{id}", "تفاصيل استبعاد أصل"),
        ],
    ),
    (
        "الضرائب والمشروعات والموازنات",
        [
            ("/taxes/codes", "أكواد الضرائب"),
            ("/taxes/codes/create", "إضافة كود ضريبة"),
            ("/taxes/codes/{id}/edit", "تعديل كود ضريبة"),
            ("/taxes/rates", "نسب الضرائب"),
            ("/taxes/periods", "الفترات الضريبية"),
            ("/taxes/periods/{id}", "تفاصيل الإقرار"),
            ("/projects", "المشروعات"),
            ("/cost-centers", "مراكز التكلفة"),
            ("/budgeting/budgets", "الموازنات"),
            ("/budgeting/variance", "الموازنة مقابل الفعلي"),
        ],
    ),
    (
        "الإعدادات وسجل التدقيق",
        [
            ("/settings", "مركز الإعدادات"),
            ("/settings/company", "بيانات الشركة"),
            ("/settings/branches", "الفروع"),
            ("/settings/numbering", "ترقيم المستندات"),
            ("/settings/users", "المستخدمون والأدوار"),
            ("/settings/branch-approval-rules", "قواعد اعتماد الفروع"),
            ("/audit-log", "سجل التدقيق"),
        ],
    ),
    (
        "التقارير",
        [
            ("/reports", "مركز التقارير"),
            ("/reports/customer-statement", "كشف حساب عميل"),
            ("/reports/supplier-statement", "كشف حساب مورد"),
            ("/reports/ar-aging", "أعمار ديون العملاء"),
            ("/reports/ap-aging", "أعمار ديون الموردين"),
            ("/reports/cash-book", "دفتر الخزينة"),
            ("/reports/bank-book", "دفتر البنك"),
            ("/reports/cheque-register", "سجل الشيكات"),
            ("/reports/bank-reconciliations", "تقرير تسويات البنوك"),
            ("/reports/bank-reconciliations/{id}", "تفاصيل تقرير تسوية بنك"),
            ("/reports/ar-gl-reconciliation", "مطابقة العملاء مع الأستاذ"),
            ("/reports/ap-gl-reconciliation", "مطابقة الموردين مع الأستاذ"),
            ("/reports/sales-orders", "تقرير أوامر البيع"),
            ("/reports/purchase-orders", "تقرير أوامر الشراء"),
            ("/reports/delivery-notes", "تقرير أذون التسليم"),
            ("/reports/goods-receipts", "تقرير أذون الاستلام"),
            ("/reports/customer-invoices", "تقرير فواتير العملاء"),
            ("/reports/supplier-bills", "تقرير فواتير الموردين"),
            ("/reports/stock-movements", "تقرير حركات المخزون"),
            ("/reports/branch-operations", "تشغيل الفروع"),
            ("/reports/branch-profitability", "ربحية الفروع"),
            ("/reports/project-profitability", "ربحية المشروعات"),
            ("/reports/cost-center-actuals", "فعليات مراكز التكلفة"),
            ("/reports/rentals", "تشغيل الإيجارات"),
            ("/reports/balance-sheet", "الميزانية العمومية"),
            ("/reports/income-statement", "قائمة الدخل"),
            ("/reports/cash-flow", "التدفقات النقدية"),
            ("/reports/fixed-asset-register", "سجل الأصول الثابتة"),
            ("/reports/fixed-asset-net-book-values", "صافي القيمة الدفترية"),
            ("/reports/fixed-asset-depreciation", "جدول إهلاك الأصول"),
            ("/reports/fixed-asset-depreciation-runs", "تقرير جولات الإهلاك"),
            ("/reports/fixed-asset-disposals", "تقرير استبعادات الأصول"),
            ("/reports/vat-register", "سجل القيمة المضافة"),
            ("/reports/vat-summary", "ملخص القيمة المضافة"),
            ("/reports/vat-gl-reconciliation", "مطابقة القيمة المضافة مع الأستاذ"),
        ],
    ),
]


def screen_table(screens: Sequence[Screen]) -> LongTable:
    header = [
        RTLText("المسار", font_name=FONT_BOLD, font_size=9.5, color=WHITE),
        RTLText("الصفحة وما تفعله", font_name=FONT_BOLD, font_size=9.5, color=WHITE),
    ]
    rows: list[list[object]] = [header]
    for screen in screens:
        rows.append(
            [
                LTRText(screen.route, font_name=FONT_BOLD, font_size=8.4, color=BLUE_DARK),
                [
                    RTLText(screen.title, font_name=FONT_BOLD, font_size=10.5, leading=16.5, color=NAVY),
                    Spacer(1, 3),
                    RTLText(screen.description, font_size=9.5, leading=15.5, color=SLATE),
                ],
            ]
        )

    table = LongTable(
        rows,
        colWidths=[60 * mm, CONTENT_WIDTH - 60 * mm],
        repeatRows=1,
        hAlign="CENTER",
        splitByRow=1,
        splitInRow=0,
    )
    style = [
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("BOX", (0, 0), (-1, -1), 0.6, BORDER),
        ("INNERGRID", (0, 0), (-1, -1), 0.35, BORDER),
        ("LEFTPADDING", (0, 0), (-1, -1), 7),
        ("RIGHTPADDING", (0, 0), (-1, -1), 7),
        ("TOPPADDING", (0, 0), (-1, -1), 7),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
    ]
    for row in range(1, len(rows)):
        if row % 2 == 0:
            style.append(("BACKGROUND", (0, row), (-1, row), PALE))
    table.setStyle(TableStyle(style))
    return table


def role_table() -> LongTable:
    rows: list[list[object]] = [
        [
            RTLText("الدور", font_name=FONT_BOLD, font_size=9.5, color=WHITE),
            RTLText("النطاق الافتراضي", font_name=FONT_BOLD, font_size=9.5, color=WHITE),
        ]
    ]
    for role, scope in ROLES:
        role_label = (
            RTLText(role, font_name=FONT_BOLD, font_size=9.2, color=BLUE_DARK)
            if contains_arabic(role)
            else LTRText(role, font_name=FONT_BOLD, font_size=9.2, color=BLUE_DARK)
        )
        rows.append(
            [
                role_label,
                RTLText(scope, font_size=10, leading=16.5, color=SLATE),
            ]
        )

    table = LongTable(
        rows,
        colWidths=[44 * mm, CONTENT_WIDTH - 44 * mm],
        repeatRows=1,
        splitByRow=1,
        splitInRow=0,
    )
    style = [
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("BOX", (0, 0), (-1, -1), 0.6, BORDER),
        ("INNERGRID", (0, 0), (-1, -1), 0.35, BORDER),
        ("LEFTPADDING", (0, 0), (-1, -1), 8),
        ("RIGHTPADDING", (0, 0), (-1, -1), 8),
        ("TOPPADDING", (0, 0), (-1, -1), 8),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 8),
    ]
    for row in range(1, len(rows)):
        if row % 2 == 0:
            style.append(("BACKGROUND", (0, row), (-1, row), PALE))
    table.setStyle(TableStyle(style))
    return table


def route_directory_table(group_title: str, routes: Sequence[tuple[str, str]]) -> LongTable:
    rows: list[list[object]] = [
        [
            RTLText(group_title, font_name=FONT_BOLD, font_size=12, leading=18, color=WHITE),
            "",
        ],
        [
            RTLText("المسار", font_name=FONT_BOLD, font_size=9.5, color=WHITE),
            RTLText("اسم الشاشة", font_name=FONT_BOLD, font_size=9.5, color=WHITE),
        ]
    ]
    for route, title in routes:
        rows.append(
            [
                LTRText(route, font_name=FONT_BOLD, font_size=8.3, color=BLUE_DARK),
                RTLText(title, font_size=9.5, leading=15, color=SLATE),
            ]
        )

    table = LongTable(
        rows,
        colWidths=[90 * mm, CONTENT_WIDTH - 90 * mm],
        repeatRows=2,
        hAlign="CENTER",
        splitByRow=1,
        splitInRow=0,
    )
    style = [
        ("SPAN", (0, 0), (-1, 0)),
        ("BACKGROUND", (0, 0), (-1, 0), INDIGO),
        ("BACKGROUND", (0, 1), (-1, 1), BLUE_DARK),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("BOX", (0, 0), (-1, -1), 0.5, BORDER),
        ("INNERGRID", (0, 0), (-1, -1), 0.3, BORDER),
        ("LEFTPADDING", (0, 0), (-1, -1), 7),
        ("RIGHTPADDING", (0, 0), (-1, -1), 7),
        ("TOPPADDING", (0, 0), (-1, -1), 7),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 7),
    ]
    for row in range(2, len(rows)):
        if row % 2 == 1:
            style.append(("BACKGROUND", (0, row), (-1, row), PALE))
    table.setStyle(TableStyle(style))
    return table


class StoryBuilder:
    def __init__(self) -> None:
        self.story: list[Flowable] = []
        self.heading_counter = 0

    def heading(self, title: str, *, level: int = 0, page_break: bool = False) -> None:
        if page_break and self.story:
            self.story.append(PageBreak())
        elif level > 0:
            self.story.append(CondPageBreak(38 * mm))
        self.heading_counter += 1
        bookmark = f"heading-{self.heading_counter}"
        self.story.extend(
            [
                RTLHeading(title, level=level, bookmark=bookmark),
                Spacer(1, 10 if level == 0 else 7),
            ]
        )

    def outline_marker(self, title: str, *, level: int) -> None:
        self.story.append(CondPageBreak(45 * mm))
        self.heading_counter += 1
        self.story.append(
            OutlineMarker(title, level=level, bookmark=f"heading-{self.heading_counter}")
        )

    def p(self, text: str, *, size: float = 11.2, color: colors.Color = SLATE) -> None:
        self.story.extend([paragraph(text, size=size, color=color), Spacer(1, 7)])

    def bullets(self, items: Iterable[str]) -> None:
        for item in items:
            self.story.extend([bullet(item), Spacer(1, 3)])
        self.story.append(Spacer(1, 6))


def build_story() -> list[Flowable]:
    builder = StoryBuilder()
    story = builder.story

    story.extend(
        [
            Spacer(1, 31 * mm),
            RTLText(
                "الدليل الشامل لنظام Mini ERP",
                font_name=FONT_BOLD,
                font_size=34,
                leading=48,
                color=WHITE,
            ),
            Spacer(1, 5 * mm),
            RTLText(
                "دليل المستخدم والإدارة والتشغيل المالي",
                font_name=FONT_BOLD,
                font_size=18.5,
                leading=28,
                color=colors.HexColor("#BFDBFE"),
            ),
            Spacer(1, 16 * mm),
        ]
    )

    stat_rows = [
        [
            [
                Paragraph(
                    "132",
                    ParagraphStyle(
                        "CoverStat",
                        fontName=FONT_BOLD,
                        fontSize=25,
                        leading=29,
                        textColor=WHITE,
                        alignment=TA_RIGHT,
                    ),
                ),
                RTLText("شاشة فعلية", font_size=10.5, leading=16, color=colors.HexColor("#BFDBFE")),
            ],
            [
                Paragraph(
                    "35",
                    ParagraphStyle(
                        "CoverStat2",
                        fontName=FONT_BOLD,
                        fontSize=25,
                        leading=29,
                        textColor=WHITE,
                        alignment=TA_RIGHT,
                    ),
                ),
                RTLText("شاشة تقارير", font_size=10.5, leading=16, color=colors.HexColor("#BFDBFE")),
            ],
            [
                Paragraph(
                    "AR + EN",
                    ParagraphStyle(
                        "CoverStat3",
                        fontName=FONT_BOLD,
                        fontSize=20,
                        leading=29,
                        textColor=WHITE,
                        alignment=TA_RIGHT,
                    ),
                ),
                RTLText("واجهة ثنائية اللغة", font_size=10.5, leading=16, color=colors.HexColor("#BFDBFE")),
            ],
        ]
    ]
    stats = Table(stat_rows, colWidths=[CONTENT_WIDTH / 3] * 3)
    stats.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), colors.HexColor("#172554")),
                ("BOX", (0, 0), (-1, -1), 0.8, colors.HexColor("#60A5FA")),
                ("INNERGRID", (0, 0), (-1, -1), 0.8, colors.HexColor("#60A5FA")),
                ("LEFTPADDING", (0, 0), (-1, -1), 11),
                ("RIGHTPADDING", (0, 0), (-1, -1), 11),
                ("TOPPADDING", (0, 0), (-1, -1), 14),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 14),
                ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
            ]
        )
    )
    story.extend(
        [
            stats,
            Spacer(1, 22 * mm),
            RTLText(
                "الإصدار 1.1 | 31 أغسطس 2026",
                font_name=FONT_BOLD,
                font_size=12.5,
                leading=20,
                color=colors.HexColor("#DBEAFE"),
            ),
            Spacer(1, 3 * mm),
            RTLText(
                "مبني على تطبيق Laravel الفعلي ومساراته وصلاحياته الحالية",
                font_size=11,
                leading=18,
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
            fontName=FONT_BOLD,
            fontSize=12.5,
            leading=22,
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
            leading=18,
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
                "ابدأ بالتهيئة والصلاحيات، ثم انتقل إلى الوحدة التي تعمل عليها. يوجد في نهاية الدليل فهرس كامل لكل شاشة فعلية ومسارها.",
            ),
            Spacer(1, 10),
            toc,
        ]
    )

    builder.heading("عن النظام ونطاق هذا الدليل", page_break=True)
    builder.p(
        "Mini ERP هو نظام تخطيط موارد مؤسسي مالي وتشغيلي مبني على Laravel وInertia وReact. يجمع المحاسبة والعملاء والموردين والمبيعات والمشتريات والمخزون والخزينة والمصروفات والمرتبات والإيجارات والأصول والضرائب والموازنات والتقارير في تطبيق واحد."
    )
    story.append(
        cards(
            [
                ("تثبيت واحد", "النظام ليس منصة متعددة المستأجرين. بيانات الشركة إعداد للنشاط الحالي وليست مالكًا أمنيًا لبيانات منفصلة."),
                ("الفروع", "الفرع مرجع تشغيلي للتقارير والمخازن والحركات وقواعد الاعتماد، وليس نطاق تسجيل دخول أو عزل بيانات."),
                ("محاسبة محكومة", "الترحيل يمر عبر محرك واحد يطبق التوازن والفترة المفتوحة والعملة والربط المحاسبي."),
                ("صلاحيات وتدقيق", "تظهر الروابط والأزرار حسب صلاحية المستخدم وتسجل العمليات المهمة في سجل التدقيق."),
            ]
        )
    )
    story.extend([Spacer(1, 8), callout(
        "مصدر الحقيقة",
        "يعتمد الدليل على تطبيق laravel الفعلي وعدد 132 شاشة Inertia نشطة. لم يتم اعتبار تطبيق Next.js القديم أو المواصفات المستقبلية وظائف جاهزة.",
        background=EMERALD_PALE,
        border=EMERALD,
    )])
    builder.heading("ما ليس ضمن الوظائف النشطة", level=1)
    builder.bullets(
        [
            "قسم مستقل للمعدات والصيانة، والشركاء وحقوق الملكية، والمعاملات المتكررة.",
            "عروض الأسعار وطلبات الشراء والتنبؤات وإعادة تقييم الأصول.",
            "قسائم المرتبات والقروض والسلف والحضور والتأمينات والضرائب الخاصة بالمرتبات كصفحات مستقلة.",
            "البحث العام وQuick Create وملف المستخدم كما وردت في بعض الخطط القديمة.",
        ]
    )

    builder.heading("تسجيل الدخول والواجهة والجولة الإرشادية", page_break=True)
    builder.p(
        "افتح صفحة تسجيل الدخول، واختر اللغة والمظهر، ثم أدخل البريد وكلمة المرور اللذين خصصهما المسؤول. بعد الدخول، تعرض القائمة الجانبية فقط الوحدات المسموح بها لحسابك."
    )
    story.extend(workflow_diagram(["تسجيل الدخول", "اختيار القسم", "تشغيل الجولة", "تنفيذ المهمة"]))
    builder.heading("زر الجولة الإرشادية", level=1)
    builder.bullets(
        [
            "ستجد زر جولة إرشادية في الشريط العلوي لكل شاشة داخل النظام، وفي صفحة تسجيل الدخول أيضًا.",
            "تبدأ الجولة بعنوان الصفحة ثم توضح إجراءاتها ونماذجها أو مرشحاتها وجدول النتائج والقائمة الجانبية والأدوات العامة.",
            "تتخطى الجولة أي عنصر غير موجود أو غير ظاهر في الشاشة الحالية، لذلك تتكيف مع الصلاحيات وحجم الشاشة.",
            "استخدم التالي والسابق أو مفاتيح الأسهم، واضغط Escape للإغلاق. تعود لوحة المفاتيح إلى زر الجولة بعد الانتهاء.",
            "الجولة للشرح فقط ولا تضغط أزرار الحفظ أو الاعتماد أو الترحيل نيابة عن المستخدم.",
        ]
    )
    story.append(
        callout(
            "مهم",
            "الإجراء غير الظاهر غالبًا غير مسموح به لصلاحياتك أو غير متاح في حالة المستند الحالية. لا تعتبر غياب الزر خطأ قبل مراجعة الدور والحالة والفترة.",
            background=AMBER_PALE,
            border=AMBER,
        )
    )
    builder.heading("مكونات الواجهة", level=1)
    story.append(
        cards(
            [
                ("القائمة الجانبية", "تنقل بين الوحدات، ويمكن طيها على سطح المكتب أو فتحها من زر القائمة على الهاتف."),
                ("الشريط العلوي", "الجولة والإشعارات واللغة والمظهر وحالة النظام وقائمة المستخدم."),
                ("عنوان الصفحة", "يوضح الغرض وغالبًا يضم أزرار الإنشاء أو التصدير أو الطباعة."),
                ("الحالات", "شارات توضح مسودة أو مرسل أو معتمد أو مرحل أو ملغي أو مكتمل حسب نوع المستند."),
            ]
        )
    )

    builder.heading("التهيئة لأول مرة", page_break=True)
    builder.p(
        "ينفذ مسؤول النظام والمحاسب الرئيسي الخطوات التالية بالترتيب. تغيير هذا الترتيب قد يسبب غياب حسابات الترحيل أو العملات أو الفترات عند بدء العمليات."
    )
    story.extend([numbered_table(FIRST_SETUP), Spacer(1, 8)])
    story.append(
        callout(
            "بوابة الجاهزية",
            "قبل التشغيل الفعلي اختبر دورة بيع وشراء وترحيل وقبض ودفع وجرد، ثم راجع ميزان المراجعة والمطابقات وسجل التدقيق باستخدام مستخدم بصلاحيات حقيقية.",
            background=EMERALD_PALE,
            border=EMERALD,
        )
    )

    builder.heading("الأدوار والصلاحيات", page_break=True)
    builder.p(
        "يعتمد النظام على أدوار قالبية وأدوار مخصصة. القاعدة الموصى بها هي أقل صلاحية لازمة، مع فصل الإنشاء عن الاعتماد والترحيل كلما سمح حجم الفريق."
    )
    story.extend([role_table(), Spacer(1, 8)])
    builder.heading("قدرات حساسة مستقلة", level=1)
    builder.bullets(
        [
            "عرض البيانات المالية view_financials وعرض بيانات المرتبات view_payroll.",
            "تجاوز حد الائتمان وتجاوز المخزون السالب.",
            "إغلاق الفترة وإعادة فتحها، وتقديم الإقرار الضريبي.",
            "إدارة العملات وأسعار الصرف وتصنيفات الحسابات والربط المحاسبي.",
            "بعض أزرار الترحيل والعكس والإغلاق تطلب تأكيدًا حساسًا وسببًا أو رمز إجراء.",
        ]
    )
    story.append(
        callout(
            "ملاحظة التقارير",
            "الوصول إلى مجموعة التقارير الحالية يحتاج reports.view وview_financials معًا. منح reports.view وحدها قد لا يكفي لفتح مركز التقارير.",
            background=AMBER_PALE,
            border=AMBER,
        )
    )

    for module in MODULES:
        builder.heading(module.title, page_break=True)
        builder.p(module.purpose)
        story.extend(workflow_diagram(module.workflow))
        story.append(Spacer(1, 4))
        story.append(screen_table(module.screens))
        story.append(Spacer(1, 8))
        builder.heading("ضوابط مهمة", level=1)
        builder.bullets(module.controls)

    builder.heading("مركز التقارير", page_break=True)
    builder.p(
        "يجمع مركز التقارير 35 شاشة مالية وفرعية وتشغيلية وضريبية. تعرض الأزرار والمرشحات وفق التقرير والصلاحية، لذلك استخدم التصدير أو الطباعة فقط عندما يظهر الزر."
    )
    story.append(
        cards(
            [
                ("القوائم المالية", "الميزانية العمومية وقائمة الدخل والتدفقات النقدية."),
                ("العملاء والموردون", "الكشوف والأعمار ومطابقة AR وAP مع الأستاذ."),
                ("الخزينة والبنوك", "دفتر الخزينة والبنك والشيكات وتسويات البنك."),
                ("التشغيل", "أوامر البيع والشراء والتسليم والاستلام والفواتير وحركات المخزون."),
                ("الأبعاد", "الفروع والمشروعات ومراكز التكلفة والموازنة مقابل الفعلي."),
                ("الأصول والضرائب والإيجارات", "سجل وإهلاك واستبعاد الأصول، VAT، وتشغيل الإيجارات."),
            ]
        )
    )
    builder.heading("طريقة الاستخدام العامة", level=1)
    builder.bullets(
        [
            "اختر الفترة أو التاريخ المرجعي والعملة المطلوبة.",
            "حدد الفرع أو المشروع أو مركز التكلفة أو الحالة عندما تتوفر هذه المرشحات.",
            "طبق المرشحات وراجع إجماليات الملخص قبل تفاصيل السطور.",
            "افتح المستند المصدر من الرابط عندما يكون متاحًا.",
            "صدّر CSV أو استخدم الطباعة إذا ظهر الزر وكانت الصلاحية متاحة.",
            "عند وجود أكثر من عملة، لا تفسر الإجمالي المختلط كعملة واحدة إلا إذا صرح التقرير بذلك.",
        ]
    )

    builder.heading("الإعدادات والإشعارات والمرفقات وسجل التدقيق", page_break=True)
    story.append(
        cards(
            [
                ("بيانات الشركة", "الاسم العربي والإنجليزي والعملة الأساسية والمرفقات."),
                ("الفروع", "كود الفرع والاسم والحالة والمرفقات، كمرجع تشغيلي لا أمني."),
                ("ترقيم المستندات", "المفتاح والبادئة والسنة والخانات وسياسة إعادة الضبط والرقم التالي."),
                ("المستخدمون والأدوار", "إنشاء وتعديل وتعطيل المستخدم وتعيين أو سحب الأدوار والصلاحيات."),
                ("قواعد الاعتماد", "نوع المستند وطريقة مطابقة الفرع والفرع الاختياري والصلاحية المطلوبة."),
                ("سجل التدقيق", "تصفية حسب المستخدم والنوع والإجراء والطلب والتاريخ ثم عرض تفاصيل التغيير."),
            ]
        )
    )
    builder.heading("المرفقات", level=1)
    builder.bullets(
        [
            "الحد الأقصى للملف 10 MB.",
            "الصيغ المدعومة: PDF وPNG وJPG وWEBP وTXT وCSV وXLSX وDOCX.",
            "قد تجعل سياسة فئة المصروف أو نوع العملية إرفاق المستند إلزاميًا.",
            "حذف المرفق يحتاج صلاحية الإجراء ولا يلغي أثر المستند المالي نفسه.",
        ]
    )
    builder.heading("الإشعارات", level=1)
    builder.bullets(
        [
            "يعرض الجرس عدد الإشعارات غير المقروءة وآخر الأحداث.",
            "يمكن فتح صفحة الإشعارات وقراءة إشعار منفرد أو تعليم الجميع كمقروء.",
        ]
    )

    builder.heading("سيناريوهات تشغيل كاملة", page_break=True)
    scenarios = [
        ("دورة البيع", ["عميل", "أمر بيع", "تأكيد", "إذن تسليم", "فاتورة", "ترحيل", "قبض", "تخصيص"]),
        ("دورة الشراء", ["مورد", "أمر شراء", "تأكيد", "استلام", "تكلفة وصول", "فاتورة", "ترحيل", "دفع"]),
        ("الجرد", ["جلسة جرد", "إدخال المعدود", "إرسال", "اعتماد", "ترحيل الفروق"]),
        ("الإيجار", ["عنصر", "عقد", "تفعيل", "تسليم", "عودة وفحص", "فاتورة"]),
        ("الأصل", ["إضافة", "رسملة", "جدول إهلاك", "جولة دورية", "نقل أو استبعاد"]),
    ]
    for title, steps in scenarios:
        builder.heading(title, level=1)
        story.extend(workflow_diagram(steps))

    builder.heading("إغلاق الشهر", level=1)
    builder.bullets(
        [
            "راجع المستندات المرسلة أو المعتمدة غير المرحلة.",
            "أكمل التسويات البنكية وتخصيصات العملاء والموردين.",
            "رحل الإهلاك والمصروفات المقدمة والمستحقة والمرتبات المطلوبة.",
            "راجع VAT والمطابقات وميزان المراجعة والقوائم المالية.",
            "افتح فحص جاهزية الفترة وعالج كل مانع ظاهر.",
            "أغلق الفترة بصلاحية close_period. إعادة الفتح تحتاج صلاحية مستقلة وحوكمة واضحة.",
        ]
    )

    builder.heading("القواعد العامة وحل المشكلات", page_break=True)
    story.append(
        cards(
            [
                ("زر غير ظاهر", "راجع صلاحية المستخدم وحالة المستند. الواجهة تخفي الإجراء غير المسموح أو غير المناسب للحالة."),
                ("فشل الترحيل", "راجع الفترة المفتوحة والتوازن والعملة وسعر الصرف وربط الحسابات والمستندات المصدرية."),
                ("فرق تسوية البنك", "راجع السطور غير المطابقة والقيم والتواريخ؛ لا يمكن الإنهاء حتى يصبح الفرق صفرًا."),
                ("إجمالي بعملات مختلطة", "اختر عملة التقرير أو اقرأ وسم العملات المختلطة بدل نسب الإجمالي إلى EGP تلقائيًا."),
                ("مخزون غير كاف", "راجع المخزن والكمية والحركات؛ التجاوز يحتاج صلاحية مستقلة عند السماح به."),
                ("مستند مرحل", "لا تعدله أو تحذفه. استخدم العكس أو المرتجع أو الإشعار المقابل حسب الوحدة."),
            ]
        )
    )
    story.extend([Spacer(1, 8), callout(
        "قاعدة الأمان",
        "لا تشارك حساب SUPER_ADMIN للعمل اليومي. أنشئ مستخدمًا لكل شخص، واستخدم الأدوار المخصصة عند الحاجة، وراجع سجل التدقيق دوريًا.",
        background=RED_PALE,
        border=RED,
    )])

    builder.heading("فهرس جميع شاشات النظام", page_break=True)
    route_count = sum(len(routes) for _, routes in ALL_ROUTE_GROUPS)
    builder.p(
        f"الفهرس التالي يحصر {route_count} شاشة Inertia فعلية ومسار الوصول إليها. المسارات التي تحتوي على id تفتح سجلًا محددًا من الصفحة الأم."
    )
    assert route_count == 132, f"Expected 132 active screens, found {route_count}."

    for group_title, routes in ALL_ROUTE_GROUPS:
        builder.outline_marker(group_title, level=1)
        story.extend([route_directory_table(group_title, routes), Spacer(1, 9)])

    builder.heading("قاموس الحالات والمصطلحات", page_break=True)
    story.append(
        cards(
            [
                ("مسودة", "سجل قابل للتعديل ولم يدخل دورة الاعتماد أو الأثر المالي النهائي."),
                ("مرسل", "اكتمل إدخاله وينتظر المراجعة أو الاعتماد."),
                ("معتمد", "تمت الموافقة عليه لكنه قد ينتظر الترحيل أو التنفيذ التشغيلي."),
                ("مرحل", "أنشأ أثره المالي أو التشغيلي النهائي وأصبح غير قابل للتعديل المباشر."),
                ("ملغي", "أوقف قبل الترحيل وفق قواعد الوحدة، ولا يعني حذف السجل."),
                ("معكوس", "تم إنشاء حركة مقابلة تلغي أثر الحركة المرحلة مع بقاء الأثر التاريخي."),
                ("AR", "حسابات العملاء والمبالغ المدينة المستحقة للنشاط."),
                ("AP", "حسابات الموردين والمبالغ الدائنة واجبة السداد."),
                ("GL", "دفتر الأستاذ العام الذي يستقبل الحركات المالية المرحلة."),
                ("VAT", "ضريبة القيمة المضافة للمدخلات والمخرجات والإقرار والمطابقة."),
                ("CSV", "ملف بيانات نصي للتصدير والتحليل عند توفر زر التصدير."),
                ("RTL", "عرض الواجهة من اليمين إلى اليسار عند اختيار العربية."),
            ]
        )
    )
    story.extend([Spacer(1, 10), callout(
        "نهاية الدليل",
        "استخدم زر الجولة الإرشادية داخل أي صفحة لتفسير عناصرها الحالية، وارجع إلى هذا الدليل لفهم دورة العمل الكاملة والضوابط بين الوحدات.",
        background=EMERALD_PALE,
        border=EMERALD,
    )])

    return story


def main() -> None:
    register_fonts()
    OUTPUT_PATH.parent.mkdir(parents=True, exist_ok=True)
    document = GuideDocTemplate(str(OUTPUT_PATH))
    story = build_story()
    document.multiBuild(story)
    print(OUTPUT_PATH)


if __name__ == "__main__":
    main()
