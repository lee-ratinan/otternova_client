<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class DashboardTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    // Client 'user' as returned by v1/login and v1/verify-otp
    private const CLIENT_USER = [
        'id'                 => 1,
        'uid'                => 'test-uid',
        'email_address'      => 'client@example.com',
        'telephone_number'   => null,
        'full_name'          => 'Test Client',
        'avatar_path'        => null,
        'otp_mode'           => 'E',
        'account_status'     => 'A',
        'account_type'       => 'C',
        'password_expiry'    => '2026-12-01',
        'created_at'         => '2026-11-01 00:00:00',
        'updated_at'         => '2026-11-01 00:00:00',
        'display_name'       => null,
        'status_message'     => null,
        'status_emoji'       => null,
        'preferred_language' => 'en-US',
    ];

    private function signedInAs(array $user)
    {
        return $this->withSession([
            'access_token' => 'test-token',
            'expires_at'   => time() + 3600,
            'user'         => $user,
        ])->get('admin/dashboard');
    }

    public function testDashboardShowsDefaultAvatarAndFullName(): void
    {
        $result = $this->signedInAs(self::CLIENT_USER);

        $result->assertOK();
        $result->assertSee(base_url('assets/img/profile-img.webp'));
        $result->assertSee('Test Client');
        $result->assertSee('client@example.com');
        $result->assertDontSee('John Doe');
    }

    public function testDashboardShowsAvatarPathAndDisplayName(): void
    {
        $result = $this->signedInAs(['avatar_path' => 'uploads/avatars/a.webp', 'display_name' => 'Otter'] + self::CLIENT_USER);

        $result->assertOK();
        $result->assertSee(base_url('uploads/avatars/a.webp'));
        $result->assertSee('Otter');
        $result->assertDontSee('assets/img/profile-img.webp');
    }

    public function testDashboardRequiresSignIn(): void
    {
        $this->get('admin/dashboard')->assertRedirectTo(base_url());
    }
}
