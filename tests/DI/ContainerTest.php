<?php

namespace Reaction\Tests\Framework\DI;

use Reaction\Tests\Framework\TestCase;

class ContainerTest extends TestCase
{
    /**
     *
     */
    public function testSomethingUseful()
    {
        $data = [1];
        $this->assertNotEmpty($data, 'Message');
        $this->assertCount(1, $data);
    }

}