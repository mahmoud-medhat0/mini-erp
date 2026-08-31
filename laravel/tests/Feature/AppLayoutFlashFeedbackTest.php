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
}
