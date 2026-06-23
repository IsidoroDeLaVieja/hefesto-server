<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use App\Core\Groups;

class GroupsTest extends TestCase
{
    private Groups $groups;

    protected function setUp(): void
    {
        $this->groups = new Groups();
    }

    // --- Initial State ---

    public function testInitialReadReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->groups->read());
    }

    public function testIsEnabledReturnsFalseForAnyKey(): void
    {
        $this->assertFalse($this->groups->isEnabled('ISIGROUP'));
        $this->assertFalse($this->groups->isEnabled('OTHER'));
    }

    // --- Enable / Disable ---

    public function testEnableAddsGroupAndIsEnabledReturnsTrue(): void
    {
        $this->groups->enable('isigroup');

        $this->assertTrue($this->groups->isEnabled('ISIGROUP'));
        $this->assertSame(['ISIGROUP'], $this->groups->read());
    }

    public function testEnableIsCaseInsensitive(): void
    {
        $this->groups->enable('MyGroup');

        $this->assertTrue($this->groups->isEnabled('MYGROUP'));
        $this->assertTrue($this->groups->isEnabled('mygroup'));
        $this->assertTrue($this->groups->isEnabled('MyGroup'));
    }

    public function testDisableRemovesGroup(): void
    {
        $this->groups->enable('isigroup');
        $this->groups->disable('isigroup');

        $this->assertFalse($this->groups->isEnabled('ISIGROUP'));
        $this->assertSame([], $this->groups->read());
    }

    public function testDisableNonExistentKeyDoesNothing(): void
    {
        $this->groups->enable('isigroup');
        $this->groups->disable('nonexistent');

        $this->assertTrue($this->groups->isEnabled('ISIGROUP'));
        $this->assertSame(['ISIGROUP'], $this->groups->read());
    }

    // --- Duplicates ---

    public function testEnablingSameGroupTwiceStoresOnlyOnce(): void
    {
        $this->groups->enable('isigroup');
        $this->groups->enable('isigroup');

        $this->assertSame(['ISIGROUP'], $this->groups->read());
    }

    // --- QUEUE_FLOW / ERROR_FLOW ---

    public function testEnablingQueueFlowDisablesAllOtherGroups(): void
    {
        $this->groups->enable('isigroup');
        $this->groups->enable('isigroup2');
        $this->groups->enable(Groups::QUEUE_FLOW);

        $this->assertFalse($this->groups->isEnabled('ISIGROUP'));
        $this->assertTrue($this->groups->isEnabled(Groups::QUEUE_FLOW));
        $this->assertSame([Groups::QUEUE_FLOW], $this->groups->read());
    }

    public function testEnablingErrorFlowDisablesAllOtherGroups(): void
    {
        $this->groups->enable('isigroup');
        $this->groups->enable(Groups::ERROR_FLOW);

        $this->assertFalse($this->groups->isEnabled('ISIGROUP'));
        $this->assertTrue($this->groups->isEnabled(Groups::ERROR_FLOW));
        $this->assertSame([Groups::ERROR_FLOW], $this->groups->read());
    }

    public function testEnablingNormalFlowDoesNotDisableOthers(): void
    {
        $this->groups->enable('isigroup');
        $this->groups->enable(Groups::NORMAL_FLOW);

        $this->assertTrue($this->groups->isEnabled('ISIGROUP'));
        $this->assertTrue($this->groups->isEnabled(Groups::NORMAL_FLOW));
    }

    // --- disableAll ---

    public function testDisableAllClearsAllGroups(): void
    {
        $this->groups->enable('isigroup');
        $this->groups->enable('isigroup2');
        $this->groups->disableAll();

        $this->assertSame([], $this->groups->read());
        $this->assertFalse($this->groups->isEnabled('ISIGROUP'));
        $this->assertFalse($this->groups->isEnabled('ISIGROUP2'));
    }

    // --- isAnyKeyEnabled ---

    public function testIsAnyKeyEnabledReturnsTrueWhenAtLeastOneKeyMatches(): void
    {
        $this->groups->enable('isigroup');

        $this->assertTrue($this->groups->isAnyKeyEnabled(['isigroup', 'other']));
        $this->assertTrue($this->groups->isAnyKeyEnabled(['OTHER', 'isigroup']));
    }

    public function testIsAnyKeyEnabledReturnsFalseWhenNoKeyMatches(): void
    {
        $this->groups->enable('isigroup');

        $this->assertFalse($this->groups->isAnyKeyEnabled(['other']));
        $this->assertFalse($this->groups->isAnyKeyEnabled([]));
    }

    public function testIsAnyKeyEnabledReturnsFalseWhenNoGroupsEnabled(): void
    {
        $this->assertFalse($this->groups->isAnyKeyEnabled(['isigroup']));
    }
}