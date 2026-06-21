<?php

namespace Modules\Authentication\Tests\Feature;

use InvalidArgumentException;
use Modules\Authentication\Support\PhoneInputConfig;
use Modules\Authentication\Support\PhoneNumberNormalizer;
use Tests\TestCase;

class PhoneInputConfigTest extends TestCase
{
    public function test_phone_number_normalizer_respects_international_store_format(): void
    {
        config()->set('authentication.phone_input.store_format', 'international');

        $this->assertSame('+91 9876543210', PhoneNumberNormalizer::normalize('09876 543210'));
    }

    public function test_invalid_phone_store_format_throws_clear_exception(): void
    {
        config()->set('authentication.phone_input.store_format', 'raw');

        $this->expectException(InvalidArgumentException::class);

        PhoneNumberNormalizer::normalize('09876 543210');
    }

    public function test_phone_input_view_config_reads_library_settings(): void
    {
        config()->set('authentication.phone_input.library', 'intl-tel-input');
        config()->set('authentication.phone_input.cdn', false);
        config()->set('authentication.phone_input.version', '24.0.0');
        config()->set('authentication.phone_input.separate_dial_code', true);

        $config = PhoneInputConfig::viewConfig();

        $this->assertSame('intl-tel-input', $config['library']);
        $this->assertFalse($config['cdn']);
        $this->assertSame('24.0.0', $config['version']);
        $this->assertTrue($config['separate_dial_code']);
    }
}
