<?php

declare(strict_types=1);

namespace ERTAppointment\Tests\Unit\Settings;

use PHPUnit\Framework\TestCase;
use ERTAppointment\Settings\SettingsManager;

final class SettingsManagerTest extends TestCase
{
    private SettingsManager $manager;

    protected function setUp(): void
    {
        // Use in-memory SQLite via wpdb stub.
        $this->manager = new SettingsManager($this->makeWpdbStub());
    }

    public function test_set_and_get_global_setting(): void
    {
        $this->manager->set('global', null, 'slot_duration_minutes', 45);
        $value = $this->manager->getGlobal('slot_duration_minutes');

        $this->assertSame(45, $value);
    }

    public function test_get_returns_default_when_key_missing(): void
    {
        $value = $this->manager->getGlobal('nonexistent_key', 'default_val');

        $this->assertSame('default_val', $value);
    }

    public function test_bulk_set_saves_multiple_keys(): void
    {
        $this->manager->bulkSet('global', null, [
            'auto_confirm'  => true,
            'currency'      => 'TRY',
            'max_advance_days' => 90,
        ]);

        $this->assertTrue($this->manager->getGlobal('auto_confirm'));
        $this->assertSame('TRY', $this->manager->getGlobal('currency'));
        $this->assertSame(90, $this->manager->getGlobal('max_advance_days'));
    }

    public function test_get_all_returns_array(): void
    {
        $this->manager->set('global', null, 'currency', 'USD');
        $all = $this->manager->getAll('global', null);

        $this->assertIsArray($all);
        $this->assertArrayHasKey('currency', $all);
    }

    public function test_department_scope_does_not_leak_to_global(): void
    {
        $this->manager->set('global',     null, 'slot_duration_minutes', 30);
        $this->manager->set('department', 5,    'slot_duration_minutes', 60);

        $this->assertSame(30, $this->manager->getGlobal('slot_duration_minutes'));
    }

    // ── Stub ─────────────────────────────────────────────────────────────

    private function makeWpdbStub(): object
    {
        // Simple in-memory store so tests run without a real DB.
        return new class {
            private array $store = [];
            public string $prefix = 'wp_';

            public function get_var(string $q): mixed { return null; }
            public function get_row(string $q): mixed  { return null; }

            public function get_results(string $q): array
            {
                // Return all stored rows for the queried scope.
                preg_match("/scope = '([^']+)' AND scope_id = '?(\w+)'?/", $q, $m);
                $key = ($m[1] ?? '') . ':' . ($m[2] ?? 'null');
                return $this->store[$key] ?? [];
            }

            public function prepare(string $q, mixed ...$args): string
            {
                // Minimal sprintf-style substitution for tests.
                return vsprintf(str_replace('%s', "'%s'", str_replace('%d', '%d', $q)), $args);
            }

            public function replace(string $table, array $data): void
            {
                $key = ($data['scope'] ?? '') . ':' . ($data['scope_id'] ?? 'null');
                $this->store[$key][] = (object) $data;
            }

            public function delete(string $table, array $where): void {}
        };
    }
}
