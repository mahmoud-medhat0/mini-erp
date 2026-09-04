<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppLayoutFlashFeedbackTest extends TestCase
{
    public function test_app_layout_renders_accessible_flash_feedback_from_shared_props(): void
    {
        $source = (string) file_get_contents(resource_path('js/Components/AppLayout.tsx'));

        $this->assertStringContainsString('buildFlashNotices(props.flash', $source);
        $this->assertStringContainsString('role={isError ?', $source);
        $this->assertStringContainsString("aria-live={isError ? 'assertive' : 'polite'}", $source);
        $this->assertStringContainsString('aria-atomic="true"', $source);
        $this->assertStringContainsString('{notice.message}', $source);
    }

    public function test_flash_feedback_can_be_dismissed_without_stealing_keyboard_focus(): void
    {
        $source = (string) file_get_contents(resource_path('js/Components/AppLayout.tsx'));

        $this->assertStringContainsString('window.setTimeout', $source);
        $this->assertStringContainsString('data-flash-dismiss', $source);
        $this->assertStringContainsString('onFocusCapture={clearDismissTimer}', $source);
        $this->assertStringContainsString('event.detail === 0', $source);
        $this->assertStringContainsString('mainContentRef.current?.focus({ preventScroll: true })', $source);
        $this->assertStringContainsString("router.on('success'", $source);
    }

    public function test_app_layout_supplies_safe_automatic_pagination_for_paginated_pages(): void
    {
        $layout = (string) file_get_contents(resource_path('js/Components/AppLayout.tsx'));
        $automatic = (string) file_get_contents(resource_path('js/Components/UniversalPagination.tsx'));
        $primitives = (string) file_get_contents(resource_path('js/Components/Primitives.tsx'));

        $this->assertStringContainsString("pagination = 'auto'", $layout);
        $this->assertStringContainsString('<UniversalPagination', $layout);
        $this->assertStringContainsString('if (paginators.length !== 1) return null', $automatic);
        $this->assertStringContainsString('paginator.last_page > 1', $automatic);
        $this->assertStringContainsString('paginationUrlWithCurrentQuery', $primitives);
        $this->assertStringContainsString('data-pagination-controls', $primitives);

        // GeneralJournal.tsx was migrated to ServerDataTable (no more Laravel
        // paginator prop), so AppLayout's automatic pagination detector has
        // nothing to find there and the manual opt-out is no longer needed.
        $manualPages = [
            'js/Pages/AuditLog/Index.tsx',
            'js/Pages/Budgeting/Budgets.tsx',
            'js/Pages/CostCenters/Index.tsx',
            'js/Pages/Projects/Index.tsx',
        ];

        foreach ($manualPages as $path) {
            $this->assertStringContainsString('pagination="manual"', (string) file_get_contents(resource_path($path)), $path);
        }
    }
}
