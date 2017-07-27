<?php

namespace BddBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SitemapControllerTest extends WebTestCase
{
    public function testIndex()
    {
        $client = static::createClient();

        $client->request('GET', '/sitemap.xml');

        $this->assertTrue(
            $client->getResponse()->isSuccessful(),
            "Sitemap 200 OK"
        );
    }

}
