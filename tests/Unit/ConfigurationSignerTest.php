<?php

namespace Tests\Unit;

use App\Services\ConfigurationSigner;
use PHPUnit\Framework\TestCase;

/** The server signer and the Cart Transform Function agree (tests/engine/signing-vectors.json). */
class ConfigurationSignerTest extends TestCase
{
    public function test_vectors_match_the_function(): void
    {
        $vectors = json_decode((string) file_get_contents(dirname(__DIR__).'/engine/signing-vectors.json'), true);
        foreach ($vectors as $v) {
            $message = ConfigurationSigner::message($v['shop'], $v['config_id'], $v['price_cents'], $v['base_cents'], $v['currency'], $v['components']);
            $this->assertSame($v['message'], $message);
            $this->assertSame(hash_hmac('sha256', $v['message'], $v['secret']), ConfigurationSigner::sign($v['secret'], $message));
        }
    }

    public function test_tampered_price_fails(): void
    {
        $message = ConfigurationSigner::message('a.myshopify.com', 'cfg_1', 1000, 500, 'USD');
        $signature = ConfigurationSigner::sign('s', $message);

        $this->assertTrue(ConfigurationSigner::verify('s', $message, $signature));
        $this->assertFalse(ConfigurationSigner::verify('s', ConfigurationSigner::message('a.myshopify.com', 'cfg_1', 100, 500, 'USD'), $signature));
        $this->assertFalse(ConfigurationSigner::verify('other', $message, $signature));
    }
}
