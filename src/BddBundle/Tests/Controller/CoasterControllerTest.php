<?php

namespace BddBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CoasterControllerTest extends WebTestCase
{
    public function testCoaster()
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/coaster/{slug}');
    }

}
