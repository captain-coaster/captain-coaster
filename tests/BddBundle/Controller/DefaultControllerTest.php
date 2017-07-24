<?php

namespace BddBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class DefaultControllerTest extends WebTestCase
{
    public function testRoot()
    {
        $client = static::createClient();

        $client->request('GET', '/');

        $this->assertTrue($client->getResponse()->isRedirection());
        $this->assertEquals($client->getResponse()->getStatusCode(), Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testRootEnglish()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/',
            [],
            [],
            [
                'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.8,fr;q=0.6,fr-FR;q=0.4,de;q=0.2',
            ]
        );

        $this->assertTrue($client->getResponse()->isRedirect('/en/'));
        $this->assertEquals($client->getResponse()->getStatusCode(), Response::HTTP_MOVED_PERMANENTLY);

    }

    public function testRootFrench()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/',
            [],
            [],
            [
                'HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.8,en;q=0.6,en-US;q=0.4,de;q=0.2',
            ]
        );

        $this->assertTrue($client->getResponse()->isRedirect('/fr/'));
        $this->assertEquals($client->getResponse()->getStatusCode(), Response::HTTP_MOVED_PERMANENTLY);
    }

    public function testRootDefaultLanguage()
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/',
            [],
            [],
            [
                'HTTP_ACCEPT_LANGUAGE' => 'de;q=0.8',
            ]
        );

        $this->assertTrue($client->getResponse()->isRedirect('/en/'));
        $this->assertEquals($client->getResponse()->getStatusCode(), Response::HTTP_MOVED_PERMANENTLY);
    }
}
