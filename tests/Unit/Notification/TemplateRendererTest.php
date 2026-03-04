<?php

declare(strict_types=1);

namespace ERTAppointment\Tests\Unit\Notification;

use PHPUnit\Framework\TestCase;
use ERTAppointment\Domain\Notification\TemplateRenderer;

final class TemplateRendererTest extends TestCase
{
    private TemplateRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new TemplateRenderer();
    }

    public function test_replaces_known_tokens(): void
    {
        $context = [
            'customer_name'    => 'Ali Veli',
            'appointment_date' => '2025-06-15',
            'appointment_time' => '10:00',
        ];

        $result = $this->renderer->render(
            'Randevunuz: {{appointment_date}}',
            'Merhaba {{customer_name}}, randevunuz {{appointment_date}} {{appointment_time}}.',
            $context
        );

        $this->assertSame('Randevunuz: 2025-06-15', $result['subject']);
        $this->assertSame('Merhaba Ali Veli, randevunuz 2025-06-15 10:00.', $result['body']);
    }

    public function test_unknown_tokens_are_left_as_is(): void
    {
        $result = $this->renderer->render('', 'Hello {{unknown_token}}', []);

        $this->assertStringContainsString('{{unknown_token}}', $result['body']);
    }

    public function test_empty_template_returns_empty_strings(): void
    {
        $result = $this->renderer->render('', '', []);

        $this->assertSame('', $result['subject']);
        $this->assertSame('', $result['body']);
    }

    public function test_available_placeholders_returns_array(): void
    {
        $hints = $this->renderer->availablePlaceholders();

        $this->assertIsArray($hints);
        $this->assertNotEmpty($hints);

        foreach ($hints as $hint) {
            $this->assertArrayHasKey('token', $hint);
            $this->assertArrayHasKey('description', $hint);
            $this->assertStringStartsWith('{{', $hint['token']);
            $this->assertStringEndsWith('}}', $hint['token']);
        }
    }

    public function test_render_returns_subject_and_body_keys(): void
    {
        $result = $this->renderer->render('Subject', 'Body', []);

        $this->assertArrayHasKey('subject', $result);
        $this->assertArrayHasKey('body', $result);
    }

    public function test_multiple_occurrences_of_same_token_are_replaced(): void
    {
        $result = $this->renderer->render(
            '',
            '{{customer_name}} - {{customer_name}}',
            ['customer_name' => 'Ayşe']
        );

        $this->assertSame('Ayşe - Ayşe', $result['body']);
    }
}
