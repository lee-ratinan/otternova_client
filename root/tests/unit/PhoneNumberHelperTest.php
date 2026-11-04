<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class PhoneNumberHelperTest extends CIUnitTestCase
{
    public function testFormatToE164(): void
    {
        $this->assertSame('+66812345678', formatPhoneNumberStandard('0812345678', 'TH'));
        $this->assertSame('+6591234567', formatPhoneNumberStandard('9123 4567', 'sg'));
        $this->assertFalse(formatPhoneNumberStandard('123', 'TH'));
        $this->assertFalse(formatPhoneNumberStandard('0812345678', 'US'));
    }

    public function testSplitE164IntoCountryAndNumber(): void
    {
        $this->assertSame(['TH', '0812345678'], splitPhoneNumber('+66812345678'));
        $this->assertSame(['SG', '91234567'], splitPhoneNumber('+6591234567'));
        $this->assertSame(['JP', '0312345678'], splitPhoneNumber('+81312345678'));
        $this->assertSame(['', ''], splitPhoneNumber(null));
        $this->assertSame(['', 'not a number'], splitPhoneNumber('not a number'));
    }
}
