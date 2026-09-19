<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class ApplyFlutterwaveEnvScriptTest extends TestCase
{
    public function test_script_writes_live_flutterwave_keys(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cityshop-flw-env-');
        $this->assertNotFalse($path);
        file_put_contents($path, "APP_NAME=CityShop\n");

        $secret = 'FLWSECK-'.str_repeat('a', 32).'-'.str_repeat('b', 12).'-X';
        $script = dirname(__DIR__, 2).'/scripts/apply-flutterwave-env.php';

        $cmd = 'FLW_SECRET_KEY='.escapeshellarg($secret)
            .' php '.escapeshellarg($script).' '.escapeshellarg($path).' 2>&1';
        exec($cmd, $output, $code);

        $this->assertSame(0, $code, implode("\n", $output));

        $env = (string) file_get_contents($path);
        @unlink($path);

        $this->assertStringContainsString('FLW_PUBLIC_KEY=FLWPUBK-d1a705454d05adc18215a13871564a4c-X', $env);
        $this->assertStringContainsString('FLW_SECRET_KEY='.$secret, $env);
        $this->assertStringContainsString('FLW_SECRET_HASH=CityUnlockFlwWh2026', $env);
        $this->assertStringContainsString('VITE_FLW_PUBLIC_KEY=${FLW_PUBLIC_KEY}', $env);
    }

    public function test_script_refuses_a_test_secret(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'cityshop-flw-env-');
        $this->assertNotFalse($path);
        file_put_contents($path, "APP_NAME=CityShop\n");

        $script = dirname(__DIR__, 2).'/scripts/apply-flutterwave-env.php';
        $cmd = 'FLW_SECRET_KEY='.escapeshellarg('FLWSECK_TEST-deadbeef-X')
            .' php '.escapeshellarg($script).' '.escapeshellarg($path).' 2>&1';
        exec($cmd, $output, $code);

        @unlink($path);

        $this->assertSame(1, $code, implode("\n", $output));
        $this->assertStringContainsString('TEST', implode("\n", $output));
    }
}
