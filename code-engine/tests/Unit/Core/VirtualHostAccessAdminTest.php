<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use App\Core\VirtualHostStorage;
use App\Core\VirtualHostAccessAdmin;

class VirtualHostAccessAdminTest extends TestCase
{
    private VirtualHostStorage&MockObject $virtualHostStorage;
    private VirtualHostAccessAdmin $virtualHostAccessAdmin;

    protected function setUp(): void
    {
        $this->virtualHostStorage = $this->createMock(VirtualHostStorage::class);
        $this->virtualHostAccessAdmin = new VirtualHostAccessAdmin(
            $this->virtualHostStorage
        );
    }

    public function testGetReturnsInfoWhenAdminAndPublicExistWithCheckKeyFalse(): void
    {
        $hostAdmin = 'admin.delavieja.com';
        $hostPublic = 'public.delavieja.com';
        $key = '333';
        $env = 'pro';
        $path = '';

        $this->virtualHostStorage
            ->expects($this->once())
            ->method('getAdmin')
            ->with($hostAdmin)
            ->willReturn(['ORG' => $hostAdmin, 'TYPE' => 'ADMIN']);

        $this->virtualHostStorage
            ->expects($this->once())
            ->method('getPublic')
            ->with($hostPublic)
            ->willReturn([
                'ORG' => $hostPublic,
                'TYPE' => 'PUBLIC',
                'KEY' => $key,
                'ENV' => $env,
                'PATH' => $path,
            ]);

        $info = $this->virtualHostAccessAdmin->get(
            $hostAdmin, $hostPublic, 'wrong-key', false
        );

        $this->assertSame(
            ['ORG' => $hostPublic, 'TYPE' => 'ADMIN', 'ENV' => $env, 'PATH' => $path],
            $info
        );
    }

    public function testGetReturnsInfoWhenAdminAndPublicExistWithCorrectKey(): void
    {
        $hostAdmin = 'admin.delavieja.com';
        $hostPublic = 'public.delavieja.com';
        $key = '333';
        $env = 'pro';
        $path = '';

        $this->virtualHostStorage
            ->expects($this->once())
            ->method('getAdmin')
            ->with($hostAdmin)
            ->willReturn(['ORG' => $hostAdmin, 'TYPE' => 'ADMIN']);

        $this->virtualHostStorage
            ->expects($this->once())
            ->method('getPublic')
            ->with($hostPublic)
            ->willReturn([
                'ORG' => $hostPublic,
                'TYPE' => 'PUBLIC',
                'KEY' => $key,
                'ENV' => $env,
                'PATH' => $path,
            ]);

        $info = $this->virtualHostAccessAdmin->get(
            $hostAdmin, $hostPublic, $key, true
        );

        $this->assertSame(
            ['ORG' => $hostPublic, 'TYPE' => 'ADMIN', 'ENV' => $env, 'PATH' => $path],
            $info
        );
    }

    public function testExceptionWhenAdminDoesNotExist(): void
    {
        $hostAdmin = 'admin.delavieja.com';
        $hostPublic = 'public.delavieja.com';

        $this->virtualHostStorage
            ->expects($this->once())
            ->method('getAdmin')
            ->with($hostAdmin)
            ->willReturn(null);

        $this->virtualHostStorage
            ->expects($this->never())
            ->method('getPublic');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('admin does not exist');

        $this->virtualHostAccessAdmin->get(
            $hostAdmin, $hostPublic, '333', true
        );
    }

    public function testExceptionWhenPublicDoesNotExist(): void
    {
        $hostAdmin = 'admin.delavieja.com';
        $hostPublic = 'public.delavieja.com';

        $this->virtualHostStorage
            ->expects($this->once())
            ->method('getAdmin')
            ->with($hostAdmin)
            ->willReturn(['ORG' => $hostAdmin, 'TYPE' => 'ADMIN']);

        $this->virtualHostStorage
            ->expects($this->once())
            ->method('getPublic')
            ->with($hostPublic)
            ->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('public does not exist');

        $this->virtualHostAccessAdmin->get(
            $hostAdmin, $hostPublic, '333', true
        );
    }

    public function testExceptionWhenKeyIsNotCorrect(): void
    {
        $hostAdmin = 'admin.delavieja.com';
        $hostPublic = 'public.delavieja.com';
        $key = '333';

        $this->virtualHostStorage
            ->expects($this->once())
            ->method('getAdmin')
            ->with($hostAdmin)
            ->willReturn(['ORG' => $hostAdmin, 'TYPE' => 'ADMIN']);

        $this->virtualHostStorage
            ->expects($this->once())
            ->method('getPublic')
            ->with($hostPublic)
            ->willReturn([
                'ORG' => $hostPublic,
                'TYPE' => 'PUBLIC',
                'KEY' => $key,
                'ENV' => 'pro',
                'PATH' => '',
            ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('key is not correct');

        $this->virtualHostAccessAdmin->get(
            $hostAdmin, $hostPublic, 'wrong-key', true
        );
    }
}