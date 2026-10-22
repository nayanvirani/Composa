<?php

namespace Tests\Unit;

use App\Shopify\SessionToken;
use App\Shopify\ShopDomain;
use App\Shopify\Signatures;
use App\Support\Jwt;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ShopifySignaturesTest extends TestCase
{
    private const SECRET = 'shpss_test_secret';

    public function test_webhook_hmac(): void
    {
        $body = '{"id":1}';
        $hmac = base64_encode(hash_hmac('sha256', $body, self::SECRET, true));

        $this->assertTrue(Signatures::validWebhook($body, $hmac, self::SECRET));
        $this->assertFalse(Signatures::validWebhook($body.' ', $hmac, self::SECRET));
        $this->assertFalse(Signatures::validWebhook($body, null, self::SECRET));
    }

    public function test_app_proxy_signature(): void
    {
        $query = ['shop' => 'maison-oro.myshopify.com', 'path_prefix' => '/apps/composa', 'timestamp' => '1317327555', 'extra' => ['1', '2']];
        $message = 'extra=1,2path_prefix=/apps/composashop=maison-oro.myshopify.comtimestamp=1317327555';
        $query['signature'] = hash_hmac('sha256', $message, self::SECRET);

        $this->assertTrue(Signatures::validProxy($query, self::SECRET));
        $query['shop'] = 'evil.myshopify.com';
        $this->assertFalse(Signatures::validProxy($query, self::SECRET));
    }

    public function test_session_token(): void
    {
        $payload = ['iss' => 'https://maison-oro.myshopify.com/admin', 'dest' => 'https://maison-oro.myshopify.com', 'aud' => 'key123', 'sub' => '42', 'exp' => time() + 60, 'nbf' => time() - 5];
        $token = Jwt::encode($payload, self::SECRET);

        $result = SessionToken::verify($token, 'key123', self::SECRET);
        $this->assertSame('maison-oro.myshopify.com', $result['shop']);
        $this->assertSame('42', $result['user_id']);

        $this->expectException(RuntimeException::class);
        SessionToken::verify($token, 'other-app', self::SECRET);
    }

    public function test_expired_session_token_is_rejected(): void
    {
        $token = Jwt::encode(['iss' => 'https://a.myshopify.com/admin', 'dest' => 'https://a.myshopify.com', 'aud' => 'k', 'exp' => time() - 120], self::SECRET);

        $this->expectException(RuntimeException::class);
        SessionToken::verify($token, 'k', self::SECRET);
    }

    public function test_shop_domain(): void
    {
        $this->assertSame('maison-oro.myshopify.com', ShopDomain::normalize('https://Maison-Oro.myshopify.com/admin'));
        $this->assertNull(ShopDomain::normalize('maison-oro.myshopify.com.evil.com'));
        $this->assertNull(ShopDomain::normalize('evil.com'));
    }
}
