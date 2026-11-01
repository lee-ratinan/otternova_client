<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class UserHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('user');
    }

    public function testAvatarFallsBackToDefaultWhenNotSet(): void
    {
        $default = base_url('assets/img/profile-img.webp');
        $this->assertSame($default, userAvatarUrl(null));
        $this->assertSame($default, userAvatarUrl(''));
        $this->assertSame($default, userAvatarUrl('   '));
    }

    public function testAvatarUsesAvatarPathWhenSet(): void
    {
        $this->assertSame(base_url('uploads/avatars/a.webp'), userAvatarUrl('/uploads/avatars/a.webp'));
    }

    public function testDisplayNameFallsBackToFullName(): void
    {
        $this->assertSame('Otter', userDisplayName(['display_name' => 'Otter', 'full_name' => 'Ratinan Lee']));
        $this->assertSame('Ratinan Lee', userDisplayName(['display_name' => null, 'full_name' => 'Ratinan Lee']));
        $this->assertSame('Ratinan Lee', userDisplayName(['display_name' => ' ', 'full_name' => 'Ratinan Lee']));
        $this->assertSame('', userDisplayName([]));
    }

    public function testLocalTimeTagMarksUtcDatetime(): void
    {
        $this->assertSame(
            '<time datetime="2026-11-01T10:30:00Z" data-local-time="datetime">2026-11-01 10:30:00 UTC</time>',
            localTimeTag('2026-11-01 10:30:00')
        );
        $this->assertSame('', localTimeTag(null));
    }
}
