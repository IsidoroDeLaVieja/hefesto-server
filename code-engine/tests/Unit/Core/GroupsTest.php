<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Groups;

class GroupsTest extends TestCase 
{
    private $groups;
    
    protected function setUp() : void
    {
        $this->groups = new Groups();
    }

    public function testIsEnabledAndEnableAndDisableAndRead() : void
    {
        $this->assertSame([],$this->groups->read());
        $this->assertFalse($this->groups->isEnabled('ISIGROUP'));
        
        $this->groups->enable('isigroup');
        $this->assertTrue($this->groups->isEnabled('ISIGROUP'));
        $this->assertSame(['ISIGROUP'],$this->groups->read());

        $this->groups->disable('isigroup');
        $this->assertFalse($this->groups->isEnabled('ISIGROUP'));
        $this->assertSame([],$this->groups->read());
    }

    public function testDisableAllIsAnyKeyEnabled() : void
    {
        $this->groups->enable('isigroup');
        $this->groups->enable('isigroup2');
        $this->assertTrue($this->groups->isAnyKeyEnabled(['isigroup','isigroup3']));
        $this->assertTrue($this->groups->isAnyKeyEnabled(['isigroup2']));
        
        $this->groups->disableAll();
        
        $this->assertFalse($this->groups->isAnyKeyEnabled(['isigroup']));
        $this->assertFalse($this->groups->isAnyKeyEnabled(['isigroup2']));
    }

}