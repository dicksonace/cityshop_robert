<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComputerOsOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_computer_operating_system_includes_older_windows(): void
    {
        $expected = [
            'Windows 7',
            'Windows 8',
            'Windows 10',
            'Windows 10 Pro',
            'Windows 11',
            'macOS',
            'Linux',
            'Chrome OS',
        ];

        $os = collect(config('category_specs.computers.fields'))->firstWhere('key', 'os');
        $this->assertSame($expected, $os['options'] ?? []);

        $fields = Category::query()->where('slug', 'computers')->value('spec_schema')['fields'] ?? [];
        $stored = collect($fields)->firstWhere('key', 'os');
        $this->assertSame($expected, $stored['options'] ?? []);
    }
}
